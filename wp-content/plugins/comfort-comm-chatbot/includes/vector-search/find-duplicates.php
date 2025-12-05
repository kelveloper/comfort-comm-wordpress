<?php
/**
 * Find duplicate FAQs using vector similarity
 * Run via: php find-duplicates.php
 */

// Load WordPress
$wp_load_paths = [
    dirname(__FILE__) . '/../../../../../wp-load.php',
    '/Users/kelvin/Studio/chatbot-test/wp-load.php'
];

$loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    die("Could not find wp-load.php\n");
}

echo "=== Finding Duplicate FAQs ===\n\n";

// Load all FAQs
$faqs = chatbot_faq_load();
echo "Total FAQs: " . count($faqs) . "\n\n";

if (empty($faqs)) {
    die("No FAQs found.\n");
}

// Check each FAQ against all others
$duplicates = [];
$checked = [];

foreach ($faqs as $i => $faq1) {
    $faq1_num = $i + 1;

    // Skip if already identified as a duplicate
    if (in_array($faq1['id'], $checked)) {
        continue;
    }

    echo "Checking FAQ #{$faq1_num}: " . substr($faq1['question'], 0, 50) . "...\n";

    // Search for similar FAQs
    $results = chatbot_vector_search($faq1['question'], [
        'threshold' => 0.75, // 75% threshold to find near-duplicates
        'limit' => 5,
        'return_scores' => true
    ]);

    if (!$results['success']) {
        echo "  Error searching: " . ($results['error'] ?? 'Unknown') . "\n";
        continue;
    }

    foreach ($results['results'] as $match) {
        // Skip self-match
        if ($match['faq_id'] === $faq1['id']) {
            continue;
        }

        // Skip if below 80% similarity
        if ($match['similarity'] < 0.80) {
            continue;
        }

        // Find the FAQ number of the match
        $match_num = 0;
        foreach ($faqs as $j => $faq2) {
            if ($faq2['id'] === $match['faq_id']) {
                $match_num = $j + 1;
                break;
            }
        }

        // Record duplicate
        $key = min($faq1_num, $match_num) . '-' . max($faq1_num, $match_num);
        if (!isset($duplicates[$key])) {
            $duplicates[$key] = [
                'faq1_num' => $faq1_num,
                'faq1_id' => $faq1['id'],
                'faq1_question' => $faq1['question'],
                'faq2_num' => $match_num,
                'faq2_id' => $match['faq_id'],
                'faq2_question' => $match['question'],
                'similarity' => round($match['similarity'] * 100)
            ];
            $checked[] = $match['faq_id'];
        }
    }
}

echo "\n=== Duplicate Pairs Found ===\n\n";

if (empty($duplicates)) {
    echo "No duplicates found above 80% similarity!\n";
} else {
    echo "Found " . count($duplicates) . " duplicate pair(s):\n\n";

    foreach ($duplicates as $dup) {
        echo "----------------------------------------\n";
        echo "FAQ #{$dup['faq1_num']} vs FAQ #{$dup['faq2_num']} ({$dup['similarity']}% match)\n";
        echo "  #{$dup['faq1_num']}: {$dup['faq1_question']}\n";
        echo "  #{$dup['faq2_num']}: {$dup['faq2_question']}\n";
        echo "  IDs: {$dup['faq1_id']} | {$dup['faq2_id']}\n";
        echo "\n";
    }

    echo "----------------------------------------\n";
    echo "To delete duplicates, remove the newer FAQ (higher number) from the admin UI.\n";
}
