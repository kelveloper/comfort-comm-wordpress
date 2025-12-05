<?php
/**
 * Kognetiks Chatbot - Database Management for Reporting - Ver 1.6.3
 *
 * This file contains the code for table actions for reporting
 * to display the Chatbot on the website.
 *
 * @package chatbot-chatgpt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die();
}

// Create the interaction tracking table - Ver 1.6.3
function create_chatbot_chatgpt_interactions_table() {
    
    global $wpdb;

    $table_name = $wpdb->prefix . 'chatbot_chatgpt_interactions';
    
    $charset_collate = $wpdb->get_charset_collate();

    // Fallback cascade for invalid or unsupported character sets
    if (empty($charset_collate) || strpos($charset_collate, 'utf8mb4') === false) {
        if (strpos($charset_collate, 'utf8') === false) {
            // Fallback to utf8 if utf8mb4 is not supported
            $charset_collate = "CHARACTER SET utf8 COLLATE utf8_general_ci";
        }
    }

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        date DATE PRIMARY KEY,
        count INT
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Check for errors after dbDelta
    if ($wpdb->last_error) {
        // logErrorToServer('Failed to create table: ' . $table_name);
        // logErrorToServer('SQL: ' . $sql);
        // logErrorToServer('Error details: ' . $wpdb->last_error);
        error_log('[Chatbot] [chatbot-db-management.php] Failed to insert row into table: ' . $table_name);
        error_log('[Chatbot] [chatbot-db-management.php] Failed to create table: ' . $table_name);
        error_log('[Chatbot] [chatbot-db-management.php] SQL: ' . $sql);
        error_log('[Chatbot] [chatbot-db-management.php] Error details: ' . $wpdb->last_error);
        return false;  // Table creation failed
    }

    // DIAG - Diagnostics
    // back_trace( 'SUCCESS', 'Successfully created chatbot_chatgpt_interactions table');
    return;

}
// Hook to run the function when the plugin is activated
// register_activation_hook(__FILE__, 'create_chatbot_chatgpt_interactions_table');

// Update Interaction Tracking - Ver 1.6.3
// Updated Ver 2.4.8: Uses Supabase only
function update_interaction_tracking() {
    // Use Supabase for all interaction tracking
    if (function_exists('chatbot_supabase_update_interaction_count')) {
        $result = chatbot_supabase_update_interaction_count();
        return $result['success'] ?? false;
    }
    return false;
}

// Conversation Tracking - Ver 1.7.6
function create_conversation_logging_table() {

    global $wpdb;

    // Check version and create table if necessary
    // FIXME - WHAT IF THE TABLE WAS DROPPED? - Ver 1.7.6
    // chatbot_chatgpt_check_version();

    // Check if the table already exists
    $table_name = $wpdb->prefix . 'chatbot_chatgpt_conversation_log';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
        // DIAG - Diagnostics
        // back_trace( 'NOTICE', 'Table already exists: ' . $table_name);

        // Modify interaction_time column to remove DEFAULT CURRENT_TIMESTAMP
        $sql = "ALTER TABLE $table_name MODIFY COLUMN interaction_time datetime NOT NULL;";
        $result = $wpdb->query($sql);
        if ($result === false) {
            // If there was an error, log it
            // back_trace( 'ERROR', 'Error modifying interaction_time column: ' . $wpdb->last_error);
        } else {
            // If the operation was successful, log the success
            // back_trace( 'SUCCESS', 'Successfully modified interaction_time column');
        }

        if ($wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $table_name LIKE %s", 'assistant_name')) === 'assistant_name') {
            // DIAG - Diagnostics
            // back_trace( 'NOTICE', 'Column assistant_name already exists in table: ' . $table_name);
        } else {
            // Directly execute the ALTER TABLE command without prepare()
            $sql = "ALTER TABLE $table_name ADD COLUMN assistant_name VARCHAR(255) AFTER assistant_id";
            $result = $wpdb->query($sql);
            if ($result === false) {
                // If there was an error, log it
                // back_trace( 'ERROR', 'Error altering chatbot_chatgpt_conversation_log table: ' . $wpdb->last_error);
            } else {
                // If the operation was successful, log the success
                // back_trace( 'SUCCESS', 'Successfully altered chatbot_chatgpt_conversation_log table');
            }
        }

        // Check and add sentiment_score column if it doesn't exist
        if ($wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $table_name LIKE %s", 'sentiment_score')) === 'sentiment_score') {
            // DIAG - Diagnostics
            // back_trace( 'NOTICE', 'Column sentiment_score already exists in table: ' . $table_name);
        } else {
            // Directly execute the ALTER TABLE command without prepare()
            $sql = "ALTER TABLE $table_name ADD COLUMN sentiment_score FLOAT AFTER message_text";
            $result = $wpdb->query($sql);
            if ($result === false) {
                // If there was an error, log it
                // back_trace( 'ERROR', 'Error adding sentiment_score column: ' . $wpdb->last_error);
            } else {
                // If the operation was successful, log the success
                // back_trace( 'SUCCESS', 'Successfully added sentiment_score column');
            }
        }

        // Directly execute the ALTER TABLE command without prepare()
        $sql = "ALTER TABLE $table_name MODIFY COLUMN user_type ENUM('Chatbot', 'Visitor', 'Prompt Tokens', 'Completion Tokens', 'Total Tokens')";
        $result = $wpdb->query($sql);
        if ($result === false) {
            // If there was an error, log it
            // back_trace( 'ERROR', 'Error altering chatbot_chatgpt_conversation_log table: ' . $wpdb->last_error);
        } else {
            // If the operation was successful, log the success
            // back_trace( 'SUCCESS', 'Successfully altered chatbot_chatgpt_conversation_log table');
        }

        // Fetch rows where user_type is missing
        $rows = $wpdb->get_results("SELECT id FROM $table_name WHERE user_type IS NULL OR user_type = '' ORDER BY id ASC", ARRAY_A);

        // Sequence of user_types to update with
        $sequence = ["Prompt Tokens", "Completion Tokens", "Total Tokens"];
        $sequenceIndex = 0;

        foreach ($rows as $row) {
            // Update the row with the corresponding sequence value
            $update_result = $wpdb->update(
                $table_name, 
                ['user_type' => $sequence[$sequenceIndex]], // data
                ['id' => $row['id']] // where
            );

            // Move to the next sequence value, or reset if at the end of the sequence
            $sequenceIndex = ($sequenceIndex + 1) % count($sequence);

            if ($update_result === false) {
                // If there was an error, log it
                // back_trace( 'ERROR', 'Error updating missing chatbot_chatgpt_conversation_log table: ' . $wpdb->last_error);
            } else {
                // If the operation was successful, log the success
                // back_trace( 'SUCCESS', 'Successfully updated missing values in chatbot_chatgpt_conversation_log table');
            }
        }
        
        // DIAG - Diagnostics - Ver 1.9.9
        // back_trace( 'SUCCESS', 'Successfully updated chatbot_chatgpt_conversation_log table');

    } else {
        // DIAG - Diagnostics
        // back_trace( 'NOTICE', 'Table does not exist: ' . $table_name);
        // SQL to create the conversation logging table

        $charset_collate = $wpdb->get_charset_collate();

        // Fallback cascade for invalid or unsupported character sets
        if (empty($charset_collate) || strpos($charset_collate, 'utf8mb4') === false) {
            if (strpos($charset_collate, 'utf8') === false) {
                // Fallback to utf8 if utf8mb4 is not supported
                $charset_collate = "CHARACTER SET utf8 COLLATE utf8_general_ci";
            }
        }

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(255) NOT NULL,
            user_id VARCHAR(255),
            page_id VARCHAR(255),
            interaction_time datetime NOT NULL,
            user_type ENUM('Chatbot', 'Visitor', 'Prompt Tokens', 'Completion Tokens', 'Total Tokens') NOT NULL,
            thread_id VARCHAR(255),
            assistant_id VARCHAR(255),
            assistant_name VARCHAR(255),
            message_text text NOT NULL,
            sentiment_score FLOAT,
            PRIMARY KEY  (id),
            INDEX session_id_index (session_id),
            INDEX user_id_index (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Check for errors after dbDelta
        if ($wpdb->last_error) {
            error_log('[Chatbot] [chatbot-db-management.php] Failed to create table: ' . $table_name);
            error_log('[Chatbot] [chatbot-db-management.php] SQL: ' . $sql);
            error_log('[Chatbot] [chatbot-db-management.php] Error details: ' . $wpdb->last_error);
            return false;  // Table creation failed
        }
    }

    // back_trace( 'SUCCESS', 'Successfully created/updated chatbot_chatgpt_conversation_log table');
    
    return;

}

// Append message to conversation log in the database - Ver 1.7.6
// Updated Ver 2.4.8: Uses Supabase only
function append_message_to_conversation_log($session_id, $user_id, $page_id, $user_type, $thread_id, $assistant_id, $assistant_name, $message) {

    // Check if conversation logging is enabled
    if (esc_attr(get_option('chatbot_chatgpt_enable_conversation_logging')) !== 'On') {
        // Logging is disabled, so just return without doing anything
        return;
    }

    // Belt & Suspenders - Ver 1.9.3
    if ( $user_id == $session_id ) {
        $user_id = 0;
    }

    // Get the $assistant_name from the transient
    $assistant_name = get_chatbot_chatgpt_transients('assistant_name', $user_id, $page_id, $session_id);

    // Use Supabase for all conversation logging
    if (function_exists('chatbot_supabase_log_conversation')) {
        return chatbot_supabase_log_conversation(
            $session_id,
            $user_id,
            $page_id,
            $user_type,
            $thread_id,
            $assistant_id,
            $assistant_name,
            $message,
            0 // Default sentiment score
        );
    }

    return false;
}

// Function to delete specific expired transients - Ver 1.7.6
function clean_specific_expired_transients() {


    global $wpdb;

    // Prefix for transients in the options table.
    $prefix = '_transient_';

    // The pattern to match in the transient's name.
    $pattern = 'chatbot_chatgpt';

    // SQL query to select expired transients that match the pattern.
    $sql = $wpdb->prepare(
        "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s AND option_name LIKE %s",
        $wpdb->esc_like($prefix . 'timeout_') . '%',
        '%' . $wpdb->esc_like($pattern) . '%'
    );

    // Execute the query.
    $expired_transients = $wpdb->get_col($sql);

    // Iterate through the results and delete each expired transient.
    foreach ($expired_transients as $transient) {
        // Extract the transient name by removing the '_transient_timeout_' prefix.
        $transient_name = str_replace($prefix . 'timeout_', '', $transient);

        // Delete the transient.
        delete_transient( $transient_name );

        // Delete the transient timeout.
        $wpdb->delete($wpdb->options, ['option_name' => $transient]);
    }
}

// Function to purge conversation log entries that are older than the specified number of days - Ver 1.7.6
// Updated Ver 2.4.8: Uses Supabase only
function chatbot_chatgpt_conversation_log_cleanup() {

    // Get the number of days to keep the conversation log
    $days_to_keep = esc_attr(get_option('chatbot_chatgpt_conversation_log_days_to_keep'));

    // If the number of days is not set, then set it to 30 days
    if ($days_to_keep === false || empty($days_to_keep)) {
        $days_to_keep = 30;
    }

    // Clean Supabase data
    if (function_exists('chatbot_supabase_delete_old_conversations')) {
        chatbot_supabase_delete_old_conversations($days_to_keep);
    }
    return true;

}
// Register activation and deactivation hooks
register_activation_hook(plugin_dir_path(dirname(__FILE__)) . 'chatbot-chatgpt.php', 'chatbot_chatgpt_activate_db');
register_deactivation_hook(plugin_dir_path(dirname(__FILE__)) . 'chatbot-chatgpt.php', 'chatbot_chatgpt_deactivate_db');

// Function to handle database setup on activation
function chatbot_chatgpt_activate_db() {
    // Create the interaction tracking table
    create_chatbot_chatgpt_interactions_table();

    // Create the conversation logging table
    create_conversation_logging_table();

    // Create gap analysis tables - Ver 2.4.2
    create_chatbot_gap_questions_table();
    create_chatbot_gap_clusters_table();
    create_chatbot_faq_usage_table();

    // Schedule the cleanup cron job
    if (!wp_next_scheduled('chatbot_chatgpt_conversation_log_cleanup_event')) {
        wp_schedule_event(time(), 'daily', 'chatbot_chatgpt_conversation_log_cleanup_event');
    }

    // Schedule quarterly gap analysis (every 90 days)
    if (!wp_next_scheduled('chatbot_gap_analysis_event')) {
        wp_schedule_event(time() + (90 * DAY_IN_SECONDS), 'quarterly', 'chatbot_gap_analysis_event');
    }
}

// Ensure quarterly gap analysis is scheduled - runs on admin_init as fallback
function chatbot_ensure_gap_analysis_scheduled() {
    if (!wp_next_scheduled('chatbot_gap_analysis_event')) {
        wp_schedule_event(time() + (90 * DAY_IN_SECONDS), 'quarterly', 'chatbot_gap_analysis_event');
    }
}
add_action('admin_init', 'chatbot_ensure_gap_analysis_scheduled');

/**
 * Auto-run gap analysis when there are 30+ unclustered questions
 * Runs on wp_footer (frontend) to avoid slowing down admin
 * Uses transient to prevent running too frequently (max once per hour)
 * Only runs if auto-analysis is enabled in settings
 */
