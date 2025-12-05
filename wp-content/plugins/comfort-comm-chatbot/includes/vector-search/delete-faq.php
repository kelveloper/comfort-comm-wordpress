<?php
/**
 * Delete FAQ by ID
 * Run via: php delete-faq.php <faq_id>
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

// Get FAQ ID from argument
$faq_id = isset($argv[1]) ? $argv[1] : null;

if (!$faq_id) {
    die("Usage: php delete-faq.php <faq_id>\n");
}

echo "Deleting FAQ: {$faq_id}...\n";

if (chatbot_faq_delete($faq_id)) {
    echo "SUCCESS: FAQ deleted\n";
} else {
    echo "FAILED: Could not delete FAQ\n";
}
