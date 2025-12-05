<?php
/**
 * Migrate WordPress SQLite Data to Supabase
 *
 * Run from command line:
 * php wp-content/plugins/comfort-comm-chatbot/includes/supabase/migrate-wordpress-to-supabase.php
 *
 * @package comfort-comm-chatbot
 */

// Load WordPress
$wp_load_path = dirname(__FILE__) . '/../../../../../wp-load.php';
if (!file_exists($wp_load_path)) {
    die("Error: Could not find wp-load.php\n");
}

error_reporting(E_ERROR | E_WARNING | E_PARSE);
require_once $wp_load_path;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     WordPress SQLite → Supabase Data Migration               ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Check Supabase is configured
if (!defined('CHATBOT_SUPABASE_ANON_KEY') || !defined('CHATBOT_PG_HOST')) {
    die("Error: Supabase not configured. Set CHATBOT_SUPABASE_ANON_KEY and CHATBOT_PG_HOST in wp-config.php\n");
}

/**
 * Make Supabase REST API request
 */
function migrate_supabase_request($endpoint, $method = 'GET', $data = null, $query_params = []) {
    // Extract project ref from host
    $host = CHATBOT_PG_HOST;
    if (!preg_match('/db\.([^.]+)\.supabase\.co/', $host, $matches)) {
        return ['success' => false, 'error' => 'Invalid host format'];
    }
    $base_url = 'https://' . $matches[1] . '.supabase.co/rest/v1';

    $url = $base_url . '/' . $endpoint;

    if (!empty($query_params)) {
        $url .= '?' . http_build_query($query_params);
    }

    $headers = [
        'apikey: ' . CHATBOT_SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . CHATBOT_SUPABASE_ANON_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    $decoded = json_decode($response, true);

    if ($http_code >= 200 && $http_code < 300) {
        return ['success' => true, 'data' => $decoded, 'http_code' => $http_code];
    }

    return ['success' => false, 'error' => $decoded['message'] ?? $response, 'http_code' => $http_code];
}

global $wpdb;

// =============================================================================
// 1. Migrate Conversations
// =============================================================================
echo "1. Migrating Conversations...\n";

$table = $wpdb->prefix . 'chatbot_chatgpt_conversation_log';
$conversations = $wpdb->get_results("SELECT * FROM $table ORDER BY interaction_time ASC", ARRAY_A);

$conv_count = 0;
$conv_errors = 0;

foreach ($conversations as $row) {
    $data = [
        'session_id' => $row['session_id'] ?? '',
        'user_id' => (string)($row['user_id'] ?? '0'),
        'page_id' => (string)($row['page_id'] ?? '0'),
        'user_type' => $row['user_type'] ?? 'Visitor',
        'thread_id' => $row['thread_id'] ?? null,
        'assistant_id' => $row['assistant_id'] ?? null,
        'assistant_name' => $row['assistant_name'] ?? null,
        'message_text' => $row['message_text'] ?? '',
        'interaction_time' => $row['interaction_time'] ?? gmdate('c'),
        'sentiment_score' => isset($row['sentiment_score']) ? (float)$row['sentiment_score'] : null
    ];

    $result = migrate_supabase_request('chatbot_conversations', 'POST', $data);

    if ($result['success']) {
        $conv_count++;
    } else {
        $conv_errors++;
        if ($conv_errors <= 3) {
            echo "   Error: " . ($result['error'] ?? 'Unknown') . "\n";
        }
    }

    // Progress indicator
    if ($conv_count % 50 === 0) {
        echo "   Migrated $conv_count conversations...\n";
    }
}

echo "   ✓ Migrated $conv_count conversations";
if ($conv_errors > 0) {
    echo " ($conv_errors errors)";
}
echo "\n\n";

// =============================================================================
// 2. Migrate Gap Questions
// =============================================================================
echo "2. Migrating Gap Questions...\n";

$table = $wpdb->prefix . 'chatbot_gap_questions';
$gap_questions = $wpdb->get_results("SELECT * FROM $table ORDER BY asked_date ASC", ARRAY_A);

$gap_count = 0;
$gap_errors = 0;

foreach ($gap_questions as $row) {
    $data = [
        'question_text' => $row['question_text'] ?? '',
        'session_id' => $row['session_id'] ?? null,
        'user_id' => (int)($row['user_id'] ?? 0),
        'page_id' => (int)($row['page_id'] ?? 0),
        'faq_confidence' => isset($row['faq_confidence']) ? (float)$row['faq_confidence'] : null,
        'faq_match_id' => $row['faq_match_id'] ?? null,
        'asked_date' => $row['asked_date'] ?? gmdate('c'),
        'is_clustered' => (bool)($row['is_clustered'] ?? false),
        'cluster_id' => isset($row['cluster_id']) ? (int)$row['cluster_id'] : null,
        'is_resolved' => (bool)($row['is_resolved'] ?? false)
    ];

    $result = migrate_supabase_request('chatbot_gap_questions', 'POST', $data);

    if ($result['success']) {
        $gap_count++;
    } else {
        $gap_errors++;
        if ($gap_errors <= 3) {
            echo "   Error: " . ($result['error'] ?? 'Unknown') . "\n";
        }
    }
}

echo "   ✓ Migrated $gap_count gap questions";
if ($gap_errors > 0) {
    echo " ($gap_errors errors)";
}
echo "\n\n";

// =============================================================================
// 3. Migrate FAQ Usage
// =============================================================================
echo "3. Migrating FAQ Usage...\n";

$table = $wpdb->prefix . 'chatbot_faq_usage';
$table_exists = $wpdb->get_var("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='$table'");

$usage_count = 0;
$usage_errors = 0;

if ($table_exists) {
    $faq_usage = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);

    foreach ($faq_usage as $row) {
        $data = [
            'faq_id' => $row['faq_id'] ?? '',
            'hit_count' => (int)($row['hit_count'] ?? 0),
            'last_asked' => $row['last_asked'] ?? null,
            'avg_confidence' => isset($row['avg_confidence']) ? (float)$row['avg_confidence'] : null,
            'created_at' => $row['created_at'] ?? gmdate('c'),
            'updated_at' => $row['updated_at'] ?? gmdate('c')
        ];

        $result = migrate_supabase_request('chatbot_faq_usage', 'POST', $data);

        if ($result['success']) {
            $usage_count++;
        } else {
            $usage_errors++;
            if ($usage_errors <= 3) {
                echo "   Error: " . ($result['error'] ?? 'Unknown') . "\n";
            }
        }
    }
}