function chatbot_auto_run_gap_analysis() {
    // Check if auto-analysis is enabled (default: off)
    $auto_enabled = get_option('chatbot_gap_auto_analysis_enabled', 'off');
    if ($auto_enabled !== 'on') {
        return;
    }

    // Only run if not already running recently (1 hour cooldown)
    $last_run = get_transient('chatbot_gap_analysis_last_auto_run');
    if ($last_run) {
        return;
    }

    // Check if we have enough unclustered questions
    if (!function_exists('chatbot_supabase_get_gap_questions_count')) {
        return;
    }

    $unclustered_count = chatbot_supabase_get_gap_questions_count(false, false);

    // Only auto-run if 30+ questions waiting
    if ($unclustered_count < 30) {
        return;
    }

    error_log("[Chatbot Gap Analysis] Auto-triggering: {$unclustered_count} unclustered questions found");

    // Set cooldown transient (1 hour)
    set_transient('chatbot_gap_analysis_last_auto_run', time(), HOUR_IN_SECONDS);

    // Run the analysis (we already checked 30+ above, so pass false to let it re-verify)
    if (function_exists('chatbot_run_gap_analysis')) {
        chatbot_run_gap_analysis(false); // false = respect minimum check
    }
}
add_action('wp_footer', 'chatbot_auto_run_gap_analysis');

