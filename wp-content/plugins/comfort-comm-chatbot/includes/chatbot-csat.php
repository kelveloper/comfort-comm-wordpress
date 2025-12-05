<?php
/**
 * Kognetiks Chatbot - CSAT (Customer Satisfaction) Handler
 *
 * This file handles CSAT feedback collection and storage
 *
 * @package chatbot-chatgpt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die();
}

// AJAX handler for CSAT feedback submission
function chatbot_chatgpt_submit_csat() {
    error_log('========== CSAT AJAX HANDLER CALLED ==========');
    error_log('POST data: ' . print_r($_POST, true));

    // Verify nonce
    error_log('Nonce check - isset: ' . (isset($_POST['chatbot_nonce']) ? 'YES' : 'NO'));
    if (isset($_POST['chatbot_nonce'])) {
        $nonce_valid = wp_verify_nonce($_POST['chatbot_nonce'], 'chatbot_message_nonce');
        error_log('Nonce validation result: ' . ($nonce_valid ? 'VALID' : 'INVALID'));
    }

    if (!isset($_POST['chatbot_nonce']) || !wp_verify_nonce($_POST['chatbot_nonce'], 'chatbot_message_nonce')) {
        error_log('CSAT ERROR: Nonce verification failed!');
        wp_send_json_error('Invalid nonce');
        return;
    }

    error_log('Nonce verified successfully, processing feedback...');

    // Get feedback data
    $feedback = sanitize_text_field($_POST['feedback']); // 'yes' or 'no'
    $question = sanitize_textarea_field($_POST['question']);
    $answer = sanitize_textarea_field($_POST['answer']);
    $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
    $user_id = sanitize_text_field($_POST['user_id']);
    $session_id = sanitize_text_field($_POST['session_id']);
    $page_id = intval($_POST['page_id']);
    $timestamp = current_time('mysql');

    // Rate limiting check (per session)
    $rate_limit = intval(get_option('chatbot_rate_limit_per_session', 3));
    $session_feedback_count = chatbot_get_session_feedback_count($session_id);

    if ($session_feedback_count >= $rate_limit) {
        error_log('CSAT: Rate limit reached for session ' . $session_id);
        wp_send_json_error(array(
            'message' => 'Rate limit reached',
            'limit' => $rate_limit
        ));
        return;
    }

    // Get confidence score and FAQ ID by searching FAQ using vector search
    $confidence_score = 'unknown';
    $matched_faq_id = null;
    if (function_exists('chatbot_vector_faq_search')) {
        // Use vector search (no fallback)
        $faq_result = chatbot_vector_faq_search($question, true, $session_id, $user_id, $page_id);
        if ($faq_result && isset($faq_result['confidence'])) {
            $confidence_score = $faq_result['confidence'];
        }
        if ($faq_result && isset($faq_result['faq_id'])) {
            $matched_faq_id = $faq_result['faq_id'];
        }
    }

    // Debug logging
    prod_trace('NOTICE', 'CSAT - Received question: ' . $question);
    prod_trace('NOTICE', 'CSAT - Received answer: ' . $answer);
    prod_trace('NOTICE', 'CSAT - Confidence score: ' . $confidence_score);
    prod_trace('NOTICE', 'CSAT - Matched FAQ ID: ' . ($matched_faq_id ?? 'none'));

    // Store in WordPress options (simple approach for P0)
    $csat_data = get_option('chatbot_chatgpt_csat_data', array(
        'total' => 0,
        'helpful' => 0,
        'not_helpful' => 0,
        'responses' => array()
    ));

    // Update counts
    $csat_data['total']++;
    if ($feedback === 'yes') {
        $csat_data['helpful']++;
    } else {
        $csat_data['not_helpful']++;
    }

    // Store individual response
    $csat_data['responses'][] = array(
        'feedback' => $feedback,
        'question' => $question,
        'answer' => $answer,
        'comment' => $comment,
        'confidence_score' => $confidence_score,
        'faq_id' => $matched_faq_id,
        'user_id' => $user_id,
        'session_id' => $session_id,
        'page_id' => $page_id,
        'timestamp' => $timestamp
    );

    // Keep only last 1000 responses to prevent option size from growing too large
    if (count($csat_data['responses']) > 1000) {
        $csat_data['responses'] = array_slice($csat_data['responses'], -1000);
    }

    // Save updated data
    $saved = update_option('chatbot_chatgpt_csat_data', $csat_data);
    error_log('CSAT Data saved to database: ' . ($saved ? 'SUCCESS' : 'FAILED'));
    error_log('Total responses now: ' . $csat_data['total']);

    // If negative feedback, check threshold and add to learning review queue
    if ($feedback === 'no' && $matched_faq_id) {
        chatbot_check_learning_threshold($matched_faq_id, $question, $answer, $confidence_score, $comment);
    }

    // Calculate CSAT score
    $csat_score = 0;
    if ($csat_data['total'] > 0) {
        $csat_score = round(($csat_data['helpful'] / $csat_data['total']) * 100, 1);
    }

    // Log the feedback
    prod_trace('NOTICE', 'CSAT feedback received: ' . $feedback . ' | Score: ' . $csat_score . '%');

    // Return success with current score
    wp_send_json_success(array(
        'message' => 'Feedback saved',
        'csat_score' => $csat_score,
        'total_responses' => $csat_data['total'],
        'helpful_count' => $csat_data['helpful']
    ));
}
add_action('wp_ajax_chatbot_chatgpt_submit_csat', 'chatbot_chatgpt_submit_csat');
add_action('wp_ajax_nopriv_chatbot_chatgpt_submit_csat', 'chatbot_chatgpt_submit_csat');

// Function to get CSAT statistics (for admin dashboard)
function chatbot_chatgpt_get_csat_stats() {
    $csat_data = get_option('chatbot_chatgpt_csat_data', array(
        'total' => 0,
        'helpful' => 0,
        'not_helpful' => 0,
        'responses' => array()
    ));

    $csat_score = 0;
    if ($csat_data['total'] > 0) {
        $csat_score = round(($csat_data['helpful'] / $csat_data['total']) * 100, 1);
    }

    return array(
        'total_responses' => $csat_data['total'],
        'helpful_count' => $csat_data['helpful'],
        'not_helpful_count' => $csat_data['not_helpful'],
        'csat_score' => $csat_score,
        'target_met' => $csat_score >= 70 // P0 Success Metric: >70%
    );
}

// Function to reset CSAT data (for admin use)
function chatbot_chatgpt_reset_csat_data() {
    delete_option('chatbot_chatgpt_csat_data');
    prod_trace('NOTICE', 'CSAT data has been reset');
}

// Get feedback count for a session (for rate limiting)
function chatbot_get_session_feedback_count($session_id) {
    $csat_data = get_option('chatbot_chatgpt_csat_data', array('responses' => array()));
    $count = 0;

    // Count feedback from this session in the last 24 hours
    $cutoff_time = strtotime('-24 hours');

    foreach ($csat_data['responses'] as $response) {
        if (isset($response['session_id']) && $response['session_id'] === $session_id) {
            $response_time = strtotime($response['timestamp'] ?? '');
            if ($response_time >= $cutoff_time) {
                $count++;
            }
        }
    }

    return $count;
}

// Check if FAQ has reached negative threshold and add to review queue
function chatbot_check_learning_threshold($faq_id, $question, $answer, $confidence_score, $comment = '') {
    // Learning is always on with human approval required
    $threshold = 5; // Fixed: 5 negatives before flagging
    $csat_data = get_option('chatbot_chatgpt_csat_data', array('responses' => array()));

    // Count negative feedback for this FAQ
    $negative_count = 0;
    $user_comments = array();

    foreach ($csat_data['responses'] as $response) {
        if (isset($response['faq_id']) && $response['faq_id'] === $faq_id && $response['feedback'] === 'no') {
            $negative_count++;
            if (!empty($response['comment'])) {
                $user_comments[] = $response['comment'];
            }
        }
    }

    // Add current comment
    if (!empty($comment)) {
        $user_comments[] = $comment;
    }

    error_log("Learning: FAQ $faq_id has $negative_count negative votes (threshold: $threshold)");

    // If threshold reached, add to review queue
    if ($negative_count >= $threshold) {
        chatbot_add_to_review_queue($faq_id, $question, $answer, $confidence_score, $negative_count, $user_comments);
    }
}

// =============================================================================
// NET PROMOTER SCORE (NPS) - Ver 2.5.0
// =============================================================================

/**
 * AJAX handler for NPS submission
 * NPS Scale: 0-10
 * - Promoters: 9-10
 * - Passives: 7-8
 * - Detractors: 0-6
 */
