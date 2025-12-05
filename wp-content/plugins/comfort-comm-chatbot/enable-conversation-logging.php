<?php
/**
 * Quick script to enable conversation logging
 * Visit: http://localhost:8881/wp-content/plugins/comfort-comm-chatbot/enable-conversation-logging.php
 */

// Load WordPress
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Unauthorized');
}

// Enable conversation logging
update_option('chatbot_chatgpt_enable_conversation_logging', 'On');

echo '<h1>✓ Conversation Logging Enabled!</h1>';
echo '<p>Conversation logging has been turned ON.</p>';
echo '<p>Now:</p>';
echo '<ol>';
echo '<li>Go to your website and have 4 conversations with the chatbot</li>';
echo '<li>Then go to Gap Analysis and click "Analyze Last 4 Conversations"</li>';
echo '</ol>';
echo '<p><a href="/wp-admin/admin.php?page=chatbot-chatgpt&tab=reporting">Go to Gap Analysis Dashboard</a></p>';
