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
 * Tests unitaires pour category_manager
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
require_once($CFG->dirroot . '/local/question_diagnostic/classes/category_manager.php');

use advanced_testcase;
use context_system;
use category_manager;

/**
 * Tests pour la classe category_manager
 */
class category_manager_test extends advanced_testcase {

    /**
     * Test de récupération des statistiques globales
     */
    public function test_get_global_stats() {
        $this->resetAfterTest(true);

        // Récupérer les stats globales
        $stats = category_manager::get_global_stats();

        // Vérifier que la structure est correcte
        $this->assertIsObject($stats);
        $this->assertObjectHasAttribute('total_categories', $stats);
        $this->assertObjectHasAttribute('empty_categories', $stats);
        $this->assertObjectHasAttribute('orphan_categories', $stats);
        $this->assertObjectHasAttribute('duplicate_names', $stats);
        $this->assertObjectHasAttribute('protected_with_description', $stats);
        $this->assertObjectHasAttribute('protected_root_course', $stats);
        $this->assertObjectHasAttribute('protected_root_all', $stats);

        // Vérifier les types
        $this->assertIsInt($stats->total_categories);
        $this->assertIsInt($stats->empty_categories);
        $this->assertIsInt($stats->orphan_categories);
        $this->assertIsInt($stats->duplicate_names);
    }

    /**
     * Test de création et suppression de catégorie
     */
    public function test_delete_category() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Créer une catégorie de test
        $category = new \stdClass();
        $category->name = 'Test Category for Deletion';
        $category->contextid = context_system::instance()->id;
        $category->info = '';
        $category->infoformat = FORMAT_HTML;
        $category->stamp = make_unique_id_code();
        $category->parent = 0;
        $category->sortorder = 999;
        $category->idnumber = null;

        $categoryid = $DB->insert_record('question_categories', $category);
        $this->assertGreaterThan(0, $categoryid);

