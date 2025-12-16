<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English language strings for Question Diagnostic Tool
 *
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Question Category Management for Deletion';
$string['managequestions'] = 'Manage categories to delete';
$string['accessdenied'] = 'Access denied. You must be a site administrator.';

// Version badge
$string['version_label'] = 'Version';
$string['version_tooltip'] = 'Question Diagnostic Plugin {$a->version} - Last update: {$a->date}';

// Dashboard
$string['dashboard'] = 'Dashboard';
$string['totalcategories'] = 'Total categories';
$string['emptycategories'] = 'Empty categories';
$string['orphancategories'] = 'Orphan categories';
$string['duplicates'] = 'Duplicates detected';
$string['totalquestions'] = 'Total questions';

// Filters
$string['filters'] = 'Filters and search';
$string['search'] = 'Search';
$string['searchplaceholder'] = 'Category name or ID...';
$string['status'] = 'Status';
$string['context'] = 'Context';
$string['all'] = 'All';
$string['empty'] = 'Empty';
$string['orphan'] = 'Orphan';
$string['ok'] = 'OK';

// Actions
$string['actions'] = 'Actions';
$string['delete'] = 'Delete';
$string['merge'] = 'Merge';
$string['move'] = 'Move';
$string['move_root_parent'] = 'Root (parent=0)';
$string['export'] = 'Export to CSV';
$string['bulkdelete'] = 'Delete selection';
$string['selectall'] = 'Select all';
$string['select'] = 'Select';
$string['selected_questions'] = 'Selected questions';
$string['select_group'] = 'Select this group';

$string['current_category_path'] = 'Location (category)';
$string['move_selected_button'] = 'Move selected';
$string['no_selected_questions'] = 'No questions selected';
$string['confirm_move_selected_to_olution'] = 'Confirm moving the selected questions to Olution';
$string['move_selected_warning'] = 'This action will move the selected questions into their target Olution categories. Review the list before confirming.';

// Messages
$string['deleteconfirm'] = 'Are you sure you want to delete this category?';
$string['deletesuccess'] = 'Category deleted successfully';
$string['deleteerror'] = 'Error deleting category';
$string['mergesuccess'] = 'Categories merged successfully';
$string['mergeerror'] = 'Error merging categories';
$string['movesuccess'] = 'Category moved successfully';
$string['moveerror'] = 'Error moving category';
$string['categoriesselected'] = 'category(ies) selected';

// Table
$string['categoryid'] = 'ID';
$string['categoryname'] = 'Name';
$string['categorycontext'] = 'Context';
$string['categoryparent'] = 'Parent';
$string['categoryquestions'] = 'Questions';
$string['categorysubcats'] = 'Subcategories';
$string['categorystatus'] = 'Status';

// Generic labels.
$string['type'] = 'Type';

// Main menu
$string['mainmenu'] = 'Main menu';
$string['toolsmenu'] = 'Available tools';
$string['backtomenu'] = 'Back to main menu';
$string['purge_caches'] = 'Purge caches';
$string['purge_caches_tooltip'] = 'Purge Moodle caches (recommended after changes)';

// Cache purge.
$string['purge_cache_title'] = 'Cache purge';
$string['purge_cache_heading'] = 'Moodle Cache Purge';
$string['purge_cache_why_title'] = 'Why purge caches?';
$string['purge_cache_why_desc'] = 'Purging caches is necessary after:';
$string['purge_cache_reason_lib'] = 'Editing the <code>lib.php</code> file';
$string['purge_cache_reason_update'] = 'Updating the plugin';
$string['purge_cache_reason_functions'] = 'Adding new functions';
$string['purge_cache_reason_bugs'] = 'Fixing bugs';
$string['purge_cache_warning_title'] = '⚠️ Warning';
$string['purge_cache_warning_desc'] = 'Purging caches will:';
$string['purge_cache_effect_reload'] = '✅ Force reload of all PHP files';
$string['purge_cache_effect_fix'] = '✅ Fix "Call to undefined function" errors';
$string['purge_cache_effect_slow'] = '⚠️ Temporarily slow down the site (while caches rebuild)';
$string['purge_cache_effect_logout'] = '⚠️ Potentially log out some users';
$string['purge_cache_recommendation'] = '<strong>Recommendation:</strong> Perform this operation outside peak hours if possible.';
$string['purge_cache_confirm'] = 'Purge Caches Now';
$string['purge_cache_back_previous'] = 'Back to previous page';
$string['purge_cache_running_title'] = '🔄 Purge in progress...';
$string['purge_cache_running_desc'] = 'Please wait, this operation may take a few seconds.';
$string['purge_cache_success_title'] = '✅ Caches purged successfully!';
$string['purge_cache_success_desc'] = 'All Moodle caches have been purged.';
$string['purge_cache_next_steps_title'] = '📋 Next steps';
$string['purge_cache_step_browser_cache'] = '<strong>Clear your browser cache</strong> (Ctrl+Shift+Delete or Cmd+Shift+Delete)';
$string['purge_cache_step_restart_browser'] = '<strong>Close and reopen your browser</strong> (optional but recommended)';
$string['purge_cache_step_test'] = '<strong>Test the feature</strong>: try deleting a question';
$string['purge_cache_test_now'] = '🧪 Test now';
$string['purge_cache_test_functions'] = '🔍 Test Functions';
$string['purge_cache_questions_tool'] = '📊 Questions Tool';
$string['purge_cache_error_title'] = '❌ Error during purge';
$string['purge_cache_error_desc'] = 'An error occurred: {$a}';
$string['purge_cache_error_alternative'] = '<strong>Alternative:</strong> Go to Site administration → Development → Purge caches';
$string['overview'] = 'Global overview';
$string['welcomemessage'] = 'Welcome to the question bank diagnostic tool. This tool allows you to detect and fix issues in your Moodle question bank.';

