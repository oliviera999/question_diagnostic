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
 * Tests unitaires pour question_analyzer
 * 
 * 🆕 v1.9.30 : Tests de base pour les fonctions critiques d'analyse de questions
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_question_diagnostic;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/question_diagnostic/classes/question_analyzer.php');
require_once($CFG->dirroot . '/question/type/questionbase.php');

use advanced_testcase;
use context_system;
use question_analyzer;

/**
 * Tests pour la classe question_analyzer
 */
class question_analyzer_test extends advanced_testcase {

    /**
     * Test de récupération des statistiques globales
     */
    public function test_get_global_stats() {
        $this->resetAfterTest(true);

        // Récupérer les stats globales
        $stats = question_analyzer::get_global_stats();

        // Vérifier que la structure est correcte
        $this->assertIsObject($stats);
        $this->assertObjectHasAttribute('total_questions', $stats);
        $this->assertObjectHasAttribute('hidden_questions', $stats);
        $this->assertObjectHasAttribute('questions_with_attempts', $stats);

        // Vérifier les types
        $this->assertIsInt($stats->total_questions);
        $this->assertIsInt($stats->hidden_questions);
        $this->assertIsInt($stats->questions_with_attempts);
    }

    /**
     * Test de récupération des questions avec pagination
     * 
     * 🆕 v1.9.30 : Test pagination serveur
     */
    public function test_get_all_questions_with_stats_pagination() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

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

        // Créer des questions de test
        $question_count = 5;
        for ($i = 1; $i <= $question_count; $i++) {
            $question = new \stdClass();
            $question->category = $categoryid;
            $question->name = 'Test Question ' . $i;
            $question->questiontext = 'Question text ' . $i;
            $question->questiontextformat = FORMAT_HTML;
            $question->generalfeedback = '';
            $question->generalfeedbackformat = FORMAT_HTML;
            $question->qtype = 'truefalse';
            $question->length = 1;
            $question->stamp = make_unique_id_code();
            $question->version = make_unique_id_code();
            $question->timecreated = time();
            $question->timemodified = time();
            $question->createdby = 2;
            $question->modifiedby = 2;

            $DB->insert_record('question', $question);
        }

        // Test pagination : récupérer 2 questions à partir de l'offset 1
        $result = question_analyzer::get_all_questions_with_stats(false, 2, 1);

        // Vérifier qu'on a bien 2 questions
        $this->assertCount(2, $result, 'Devrait retourner 2 questions');

        // Test sans pagination
        $result_all = question_analyzer::get_all_questions_with_stats(false, 0, 0);