// Add sentiment_score column if missing - Ver 2.3.1
function chatbot_chatgpt_add_sentiment_score_column() {

    global $wpdb;
    
    $table_name = $wpdb->prefix . 'chatbot_chatgpt_conversation_log';
    
    // Check if the table exists
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
        // Table doesn't exist, nothing to do
        return false;
    }
    
    // Check if sentiment_score column already exists
    if ($wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $table_name LIKE %s", 'sentiment_score')) === 'sentiment_score') {
        // Column already exists
        return true;
    }
    
    // Add the sentiment_score column
    $sql = "ALTER TABLE $table_name ADD COLUMN sentiment_score FLOAT AFTER message_text";
    $result = $wpdb->query($sql);
    
    if ($result === false) {
        error_log('[Chatbot] [chatbot-db-management.php] Error adding sentiment_score column: ' . $wpdb->last_error);
        return false;
    }
    
    return true;
}

// Create gap questions table - Ver 2.4.2
function create_chatbot_gap_questions_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'chatbot_gap_questions';
    $charset_collate = $wpdb->get_charset_collate();

    // Fallback cascade for invalid or unsupported character sets
    if (empty($charset_collate) || strpos($charset_collate, 'utf8mb4') === false) {
        if (strpos($charset_collate, 'utf8') === false) {
            $charset_collate = "CHARACTER SET utf8 COLLATE utf8_general_ci";
        }
    }

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        question_text TEXT NOT NULL,
        session_id VARCHAR(255),
        user_id BIGINT(20),
        page_id BIGINT(20),
        faq_confidence FLOAT,
        faq_match_id VARCHAR(50),
        asked_date DATETIME NOT NULL,
        is_clustered BOOLEAN DEFAULT 0,
        cluster_id INT,
        is_resolved BOOLEAN DEFAULT 0,
        INDEX session_id_index (session_id),
        INDEX asked_date_index (asked_date),
        INDEX is_resolved_index (is_resolved)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if ($wpdb->last_error) {
        error_log('[Chatbot] [chatbot-db-management.php] Failed to create table: ' . $table_name);
        error_log('[Chatbot] [chatbot-db-management.php] Error: ' . $wpdb->last_error);
        return false;
    }

    return true;
}