// Tool 1: Category management for deletion
$string['tool_categories_title'] = 'Category Management for Deletion';
$string['tool_categories_desc'] = 'Manage question categories: detect and fix orphan, empty or duplicate categories. Merge, move or delete problematic categories.';

// 🆕 Category integrity diagnostic (Moodle best practices)
$string['categories_integrity_title'] = 'Category integrity diagnostic';
$string['categories_integrity_desc'] = 'Checks question category integrity (contexts, parent/child hierarchy, idnumber, orphan references) using Moodle best practices. No changes are performed.';
$string['categories_integrity_run'] = 'Run diagnostic';
$string['categories_integrity_stop'] = 'Hide diagnostic';
$string['categories_integrity_ok'] = 'No critical issues detected';
$string['categories_integrity_issues_found'] = 'Critical issues detected';
$string['categories_integrity_warnings_found'] = 'Warnings detected';
$string['categories_integrity_summary'] = '{$a->categories} category(ies) analyzed — errors: {$a->errors}, warnings: {$a->warnings}.';
$string['categories_integrity_details'] = 'Diagnostic details';
$string['categories_integrity_fix'] = 'Auto-fix';
$string['categories_integrity_fix_confirm_title'] = 'Fix category integrity issues';
$string['categories_integrity_fix_confirm_intro'] = 'This action will propose automatic fixes (with confirmation) for some detected structural inconsistencies.';
$string['categories_integrity_fix_warning'] = '⚠️ This action MODIFIES the database. Please make a backup before confirming.';
$string['categories_integrity_fix_operations'] = 'Proposed changes';
$string['categories_integrity_fix_done'] = 'Fix completed: {$a->success} success, {$a->failed} failure(s).';
$string['categories_integrity_fix_failed'] = 'Some fixes failed:';
$string['categories_integrity_fix_nothing'] = 'No automatic fix applicable.';

// 🆕 Question integrity diagnostic
$string['questions_integrity_title'] = 'Question integrity diagnostic';
$string['questions_integrity_desc'] = 'Analyzes question bank integrity (versioning, orphan entries, broken references, missing question types). No changes are performed.';
$string['questions_integrity_run'] = 'Run diagnostic';
$string['questions_integrity_stop'] = 'Hide diagnostic';
$string['questions_integrity_ok'] = 'No critical issues detected';
$string['questions_integrity_issues_found'] = 'Critical issues detected';
$string['questions_integrity_warnings_found'] = 'Warnings detected';
$string['questions_integrity_summary'] = '{$a->questions} question(s) analyzed — errors: {$a->errors}, warnings: {$a->warnings}.';
$string['questions_integrity_details'] = 'Diagnostic details';
$string['questions_integrity_fix'] = 'Fix automatically';
$string['questions_integrity_fix_confirm_title'] = 'Fix question integrity issues';
$string['questions_integrity_fix_confirm_intro'] = 'This action will propose automatic fixes (with confirmation) for some detected structural inconsistencies in the question bank.';
$string['questions_integrity_fix_warning'] = '⚠️ This action MODIFIES the database. Please make a backup before confirming.';
$string['questions_integrity_fix_operations'] = 'Proposed changes';
$string['questions_integrity_fix_done'] = 'Fix completed: {$a->success} success, {$a->failed} failure(s).';
$string['questions_integrity_fix_failed'] = 'Some fixes failed:';
$string['questions_integrity_fix_nothing'] = 'No automatic fix applicable.';

// Tool 2: Link checking
$string['tool_links_title'] = 'Link Verification';
$string['tool_links_desc'] = 'Detect questions with broken links to missing images or files in moodledata. Supports all question types, including third-party plugins like "drag and drop on image".';

// Tool 3: Unhide questions
$string['tool_unhide_title'] = 'Unhide Questions';
$string['tool_unhide_desc'] = 'Make all hidden questions visible at once. Only manually hidden (unused) questions will be affected. Deleted questions (soft delete) used in quizzes will be protected.';
$string['unhide_questions'] = 'Unhide questions';
$string['unhide_questions_title'] = 'Hidden Questions Management';
$string['unhide_questions_intro'] = 'This page allows you to make all hidden questions visible at once. Only manually hidden questions (not used in quizzes) will be affected. Deleted questions (soft delete) still referenced in quizzes will be automatically excluded to preserve existing attempt integrity.';
$string['total_hidden_questions'] = 'Hidden Questions';
$string['manually_hidden_only'] = 'Manually hidden (unused)';

// Broken links
$string['brokenlinks'] = 'Question link verification';
$string['brokenlinks_heading'] = 'Diagnostic Tool - Questions with broken links';
$string['brokenlinks_stats'] = 'Global statistics';
$string['questions_with_broken_links'] = 'Problematic Questions';
$string['total_broken_links'] = 'Broken Links';
$string['global_health'] = 'Global Health';
$string['questions_ok'] = 'Questions without issues';
$string['brokenlinks_by_type'] = 'Distribution by question type';
$string['brokenlinks_table'] = 'Questions with broken links';
$string['no_broken_links'] = 'No questions with broken links detected!';
$string['question_id'] = 'Question ID';
$string['question_name'] = 'Question name';
$string['question_content_excerpt'] = 'Excerpt (content)';
$string['question_type'] = 'Type';
$string['question_hidden_status'] = 'Visibility';
$string['question_hidden'] = '🔒 Hidden';
$string['question_visible'] = '👁️ Visible';
$string['question_deleted'] = '🗑️ Deleted';
$string['question_deleted_tooltip'] = 'Question deleted but kept because used in quizzes (soft delete)';
$string['question_hidden_tooltip'] = 'Question manually hidden (not used)';
$string['question_version_count'] = 'Versions';
$string['question_version_count_tooltip'] = 'Number of versions of this question in the bank';
$string['question_category'] = 'Category';
$string['broken_links_count'] = 'Broken links';
$string['broken_links_details'] = 'Details';
$string['field'] = 'Field';
$string['url'] = 'URL';
$string['reason'] = 'Reason';
$string['repair_options'] = 'Repair options';
$string['repair'] = 'Repair';
$string['remove_reference'] = 'Remove reference';
$string['remove_reference_confirm'] = 'Are you sure you want to remove this reference?';
$string['remove_reference_desc'] = 'Replaces the link with [Image removed]';
$string['repair_modal_title'] = 'Repair options';
$string['repair_recommendation'] = 'First check the question in the question bank to see if files can be manually reuploaded. Removing the reference is a last resort solution.';
$string['file_not_found'] = 'Image file not found';
$string['pluginfile_not_found'] = 'Pluginfile not found';
$string['bgimage_missing'] = 'Background image missing';
$string['link_removed_success'] = 'Broken link removed successfully.';
$string['link_removed_error'] = 'Error removing link.';