echo "   ✓ Migrated $usage_count FAQ usage records";
if ($usage_errors > 0) {
    echo " ($usage_errors errors)";
}
echo "\n\n";

// =============================================================================
// Summary
// =============================================================================
echo "══════════════════════════════════════════════════════════════\n";
echo "  MIGRATION COMPLETE\n";
echo "══════════════════════════════════════════════════════════════\n";
echo "  Conversations: $conv_count migrated\n";
echo "  Gap Questions: $gap_count migrated\n";
echo "  FAQ Usage:     $usage_count migrated\n";
echo "\n";

// Verify counts in Supabase
echo "Verifying Supabase counts...\n";
$tables = ['chatbot_conversations', 'chatbot_gap_questions', 'chatbot_faq_usage', 'chatbot_faqs'];

foreach ($tables as $table) {
    $result = migrate_supabase_request($table, 'GET', null, ['select' => 'id', 'limit' => 1]);
    if ($result['success']) {
        // Get count via header
        $host = CHATBOT_PG_HOST;
        preg_match('/db\.([^.]+)\.supabase\.co/', $host, $matches);
        $url = 'https://' . $matches[1] . '.supabase.co/rest/v1/' . $table . '?select=id';

        $headers = [
            'apikey: ' . CHATBOT_SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . CHATBOT_SUPABASE_ANON_KEY,
            'Prefer: count=exact'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers_str = substr($response, 0, $header_size);

        if (preg_match('/content-range: \d+-\d+\/(\d+)/i', $headers_str, $matches)) {
            echo "  $table: " . $matches[1] . " rows\n";
        } elseif (preg_match('/content-range: \*\/(\d+)/i', $headers_str, $matches)) {
            echo "  $table: " . $matches[1] . " rows\n";
        } else {
            echo "  $table: ✓\n";
        }
    }
}

echo "\n";
echo "Migration complete! You can now remove WordPress fallback code.\n\n";