// Create gap clusters table - Ver 2.4.2
function create_chatbot_gap_clusters_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'chatbot_gap_clusters';
    $charset_collate = $wpdb->get_charset_collate();

    // Fallback cascade for invalid or unsupported character sets
    if (empty($charset_collate) || strpos($charset_collate, 'utf8mb4') === false) {
        if (strpos($charset_collate, 'utf8') === false) {
            $charset_collate = "CHARACTER SET utf8 COLLATE utf8_general_ci";
        }
    }

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cluster_name VARCHAR(255),
        cluster_description TEXT,
        question_count INT DEFAULT 0,
        sample_questions TEXT,
        suggested_faq TEXT,
        action_type ENUM('create', 'improve') DEFAULT 'create',
        existing_faq_id VARCHAR(50),
        priority_score FLOAT,
        status ENUM('new', 'reviewed', 'faq_created', 'dismissed') DEFAULT 'new',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        INDEX status_index (status),
        INDEX priority_index (priority_score),
        INDEX action_type_index (action_type)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if ($wpdb->last_error) {
        error_log('[Chatbot] [chatbot-db-management.php] Failed to create table: ' . $table_name);
        error_log('[Chatbot] [chatbot-db-management.php] Error: ' . $wpdb->last_error);
        return false;
    }

    return true;
}