// Tips
$string['usage_tips'] = 'Usage tips';
$string['tip_orphan_categories'] = 'Orphan categories: These are categories whose context (course, module) no longer exists. They should be merged or deleted.';
$string['tip_empty_categories'] = 'Empty categories: Categories with no questions or subcategories. They can be safely deleted.';
$string['tip_broken_links'] = 'Broken links: Images or files referenced in questions but missing from moodledata. This may affect question display.';
$string['tip_backup'] = 'Backup recommended: Before any deletion or merge operation, it is recommended to backup your database.';

// Tool 3: Question statistics
$string['tool_questions_title'] = 'Question Statistics';
$string['tool_questions_desc'] = 'Analyze all your questions in detail: identify used/unused questions, detect duplicates with similarity calculation, and access comprehensive statistics. Filter and sort easily for efficient cleanup.';

// Question statistics page
$string['questions_cleanup'] = 'Question Statistics and Cleanup';
$string['questions_cleanup_heading'] = 'Analysis Tool - Comprehensive Question Statistics';
$string['questions_stats'] = 'Global question statistics';
$string['loading_stats'] = 'Statistics calculation may take time if you have many questions.';
$string['loading_questions'] = 'Loading questions...';
$string['loading_large_db'] = 'This may take a few moments for large databases.';

// Statistics
$string['total_questions_stats'] = 'Total Questions';
$string['questions_used'] = 'Used Questions';
$string['questions_unused'] = 'Unused Questions';
$string['questions_duplicates'] = 'Duplicate Questions';
$string['questions_hidden'] = 'Hidden Questions';
$string['questions_broken_links'] = 'Broken Links';
$string['questions_with_problems'] = 'Questions with problems';
$string['in_database'] = 'In database';
$string['in_quizzes_or_attempts'] = 'In quizzes or with attempts';
$string['close'] = 'Close';
$string['never_used'] = 'Never used';
$string['total_duplicates_found'] = 'total duplicates';
$string['not_visible'] = 'Not visible';

// Distribution
$string['distribution_by_type'] = 'Distribution by question type';

// Columns
$string['columns_to_display'] = 'Columns to display';
$string['column_id'] = 'ID';
$string['column_name'] = 'Name';
$string['column_type'] = 'Type';
$string['column_category'] = 'Category';
$string['column_context'] = 'Context';
$string['column_creator'] = 'Creator';
$string['column_created'] = 'Created date';
$string['column_modified'] = 'Modified date';
$string['column_visible'] = 'Visible';
$string['column_quizzes'] = 'Quizzes';
$string['column_attempts'] = 'Attempts';
$string['column_duplicates'] = 'Duplicates';
$string['column_excerpt'] = 'Excerpt';
$string['column_actions'] = 'Actions';

// Advanced filters
$string['filter_search_placeholder'] = 'Name, ID, text...';
$string['filter_usage'] = 'Usage';
$string['filter_all'] = 'All';
$string['filter_used'] = 'Used';
$string['filter_unused'] = 'Unused';
$string['filter_duplicates'] = 'Duplicates';
$string['filter_with_duplicates'] = 'With duplicates';
$string['filter_no_duplicates'] = 'Without duplicates';

// Table
$string['questions_list'] = 'Detailed question list';
$string['view_category'] = 'View category';
$string['used_in_quiz'] = 'Used in {$a} quiz(zes)';
$string['view_question'] = 'View';
$string['view_in_bank'] = 'View in question bank';

// Duplicates
$string['duplicates_modal_title'] = 'Duplicate questions';
$string['duplicates_detected'] = 'duplicate question(s) detected';
$string['duplicates_similar'] = 'These questions have similar content (name, text, type).';
$string['duplicates_recommendation'] = 'Manually verify these questions to confirm they are duplicates. You can then delete or merge redundant questions.';
$string['click_to_view_duplicates'] = 'Click to view duplicates';

// Export
$string['export_questions_csv'] = 'Export questions to CSV';

// Result messages
$string['questions_displayed'] = '{$a->visible} question(s) displayed out of {$a->total}';

// Buttons
$string['toggle_columns'] = 'Columns';
$string['analyze_questions'] = 'Analyze questions';

// Test page
$string['test_page_title'] = 'Test page';
$string['test_page_heading'] = 'Test page';
$string['test_page_desc'] = 'Test page to perform checks and test functionalities.';
$string['test_content'] = 'Test';

