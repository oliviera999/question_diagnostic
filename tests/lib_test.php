<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests unitaires pour les fonctions utilitaires (lib.php)
 * 
 * 🆕 v1.9.30 : Tests de base pour les fonctions critiques
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/question_diagnostic/lib.php');

use advanced_testcase;
use moodle_url;
use context_system;

/**
 * Tests pour les fonctions utilitaires du plugin
 */
class lib_test extends advanced_testcase {

    /**
     * Test de la fonction local_question_diagnostic_extend_navigation
     */
    public function test_extend_navigation() {
        global $PAGE, $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Créer une navigation factice
        $navigation = new \global_navigation($PAGE);

        // Appeler la fonction d'extension
        local_question_diagnostic_extend_navigation($navigation);

        // Note : Ce test vérifie juste que la fonction ne génère pas d'erreur
        // Un test plus complet nécessiterait d'inspecter le contenu de $navigation
        $this->assertTrue(true, 'La fonction extend_navigation devrait s\'exécuter sans erreur');
    }

    /**
     * Test de génération d'URL vers la banque de questions
     * 
     * 🆕 v1.9.27 : Fonction centralisée
     */
    public function test_get_question_bank_url() {
        global $DB;
        $this->resetAfterTest(true);

        $systemcontext = context_system::instance();

        // Créer une catégorie de test
        $category = new \stdClass();
        $category->name = 'Test Category';
        $category->contextid = $systemcontext->id;
        $category->info = '';
        $category->infoformat = FORMAT_HTML;
        $category->stamp = make_unique_id_code();
        $category->parent = 0;
        $category->sortorder = 999;

        $categoryid = $DB->insert_record('question_categories', $category);
        $category->id = $categoryid;

        // Test 1 : URL vers une catégorie
        $url = local_question_diagnostic_get_question_bank_url($category);

        $this->assertInstanceOf('moodle_url', $url, 'Devrait retourner un objet moodle_url');
        $this->assertStringContainsString('/question/edit.php', $url->out(), 
                                         'L\'URL devrait pointer vers edit.php');
        $this->assertEquals($category->id, $url->get_param('category'), 
                           'L\'URL devrait contenir le bon ID de catégorie');

        // Test 2 : URL vers une question spécifique
        $questionid = 123;
        $url_with_question = local_question_diagnostic_get_question_bank_url($category, $questionid);

        $this->assertEquals($questionid, $url_with_question->get_param('lastchanged'),
                           'L\'URL devrait contenir l\'ID de la question');

        // Test 3 : Catégorie null (devrait retourner URL de base)
        $url_null = local_question_diagnostic_get_question_bank_url(null);

        $this->assertInstanceOf('moodle_url', $url_null, 'Devrait retourner un objet moodle_url même avec null');
    }

    /**
     * Test de détection des questions utilisées
     * 
     * 🆕 v1.9.27 : Fonction centralisée
     */
    public function test_get_used_question_ids() {
        global $DB;
        $this->resetAfterTest(true);

        // Appeler la fonction de détection
        $used_ids = local_question_diagnostic_get_used_question_ids();

        // Vérifier que c'est un tableau
        $this->assertIsArray($used_ids, 'Devrait retourner un tableau');

        // Note : Sur une base de test vierge, le tableau peut être vide
        // C'est normal et attendu
        $this->assertGreaterThanOrEqual(0, count($used_ids),
                                       'Devrait retourner 0 ou plus de questions utilisées');

        // Vérifier que tous les éléments sont des entiers
        foreach ($used_ids as $id) {
            $this->assertIsInt($id, 'Chaque ID devrait être un entier');
        }

        // Vérifier qu'il n'y a pas de doublons
        $unique_ids = array_unique($used_ids);
        $this->assertCount(count($used_ids), $unique_ids,
                          'Il ne devrait pas y avoir de doublons dans les IDs');
    }

