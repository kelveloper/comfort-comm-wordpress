<?php
/**
 * Chatbot Vector Search - Integration with Existing Chatbot
 *
 * This file integrates vector search into the existing chatbot logic.
 * Vector search is REQUIRED - no fallback to keyword search.
 *
 * @package comfort-comm-chatbot
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die();
}

// Include vector search components
require_once plugin_dir_path(__FILE__) . 'chatbot-vector-search.php';

/**
 * Check if vector search is properly configured
 *
 * @return array Status with details
 */
function chatbot_vector_check_status() {
    $status = [
        'configured' => false,
        'connected' => false,
        'pgvector_installed' => false,
        'faqs_migrated' => false,
        'ready' => false,
        'errors' => []
    ];

    // Check if Supabase config exists (from admin settings or wp-config.php)
    $config = function_exists('chatbot_supabase_get_config') ? chatbot_supabase_get_config() : [];

    if (empty($config['project_url']) && empty($config['db_host'])) {
        $status['errors'][] = 'Supabase not configured. Go to Steve-Bot → Database to configure.';
        return $status;
    }

    if (empty($config['anon_key'])) {
        $status['errors'][] = 'Supabase Anon Key not configured. Go to Steve-Bot → Database to configure.';
        return $status;
    }
    $status['configured'] = true;

    // Test connection using REST API (doesn't need direct DB access)
    if (function_exists('chatbot_supabase_test_connection')) {
        $test = chatbot_supabase_test_connection($config);
        if (!$test['success']) {
            $status['errors'][] = $test['message'];
            return $status;
        }
    }
    $status['connected'] = true;

    // Check pgvector extension
    if (!chatbot_vector_is_available()) {
        $status['errors'][] = 'pgvector extension is not installed in PostgreSQL';
        return $status;
    }
    $status['pgvector_installed'] = true;

    // Check if FAQs are migrated
    $stats = chatbot_vector_get_stats();
    if (!isset($stats['faqs_with_embeddings']) || $stats['faqs_with_embeddings'] === 0) {
        $status['errors'][] = 'No FAQs with embeddings found. Run migration first.';
        return $status;
    }
    $status['faqs_migrated'] = true;
    $status['faq_count'] = $stats['faq_count'];
    $status['faqs_with_embeddings'] = $stats['faqs_with_embeddings'];

    // All checks passed
    $status['ready'] = true;
    return $status;
}

/**
 * Enhanced FAQ search - VECTOR ONLY
 *
 * This function replaces chatbot_faq_search() entirely.
 * No fallback to keyword search.
 *
 * @param string $query User's question
 * @param bool $return_score Whether to return score information
 * @param string|null $session_id Session ID for analytics
 * @param int|null $user_id User ID for analytics
 * @param int|null $page_id Page ID for analytics
 * @return array|null Search result
 */
function chatbot_enhanced_faq_search($query, $return_score = false, $session_id = null, $user_id = null, $page_id = null) {
    // Use vector search only - no fallback
    return chatbot_vector_faq_search($query, $return_score, $session_id, $user_id, $page_id);
}

/**
 * Get FAQ answer with confidence-based response strategy
 *
 * Returns the FAQ answer directly for high confidence matches,
 * or provides context to AI for lower confidence matches.
 *
 * @param string $query User's question
 * @param array $options Options array:
 *   - session_id: Session ID
 *   - user_id: User ID
 *   - page_id: Page ID
 *   - include_related: Include related questions
 * @return array Response with answer and metadata
 */
function chatbot_get_faq_response($query, $options = []) {
    $session_id = $options['session_id'] ?? null;
    $user_id = $options['user_id'] ?? null;
    $page_id = $options['page_id'] ?? null;
    $include_related = $options['include_related'] ?? false;

    // Search for matching FAQ using vector search only
    $result = chatbot_enhanced_faq_search($query, true, $session_id, $user_id, $page_id);

    // No match found
    if (!$result || !$result['match']) {
        return [
            'found' => false,
            'answer' => null,
            'confidence' => 'none',
            'score' => 0,
            'use_ai' => true,
            'context' => null
        ];
    }

    $response = [
        'found' => true,
        'faq_id' => $result['match']['id'] ?? null,
        'question' => $result['match']['question'],
        'answer' => $result['match']['answer'],
        'category' => $result['match']['category'] ?? '',
        'confidence' => $result['confidence'],
        'score' => $result['score'],
        'match_type' => $result['match_type'] ?? 'vector'
    ];

    // Determine response strategy based on confidence
    switch ($result['confidence']) {
        case 'very_high':
            // 85%+ - Return FAQ directly, no AI needed
            $response['use_ai'] = false;
            $response['strategy'] = 'direct';
            break;

        case 'high':
            // 75-85% - Return FAQ with minimal AI enhancement
            $response['use_ai'] = true;
            $response['strategy'] = 'enhance';
            $response['ai_instruction'] = 'Use this FAQ answer as the primary response. You may slightly rephrase for naturalness but keep the core information unchanged.';
            break;

        case 'medium':
            // 65-75% - Use FAQ as context, AI provides response
            $response['use_ai'] = true;
            $response['strategy'] = 'contextual';
            $response['ai_instruction'] = 'This FAQ may be relevant to the user\'s question. Use it as context but formulate your own response that addresses their specific query.';
            break;

        case 'low':
            // 50-65% - FAQ is a weak match, AI should handle
            $response['use_ai'] = true;
            $response['strategy'] = 'fallback';
            $response['ai_instruction'] = 'This FAQ has low relevance to the user\'s question. Consider it as background information only. Provide a helpful response based on your knowledge.';
            break;

        default:
            // Below threshold - No usable FAQ match
            $response['found'] = false;
            $response['use_ai'] = true;
            $response['strategy'] = 'ai_only';
            break;
    }

    // Get related questions if requested and we have a match
    if ($include_related && $response['found'] && isset($response['faq_id'])) {
        $related = chatbot_vector_get_similar_faqs($response['faq_id'], 3);
        $response['related_questions'] = array_map(function($faq) {
            return [
                'question' => $faq['question'],
                'category' => $faq['category']
            ];
        }, $related);
    }

    return $response;
}