// 🆕 v1.9.0 : Safe question deletion
$string['delete_question_forbidden'] = 'Deletion Forbidden';
$string['cannot_delete_question'] = 'This question cannot be deleted';
$string['reason'] = 'Reason';
$string['protection_rules'] = 'Protection Rules';
$string['protection_rules_desc'] = 'To ensure the safety of your educational data, this plugin applies strict rules:';
$string['rule_used_protected'] = 'Questions used in quizzes or with attempts are PROTECTED';
$string['rule_hidden_protected'] = 'Hidden questions are PROTECTED';
$string['rule_unique_protected'] = 'Unique questions (without duplicates) are PROTECTED';
$string['rule_duplicate_deletable'] = 'Only duplicate AND unused AND visible questions can be deleted';
$string['backtoquestions'] = 'Back to questions list';
$string['confirm_delete_question'] = 'Confirm Deletion';
$string['question_to_delete'] = 'Question to Delete';
$string['duplicate_info'] = 'Duplicate Information';
$string['action_irreversible'] = 'This action is IRREVERSIBLE!';
$string['confirm_delete_message'] = 'Are you absolutely sure you want to delete this question? Other versions (duplicates) will be kept.';
$string['confirm_delete'] = 'Yes, delete permanently';
$string['question_deleted_success'] = 'Question successfully deleted';
$string['question_protected'] = 'Protected question';
$string['question_hidden_protected'] = 'Hidden question protected';
$string['question_hidden_info'] = 'This question is hidden in the question bank. Hidden questions are protected against deletion to prevent accidental loss of educational content.';

// 🆕 v1.10.5 : Deletable column
$string['deletable'] = 'Deletable';
$string['deletable_yes'] = 'YES';
$string['deletable_no'] = 'NO';
$string['deletable_reason_category_questions'] = '{$a} question(s)';
$string['deletable_reason_category_subcategories'] = '{$a} subcategory(ies)';
$string['deletable_reason_category_protected'] = 'Protected category';
$string['deletable_reason_question_used'] = 'Question used in {$a} quiz(zes)';
$string['deletable_reason_question_hidden'] = 'Hidden question (protected)';
$string['deletable_reason_question_unique'] = 'Unique question (no duplicate)';
$string['deletable_reason_question_duplicate_unused'] = 'Unused duplicate';

// 🆕 v1.9.40 : Scheduled task
$string['task_scan_broken_links'] = 'Automated broken links scan';

// 🆕 v1.9.41 : Capabilities (granular permissions)
$string['question_diagnostic:view'] = 'View Question Diagnostic plugin';
$string['question_diagnostic:viewcategories'] = 'View categories';
$string['question_diagnostic:viewquestions'] = 'View questions';
$string['question_diagnostic:viewbrokenlinks'] = 'View broken links';
$string['question_diagnostic:viewauditlogs'] = 'View audit logs';
$string['question_diagnostic:viewmonitoring'] = 'View monitoring';
$string['question_diagnostic:managecategories'] = 'Manage categories';
$string['question_diagnostic:deletecategories'] = 'Delete categories';
$string['question_diagnostic:mergecategories'] = 'Merge categories';
$string['question_diagnostic:movecategories'] = 'Move categories';
$string['question_diagnostic:deletequestions'] = 'Delete questions';
$string['question_diagnostic:export'] = 'Export data (CSV)';
$string['question_diagnostic:configureplugin'] = 'Configure plugin';

// 🆕 v1.9.45 : Duplicate groups summary table
$string['duplicate_groups_table_title'] = 'Duplicate question groups';
$string['duplicate_group_name'] = 'Question name';
$string['duplicate_group_count'] = 'Number of duplicates';
$string['duplicate_group_used'] = 'Used versions';
$string['duplicate_group_unused'] = 'Unused versions';
$string['duplicate_group_deletable'] = 'Deletable'; // 🆕 v1.9.53
$string['duplicate_group_deletable_help'] = 'Number of actually deletable versions (unused and unprotected duplicates)'; // 🆕 v1.9.53
$string['duplicate_group_details'] = 'Details';

// Duplicate group details - Clarified terminology
$string['duplicate_instances_count'] = 'Number of duplicate instances';
$string['used_instances'] = 'Used instances';
$string['unused_instances'] = 'Unused instances';
$string['all_duplicate_instances'] = 'All duplicate instances of this question';
$string['representative_marker'] = '🎯 Representative instance (used to identify this group)';
$string['duplicate_analysis'] = 'Duplicate group analysis';
$string['total_instances'] = 'Total instances';
$string['used_instances_desc'] = 'Used instances (present in at least 1 quiz)';
$string['unused_instances_deletable'] = 'Unused instances (deletable)';
$string['total_quizzes_using'] = 'Total quizzes using these instances';
$string['total_usages_count'] = 'Total usages in quizzes';
$string['recommendation_unused'] = 'This group contains <strong>{$a->unused} unused instance(s)</strong> that could be deleted to clean up the database. Used instances ({$a->used}) must be kept.';
$string['recommendation_all_used'] = 'All instances of this question are used. No deletion recommended.';
$string['optimized_mode_enabled'] = 'Optimized mode enabled'; // 🆕 v1.9.53
$string['optimized_mode_desc'] = 'Only groups containing at least 1 deletable version are displayed. Groups where all versions are used or protected are automatically hidden to speed up display.'; // 🆕 v1.9.53
$string['load_more_groups'] = 'Load 5 more groups';
$string['showing_groups'] = 'Showing {$a->shown} group(s) of {$a->total}';
$string['question_group_detail_title'] = 'Duplicate group details';
$string['back_to_groups_list'] = 'Back to groups list';
$string['no_duplicate_groups_found'] = 'No duplicate groups found';
$string['no_duplicate_groups_desc'] = 'All your questions are unique. No duplicates detected.';
$string['group_summary'] = 'Group summary';
$string['all_versions_in_group'] = 'All versions of this question';