// Create FAQ usage tracking table - Ver 2.4.2
function create_chatbot_faq_usage_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'chatbot_faq_usage';
    $charset_collate = $wpdb->get_charset_collate();

    // Fallback cascade for invalid or unsupported character sets
    if (empty($charset_collate) || strpos($charset_collate, 'utf8mb4') === false) {
        if (strpos($charset_collate, 'utf8') === false) {
            $charset_collate = "CHARACTER SET utf8 COLLATE utf8_general_ci";
        }
    }

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        faq_id VARCHAR(50) NOT NULL UNIQUE,
        hit_count INT DEFAULT 1,
        last_asked DATETIME,
        avg_confidence FLOAT,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        INDEX faq_id_index (faq_id),
        INDEX hit_count_index (hit_count)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if ($wpdb->last_error) {
        error_log('[Chatbot] [chatbot-db-management.php] Failed to create table: ' . $table_name);
        error_log('[Chatbot] [chatbot-db-management.php] Error: ' . $wpdb->last_error);
        return false;
    }

    return true;
}

// Function to handle cleanup on deactivation
function chatbot_chatgpt_deactivate_db() {
    // Clear the scheduled cleanup event
    wp_clear_scheduled_hook('chatbot_chatgpt_conversation_log_cleanup_event');

    // Clear gap analysis event
    wp_clear_scheduled_hook('chatbot_gap_analysis_event');

    // Clean up any expired transients
    clean_specific_expired_transients();
}

// Hook for the cleanup event
add_action('chatbot_chatgpt_conversation_log_cleanup_event', 'chatbot_chatgpt_conversation_log_cleanup');

// =============================================================================
// DATA RETENTION & AUTOMATIC CLEANUP - Ver 2.5.0
// =============================================================================

/**
 * Master cleanup function - runs daily via cron
 * Cleans all tables based on retention settings
 */
