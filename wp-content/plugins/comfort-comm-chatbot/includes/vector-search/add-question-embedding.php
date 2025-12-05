<?php
/**
 * Add question_embedding column and search function
 */

require_once dirname(__FILE__) . '/../../../../../wp-load.php';

echo "=== Adding Question Embedding Support ===\n\n";

// Get Supabase config
$config = chatbot_vector_get_supabase_config();

if (!$config || empty($config['service_role_key'])) {
    // Try with anon key
    $api_key = $config['anon_key'] ?? '';
    echo "Using anon key (may have limited permissions)\n";
} else {
    $api_key = $config['service_role_key'];
    echo "Using service role key\n";
}

if (empty($api_key)) {
    die("No API key found\n");
}

$url = $config['url'];

// SQL statements to run
$sql_statements = [
    "ALTER TABLE chatbot_faqs ADD COLUMN IF NOT EXISTS question_embedding vector(768)",

    "CREATE OR REPLACE FUNCTION search_faqs_by_question(
      query_embedding vector(768),
      match_threshold float DEFAULT 0.5,
      match_count int DEFAULT 5
    )
    RETURNS TABLE (
      faq_id text,
      question text,
      answer text,
      category text,
      similarity float
    )
    LANGUAGE plpgsql
    AS \$\$
    BEGIN
      RETURN QUERY
      SELECT
        f.faq_id,
        f.question,
        f.answer,
        f.category,
        1 - (f.question_embedding <=> query_embedding) AS similarity
      FROM chatbot_faqs f
      WHERE f.question_embedding IS NOT NULL
        AND 1 - (f.question_embedding <=> query_embedding) > match_threshold
      ORDER BY f.question_embedding <=> query_embedding
      LIMIT match_count;
    END;
    \$\$"
];

foreach ($sql_statements as $i => $sql) {
    echo "Running SQL " . ($i + 1) . "...\n";

    // Try via RPC
    $response = wp_remote_post($url . '/rest/v1/rpc/exec_sql', [
        'headers' => [
            'apikey' => $api_key,
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode(['query' => $sql]),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        echo "  Error: " . $response->get_error_message() . "\n";
        continue;
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($status >= 400) {
        echo "  Failed (status $status): $body\n";
        echo "  You may need to run this SQL manually in Supabase dashboard.\n";
    } else {
        echo "  Success!\n";
    }
}

echo "\n=== Done ===\n";
echo "If the above failed, run this SQL in Supabase SQL Editor:\n\n";
echo "-- 1. Add column\n";
echo "ALTER TABLE chatbot_faqs ADD COLUMN IF NOT EXISTS question_embedding vector(768);\n\n";
echo "-- 2. Create function (copy from the script)\n";