// 🆕 v1.9.49 : Automatic cleanup of duplicates
$string['cleanup_group'] = 'Clean up';
$string['cleanup_selection'] = 'Clean up selection';
$string['cleanup_confirm_title'] = 'Cleanup confirmation';
$string['cleanup_confirm_message'] = 'This action will delete {$a} unused version(s)';
$string['cleanup_success'] = 'Cleanup completed: {$a->deleted} question(s) deleted, {$a->kept} version(s) kept';
$string['cleanup_no_action'] = 'No questions to delete in the selected groups';

// 🆕 v1.9.52 : Global duplicate cleanup
$string['cleanup_all_duplicates'] = 'Global Duplicate Cleanup';
$string['cleanup_all_duplicates_desc'] = 'Automatically delete ALL unused duplicates from the site';
$string['cleanup_all_preview_title'] = 'Global cleanup preview';
$string['cleanup_all_preview_desc'] = 'Here is a preview of what will be deleted during the global duplicate cleanup';
$string['cleanup_all_stats_groups'] = 'Duplicate groups to clean';
$string['cleanup_all_stats_to_delete'] = 'Questions to delete';
$string['cleanup_all_stats_to_keep'] = 'Questions to keep';
$string['cleanup_all_estimated_time'] = 'Estimated time';
$string['cleanup_all_estimated_batches'] = 'Number of processing batches';
$string['cleanup_all_download_csv'] = 'Download complete list (CSV)';
$string['cleanup_all_confirm_button'] = 'Confirm and start cleanup';
$string['cleanup_all_warning'] = '⚠️ WARNING: This action will delete {$a} question(s) IRREVERSIBLY!';
$string['cleanup_all_progress_title'] = 'Cleanup in progress...';
$string['cleanup_all_progress_batch'] = 'Processing batch {$a->current} of {$a->total}';
$string['cleanup_all_progress_stats'] = 'Deleted: {$a->deleted} | Kept: {$a->kept}';
$string['cleanup_all_complete_title'] = 'Global cleanup completed';
$string['cleanup_all_complete_summary'] = 'Summary: {$a->deleted} question(s) deleted, {$a->kept} version(s) kept from {$a->groups} group(s) processed';
$string['cleanup_all_by_type_title'] = 'Distribution by question type';
$string['cleanup_all_security_rules'] = 'Applied security rules';
$string['cleanup_all_no_duplicates'] = 'No duplicates to clean';
$string['cleanup_all_no_duplicates_desc'] = 'Your database contains no duplicate questions to delete. All your questions are either unique or all versions are in use.';

// 🆕 v1.10.0 : Orphan files management
$string['orphan_files'] = 'Orphan Files';
$string['orphan_files_heading'] = 'Orphan Files Management';
$string['orphan_files_description'] = 'Detection and cleanup of orphan files in Moodle';
$string['orphan_files_tool_desc'] = 'Identifies files in the database or in moodledata that are no longer referenced by any active content';
$string['orphan_db_records'] = 'Orphan DB records';
$string['orphan_physical_files'] = 'Orphan physical files';
$string['total_orphan_files'] = 'Total orphan files';
$string['disk_space_used'] = 'Disk space used';
$string['orphan_by_component'] = 'Distribution by component';
$string['orphan_by_type'] = 'Distribution by type';
$string['orphan_file_id'] = 'File ID';
$string['orphan_filename'] = 'Filename';
$string['orphan_component'] = 'Component';
$string['orphan_filearea'] = 'File area';
$string['orphan_filesize'] = 'Size';
$string['orphan_type'] = 'Orphan type';
$string['orphan_reason'] = 'Reason';
$string['orphan_age'] = 'Age';
$string['orphan_created'] = 'Created';
$string['orphan_reason_context'] = 'Invalid context';
$string['orphan_reason_parent'] = 'Parent element deleted';
$string['orphan_reason_unreferenced'] = 'Unreferenced';
$string['confirm_delete_orphans'] = 'Confirm deletion of orphan files';
$string['confirm_delete_orphans_message'] = 'Are you sure you want to delete {$a} orphan file(s)?';
$string['delete_orphans_warning'] = '⚠️ WARNING: This action is IRREVERSIBLE! Space to be freed: {$a}';
$string['delete_orphan_success'] = 'Orphan file deleted successfully';
$string['delete_orphan_error'] = 'Error deleting orphan file';
$string['archive_orphan'] = 'Archive';
$string['archive_orphans'] = 'Archive selection';
$string['archive_success'] = 'Files archived successfully in {$a}';
$string['archive_error'] = 'Error during archiving';
$string['export_orphans'] = 'Export orphan files';
$string['no_orphan_files'] = 'No orphan files detected';
$string['no_orphan_files_desc'] = 'Your file system is healthy. All files are properly referenced.';
$string['dry_run_mode'] = 'Simulation Mode (Dry-Run)';
$string['dry_run_enabled'] = 'Simulation mode enabled - No actual deletion';
$string['dry_run_would_delete'] = 'WOULD BE deleted';
$string['filter_by_component'] = 'Filter by component';
$string['filter_by_age'] = 'Filter by age';
$string['age_recent'] = '< 1 month';
$string['age_medium'] = '1-6 months';
$string['age_old'] = '> 6 months';
$string['filter_by_size'] = 'Filter by size';
$string['size_small'] = '< 1 MB';
$string['size_medium'] = '1-10 MB';
$string['size_large'] = '> 10 MB';
$string['orphan_files_stats'] = 'Orphan files statistics';
$string['refresh_orphan_analysis'] = 'Refresh analysis';
$string['view_archives'] = 'View archives';
$string['archive_retention_days'] = 'Retention period: {$a} days';
$string['orphan_files_limit_notice'] = 'Analysis limited to {$a} files for performance reasons';