function chatbot_run_all_data_cleanup() {
    error_log('[Chatbot Data Cleanup] Starting daily cleanup...');
    $results = [];

    // 1. Conversation logs (default: 90 days)
    $conversation_days = intval(get_option('chatbot_retention_conversations', 90));
    if ($conversation_days > 0) {
        $deleted = chatbot_cleanup_old_conversations($conversation_days);
        $results['conversations'] = $deleted;
        error_log("[Chatbot Data Cleanup] Deleted {$deleted} old conversations (>{$conversation_days} days)");
    }

    // 2. Interaction counts (default: 365 days)
    $interaction_days = intval(get_option('chatbot_retention_interactions', 365));
    if ($interaction_days > 0) {
        $deleted = chatbot_cleanup_old_interactions($interaction_days);
        $results['interactions'] = $deleted;
        error_log("[Chatbot Data Cleanup] Deleted {$deleted} old interaction records (>{$interaction_days} days)");
    }

    // 3. Gap questions - clustered & resolved (default: 30 days after clustering)
    $gap_days = intval(get_option('chatbot_retention_gap_questions', 30));
    if ($gap_days > 0) {
        $deleted = chatbot_cleanup_old_gap_questions($gap_days);
        $results['gap_questions'] = $deleted;
        error_log("[Chatbot Data Cleanup] Deleted {$deleted} old clustered gap questions (>{$gap_days} days)");
    }

    // 4. Gap clusters - resolved (default: 90 days after resolution)
    $cluster_days = intval(get_option('chatbot_retention_gap_clusters', 90));
    if ($cluster_days > 0) {
        $deleted = chatbot_cleanup_old_gap_clusters($cluster_days);
        $results['gap_clusters'] = $deleted;
        error_log("[Chatbot Data Cleanup] Deleted {$deleted} old resolved clusters (>{$cluster_days} days)");
    }

    error_log('[Chatbot Data Cleanup] Cleanup complete: ' . json_encode($results));
    return $results;
}
add_action('chatbot_daily_cleanup_event', 'chatbot_run_all_data_cleanup');

/**
 * Delete old conversations from Supabase
 */
