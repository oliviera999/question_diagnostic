<?php
// Script de diagnostic pour identifier le problème des catégories

require_once(__DIR__ . '/../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Accès refusé');
}

global $DB;

echo '<h1>Diagnostic des catégories</h1>';
echo '<pre>';

// 0. Vérifier les tables Moodle 4.x
echo "=== Vérification structure Moodle 4.x ===\n";
$tables_to_check = ['question', 'question_bank_entries', 'question_versions', 'question_categories', 'context'];
foreach ($tables_to_check as $table) {
    $exists = $DB->get_manager()->table_exists($table);
    echo "Table '{$table}' existe : " . ($exists ? "OUI" : "NON") . "\n";
    if ($exists) {
        $count = $DB->count_records($table);
        echo "  -> Nombre d'enregistrements : $count\n";
    }
}
echo "\n";

// 1. Compter toutes les catégories
$total_cats = $DB->count_records('question_categories');
echo "Total catégories : $total_cats\n\n";

// 2. Tester la requête des questions
echo "=== Test requête questions (CORRIGÉE) ===\n";
$sql_questions = "SELECT qbe.questioncategoryid,
                         COUNT(DISTINCT q.id) as total_questions,
                         SUM(CASE WHEN qv.status != 'hidden' THEN 1 ELSE 0 END) as visible_questions
                  FROM {question_bank_entries} qbe
                  INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  INNER JOIN {question} q ON q.id = qv.questionid
                  GROUP BY qbe.questioncategoryid";

try {
    $questions_counts = $DB->get_records_sql($sql_questions);
    echo "Nombre de catégories avec questions : " . count($questions_counts) . "\n";
    echo "Exemples (5 premières) :\n";
    $count = 0;
    foreach ($questions_counts as $qc) {
        echo "  - Catégorie {$qc->questioncategoryid} : {$qc->total_questions} questions ({$qc->visible_questions} visibles)\n";
        if (++$count >= 5) break;
    }
} catch (Exception $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
}

echo "\n=== Test requête sous-catégories ===\n";
$sql_subcats = "SELECT parent, COUNT(*) as subcat_count
                FROM {question_categories}
                WHERE parent IS NOT NULL AND parent > 0
                GROUP BY parent";

try {
    $subcat_counts = $DB->get_records_sql($sql_subcats);
    echo "Nombre de catégories ayant des sous-catégories : " . count($subcat_counts) . "\n";
    echo "Exemples (5 premières) :\n";
    $count = 0;
    foreach ($subcat_counts as $sc) {
        echo "  - Catégorie {$sc->parent} : {$sc->subcat_count} sous-catégories\n";
        if (++$count >= 5) break;
    }
} catch (Exception $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
}

echo "\n=== Test requête contextes invalides ===\n";
$sql_contexts = "SELECT qc.id, qc.contextid
                FROM {question_categories} qc
                LEFT JOIN {context} ctx ON ctx.id = qc.contextid
                WHERE ctx.id IS NULL";

try {
    $invalid_contexts = $DB->get_records_sql($sql_contexts);
    echo "Nombre de contextes invalides : " . count($invalid_contexts) . "\n";
    if (count($invalid_contexts) > 0) {
        echo "Exemples (5 premières) :\n";
        $count = 0;
        foreach ($invalid_contexts as $ic) {
            echo "  - Catégorie {$ic->id} : contexte {$ic->contextid} invalide\n";
            if (++$count >= 5) break;
        }
    }
} catch (Exception $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
}

echo "\n=== Test sur 5 catégories aléatoires ===\n";
$sample_cats = $DB->get_records('question_categories', null, 'id', '*', 0, 5);
foreach ($sample_cats as $cat) {
    echo "\nCatégorie ID {$cat->id} : {$cat->name}\n";
    echo "  - Parent : {$cat->parent}\n";
    echo "  - Contexte : {$cat->contextid}\n";
    
    // Vérifier contexte
    $ctx_exists = $DB->record_exists('context', ['id' => $cat->contextid]);
    echo "  - Contexte existe : " . ($ctx_exists ? "OUI" : "NON") . "\n";
    
    // Compter questions avec la NOUVELLE méthode (Moodle 4.x)
    $sql_new = "SELECT COUNT(DISTINCT q.id) 
                FROM {question_bank_entries} qbe
                INNER JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                INNER JOIN {question} q ON q.id = qv.questionid
                WHERE qbe.questioncategoryid = ?";
    try {
        $qcount_new = $DB->count_records_sql($sql_new, [$cat->id]);
        echo "  - Questions (Moodle 4.x méthode) : $qcount_new\n";
    } catch (Exception $e) {
        echo "  - Questions (Moodle 4.x méthode) : ERREUR - " . $e->getMessage() . "\n";
    }
    
    // Compter questions avec l'ANCIENNE méthode (Moodle 3.x - pour comparaison)
    $sql_old = "SELECT COUNT(DISTINCT q.id)
                FROM {question} q
                WHERE q.category = ?";
    try {
        $qcount_old = $DB->count_records_sql($sql_old, [$cat->id]);
        echo "  - Questions (Moodle 3.x méthode) : $qcount_old\n";
    } catch (Exception $e) {
        echo "  - Questions (Moodle 3.x méthode) : ERREUR - " . $e->getMessage() . "\n";
    }
    
    // Compter sous-catégories
    $subcount = $DB->count_records('question_categories', ['parent' => $cat->id]);
    echo "  - Sous-catégories : $subcount\n";
    
    // Est-elle considérée comme vide ?
    $is_empty = (isset($qcount_new) && $qcount_new == 0 && $subcount == 0);
    echo "  - Est vide : " . ($is_empty ? "OUI" : "NON") . "\n";
    
    // Est-elle considérée comme orpheline ?
    $is_orphan = !$ctx_exists;
    echo "  - Est orpheline : " . ($is_orphan ? "OUI" : "NON") . "\n";
}

echo "\n\n=== Vérification structure table 'question' ===\n";
try {
    $columns = $DB->get_columns('question');
    echo "Colonnes de la table 'question' :\n";
    foreach ($columns as $column) {
        echo "  - {$column->name} ({$column->type})\n";
    }
} catch (Exception $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
}

echo '</pre>';