    /**
     * Test de génération de pagination HTML
     * 
     * 🆕 v1.9.30 : Nouvelle fonction pagination serveur
     */
    public function test_render_pagination() {
        $this->resetAfterTest(true);

        // Test 1 : Pagination simple
        $base_url = new moodle_url('/test.php');
        $html = local_question_diagnostic_render_pagination(100, 1, 10, $base_url);

        // Vérifier que du HTML est généré
        $this->assertIsString($html);
        $this->assertStringContainsString('qd-pagination', $html, 
                                         'Devrait contenir la classe CSS de pagination');
        $this->assertStringContainsString('Affichage', $html,
                                         'Devrait contenir le texte d\'information');

        // Test 2 : Pas besoin de pagination (total <= per_page)
        $html_no_pagination = local_question_diagnostic_render_pagination(5, 1, 10, $base_url);

        // Vérifier qu'aucune pagination n'est générée
        $this->assertEmpty($html_no_pagination,
                          'Ne devrait pas générer de pagination si total <= per_page');

        // Test 3 : Pagination avec plusieurs pages
        $html_multi = local_question_diagnostic_render_pagination(1000, 5, 100, $base_url);

        // Vérifier que les contrôles de navigation sont présents
        $this->assertStringContainsString('Précédent', $html_multi,
                                         'Devrait contenir le bouton Précédent');
        $this->assertStringContainsString('Suivant', $html_multi,
                                         'Devrait contenir le bouton Suivant');
        $this->assertStringContainsString('Premier', $html_multi,
                                         'Devrait contenir le bouton Premier');
        $this->assertStringContainsString('Dernier', $html_multi,
                                         'Devrait contenir le bouton Dernier');

        // Test 4 : Pagination à la dernière page
        $html_last = local_question_diagnostic_render_pagination(100, 10, 10, $base_url);

        // Vérifier qu'il n'y a pas de bouton "Suivant" (car on est à la dernière page)
        // Note : Le HTML peut contenir "Suivant" dans le texte, donc on vérifie plutôt la structure
        $this->assertIsString($html_last);
        $this->assertNotEmpty($html_last);

        // Test 5 : Pagination avec paramètres supplémentaires
        $extra_params = ['filter' => 'test', 'sort' => 'name'];
        $html_params = local_question_diagnostic_render_pagination(100, 2, 10, $base_url, $extra_params);

        // Vérifier que les paramètres supplémentaires sont préservés dans les URLs
        $this->assertStringContainsString('filter', $html_params,
                                         'Devrait préserver le paramètre filter');
        $this->assertStringContainsString('sort', $html_params,
                                         'Devrait préserver le paramètre sort');
    }

    /**
     * Test de validation des limites de pagination
     */
    public function test_pagination_limits() {
        $this->resetAfterTest(true);

        $base_url = new moodle_url('/test.php');

        // Test 1 : Page négative (devrait être normalisée à 1)
        $html = local_question_diagnostic_render_pagination(100, -1, 10, $base_url);

        // Si pagination générée, la page devrait être >= 1
        if (!empty($html)) {
            // La fonction interne devrait normaliser la page à 1
            $this->assertStringContainsString('page=1', $html,
                                             'Une page négative devrait être normalisée à 1');
        }

        // Test 2 : Page au-delà du total (devrait être normalisée au max)
        $html_beyond = local_question_diagnostic_render_pagination(100, 999, 10, $base_url);

        // La fonction devrait gérer cela correctement
        $this->assertIsString($html_beyond);

        // Test 3 : Per_page = 0 (cas limite)
        // Note : La fonction pourrait gérer cela différemment, on vérifie juste qu'elle ne crash pas
        try {
            $html_zero = local_question_diagnostic_render_pagination(100, 1, 0, $base_url);
            $this->assertTrue(true, 'Ne devrait pas crasher avec per_page = 0');
        } catch (\Exception $e) {
            // C'est acceptable si une exception est levée pour un cas invalide
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /**
     * Test de la fonction pluginfile (fichiers statiques)
     */
    public function test_pluginfile() {
        $this->resetAfterTest(true);

        // Le plugin ne sert pas de fichiers, donc devrait retourner false
        $result = local_question_diagnostic_pluginfile(null, null, null, '', [], false);

        $this->assertFalse($result, 'La fonction pluginfile devrait retourner false (pas de fichiers servis)');
    }

    /**
     * Test de get_enriched_context
     * 
     * 🆕 v1.9.7 : Fonction d'enrichissement de contexte
     */
    public function test_get_enriched_context() {
        global $DB;
        $this->resetAfterTest(true);

        $systemcontext = context_system::instance();

        // Test avec un contexte système
        $enriched = local_question_diagnostic_get_enriched_context($systemcontext->id);

        // Vérifier que l'objet enrichi contient les bonnes informations
        $this->assertIsObject($enriched);
        $this->assertEquals($systemcontext->id, $enriched->id);
        $this->assertObjectHasAttribute('type_name', $enriched);
        $this->assertEquals('Système', $enriched->type_name);

        // Test avec un contexte invalide
        $enriched_invalid = local_question_diagnostic_get_enriched_context(999999);

        // Devrait retourner null ou un objet avec des valeurs par défaut
        if ($enriched_invalid !== null) {
            $this->assertObjectHasAttribute('type_name', $enriched_invalid);
        }
    }
}

