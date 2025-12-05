<?php
/**
 * Migrate question-only embeddings for all FAQs
 */

require_once dirname(__FILE__) . '/../../../../../wp-load.php';

echo "=== Migrating Question Embeddings ===\n\n";

// Load all FAQs
$faqs = chatbot_faq_load();
echo "Found " . count($faqs) . " FAQs\n\n";

$config = chatbot_vector_get_supabase_config();

if (!$config || empty($config['anon_key'])) {
    die("Supabase not configured\n");
}

$success = 0;
$failed = 0;

foreach ($faqs as $i => $faq) {
    $num = $i + 1;
    echo "[$num/" . count($faqs) . "] " . substr($faq['question'], 0, 50) . "...\n";

    // Generate embedding for question only
    $embedding = chatbot_vector_generate_embedding($faq['question']);

    if (!$embedding) {
        echo "  FAILED: Could not generate embedding\n";
        $failed++;
        continue;
    }

    // Update via REST API
    $url = $config['url'] . '/rest/v1/chatbot_faqs?faq_id=eq.' . urlencode($faq['id']);

    $response = wp_remote_request($url, [
        'method' => 'PATCH',
        'headers' => [
            'apikey' => $config['anon_key'],
            'Authorization' => 'Bearer ' . $config['anon_key'],
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'question_embedding' => $embedding
        ]),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        echo "  FAILED: " . $response->get_error_message() . "\n";
        $failed++;
        continue;
    }

    $status = wp_remote_retrieve_response_code($response);
    if ($status >= 400) {
        $body = wp_remote_retrieve_body($response);
        echo "  FAILED: $body\n";
        $failed++;
        continue;
    }

    echo "  OK\n";
    $success++;

    // Rate limit
    usleep(100000); // 100ms delay
}

echo "\n=== Done ===\n";
echo "Success: $success\n";
echo "Failed: $failed\n";
