<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tests unitaires pour les permissions (capabilities)
 * 
 * 🆕 v1.9.42 : Option E - Tests complets
 *
 * @package    local_question_diagnostic
 * @category   test
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/question_diagnostic/lib.php');

/**
 * Tests pour les fonctions de permissions
 *
 * @covers ::local_question_diagnostic_can_view
 * @covers ::local_question_diagnostic_can_view_categories
 * @covers ::local_question_diagnostic_can_delete_categories
 */
class local_question_diagnostic_permissions_test extends advanced_testcase {

    /**
     * Test admin a toutes les permissions
     */
    public function test_admin_has_all_permissions() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        
        // Un admin devrait avoir toutes les permissions
        $this->assertTrue(local_question_diagnostic_can_view());
        $this->assertTrue(local_question_diagnostic_can_view_categories());
        $this->assertTrue(local_question_diagnostic_can_view_questions());
        $this->assertTrue(local_question_diagnostic_can_view_broken_links());
        $this->assertTrue(local_question_diagnostic_can_view_audit_logs());
        $this->assertTrue(local_question_diagnostic_can_view_monitoring());
        $this->assertTrue(local_question_diagnostic_can_manage_categories());
        $this->assertTrue(local_question_diagnostic_can_delete_categories());
        $this->assertTrue(local_question_diagnostic_can_merge_categories());
        $this->assertTrue(local_question_diagnostic_can_move_categories());
        $this->assertTrue(local_question_diagnostic_can_delete_questions());
        $this->assertTrue(local_question_diagnostic_can_export());
        $this->assertTrue(local_question_diagnostic_can_configure_plugin());
    }

    /**
     * Test utilisateur normal sans permissions
     */
    public function test_normal_user_no_permissions() {
        $this->resetAfterTest(true);
        
        // Créer un utilisateur normal
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        
        // Un utilisateur normal ne devrait avoir aucune permission par défaut
        $this->assertFalse(local_question_diagnostic_can_view());
        $this->assertFalse(local_question_diagnostic_can_view_categories());
        $this->assertFalse(local_question_diagnostic_can_delete_categories());
    }

    /**
     * Test utilisateur avec permission view
     */
    public function test_user_with_view_permission() {
        global $DB;
        $this->resetAfterTest(true);
        
        // Créer un utilisateur
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        
        // Créer un rôle et lui donner la permission view
        $roleid = create_role('Question Viewer', 'questionviewer', 'Can view question diagnostic');
        $context = context_system::instance();
        assign_capability('local/question_diagnostic:view', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        
        // Recharger les capabilities
        accesslib_clear_all_caches_for_unit_testing();
        
        // L'utilisateur devrait maintenant avoir la permission view
        $this->assertTrue(local_question_diagnostic_can_view());
        
        // Mais pas les autres permissions
        $this->assertFalse(local_question_diagnostic_can_delete_categories());
        $this->assertFalse(local_question_diagnostic_can_delete_questions());
    }

    /**
     * Test utilisateur avec permission managecategories
     */
    public function test_user_with_manage_permission() {
        global $DB;
        $this->resetAfterTest(true);
        
        // Créer un utilisateur
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        
        // Créer un rôle et lui donner la permission managecategories
        $roleid = create_role('Question Manager', 'questionmanager', 'Can manage categories');
        $context = context_system::instance();
        assign_capability('local/question_diagnostic:managecategories', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $user->id, $context->id);
        
        // Recharger les capabilities
        accesslib_clear_all_caches_for_unit_testing();
        
        // L'utilisateur devrait avoir la permission manage
        $this->assertTrue(local_question_diagnostic_can_manage_categories());
        
        // Mais pas delete questions (admin only)
        $this->assertFalse(local_question_diagnostic_can_delete_questions());
    }

    /**
     * Test require_capability_or_die() avec permission
     */
    public function test_require_capability_or_die_with_permission() {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        
        // Un admin ne devrait pas générer d'exception
        try {
            local_question_diagnostic_require_capability_or_die('local/question_diagnostic:view');
            $this->assertTrue(true); // Si on arrive ici, pas d'exception
        } catch (\Exception $e) {
            $this->fail('Admin ne devrait pas générer d\'exception pour require_capability_or_die');
        }
    }

    /**
     * Test require_capability_or_die() sans permission
     */
    public function test_require_capability_or_die_without_permission() {
        $this->resetAfterTest(true);
        
        // Créer un utilisateur normal
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        
        // Devrait générer une exception
        $this->expectException(\moodle_exception::class);
        local_question_diagnostic_require_capability_or_die('local/question_diagnostic:view');
    }

    /**
     * Test toutes les fonctions de permissions retournent boolean
     */
    public function test_all_permission_functions_return_boolean() {
        $this->resetAfterTest(true);
        $this->setAdminUser();
        
        $this->assertIsBool(local_question_diagnostic_can_view());
        $this->assertIsBool(local_question_diagnostic_can_view_categories());
        $this->assertIsBool(local_question_diagnostic_can_view_questions());
        $this->assertIsBool(local_question_diagnostic_can_view_broken_links());
        $this->assertIsBool(local_question_diagnostic_can_view_audit_logs());
        $this->assertIsBool(local_question_diagnostic_can_view_monitoring());
        $this->assertIsBool(local_question_diagnostic_can_manage_categories());
        $this->assertIsBool(local_question_diagnostic_can_delete_categories());
        $this->assertIsBool(local_question_diagnostic_can_merge_categories());
        $this->assertIsBool(local_question_diagnostic_can_move_categories());
        $this->assertIsBool(local_question_diagnostic_can_delete_questions());
        $this->assertIsBool(local_question_diagnostic_can_export());
        $this->assertIsBool(local_question_diagnostic_can_configure_plugin());
    }
}