// 🆕 v1.10.1 : Automatic repair of orphan files
$string['repair_orphan'] = 'Repair';
$string['repair_options'] = 'Repair options';
$string['repair_analysis'] = 'Repair analysis';
$string['repair_possible'] = 'Repair possible';
$string['repairability'] = 'Repairability';
$string['repairability_high'] = 'High (>90%)';
$string['repairability_medium'] = 'Medium (60-90%)';
$string['repairability_low'] = 'Low (<60%)';
$string['repair_contenthash'] = 'Reassociation by contenthash';
$string['repair_contenthash_desc'] = 'Identical file found with valid parent';
$string['repair_filename'] = 'Reassignment by name';
$string['repair_filename_candidates'] = 'candidate(s) found';
$string['repair_filename_desc'] = 'Questions containing this filename';
$string['repair_context'] = 'Reassociation by context';
$string['repair_context_desc'] = 'Potential parents in the same context';
$string['repair_recovery'] = 'Create recovery question';
$string['repair_recovery_desc'] = 'Create a "stub" question to preserve the file';
$string['repair_confidence'] = 'Confidence level';
$string['repair_target'] = 'Repair target';
$string['repair_modal_title'] = 'Orphan file repair';
$string['repair_select_option'] = 'Select a repair option';
$string['repair_confirm'] = 'Confirm repair';
$string['repair_success_contenthash'] = 'File reassociated successfully (contenthash)';
$string['repair_success_filename'] = 'File reassigned successfully (filename)';
$string['repair_success_recovery'] = 'Recovery question created successfully';
$string['repair_error'] = 'Error during repair';
$string['repair_file_not_found'] = 'File not found';
$string['repair_no_target_found'] = 'No repair target found';
$string['repair_no_target_selected'] = 'No target selected';
$string['repair_target_not_found'] = 'Repair target not found';
$string['repair_context_not_found'] = 'Context not found';
$string['repair_unknown_type'] = 'Unknown repair type';
$string['repair_would_execute'] = 'Repair WOULD BE executed';
$string['repair_dry_run'] = 'Test (Dry-Run)';
$string['repair_execute'] = 'Repair Now';
$string['repair_bulk_analysis'] = 'Bulk repairability analysis';
$string['repair_bulk_stats'] = 'Repair statistics';
$string['repair_high_confidence_count'] = '{$a} file(s) high confidence';
$string['repair_medium_confidence_count'] = '{$a} file(s) medium confidence';
$string['repair_low_confidence_count'] = '{$a} file(s) no obvious repair';
$string['repair_auto_recommended'] = 'Automatic repair recommended';
$string['repair_manual_recommended'] = 'Manual validation recommended';
$string['repair_not_recommended'] = 'Archiving or deletion recommended';

// 🆕 v1.10.1: Unused questions page
$string['unused_questions'] = 'Unused questions';
$string['unused_questions_title'] = 'Unused questions';
$string['unused_questions_heading'] = 'Unused questions management';
$string['unused_questions_info'] = 'This page displays all questions that are not used in quizzes and have no associated attempts. These questions can potentially be deleted to clean up your database.';
$string['unused_questions_list'] = 'List of unused questions';
$string['no_unused_questions'] = 'No unused questions found';
$string['no_unused_questions_desc'] = 'All your questions are used in at least one quiz or have attempts. Congratulations! Your question bank is perfectly optimized.';
$string['export_unused_csv'] = 'Export unused questions to CSV';
$string['load_more_questions'] = 'Load 50 more questions';
$string['statistics'] = 'Statistics';
$string['tool_unused_questions_title'] = 'Unused questions';
$string['tool_unused_questions_desc'] = 'View and manage all questions that are not used in quizzes. Identify obsolete questions, delete them in bulk, or export them for archiving.';

// 🆕 v1.10.2: Global categories cleanup
$string['cleanup_all_categories'] = 'Global Categories Cleanup';
$string['cleanup_all_categories_desc'] = 'Automatically delete ALL deletable categories from the site';
$string['cleanup_all_categories_preview_title'] = 'Preview of global categories cleanup';
$string['cleanup_all_categories_preview_desc'] = 'Here is a preview of all categories that will be deleted during global cleanup. Only empty and unprotected categories will be deleted.';
$string['cleanup_all_categories_nothing_desc'] = 'All your categories are either in use or protected. No cleanup is needed.';
$string['cleanup_all_categories_warning'] = '⚠️ WARNING: This action will permanently delete {$a} category(ies) in an IRREVERSIBLE way!';
$string['cleanup_all_nothing_to_delete'] = 'Nothing to delete';
$string['cleanup_all_complete_title'] = 'Global cleanup completed';
$string['cleanup_all_complete_summary'] = '{$a->deleted} category(ies) deleted';
$string['total_categories'] = 'Total categories';
$string['backtocategories'] = 'Back to categories';