function chatbot_cleanup_old_conversations($days) {
    $cutoff = gmdate('c', strtotime("-{$days} days"));

    $base_url = chatbot_supabase_get_url();
    $anon_key = chatbot_supabase_get_anon_key();

    if (!$base_url || !$anon_key) {
        return 0;
    }

    // First get count of records to delete
    $count_url = $base_url . '/chatbot_conversations?interaction_time=lt.' . urlencode($cutoff) . '&select=id';
    $headers = [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key,
        'Prefer: count=exact'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $count_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers_str = substr($response, 0, $header_size);
    curl_close($ch);

    $count = 0;
    if (preg_match('/content-range: \d+-\d+\/(\d+)/i', $headers_str, $matches)) {
        $count = (int)$matches[1];
    }

    if ($count === 0) {
        return 0;
    }

    // Delete the records
    $delete_url = $base_url . '/chatbot_conversations?interaction_time=lt.' . urlencode($cutoff);
    $delete_headers = [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $delete_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $delete_headers);
    curl_exec($ch);
    curl_close($ch);

    return $count;
}

/**
 * Delete old interaction counts from Supabase
 */
function chatbot_cleanup_old_interactions($days) {
    $cutoff = gmdate('Y-m-d', strtotime("-{$days} days"));

    $base_url = chatbot_supabase_get_url();
    $anon_key = chatbot_supabase_get_anon_key();

    if (!$base_url || !$anon_key) {
        return 0;
    }

    // First get count
    $count_url = $base_url . '/chatbot_interactions?date=lt.' . $cutoff . '&select=date';
    $headers = [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key,
        'Prefer: count=exact'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $count_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers_str = substr($response, 0, $header_size);
    curl_close($ch);

    $count = 0;
    if (preg_match('/content-range: \d+-\d+\/(\d+)/i', $headers_str, $matches)) {
        $count = (int)$matches[1];
    }

    if ($count === 0) {
        return 0;
    }

    // Delete
    $delete_url = $base_url . '/chatbot_interactions?date=lt.' . $cutoff;
    $delete_headers = [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $delete_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $delete_headers);
    curl_exec($ch);
    curl_close($ch);

    return $count;
}

/**
 * Delete old gap questions that have been clustered
 * Only deletes questions that are BOTH clustered AND older than X days
 */
function chatbot_cleanup_old_gap_questions($days) {
    $cutoff = gmdate('c', strtotime("-{$days} days"));

    $base_url = chatbot_supabase_get_url();
    $anon_key = chatbot_supabase_get_anon_key();

    if (!$base_url || !$anon_key) {
        return 0;
    }

    // Only delete clustered questions older than X days
    $count_url = $base_url . '/chatbot_gap_questions?is_clustered=eq.true&asked_date=lt.' . urlencode($cutoff) . '&select=id';
    $headers = [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key,
        'Prefer: count=exact'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $count_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers_str = substr($response, 0, $header_size);
    curl_close($ch);

    $count = 0;
    if (preg_match('/content-range: \d+-\d+\/(\d+)/i', $headers_str, $matches)) {
        $count = (int)$matches[1];
    }

    if ($count === 0) {
        return 0;
    }

    // Delete
    $delete_url = $base_url . '/chatbot_gap_questions?is_clustered=eq.true&asked_date=lt.' . urlencode($cutoff);
    $delete_headers = [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $delete_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $delete_headers);
    curl_exec($ch);
    curl_close($ch);

    return $count;
}

/**
 * Delete old gap clusters that have been resolved (faq_created or dismissed)
 */
function chatbot_cleanup_old_gap_clusters($days) {
    $cutoff = gmdate('c', strtotime("-{$days} days"));

    $base_url = chatbot_supabase_get_url();
    $anon_key = chatbot_supabase_get_anon_key();

    if (!$base_url || !$anon_key) {
        return 0;
    }

    // Only delete resolved clusters (faq_created or dismissed) older than X days
    // Using 'or' filter for status
    $count_url = $base_url . '/chatbot_gap_clusters?or=(status.eq.faq_created,status.eq.dismissed)&created_at=lt.' . urlencode($cutoff) . '&select=id';
    $headers = [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key,
        'Prefer: count=exact'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $count_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers_str = substr($response, 0, $header_size);
    curl_close($ch);

    $count = 0;
    if (preg_match('/content-range: \d+-\d+\/(\d+)/i', $headers_str, $matches)) {
        $count = (int)$matches[1];
    }

    if ($count === 0) {
        return 0;
    }

    // Delete
    $delete_url = $base_url . '/chatbot_gap_clusters?or=(status.eq.faq_created,status.eq.dismissed)&created_at=lt.' . urlencode($cutoff);
    $delete_headers = [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $delete_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $delete_headers);
    curl_exec($ch);
    curl_close($ch);

    return $count;
}

/**
 * Get current data counts for admin display
 */
function chatbot_get_data_retention_stats() {
    $base_url = chatbot_supabase_get_url();
    $anon_key = chatbot_supabase_get_anon_key();

    if (!$base_url || !$anon_key) {
        return ['error' => 'Supabase not configured'];
    }

    $stats = [];
    $tables = [
        'chatbot_conversations' => 'Conversation Logs',
        'chatbot_interactions' => 'Daily Interaction Counts',
        'chatbot_gap_questions' => 'Gap Questions',
        'chatbot_gap_clusters' => 'Gap Clusters',
        'chatbot_faqs' => 'FAQs (Knowledge Base)',
        'chatbot_faq_usage' => 'FAQ Usage Stats',
        'chatbot_assistants' => 'Assistants'
    ];

    foreach ($tables as $table => $label) {
        $url = $base_url . '/' . $table . '?select=id';
        $headers = [
            'apikey: ' . $anon_key,
            'Authorization: Bearer ' . $anon_key,
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
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $count = 0;
        if ($http_code >= 200 && $http_code < 300) {
            if (preg_match('/content-range: \d+-\d+\/(\d+)/i', $headers_str, $matches)) {
                $count = (int)$matches[1];
            }
        }

        $stats[$table] = [
            'label' => $label,
            'count' => $count
        ];
    }

    return $stats;
}

/**
 * Ensure daily cleanup cron is scheduled
 */
function chatbot_ensure_cleanup_scheduled() {
    if (!wp_next_scheduled('chatbot_daily_cleanup_event')) {
        // Schedule for 3 AM server time daily
        $timestamp = strtotime('tomorrow 3:00am');
        wp_schedule_event($timestamp, 'daily', 'chatbot_daily_cleanup_event');
    }
}
add_action('admin_init', 'chatbot_ensure_cleanup_scheduled');

/**
 * AJAX handler for manual cleanup trigger
 */
function chatbot_ajax_run_cleanup() {
    check_ajax_referer('chatbot_settings_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    $results = chatbot_run_all_data_cleanup();
    wp_send_json_success([
        'message' => 'Cleanup completed',
        'results' => $results
    ]);
}
add_action('wp_ajax_chatbot_run_cleanup', 'chatbot_ajax_run_cleanup');
