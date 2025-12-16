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

        // 1) core_ai\manager.
        if (class_exists('\\core_ai\\manager')) {
            $mgr = '\\core_ai\\manager';
            try {
                // Plusieurs APIs possibles selon versions: on teste au runtime.
                if (method_exists($mgr, 'generate_text')) {
                    $raw = $mgr::generate_text($system, $user);
                    $usedapi = $mgr;
                    $usedmethod = 'generate_text';
                } else if (method_exists($mgr, 'chat')) {
                    $raw = $mgr::chat([
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ]);
                    $usedapi = $mgr;
                    $usedmethod = 'chat';
                } else if (method_exists($mgr, 'complete')) {
                    $raw = $mgr::complete($system . "\n\n" . $user);
                    $usedapi = $mgr;
                    $usedmethod = 'complete';
                }
            } catch (\Throwable $t) {
                $error = $t->getMessage();
            }
        }

        // 2) tool_ai\manager / tool_ai\ai_manager (fallback).
        if ($raw === null && (class_exists('\\tool_ai\\manager') || class_exists('\\tool_ai\\ai_manager'))) {
            $mgr = class_exists('\\tool_ai\\manager') ? '\\tool_ai\\manager' : '\\tool_ai\\ai_manager';
            try {
                if (method_exists($mgr, 'generate_text')) {
                    $raw = $mgr::generate_text($system, $user);
                    $usedapi = $mgr;
                    $usedmethod = 'generate_text';
                } else if (method_exists($mgr, 'chat')) {
                    $raw = $mgr::chat([
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ]);
                    $usedapi = $mgr;
                    $usedmethod = 'chat';
                } else if (method_exists($mgr, 'complete')) {
                    $raw = $mgr::complete($system . "\n\n" . $user);
                    $usedapi = $mgr;
                    $usedmethod = 'complete';
                }
            } catch (\Throwable $t) {
                $error = $t->getMessage();
            }
        }

        if ($raw === null) {
            return [
                'status' => 'unavailable',
                'message' => 'AI API entry point not found' . (!empty($error) ? (': ' . $error) : ''),
                'debug' => [
                    'core_ai_manager' => class_exists('\\core_ai\\manager'),
                    'tool_ai_manager' => class_exists('\\tool_ai\\manager'),
                    'tool_ai_ai_manager' => class_exists('\\tool_ai\\ai_manager'),
                ],
            ];
        }

        // Normaliser sortie brute.
        if (is_array($raw)) {
            // Certains clients renvoient ['text'=>...]
            $raw = $raw['text'] ?? $raw['content'] ?? json_encode($raw);
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


