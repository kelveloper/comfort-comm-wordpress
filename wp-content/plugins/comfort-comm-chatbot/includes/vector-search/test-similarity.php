<?php
/**
 * Test similarity for a question using question-only embeddings
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../../../wp-load.php';

$test_questions = [
    "What time is your store open?",
    "What are your store hours?",
    "What time does your store open and close?",
    "What are your business hours?",
    "When are you open?"
];

echo "=== Testing Question-Only Similarity ===\n\n";

foreach ($test_questions as $question) {
    echo "Testing: \"{$question}\"\n";

    // Use the new question-only search function
    $results = chatbot_vector_search_by_question($question, [
        'threshold' => 0.50, // Low threshold to see all matches
        'limit' => 5,
        'return_scores' => true
    ]);

    if ($results['success'] && !empty($results['results'])) {
        foreach ($results['results'] as $match) {
            $pct = round($match['similarity'] * 100);
            echo "  -> {$pct}% match: \"{$match['question']}\"\n";
        }
    } else {
        echo "  -> No matches found\n";
        if (!empty($results['error'])) {
            echo "  -> Error: " . $results['error'] . "\n";
        }
    }
    echo "\n";
}