/**
 * Build AI context with FAQ information
 *
 * Creates a context string for the AI model that includes relevant FAQ data.
 *
 * @param string $query User's question
 * @param int $max_faqs Maximum number of FAQs to include in context
 * @return string Context string for AI prompt
 */
function chatbot_build_faq_context($query, $max_faqs = 3) {
    $results = chatbot_vector_search($query, [
        'threshold' => CHATBOT_VECTOR_THRESHOLD_LOW,
        'limit' => $max_faqs,
        'return_scores' => true
    ]);

    if (!$results['success'] || empty($results['results'])) {
        return '';
    }

    $context_parts = ["Here are relevant FAQs that may help answer the user's question:\n"];

    foreach ($results['results'] as $i => $faq) {
        $num = $i + 1;
        $confidence_pct = round($faq['similarity'] * 100);
        $context_parts[] = "FAQ #{$num} (Relevance: {$confidence_pct}%):";
        $context_parts[] = "Q: {$faq['question']}";
        $context_parts[] = "A: {$faq['answer']}";
        $context_parts[] = "";
    }

    $context_parts[] = "Use this information to help answer the user's question. If the FAQs are highly relevant, base your answer on them. If not very relevant, use your general knowledge.";

    return implode("\n", $context_parts);
}

/**
 * Admin notice if vector search is not configured
 * Only shows on the Knowledge Base tab to avoid cluttering other pages
 */
function chatbot_vector_admin_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Only show on Knowledge Base tab
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

    if ($current_page !== 'chatbot-chatgpt' || $current_tab !== 'kn_acquire') {
        return;
    }

    $status = chatbot_vector_check_status();

    if (!$status['ready']) {
        $errors = implode('<br>', $status['errors']);
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>Knowledge Base Setup:</strong><br>';
        echo $errors;
        echo '</p></div>';
    }
}
add_action('admin_notices', 'chatbot_vector_admin_notice');

/**
 * Add sync/migrate button to admin
 */
function chatbot_vector_add_admin_actions() {
    $status = chatbot_vector_check_status();
    ?>
    <div class="chatbot-vector-admin-section" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
        <h3 style="margin-top: 0;">Vector Database Management</h3>

        <?php if ($status['ready']): ?>
            <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <strong style="color: #155724;">Status: Ready</strong><br>
                FAQs in database: <?php echo $status['faq_count']; ?><br>
                FAQs with embeddings: <?php echo $status['faqs_with_embeddings']; ?>
            </div>
        <?php else: ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <strong style="color: #721c24;">Status: Not Ready</strong><br>
                <?php echo implode('<br>', $status['errors']); ?>
            </div>
        <?php endif; ?>

        <p>
            <button type="button" id="chatbot-vector-migrate-btn" class="button button-primary">
                Migrate FAQs to Vector Database
            </button>
            <span id="chatbot-vector-migrate-status" style="margin-left: 10px;"></span>
        </p>

        <p>
            <label>
                <input type="checkbox" id="chatbot-vector-clear-existing" value="1">
                Clear existing entries before migration
            </label>
        </p>

        <script>
        jQuery(document).ready(function($) {
            $('#chatbot-vector-migrate-btn').on('click', function() {
                var btn = $(this);
                var status = $('#chatbot-vector-migrate-status');
                var clearExisting = $('#chatbot-vector-clear-existing').is(':checked') ? '1' : '0';

                if (!confirm('This will migrate all FAQs to the vector database. This may take a few minutes. Continue?')) {
                    return;
                }

                btn.prop('disabled', true);
                status.html('<span style="color:#666;">Migrating... This may take a few minutes.</span>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'chatbot_vector_migrate',
                        nonce: '<?php echo wp_create_nonce('chatbot_vector_migrate'); ?>',
                        clear_existing: clearExisting
                    },
                    timeout: 300000, // 5 minute timeout
                    success: function(response) {
                        if (response.success) {
                            status.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            status.html('<span style="color:red;">✗ ' + (response.data.message || 'Migration failed') + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        status.html('<span style="color:red;">✗ Migration failed: ' + error + '</span>');
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                    }
                });
            });
        });
        </script>
    </div>
    <?php
}