// Olution duplicates (v1.10.4+)
$string['olution_duplicates_title'] = 'Automatic Move to Olution';
$string['olution_duplicates_heading'] = 'Course → Olution Duplicates Management';
$string['olution_not_found'] = 'No system-level shared questions category was found';
$string['olution_not_found_help'] = 'To use this feature, you must already have an existing "Olution" question category (the plugin will not create it).<br>
Detection options:<br>
• System context: a question category with a name containing "Olution"<br>
• OR Course context (within the "Olution" course category): a question category with a "commun" subcategory<br>
<br>Then, duplicates outside Olution can be moved into the existing Olution subcategory where the duplicate already exists.';
$string['olution_total_duplicates'] = 'Duplicates detected';
$string['olution_movable_questions'] = 'Movable questions';
$string['olution_unmovable_questions'] = 'No match';
$string['olution_subcategories_count'] = 'Olution subcategories';
$string['olution_courses_count'] = 'Courses in Olution';
$string['source_course_and_category'] = 'Source course / Category';
$string['olution_target'] = 'Target Olution course / Category';
$string['olution_no_duplicates_found'] = 'No duplicates detected between course categories and Olution';
$string['olution_move_all_button'] = 'Move all questions ({$a})';
$string['olution_duplicates_list'] = 'List of detected duplicates';
$string['olution_duplicates_strict_info'] = '“Certain duplicates” mode: same type (qtype) + strictly identical text (questiontext).';
$string['course_category'] = 'Course category';
$string['olution_target_category'] = 'Target Olution category';
$string['similarity'] = 'Similarity';
$string['no_match'] = 'No match';
$string['confirm_move_to_olution'] = 'Confirm move to Olution';
$string['move_details'] = 'Move details';
$string['from_category'] = 'From category';
$string['to_category'] = 'To category';
$string['move_warning'] = 'This action will move the question from its current category to the corresponding Olution category. This operation is reversible (you can move it again manually if needed).';
$string['move_success'] = 'Question successfully moved to Olution';
$string['move_error'] = 'Error during move';
$string['confirm_move_all_to_olution'] = 'Confirm bulk move to Olution';
$string['move_all_details'] = 'Bulk move details';
$string['total_questions_to_move'] = 'Number of questions to move';
$string['affected_categories'] = 'Affected source categories';
$string['affected_courses'] = 'Affected source courses';
$string['from_course_category'] = 'Source course / Category';
$string['to_course_category'] = 'Target Olution course / Category';
$string['move_all_warning'] = 'This action will move ALL detected duplicate questions to their corresponding Olution categories. Although this operation is reversible (manual move), it potentially affects a large number of questions. Make sure you have reviewed the list of duplicates before proceeding.';
$string['no_movable_questions'] = 'No movable questions found';
$string['move_batch_result'] = '{$a->success} question(s) successfully moved, {$a->failed} error(s)';

// Olution triage (commun > Question à trier).
$string['olution_triage_title'] = 'Question triage (commun → subcategories)';
$string['olution_triage_heading'] = 'Triage questions from \"Question à trier\"';
$string['olution_triage_button'] = 'Triage \"Question à trier\" ({$a})';
$string['olution_triage_not_found'] = 'Category \"Question à trier\" was not found under \"commun\".';
$string['olution_triage_not_found_help'] = 'To use this triage, create a question subcategory named \"Question à trier\" under the \"commun\" category (itself under Olution).';
$string['olution_triage_detected'] = 'Triage category detected:'; 
$string['olution_triage_signatures'] = '{$a} signature(s) (name + type) with a detected target';
$string['olution_triage_movable_questions'] = 'Triageable questions';
$string['olution_triage_explain'] = 'Moves questions placed in \"Question à trier\" into the \"commun\" subcategory where a duplicate (same name + same type) already exists.';
$string['olution_triage_move_all_button'] = 'Move all triageable questions ({$a})';
$string['olution_triage_no_movable'] = 'No questions to move (no match found in other commun subcategories).';
$string['olution_triage_list_title'] = 'Questions in \"Question à trier\" with a match';
$string['olution_triage_no_candidates_page'] = 'Nothing to display on this page.';
$string['confirm_move_all_triage_to_olution'] = 'Confirm triage (move questions)';
$string['triage_move_all_warning'] = 'This action will move questions located in \"Question à trier\" into the matching \"commun\" subcategories when duplicates (name + type) exist. Review the list before confirming.';
$string['invalid_parameters'] = 'Invalid parameters';
$string['invalid_action'] = 'Invalid action';

// Olution auto sort (commun > "Question à trier" → text suggestion).
$string['olution_auto_sort_title'] = 'Auto sort (text) — "Question à trier"';
$string['olution_auto_sort_heading'] = 'Auto sort questions (title + content)';
$string['olution_auto_sort_button'] = 'Auto sort (text) ({$a})';
$string['olution_auto_sort_explain'] = 'Lists questions placed in "Question à trier" and suggests an EXISTING target subcategory under "commun" whose label (and path) best matches the question title + content. If no target stands out, a proposed new category title is shown (no creation).';
$string['olution_auto_sort_mode'] = 'Mode';
$string['olution_auto_sort_mode_heuristic'] = 'Heuristic';
$string['olution_auto_sort_mode_ai'] = 'AI (Moodle/OpenAI)';
$string['olution_auto_sort_ai_unavailable'] = 'Moodle AI is not available (or not configured).';
$string['olution_auto_sort_fallback_active'] = 'AI mode was requested, but AI did not respond: fallback heuristic is being used.';
$string['olution_auto_sort_partial_fallback'] = 'AI mode requested: {$a} row(s) fell back to the heuristic.';
$string['olution_auto_sort_used_mode'] = 'Used mode';
$string['olution_auto_sort_used_mode_ai'] = 'AI';
$string['olution_auto_sort_used_mode_fallback'] = 'Fallback';
$string['olution_auto_sort_used_mode_heuristic'] = 'Heuristic';
$string['olution_auto_sort_threshold'] = 'Threshold (0–1)';
$string['olution_auto_sort_no_results'] = 'No questions to display.';
$string['olution_auto_sort_suggestion'] = 'Suggestion';
$string['olution_auto_sort_score'] = 'Score: {$a->score}';
$string['olution_auto_sort_no_match'] = 'No existing category matches sufficiently.';
$string['olution_auto_sort_proposed_new_category'] = 'Proposed new category: {$a}';
$string['question_content'] = 'Content';

// AI debug.
$string['ai_debug_title'] = 'Moodle AI diagnostics (OpenAI)';
$string['ai_debug_heading'] = 'AI diagnostics';
$string['ai_debug_link'] = '🔎 AI diagnostics (understand fallback)';
$string['ai_debug_classes'] = 'Detected AI classes';
$string['ai_debug_test'] = 'AI call test (best-effort)';

