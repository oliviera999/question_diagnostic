<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

/**
 * Suggére une catégorie cible via le sous-système IA de Moodle (si disponible),
 * en s'appuyant sur le fournisseur configuré au niveau plateforme.
 *
 * IMPORTANT:
 * - On ne gère PAS de clé API ici.
 * - On encapsule l'appel IA derrière des checks (class_exists/method_exists) pour éviter tout fatal.
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_suggester {

    /**
     * Détecte si un sous-système IA Moodle est disponible (best-effort).
     *
     * @return bool
     */
    public static function is_available(): bool {
        // Moodle 4.5+ : sous-système IA (noms exacts variables selon packaging).
        if (class_exists('\\core_ai\\manager')) {
            return true;
        }
        if (class_exists('\\tool_ai\\manager')) {
            return true;
        }
        if (class_exists('\\tool_ai\\ai_manager')) {
            return true;
        }
        return false;
    }

    /**
     * Tente de construire une instance d'action IA Moodle 4.5 (best-effort) via reflection.
     *
     * @param string $actionclass
     * @param string $prompt
     * @return object|false
     */
    private static function build_action_instance(string $actionclass, string $prompt) {
        global $USER;

        if (!class_exists($actionclass)) {
            return false;
        }

        try {
            $rc = new \ReflectionClass($actionclass);
            $ctor = $rc->getConstructor();
            $args = [];

            if ($ctor) {
                foreach ($ctor->getParameters() as $p) {
                    $name = strtolower($p->getName());
                    $type = $p->hasType() ? (string)$p->getType() : '';

                    // Valeurs par défaut si dispo.
                    if ($p->isOptional() && $p->isDefaultValueAvailable()) {
                        $args[] = $p->getDefaultValue();
                        continue;
                    }

                    // Prompt / texte.
                    if ($type === 'string' || $type === '' || $type === 'mixed') {
                        if (strpos($name, 'prompt') !== false || strpos($name, 'text') !== false
                            || strpos($name, 'input') !== false || strpos($name, 'content') !== false) {
                            $args[] = $prompt;
                            continue;
                        }
                        if (strpos($name, 'component') !== false) {
                            $args[] = 'local_question_diagnostic';
                            continue;
                        }
                        if (strpos($name, 'context') !== false) {
                            // Certains constructeurs attendent un contexte sous forme de string; fallback neutre.
                            $args[] = 'system';
                            continue;
                        }
                    }

                    // Context / contextid.
                    if ($type === 'int' || $type === 'integer') {
                        if (strpos($name, 'context') !== false) {
                            $args[] = \context_system::instance()->id;
                            continue;
                        }
                        if (strpos($name, 'userid') !== false || strpos($name, 'user') !== false) {
                            $args[] = (int)($USER->id ?? 0);
                            continue;
                        }
                        // Default safe int.
                        $args[] = 0;
                        continue;
                    }

                    // Context object.
                    if ($type === '\\context' || $type === 'context' || $type === '\\context_system') {
                        $args[] = \context_system::instance();
                        continue;
                    }

                    // Nullable types.
                    if ($p->allowsNull()) {
                        $args[] = null;
                        continue;
                    }

                    // Dernier recours.
                    $args[] = null;
                }

                return $rc->newInstanceArgs($args);
            }

            // Pas de constructeur : instance simple.
            $inst = $rc->newInstance();

            // Essayer des setters usuels.
            foreach (['set_prompt', 'set_text', 'set_input', 'set_content'] as $setter) {
                if (method_exists($inst, $setter)) {
                    try {
                        $inst->{$setter}($prompt);
                        break;
                    } catch (\Throwable $t) {
                        // ignore
                    }
                }
            }

            return $inst;
        } catch (\Throwable $t) {
            return false;
        }
    }

    /**
     * Vérifie si une action est activée pour au moins un provider.
     *
     * @param string $actionclass
     * @return array{enabled:bool,providers:array}
     */
    private static function check_action_enabled(string $actionclass): array {
        $providers = [];
        $enabled = false;
        try {
            if (class_exists('\\core_component')) {
                $plist = \core_component::get_plugin_list('aiprovider');
                foreach (array_keys((array)$plist) as $p) {
                    $plugin = 'aiprovider_' . $p;
                    try {
                        if (\core_ai\manager::is_action_enabled($plugin, $actionclass)) {
                            $enabled = true;
                            $providers[] = $plugin;
                        }
                    } catch (\Throwable $t) {
                        // ignore
                    }
                }
            }
        } catch (\Throwable $t) {
            // ignore
        }
        return ['enabled' => $enabled, 'providers' => $providers];
    }

    /**
     * Demande à l'IA de sélectionner la meilleure catégorie parmi une liste.
     *
     * Retour:
     * - ['status'=>'ok','choice_index'=>int,'confidence'=>float,'reason'=>string,'new_category'=>string]
     * - ou ['status'=>'unavailable'|'error', 'message'=>string]
     *
     * @param string $questionname
     * @param string $questiontextplain
     * @param string[] $categorylabels Liste de labels (breadcrumb) candidats
     * @return array
     */
    public static function suggest(string $questionname, string $questiontextplain, array $categorylabels): array {
        $questionname = trim($questionname);
        $questiontextplain = trim($questiontextplain);
        $categorylabels = array_values(array_filter(array_map('strval', $categorylabels)));

        if (empty($categorylabels)) {
            return ['status' => 'error', 'message' => 'No candidate categories'];
        }
        if (!self::is_available()) {
            return ['status' => 'unavailable', 'message' => 'Moodle AI subsystem is not available'];
        }

        // Prompt strict (réponse JSON).
        $maxcats = 60; // sécurité coût.
        if (count($categorylabels) > $maxcats) {
            $categorylabels = array_slice($categorylabels, 0, $maxcats);
        }

        $list = [];
        foreach ($categorylabels as $i => $label) {
            $list[] = ($i + 1) . '. ' . $label;
        }

        $system = "You are a Moodle question bank assistant. "
            . "Your job: classify a question into the best existing category, or say NONE if nothing fits.";
        $user = "Pick the best category for this question.\n"
            . "Question title: " . $questionname . "\n"
            . "Question content (plain text): " . $questiontextplain . "\n\n"
            . "Candidate categories (choose ONE number, or NONE):\n"
            . implode("\n", $list) . "\n\n"
            . "Return ONLY valid JSON with keys:\n"
            . "- choice: integer (1.." . count($categorylabels) . ") OR 0 for NONE\n"
            . "- confidence: number 0..1\n"
            . "- reason: short string\n"
            . "- proposed_new_category: short string (empty if choice != 0)\n";

        // Best-effort: tenter plusieurs points d'entrée connus, sans fatals.
        $raw = null;
        $error = null;
        $usedapi = '';
        $usedmethod = '';

        // Moodle 4.5 : utiliser core_ai\manager::process_action() avec une action generate_text.
        if (class_exists('\\core_ai\\manager') && method_exists('\\core_ai\\manager', 'process_action')) {
            $actionclass = '\\core_ai\\aiactions\\generate_text';
            if (class_exists($actionclass)) {
                $enabledinfo = self::check_action_enabled($actionclass);
                if (empty($enabledinfo['enabled'])) {
                    return [
                        'status' => 'unavailable',
                        'message' => 'AI action is not enabled for any provider',
                        'debug' => [
                            'action' => $actionclass,
                            'enabledproviders' => $enabledinfo['providers'],
                        ],
                    ];
                }

                $prompt = $system . "\n\n" . $user;
                $action = self::build_action_instance($actionclass, $prompt);
                if ($action) {
                    try {
                        $mgr = '\\core_ai\\manager';
                        $usedapi = $mgr;
                        $usedmethod = 'process_action(' . $actionclass . ')';
                        $raw = $mgr::process_action($action);
                    } catch (\Throwable $t) {
                        $error = $t->getMessage();
                    }
                } else {
                    $error = 'Unable to build action instance for ' . $actionclass;
                }
            } else {
                $error = 'Missing action class ' . $actionclass;
            }
        }

        if ($raw === null) {
            return [
                'status' => 'unavailable',
                'message' => 'AI call failed' . (!empty($error) ? (': ' . $error) : ''),
                'debug' => [
                    'core_ai_manager' => class_exists('\\core_ai\\manager'),
                    'api' => $usedapi,
                    'method' => $usedmethod,
                ],
            ];
        }

        // Normaliser sortie brute.
        if (is_array($raw)) {
            // Certains clients renvoient ['text'=>...]
            $raw = $raw['text'] ?? $raw['content'] ?? json_encode($raw);
        } else if (is_object($raw)) {
            // Certaines implémentations renvoient un objet résultat.
            if (isset($raw->text)) {
                $raw = $raw->text;
            } else if (isset($raw->content)) {
                $raw = $raw->content;
            } else {
                $raw = json_encode($raw);
            }
        }
        $raw = trim((string)$raw);

        // Extraire le JSON si l'IA a ajouté des fences.
        if (strpos($raw, '{') !== false) {
            $raw = substr($raw, strpos($raw, '{'));
        }
        if (strrpos($raw, '}') !== false) {
            $raw = substr($raw, 0, strrpos($raw, '}') + 1);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'status' => 'error',
                'message' => 'AI returned non-JSON response',
                'raw' => $raw,
                'debug' => [
                    'api' => $usedapi,
                    'method' => $usedmethod,
                ],
            ];
        }

        $choice = isset($decoded['choice']) ? (int)$decoded['choice'] : -1;
        $confidence = isset($decoded['confidence']) ? (float)$decoded['confidence'] : 0.0;
        $reason = isset($decoded['reason']) ? (string)$decoded['reason'] : '';
        $newcat = isset($decoded['proposed_new_category']) ? (string)$decoded['proposed_new_category'] : '';

        if ($choice < 0 || $choice > count($categorylabels)) {
            $choice = 0;
        }
        $confidence = max(0.0, min(1.0, $confidence));

        return [
            'status' => 'ok',
            'choice_index' => $choice, // 0 = NONE, sinon 1..N
            'confidence' => $confidence,
            'reason' => $reason,
            'new_category' => $choice === 0 ? trim($newcat) : '',
            'debug' => [
                'api' => $usedapi,
                'method' => $usedmethod,
            ],
        ];
    }
}


