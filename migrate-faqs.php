<?php
/**
 * FAQ Vector Migration - Web Interface
 *
 * Visit this page in your browser to migrate FAQs to vector database.
 * DELETE THIS FILE AFTER MIGRATION!
 */

// Security check - only allow logged in admins
require_once dirname(__FILE__) . '/wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('You must be logged in as an administrator to run this migration.');
}

// Check if migration should run
$run_migration = isset($_GET['run']) && $_GET['run'] === 'yes';
$clear_existing = isset($_GET['clear']) && $_GET['clear'] === 'yes';

?>
<!DOCTYPE html>
<html>
<head>
    <title>FAQ Vector Migration</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h1 { color: #1e3a5f; }
        .status { padding: 15px; border-radius: 5px; margin: 20px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        pre { background: #f4f4f4; padding: 15px; overflow-x: auto; border-radius: 5px; }
        .btn { display: inline-block; padding: 12px 24px; background: #0073aa; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #005a87; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .log { background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 5px; max-height: 400px; overflow-y: auto; }
        .log .success-line { color: #4ec9b0; }
        .log .error-line { color: #f14c4c; }
    </style>
</head>
<body>
    <h1>🔄 FAQ Vector Migration</h1>

    <?php
    // Check prerequisites
    $checks = [];

    // Check PostgreSQL config
    if (defined('CHATBOT_PG_HOST') && defined('CHATBOT_PG_DATABASE')) {
        $checks['config'] = ['status' => 'ok', 'message' => 'PostgreSQL config found in wp-config.php'];
    } else {
        $checks['config'] = ['status' => 'error', 'message' => 'PostgreSQL config NOT found in wp-config.php'];
    }

    // Check PDO PostgreSQL extension
    if (extension_loaded('pdo_pgsql')) {
        $checks['pdo_pgsql'] = ['status' => 'ok', 'message' => 'PDO PostgreSQL extension loaded'];
    } else {
        $checks['pdo_pgsql'] = ['status' => 'error', 'message' => 'PDO PostgreSQL extension NOT loaded'];
    }

    // Check OpenAI API key
    $api_key = get_option('chatbot_chatgpt_api_key', '');
    if (!empty($api_key)) {
        $checks['openai'] = ['status' => 'ok', 'message' => 'OpenAI API key configured'];
    } else {
        $checks['openai'] = ['status' => 'error', 'message' => 'OpenAI API key NOT configured'];
    }

    // Check vector functions
    if (function_exists('chatbot_vector_migrate_all_faqs')) {
        $checks['functions'] = ['status' => 'ok', 'message' => 'Vector search functions loaded'];
    } else {
        $checks['functions'] = ['status' => 'error', 'message' => 'Vector search functions NOT loaded'];
    }

    // Check database connection
    if (function_exists('chatbot_vector_is_available') && chatbot_vector_is_available()) {
        $checks['connection'] = ['status' => 'ok', 'message' => 'PostgreSQL/pgvector connection successful'];
    } else {
        $checks['connection'] = ['status' => 'error', 'message' => 'Cannot connect to PostgreSQL/pgvector'];
    }

    // Check FAQs
    if (function_exists('chatbot_faq_load')) {
        $faqs = chatbot_faq_load();
        $faq_count = count($faqs);
        $checks['faqs'] = ['status' => 'ok', 'message' => "Found {$faq_count} FAQs to migrate"];
    } else {
        $checks['faqs'] = ['status' => 'error', 'message' => 'FAQ load function not available'];
        $faq_count = 0;
    }

    $all_ok = true;
    foreach ($checks as $check) {
        if ($check['status'] !== 'ok') {
            $all_ok = false;
            break;
        }
    }
    ?>

    <h2>Prerequisites Check</h2>
    <?php foreach ($checks as $name => $check): ?>
        <div class="status <?php echo $check['status'] === 'ok' ? 'success' : 'error'; ?>">
            <?php echo $check['status'] === 'ok' ? '✓' : '✗'; ?>
            <?php echo esc_html($check['message']); ?>
        </div>
    <?php endforeach; ?>

    <?php if (!$all_ok): ?>
        <div class="status warning">
            <strong>⚠️ Cannot proceed:</strong> Please fix the errors above before running migration.
        </div>

        <?php if ($checks['pdo_pgsql']['status'] === 'error'): ?>
        <div class="status info">
            <strong>PDO PostgreSQL not available in Local by Flywheel.</strong><br><br>
            You need to install PHP with PostgreSQL support. Run this in Terminal:<br>
            <pre>brew install php</pre>
            Then run the CLI migration script.
        </div>
        <?php endif; ?>

    <?php elseif (!$run_migration): ?>
        <h2>Ready to Migrate</h2>
        <p>This will generate OpenAI embeddings for all <?php echo $faq_count; ?> FAQs and store them in Supabase.</p>
        <p><strong>Estimated time:</strong> ~2-3 minutes (depends on API rate limits)</p>
        <p><strong>Cost:</strong> ~$0.01 (text-embedding-3-small is very cheap)</p>

        <a href="?run=yes" class="btn">▶️ Run Migration</a>
        <a href="?run=yes&clear=yes" class="btn btn-danger">🗑️ Clear & Re-migrate</a>

    <?php else: ?>
        <h2>Migration Running...</h2>
        <div class="log">
        <?php
        // Run migration with output
        ob_implicit_flush(true);

        echo "<div>Starting migration...</div>";
        flush();

        // Increase time limit
        set_time_limit(300);

        $result = chatbot_vector_migrate_all_faqs($clear_existing);

        if ($result['success']) {
            echo "<div class='success-line'>✓ Migration completed successfully!</div>";
            echo "<div>Total FAQs: " . $result['total'] . "</div>";
            echo "<div>Migrated: " . $result['migrated'] . "</div>";
            if ($result['errors'] > 0) {
                echo "<div class='error-line'>Errors: " . $result['errors'] . "</div>";
            }
        } else {
            echo "<div class='error-line'>✗ Migration failed: " . esc_html($result['message']) . "</div>";
        }
        ?>
        </div>

        <?php if ($result['success']): ?>
        <div class="status success">
            <strong>🎉 Migration Complete!</strong><br>
            Your <?php echo $result['migrated']; ?> FAQs now have vector embeddings for semantic search.
        </div>
        <?php endif; ?>

        <p><a href="<?php echo admin_url(); ?>" class="btn">← Back to WordPress Admin</a></p>

        <div class="status warning">
            <strong>⚠️ Security Notice:</strong> Delete this file after migration!<br>
            <code>rm <?php echo __FILE__; ?></code>
        </div>
    <?php endif; ?>

</body>
</html>