function chatbot_chatgpt_submit_nps() {
    // Verify nonce
    if (!isset($_POST['chatbot_nonce']) || !wp_verify_nonce($_POST['chatbot_nonce'], 'chatbot_message_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    $score = intval($_POST['score'] ?? -1);
    $session_id = sanitize_text_field($_POST['session_id'] ?? '');
    $user_id = sanitize_text_field($_POST['user_id'] ?? '');
    $timestamp = current_time('mysql');

    // Validate score
    if ($score < 0 || $score > 10) {
        wp_send_json_error('Invalid NPS score');
        return;
    }

    // Get existing NPS data
    $nps_data = get_option('chatbot_chatgpt_nps_data', [
        'responses' => [],
        'total' => 0
    ]);

    // Check if this session already submitted NPS (prevent duplicates)
    foreach ($nps_data['responses'] as $response) {
        if ($response['session_id'] === $session_id) {
            wp_send_json_error('NPS already submitted for this session');
            return;
        }
    }

    // Add new response
    $nps_data['responses'][] = [
        'score' => $score,
        'session_id' => $session_id,
        'user_id' => $user_id,
        'timestamp' => $timestamp
    ];
    $nps_data['total']++;

    // Keep only last 500 responses
    if (count($nps_data['responses']) > 500) {
        $nps_data['responses'] = array_slice($nps_data['responses'], -500);
    }

    update_option('chatbot_chatgpt_nps_data', $nps_data);

    // Calculate current NPS
    $nps_stats = chatbot_supabase_get_nps_stats();

    wp_send_json_success([
        'message' => 'NPS submitted',
        'nps_score' => $nps_stats['nps_score'],
        'total_responses' => $nps_stats['total_responses']
    ]);
}
add_action('wp_ajax_chatbot_chatgpt_submit_nps', 'chatbot_chatgpt_submit_nps');
add_action('wp_ajax_nopriv_chatbot_chatgpt_submit_nps', 'chatbot_chatgpt_submit_nps');

// Add FAQ to learning review queue
function chatbot_add_to_review_queue($faq_id, $question, $answer, $confidence_score, $negative_count, $user_comments = array()) {
    $review_queue = get_option('chatbot_learning_review_queue', array());

    // Check if already in queue
    foreach ($review_queue as &$item) {
        if (isset($item['faq_id']) && $item['faq_id'] === $faq_id && $item['status'] === 'pending') {
            // Update existing entry
            $item['negative_count'] = $negative_count;
            $item['user_comments'] = array_unique(array_merge($item['user_comments'] ?? array(), $user_comments));
            $item['updated_at'] = current_time('mysql');
            update_option('chatbot_learning_review_queue', $review_queue);
            error_log("Learning: Updated existing review queue item for FAQ $faq_id");
            return;
        }
    }

    // Convert confidence score to percentage
    $confidence_pct = 0;
    $confidence_map = array(
        'very_high' => 85,
        'high' => 75,
        'medium' => 60,
        'low' => 40
    );
    if (isset($confidence_map[$confidence_score])) {
        $confidence_pct = $confidence_map[$confidence_score];
    }

    // Determine suggestion type based on confidence
    $confidence_floor = intval(get_option('chatbot_confidence_floor', 50));
    $suggestion_type = 'Review answer';
    if ($confidence_pct < $confidence_floor) {
        $suggestion_type = 'Add keywords';
    } elseif ($negative_count >= 10) {
        $suggestion_type = 'Rewrite answer';
    }

    // Add new entry
    $new_id = count($review_queue) + 1;
    $review_queue[] = array(
        'id' => $new_id,
        'faq_id' => $faq_id,
        'question' => $question,
        'current_answer' => $answer,
        'current_confidence' => $confidence_pct,
        'negative_count' => $negative_count,
        'user_comments' => $user_comments,
        'suggestion_type' => $suggestion_type,
        'status' => 'pending',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    );

    update_option('chatbot_learning_review_queue', $review_queue);
    error_log("Learning: Added FAQ $faq_id to review queue (negative count: $negative_count)");
}