        // Vérifier qu'on a toutes les questions (au moins les 5 créées)
        $this->assertGreaterThanOrEqual($question_count, count($result_all), 
                                        'Devrait retourner au moins ' . $question_count . ' questions');
    }

    /**
     * Test de définition unique de doublon
     * 
     * 🆕 v1.9.28 : Test are_duplicates()
     */
    public function test_are_duplicates() {
        $this->resetAfterTest(true);

        // Créer deux questions identiques (nom + type)
        $q1 = new \stdClass();
        $q1->name = 'Test Question';
        $q1->qtype = 'truefalse';
        $q1->questiontext = 'Premier texte';

        $q2 = new \stdClass();
        $q2->name = 'Test Question';
        $q2->qtype = 'truefalse';
        $q2->questiontext = 'Deuxième texte différent';

        // Vérifier qu'elles sont considérées comme doublons
        $this->assertTrue(question_analyzer::are_duplicates($q1, $q2),
                         'Deux questions avec même nom et type devraient être des doublons');

        // Créer une question avec un nom différent
        $q3 = new \stdClass();
        $q3->name = 'Other Question';
        $q3->qtype = 'truefalse';
        $q3->questiontext = 'Premier texte';

        // Vérifier qu'elles ne sont PAS considérées comme doublons
        $this->assertFalse(question_analyzer::are_duplicates($q1, $q3),
                          'Deux questions avec noms différents ne devraient pas être des doublons');

        // Créer une question avec un type différent
        $q4 = new \stdClass();
        $q4->name = 'Test Question';
        $q4->qtype = 'multichoice';
        $q4->questiontext = 'Premier texte';

        // Vérifier qu'elles ne sont PAS considérées comme doublons
        $this->assertFalse(question_analyzer::are_duplicates($q1, $q4),
                          'Deux questions avec types différents ne devraient pas être des doublons');
    }

    /**
     * Test de détection de doublons
     */
    public function test_find_exact_duplicates() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $systemcontext = context_system::instance();

        // Créer une catégorie
        $category = new \stdClass();
        $category->name = 'Test Category';
        $category->contextid = $systemcontext->id;
        $category->info = '';
        $category->infoformat = FORMAT_HTML;
        $category->stamp = make_unique_id_code();
        $category->parent = 0;
        $category->sortorder = 999;

        $categoryid = $DB->insert_record('question_categories', $category);

        // Créer 2 questions identiques (doublons)
        $duplicate_name = 'Duplicate Question';
        for ($i = 1; $i <= 2; $i++) {
            $question = new \stdClass();
            $question->category = $categoryid;
            $question->name = $duplicate_name;
            $question->questiontext = 'Text version ' . $i;
            $question->questiontextformat = FORMAT_HTML;
            $question->generalfeedback = '';
            $question->generalfeedbackformat = FORMAT_HTML;
            $question->qtype = 'truefalse';
            $question->length = 1;
            $question->stamp = make_unique_id_code();
            $question->version = make_unique_id_code();
            $question->timecreated = time();
            $question->timemodified = time();
            $question->createdby = 2;
            $question->modifiedby = 2;

            $DB->insert_record('question', $question);
        }

        // Créer 1 question unique
        $unique = new \stdClass();
        $unique->category = $categoryid;
        $unique->name = 'Unique Question';
        $unique->questiontext = 'Unique text';
        $unique->questiontextformat = FORMAT_HTML;
        $unique->generalfeedback = '';
        $unique->generalfeedbackformat = FORMAT_HTML;
        $unique->qtype = 'truefalse';
        $unique->length = 1;
        $unique->stamp = make_unique_id_code();
        $unique->version = make_unique_id_code();
        $unique->timecreated = time();
        $unique->timemodified = time();
        $unique->createdby = 2;
        $unique->modifiedby = 2;

        $DB->insert_record('question', $unique);

        // Chercher les doublons
        $duplicates = question_analyzer::find_exact_duplicates();

        // Vérifier qu'au moins un groupe de doublons est trouvé
        $this->assertIsArray($duplicates);
        $this->assertGreaterThanOrEqual(1, count($duplicates), 
                                        'Au moins un groupe de doublons devrait être trouvé');

        // Vérifier qu'un groupe correspond à nos doublons créés
        $found_our_duplicate = false;
        foreach ($duplicates as $group) {
            if (isset($group[0]->name) && $group[0]->name === $duplicate_name) {
                $found_our_duplicate = true;
                $this->assertCount(2, $group, 'Le groupe devrait contenir 2 questions');
                break;
            }
        }

        $this->assertTrue($found_our_duplicate, 'Notre groupe de doublons devrait être trouvé');
    }

    /**
     * Test du cache pour les stats globales
     */
    public function test_cache_global_stats() {
        $this->resetAfterTest(true);

        // Première récupération (création du cache)
        $stats1 = question_analyzer::get_global_stats();

        // Deuxième récupération (depuis le cache)
        $stats2 = question_analyzer::get_global_stats();

        // Vérifier que les stats sont identiques
        $this->assertEquals($stats1->total_questions, $stats2->total_questions);

        // Purger les caches
        question_analyzer::purge_all_caches();

        // Troisième récupération (cache purgé, recalculé)
        $stats3 = question_analyzer::get_global_stats();

        // Vérifier que les stats sont toujours cohérentes
        $this->assertEquals($stats1->total_questions, $stats3->total_questions);
    }

    /**
     * Test de get_used_duplicates_questions avec pagination
     * 
     * 🆕 v1.9.30 : Test pagination serveur
     */
    public function test_get_used_duplicates_questions_pagination() {
        $this->resetAfterTest(true);

        // Test de base : devrait retourner un tableau (vide ou avec des résultats)
        $result = question_analyzer::get_used_duplicates_questions(10, 0);
        $this->assertIsArray($result, 'Devrait retourner un tableau');

        // Test pagination : offset 10, limit 5
        $result_page2 = question_analyzer::get_used_duplicates_questions(5, 10);
        $this->assertIsArray($result_page2, 'Devrait retourner un tableau pour la page 2');

        // Vérifier que les résultats ne dépassent pas la limite
        $this->assertLessThanOrEqual(5, count($result_page2), 
                                     'Ne devrait pas retourner plus de 5 résultats');
    }
}