// 🆕 v1.11.5 : Course category filter
$string['course_category_filter'] = 'Course category';
$string['course_category_filter_desc'] = 'Filter question categories by course category';
$string['all_course_categories'] = 'All course categories';
$string['filter_active_course_category'] = 'Active filter: Course category';
$string['show_all_course_categories'] = 'Show all course categories';
$string['course_category_filter_info'] = 'Displaying question categories for course category';

// 🆕 v1.11.29 : Question categories by course / activity (with questions)
$string['tool_categories_by_context_title'] = 'Categories by course / activity';
$string['tool_categories_by_context_desc'] = 'Lists question categories linked to a course (and/or an activity such as a quiz) and keeps only those that contain questions (directly or via subcategories).';
$string['tool_categories_by_context_open'] = 'Open list';

$string['tool_categories_by_context_course_search'] = 'Search a course';
$string['tool_categories_by_context_course_search_placeholder'] = 'Course name or shortname (at least 2 characters)…';
$string['tool_categories_by_context_course'] = 'Course';
$string['tool_categories_by_context_course_placeholder'] = '— Select a course —';
$string['tool_categories_by_context_course_help'] = 'Pick a course category to populate a dropdown, or type a search (name/shortname) then submit to get a result list.';

$string['tool_categories_by_context_scope'] = 'Scope';
$string['tool_categories_by_context_scope_all'] = 'Course + activities';
$string['tool_categories_by_context_scope_course'] = 'Course only';
$string['tool_categories_by_context_scope_activities'] = 'Activities only';
$string['tool_categories_by_context_scope_quiz'] = 'Quizzes only';
$string['tool_categories_by_context_scope_activity'] = 'A specific activity';

$string['tool_categories_by_context_activity'] = 'Activity (cmid)';
$string['tool_categories_by_context_activity_all'] = 'All';
$string['tool_categories_by_context_include_system'] = 'Include system context';
$string['tool_categories_by_context_apply'] = 'Show';

$string['tool_categories_by_context_intro_title'] = 'Select a course';
$string['tool_categories_by_context_intro'] = 'Choose a course category and a course, or use the search to select a course, to list question categories that contain questions.';
$string['tool_categories_by_context_activity_required'] = 'Please select an activity (cmid) for the “specific activity” scope.';
$string['tool_categories_by_context_activity_not_quiz'] = 'The “Quizzes only” scope requires a quiz activity. Selected activity: {$a->modname}.';

$string['tool_categories_by_context_no_contexts'] = 'No contexts to analyze with the current criteria.';
$string['tool_categories_by_context_no_categories'] = 'No question categories were found for these contexts.';

$string['tool_categories_by_context_summary_title'] = 'Summary';
$string['tool_categories_by_context_summary'] = 'Course: <strong>{$a->course}</strong> — contexts analyzed: <strong>{$a->contexts}</strong> — categories with questions: <strong>{$a->categories}</strong> — direct entries: <strong>{$a->directquestions}</strong> (visible: <strong>{$a->directvisible}</strong>).';
$string['tool_categories_by_context_none_with_questions'] = 'No category contains questions with the current criteria.';

$string['tool_categories_by_context_direct'] = 'Direct';
$string['tool_categories_by_context_direct_help'] = 'Number of question bank entries directly in this category.';
$string['tool_categories_by_context_total'] = 'Total (tree)';
$string['tool_categories_by_context_total_help'] = 'Number of question bank entries in this category + all its subcategories (recursive).';
$string['tool_categories_by_context_visible_direct'] = 'Visible (direct)';
$string['tool_categories_by_context_visible_total'] = 'Visible (tree)';
$string['tool_categories_by_context_move_root'] = '— Root (parent = 0) —';
$string['tool_categories_by_context_move_to'] = 'Move category to a new parent (same context)';
$string['tool_categories_by_context_move_button_help'] = 'Moves the category (changes its parent). A confirmation will be required.';
$string['tool_categories_by_context_move_to_olution_commun'] = 'Move to Olution / commun (same context)';
$string['tool_categories_by_context_move_button'] = 'Move';
$string['tool_categories_by_context_move_olution_commun_context_mismatch'] = 'Olution/commun is in a different context: Moodle does not allow moving a question category across contexts (even if the target is accessible from the course). Use “📥 Send to Catégories à trier” to move the questions instead.';
$string['tool_categories_by_context_move_questions_to_triage_title'] = 'Move questions to Olution / commun / Catégories à trier';
$string['tool_categories_by_context_move_questions_to_triage_button'] = '📥 Send to “Catégories à trier”';
$string['tool_categories_by_context_move_questions_to_triage_button_help'] = 'Moves all questions (from this category) to Olution/commun/Catégories à trier. A confirmation will be required.';
$string['tool_categories_by_context_move_questions_to_triage_nothing'] = 'No questions to move from this category.';
$string['tool_categories_by_context_move_questions_to_triage_summary_title'] = 'Move summary';
$string['tool_categories_by_context_move_questions_to_triage_summary'] = 'Source: <strong>{$a->sourcecategory}</strong><br>Target: <strong>{$a->targetcategory}</strong><br>Questions to move: <strong>{$a->count}</strong>';
$string['tool_categories_by_context_move_questions_to_triage_warning'] = '⚠️ This action MODIFIES the database (changes the question category). Make sure the target context is allowed for this course.';
$string['tool_categories_by_context_move_questions_to_triage_not_accessible'] = 'Target Olution/commun category is not accessible from the course (courseid={$a->courseid}). Target context={$a->targetcontextid}. Method={$a->method}.';
$string['tool_categories_by_context_categories_to_sort_not_found'] = 'Target category not found: create a question subcategory named “Catégories à trier” under Olution → commun.';

