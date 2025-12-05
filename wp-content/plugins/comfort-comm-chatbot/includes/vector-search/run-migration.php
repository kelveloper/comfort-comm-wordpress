#!/usr/bin/env php
<?php
/**
 * CLI Migration Script - Populate FAQs with vector embeddings
 *
 * Run from command line:
 *   php run-migration.php
 *
 * Or via WordPress:
 *   cd /path/to/wordpress && php wp-content/plugins/comfort-comm-chatbot/includes/vector-search/run-migration.php
 */

// Load WordPress
$wp_load_paths = [
    dirname(__FILE__) . '/../../../../../wp-load.php',  // Standard plugin location
    dirname(__FILE__) . '/../../../../wp-load.php',
    '/Users/kelvin/Studio/chatbot-test/wp-load.php',    // Direct path
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die("Error: Could not find wp-load.php\n");
}

echo "==============================================\n";
echo "Chatbot Vector Search - FAQ Migration\n";
echo "==============================================\n\n";

// Check if vector functions are loaded
if (!function_exists('chatbot_vector_migrate_all_faqs')) {
    // Try loading manually
    require_once dirname(__FILE__) . '/chatbot-vector-loader.php';
}

if (!function_exists('chatbot_vector_migrate_all_faqs')) {
    die("Error: Vector search functions not loaded\n");
}

// Check database connection
echo "Checking database connection...\n";
if (!chatbot_vector_is_available()) {
    die("Error: PostgreSQL/pgvector not available. Check wp-config.php settings.\n");
}
echo "✓ Database connection OK\n\n";

// Check OpenAI API key
$api_key = get_option('chatbot_chatgpt_api_key', '');
if (empty($api_key)) {
    die("Error: OpenAI API key not configured in WordPress settings.\n");
}
echo "✓ OpenAI API key found\n\n";

// Load FAQs
$faqs = chatbot_faq_load();
echo "Found " . count($faqs) . " FAQs to migrate\n\n";

if (empty($faqs)) {
    die("Error: No FAQs found in JSON file\n");
}

// Run migration
echo "Starting migration (this may take a few minutes)...\n";
echo "Generating embeddings for each FAQ using OpenAI text-embedding-3-small\n\n";

$result = chatbot_vector_migrate_all_faqs(true); // true = clear existing

echo "\n";
echo "==============================================\n";
if ($result['success']) {
    echo "✓ Migration Complete!\n";
    echo "  - Total FAQs: " . $result['total'] . "\n";
    echo "  - Migrated: " . $result['migrated'] . "\n";
    if ($result['errors'] > 0) {
        echo "  - Errors: " . $result['errors'] . "\n";
    }
} else {
    echo "✗ Migration Failed\n";
    echo "  Error: " . $result['message'] . "\n";
}
echo "==============================================\n";
