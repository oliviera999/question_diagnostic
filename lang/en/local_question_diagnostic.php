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
$string['export'] = 'Export to CSV';
$string['bulkdelete'] = 'Delete selection';
$string['selectall'] = 'Select all';

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

// Main menu
$string['mainmenu'] = 'Main menu';
$string['toolsmenu'] = 'Available tools';
$string['backtomenu'] = 'Back to main menu';
$string['overview'] = 'Global overview';
$string['welcomemessage'] = 'Welcome to the question bank diagnostic tool. This tool allows you to detect and fix issues in your Moodle question bank.';

// Tool 1: Category management for deletion
$string['tool_categories_title'] = 'Category Management for Deletion';
$string['tool_categories_desc'] = 'Manage question categories: detect and fix orphan, empty or duplicate categories. Merge, move or delete problematic categories.';

// Tool 2: Link checking
$string['tool_links_title'] = 'Link Verification';
$string['tool_links_desc'] = 'Detect questions with broken links to missing images or files in moodledata. Supports all question types, including third-party plugins like "drag and drop on image".';

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
$string['question_type'] = 'Type';
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
$string['rule_unique_protected'] = 'Unique questions (without duplicates) are PROTECTED';
$string['rule_duplicate_deletable'] = 'Only duplicate AND unused questions can be deleted';
$string['backtoquestions'] = 'Back to questions list';
$string['confirm_delete_question'] = 'Confirm Deletion';
$string['question_to_delete'] = 'Question to Delete';
$string['duplicate_info'] = 'Duplicate Information';
$string['action_irreversible'] = 'This action is IRREVERSIBLE!';
$string['confirm_delete_message'] = 'Are you absolutely sure you want to delete this question? Other versions (duplicates) will be kept.';
$string['confirm_delete'] = 'Yes, delete permanently';
$string['question_deleted_success'] = 'Question successfully deleted';
$string['question_protected'] = 'Protected question';

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
$string['duplicate_group_details'] = 'Details';
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