        // Vérifier que la catégorie existe
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $categoryid]));

        // Supprimer la catégorie
        $result = category_manager::delete_category($categoryid);

        // Vérifier que la suppression a réussi
        $this->assertTrue($result === true, 'La suppression devrait réussir pour une catégorie vide non protégée');

        // Vérifier que la catégorie n'existe plus
        $this->assertFalse($DB->record_exists('question_categories', ['id' => $categoryid]));
    }

    /**
     * Test de protection des catégories racine (parent = 0)
     * 
     * 🆕 v1.9.29 : Test protection catégories TOP
     */
    public function test_protected_root_category() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Créer une catégorie racine (parent = 0)
        $category = new \stdClass();
        $category->name = 'Test Root Category';
        $category->contextid = context_system::instance()->id;
        $category->info = '';
        $category->infoformat = FORMAT_HTML;
        $category->stamp = make_unique_id_code();
        $category->parent = 0; // RACINE
        $category->sortorder = 999;
        $category->idnumber = null;

        $categoryid = $DB->insert_record('question_categories', $category);

        // Tenter de supprimer la catégorie racine
        $result = category_manager::delete_category($categoryid);

        // Vérifier que la suppression a échoué (catégorie protégée)
        $this->assertIsString($result, 'La suppression devrait échouer avec un message d\'erreur');
        $this->assertStringContainsString('PROTÉGÉE', $result, 'Le message devrait indiquer que la catégorie est protégée');

        // Vérifier que la catégorie existe toujours
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $categoryid]));
    }

    /**
     * Test de protection des catégories avec description
     */
    public function test_protected_category_with_description() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Créer une catégorie avec description
        $category = new \stdClass();
        $category->name = 'Test Category with Description';
        $category->contextid = context_system::instance()->id;
        $category->info = 'Cette catégorie est importante et documentée';
        $category->infoformat = FORMAT_HTML;
        $category->stamp = make_unique_id_code();
        $category->parent = 0;
        $category->sortorder = 999;
        $category->idnumber = null;

        $categoryid = $DB->insert_record('question_categories', $category);

        // Obtenir les stats de la catégorie
        $cat_record = $DB->get_record('question_categories', ['id' => $categoryid]);
        $stats = category_manager::get_category_stats($cat_record);

        // Vérifier que la catégorie est protégée
        $this->assertTrue($stats->is_protected, 'Une catégorie avec description devrait être protégée');
        $this->assertStringContainsString('description', strtolower($stats->protection_reason));
    }

    /**
     * Test de fusion de catégories
     * 
     * 🆕 v1.9.30 : Test avec transactions SQL
     */
    public function test_merge_categories() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $systemcontext = context_system::instance();

        // Créer catégorie source
        $source = new \stdClass();
        $source->name = 'Source Category';
        $source->contextid = $systemcontext->id;
        $source->info = '';
        $source->infoformat = FORMAT_HTML;
        $source->stamp = make_unique_id_code();
        $source->parent = 0;
        $source->sortorder = 999;
        $source->idnumber = null;

        $sourceid = $DB->insert_record('question_categories', $source);

        // Créer catégorie destination
        $dest = new \stdClass();
        $dest->name = 'Destination Category';
        $dest->contextid = $systemcontext->id;
        $dest->info = '';
        $dest->infoformat = FORMAT_HTML;
        $dest->stamp = make_unique_id_code();
        $dest->parent = 0;
        $dest->sortorder = 998;
        $dest->idnumber = null;

        $destid = $DB->insert_record('question_categories', $dest);

        // Vérifier que les deux catégories existent
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $sourceid]));
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $destid]));

        // Fusionner les catégories
        $result = category_manager::merge_categories($sourceid, $destid);

        // Vérifier que la fusion a réussi
        $this->assertTrue($result === true, 'La fusion devrait réussir');

        // Vérifier que la source n'existe plus
        $this->assertFalse($DB->record_exists('question_categories', ['id' => $sourceid]), 
                           'La catégorie source devrait avoir été supprimée');

        // Vérifier que la destination existe toujours
        $this->assertTrue($DB->record_exists('question_categories', ['id' => $destid]),
                          'La catégorie destination devrait toujours exister');
    }

    /**
     * Test de déplacement de catégorie
     * 
     * 🆕 v1.9.30 : Test avec transactions SQL
     */
    public function test_move_category() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $systemcontext = context_system::instance();

        // Créer catégorie parent
        $parent = new \stdClass();
        $parent->name = 'Parent Category';
        $parent->contextid = $systemcontext->id;
        $parent->info = '';
        $parent->infoformat = FORMAT_HTML;
        $parent->stamp = make_unique_id_code();
        $parent->parent = 0;
        $parent->sortorder = 999;
        $parent->idnumber = null;

        $parentid = $DB->insert_record('question_categories', $parent);

        // Créer catégorie enfant
        $child = new \stdClass();
        $child->name = 'Child Category';
        $child->contextid = $systemcontext->id;
        $child->info = '';
        $child->infoformat = FORMAT_HTML;
        $child->stamp = make_unique_id_code();
        $child->parent = 0; // Initialement racine
        $child->sortorder = 998;
        $child->idnumber = null;

        $childid = $DB->insert_record('question_categories', $child);

        // Déplacer l'enfant sous le parent
        $result = category_manager::move_category($childid, $parentid);

        // Vérifier que le déplacement a réussi
        $this->assertTrue($result === true, 'Le déplacement devrait réussir');

        // Vérifier que le parent de l'enfant est correct
        $child_record = $DB->get_record('question_categories', ['id' => $childid]);
        $this->assertEquals($parentid, $child_record->parent, 'Le parent de l\'enfant devrait être mis à jour');
    }

    /**
     * Test de détection de boucle dans move_category
     */
    public function test_move_category_prevents_loop() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $systemcontext = context_system::instance();

        // Créer catégorie A
        $catA = new \stdClass();
        $catA->name = 'Category A';
        $catA->contextid = $systemcontext->id;
        $catA->info = '';
        $catA->infoformat = FORMAT_HTML;
        $catA->stamp = make_unique_id_code();
        $catA->parent = 0;
        $catA->sortorder = 999;
        $catA->idnumber = null;

        $catAid = $DB->insert_record('question_categories', $catA);

        // Créer catégorie B (enfant de A)
        $catB = new \stdClass();
        $catB->name = 'Category B';
        $catB->contextid = $systemcontext->id;
        $catB->info = '';
        $catB->infoformat = FORMAT_HTML;
        $catB->stamp = make_unique_id_code();
        $catB->parent = $catAid;
        $catB->sortorder = 998;
        $catB->idnumber = null;

        $catBid = $DB->insert_record('question_categories', $catB);

        // Tenter de déplacer A sous B (créerait une boucle)
        $result = category_manager::move_category($catAid, $catBid);

        // Vérifier que le déplacement a échoué
        $this->assertIsString($result, 'Le déplacement devrait échouer avec un message d\'erreur');
        $this->assertStringContainsString('boucle', strtolower($result), 
                                         'Le message devrait mentionner la boucle');

        // Vérifier que la structure n'a pas changé
        $catA_after = $DB->get_record('question_categories', ['id' => $catAid]);
        $this->assertEquals(0, $catA_after->parent, 'Le parent de A ne devrait pas avoir changé');
    }
}

