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
$string['olution_not_found_help'] = 'To use this feature, create a question category at the system level (context: System) with:<br>
• A name containing "Olution" (e.g., "Olution", "Olution Questions", "Olution Bank")<br>
• OR a description containing "olution", "central bank", or "shared questions"<br>
<br>The system will automatically detect this category as the main shared questions category.';
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
$string['invalid_parameters'] = 'Invalid parameters';
$string['invalid_action'] = 'Invalid action';

// 🆕 v1.11.5 : Course category filter
$string['course_category_filter'] = 'Course category';
$string['course_category_filter_desc'] = 'Filter question categories by course category';
$string['all_course_categories'] = 'All course categories';
$string['filter_active_course_category'] = 'Active filter: Course category';
$string['show_all_course_categories'] = 'Show all course categories';
$string['course_category_filter_info'] = 'Displaying question categories for course category';

