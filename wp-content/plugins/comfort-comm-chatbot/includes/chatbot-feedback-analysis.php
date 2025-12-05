<?php
/**
 * AI-Powered Feedback Analysis
 * Analyzes thumbs-down feedback to generate FAQ improvement suggestions
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die();
}

// AJAX handler for feedback analysis
function chatbot_ajax_analyze_feedback() {
    check_ajax_referer('chatbot_feedback_analysis', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    // Get time period filter
    $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'all';

    // Calculate cutoff date based on period
    $cutoff_date = null;
    switch ($period) {
        case 'weekly':
            $cutoff_date = date('Y-m-d H:i:s', strtotime('-7 days'));
            break;
        case 'monthly':
            $cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));
            break;
        case 'quarterly':
            $cutoff_date = date('Y-m-d H:i:s', strtotime('-90 days'));
            break;
        case 'yearly':
            $cutoff_date = date('Y-m-d H:i:s', strtotime('-365 days'));
            break;
        case 'all':
        default:
            $cutoff_date = null;
            break;
    }

    // Get thumbs-down feedback
    $csat_data = get_option('chatbot_chatgpt_csat_data', array('responses' => array()));
    $all_responses = $csat_data['responses'];

    // Filter only thumbs down and by time period
    $thumbs_down = array_filter($all_responses, function($response) use ($cutoff_date) {
        if ($response['feedback'] !== 'no') {
            return false;
        }

        // Filter by date if cutoff is set
        if ($cutoff_date && isset($response['timestamp'])) {
            return $response['timestamp'] >= $cutoff_date;
        }

        return true;
    });

    if (empty($thumbs_down)) {
        wp_send_json_error('No thumbs-down feedback found. Users need to give feedback first.');
        return;
    }

    // Prioritize feedback with comments
    $with_comments = array_filter($thumbs_down, function($response) {
        return !empty($response['comment']);
    });

    $without_comments = array_filter($thumbs_down, function($response) {
        return empty($response['comment']);
    });

    // Take up to 10 with comments, then fill with ones without comments
    $feedback_to_analyze = array_slice($with_comments, 0, 10);
    if (count($feedback_to_analyze) < 10) {
        $remaining = 10 - count($feedback_to_analyze);
        $feedback_to_analyze = array_merge($feedback_to_analyze, array_slice($without_comments, 0, $remaining));
    }

    error_log('📊 Analyzing ' . count($feedback_to_analyze) . ' thumbs-down feedback items');

    // Analyze with AI
    $suggestions = chatbot_analyze_feedback_with_ai($feedback_to_analyze);

    if (empty($suggestions)) {
        wp_send_json_error('AI analysis returned no suggestions. Please try again.');
        return;
    }

    // Generate HTML for suggestions
    $html = chatbot_generate_suggestions_html($suggestions);

    wp_send_json_success(array(
        'html' => $html,
        'count' => count($suggestions)
    ));
}
add_action('wp_ajax_chatbot_analyze_feedback', 'chatbot_ajax_analyze_feedback');

/**
 * Analyze feedback with Gemini AI
 */
function chatbot_analyze_feedback_with_ai($feedback_items) {
    // Get Gemini API key
    $api_key_encrypted = get_option('chatbot_gemini_api_key', '');

    if (empty($api_key_encrypted)) {
        error_log('[Chatbot] Gemini API key not set');
        return [];
    }

    // Decrypt the API key
    $api_key = chatbot_chatgpt_decrypt_api_key($api_key_encrypted, 'chatbot_gemini_api_key');

    if (empty($api_key)) {
        error_log('[Chatbot] Failed to decrypt Gemini API key');
        return [];
    }

    // Load existing FAQs
    $existing_faqs = chatbot_load_existing_faqs();
    $faq_summary = "Existing FAQs (" . count($existing_faqs) . " total):\n";
    foreach ($existing_faqs as $faq) {
        $faq_summary .= $faq['id'] . ': ' . $faq['question'] . ' [' . substr($faq['keywords'] ?? '', 0, 40) . "]\n";
    }

    // Format feedback for AI
    $feedback_text = '';
    foreach ($feedback_items as $idx => $item) {
        $confidence = isset($item['confidence_score']) ? $item['confidence_score'] : 'unknown';
        $feedback_text .= ($idx + 1) . ". Question: " . $item['question'] . "\n";
        $feedback_text .= "   Answer: " . substr($item['answer'], 0, 150) . "...\n";
        $feedback_text .= "   Confidence Score: " . $confidence . "\n";
        if (!empty($item['comment'])) {
            $feedback_text .= "   User Comment: " . $item['comment'] . "\n";
        }
        $feedback_text .= "   Feedback: 👎 Thumbs Down\n\n";
    }

    $prompt = "You are analyzing customer feedback for a chatbot FAQ system. Users gave thumbs down (negative feedback) to these chatbot answers.

EXISTING FAQ DATABASE:
$faq_summary

NEGATIVE FEEDBACK FROM USERS:
$feedback_text

UNDERSTANDING CONFIDENCE SCORES:
- very_high (80%+): Very strong keyword match - FAQ was returned directly
- high (60-80%): Strong match - FAQ was used with minimal AI processing
- medium (40-60%): Moderate match - FAQ used as reference
- low (20-40%): Weak match - FAQ only used as hint
- unknown: No FAQ match found, pure AI response

Analyze each piece of feedback and suggest improvements. For each feedback item, determine:
- If an existing FAQ should be IMPROVED (add keywords, clarify answer) - especially for medium/low confidence scores
- If a NEW FAQ should be CREATED - especially when confidence is unknown or user comment indicates missing information

Respond with a JSON array:
[
  {
    \"feedback_number\": 1,
    \"action_type\": \"improve\" or \"create\",
    \"existing_faq_id\": \"cc001\" (only if improve),
    \"suggested_faq\": {
      \"question\": \"...\",
      \"answer\": \"...\",
      \"keywords\": \"...\"
    } (only if create),
    \"reasoning\": \"Why this will help based on user feedback\"
  }
]

IMPORTANT:
- Prioritize feedback items WITH user comments (they explain what went wrong)
- Be specific and actionable
- Only respond with valid JSON";

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;

    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 4096
            ]
        ]),
        'timeout' => 45
    ]);

    if (is_wp_error($response)) {
        error_log('[Chatbot] Gemini API error: ' . $response->get_error_message());
        return [];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
        error_log('[Chatbot] Unexpected Gemini API response');
        return [];
    }

    $ai_response = $body['candidates'][0]['content']['parts'][0]['text'];
    $ai_response = preg_replace('/```json\n?/', '', $ai_response);
    $ai_response = preg_replace('/```\n?/', '', $ai_response);
    $ai_response = trim($ai_response);

    $suggestions = json_decode($ai_response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[Chatbot] Failed to parse AI suggestions: ' . json_last_error_msg());
        return [];
    }

    return $suggestions;
}

/**
 * Generate HTML for AI suggestions
 */
function chatbot_generate_suggestions_html($suggestions) {
    $html = '<div style="background: #f0f9ff; border: 1px solid #bae6fd; padding: 15px; border-radius: 6px; margin-bottom: 15px;">';
    $html .= '<strong style="color: #0369a1;">✓ Analysis Complete!</strong> Found ' . count($suggestions) . ' improvement suggestions based on user feedback.';
    $html .= '</div>';

    foreach ($suggestions as $suggestion) {
        $is_improve = ($suggestion['action_type'] === 'improve');
        $border_color = $is_improve ? '#f59e0b' : '#3b82f6';
        $action_label = $is_improve ? '🔧 Improve Existing FAQ' : '✨ Create New FAQ';

        $html .= '<div style="background: #f9fafb; border-left: 4px solid ' . $border_color . '; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; margin-bottom: 12px;">';

        $html .= '<div style="font-size: 11px; font-weight: 700; color: ' . $border_color . '; margin-bottom: 8px; text-transform: uppercase;">' . $action_label . '</div>';

        if ($is_improve) {
            // Improve existing FAQ
            $existing_faq = chatbot_get_faq_by_id($suggestion['existing_faq_id'] ?? '');

            $html .= '<div style="background: white; padding: 10px; border-radius: 4px; margin-bottom: 10px;">';
            $html .= '<div style="margin-bottom: 4px;"><strong style="font-size: 12px;">FAQ ID:</strong> <code>' . esc_html($suggestion['existing_faq_id'] ?? 'N/A') . '</code></div>';
            $html .= '<div><strong style="font-size: 12px;">Question:</strong> ' . esc_html($existing_faq['question']) . '</div>';
            $html .= '</div>';

        } else {
            // Create new FAQ
            $faq = $suggestion['suggested_faq'];

            $html .= '<div style="background: white; padding: 12px; border-radius: 4px; margin-bottom: 10px;">';
            $html .= '<div style="margin-bottom: 8px;"><strong>Q:</strong> ' . esc_html($faq['question'] ?? '') . '</div>';
            $html .= '<div style="margin-bottom: 8px;"><strong>A:</strong> ' . esc_html($faq['answer'] ?? '') . '</div>';
            $html .= '<div><strong>Keywords:</strong> <span style="font-style: italic; color: #64748b;">' . esc_html($faq['keywords'] ?? '') . '</span></div>';
            $html .= '</div>';
        }

        $html .= '<div style="background: #f0f9ff; padding: 8px; border-radius: 4px; font-size: 12px; color: #0c4a6e;">';
        $html .= '<strong>Why:</strong> ' . esc_html($suggestion['reasoning'] ?? 'No reasoning provided');
        $html .= '</div>';

        // Add action buttons
        $html .= '<div style="margin-top: 12px; display: flex; gap: 8px;">';
        if ($is_improve) {
            $html .= '<button class="button button-primary" onclick=\'chatbotEditFAQ(' . json_encode($suggestion) . ')\' style="font-size: 12px; padding: 6px 12px; height: auto;">Edit FAQ</button>';
        } else {
            $html .= '<button class="button button-primary" onclick=\'chatbotAddFAQ(' . json_encode($suggestion) . ')\' style="font-size: 12px; padding: 6px 12px; height: auto;">Add to Knowledge Base</button>';
        }
        $html .= '</div>';

        $html .= '</div>';
    }

    return $html;
}

// AJAX handler for clearing feedback data
function chatbot_ajax_clear_feedback() {
    check_ajax_referer('chatbot_clear_feedback', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    // Reset CSAT data to initial state
    $initial_data = array(
        'total' => 0,
        'helpful' => 0,
        'not_helpful' => 0,
        'responses' => array()
    );

    update_option('chatbot_chatgpt_csat_data', $initial_data);

    error_log('[Chatbot] Feedback data cleared by user');

    wp_send_json_success(array(
        'message' => 'Feedback data cleared successfully'
    ));
}
add_action('wp_ajax_chatbot_clear_feedback', 'chatbot_ajax_clear_feedback');

// AJAX handler for adding new FAQ
function chatbot_ajax_add_faq() {
    check_ajax_referer('chatbot_faq_management', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $faq_data = isset($_POST['faq_data']) ? $_POST['faq_data'] : array();

    if (empty($faq_data['question']) || empty($faq_data['answer'])) {
        wp_send_json_error('Question and answer are required');
    }

    // Load existing FAQs
    $faq_file = plugin_dir_path(dirname(__FILE__)) . 'data/comfort-comm-faqs.json';
    $faqs = json_decode(file_get_contents($faq_file), true);

    if (!is_array($faqs)) {
        wp_send_json_error('Failed to load FAQ database');
    }

    // Generate new FAQ ID
    $last_id = end($faqs)['id'];
    $id_num = intval(substr($last_id, 2)) + 1;
    $new_id = 'cc' . str_pad($id_num, 3, '0', STR_PAD_LEFT);

    // Create new FAQ entry
    $new_faq = array(
        'id' => $new_id,
        'question' => sanitize_textarea_field($faq_data['question']),
        'answer' => sanitize_textarea_field($faq_data['answer']),
        'category' => isset($faq_data['category']) ? sanitize_text_field($faq_data['category']) : 'General',
        'keywords' => isset($faq_data['keywords']) ? sanitize_text_field($faq_data['keywords']) : '',
        'created_at' => current_time('mysql')
    );

    // Add to array
    $faqs[] = $new_faq;

    // Save back to JSON file
    $result = file_put_contents($faq_file, json_encode($faqs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($result === false) {
        wp_send_json_error('Failed to save FAQ');
    }

    error_log('[Chatbot] New FAQ added: ' . $new_id);

    wp_send_json_success(array(
        'message' => 'FAQ added successfully',
        'faq_id' => $new_id
    ));
}
add_action('wp_ajax_chatbot_add_faq', 'chatbot_ajax_add_faq');

// AJAX handler for editing existing FAQ
function chatbot_ajax_edit_faq() {
    check_ajax_referer('chatbot_faq_management', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $faq_id = isset($_POST['faq_id']) ? sanitize_text_field($_POST['faq_id']) : '';
    $new_keywords = isset($_POST['keywords']) ? sanitize_text_field($_POST['keywords']) : '';

    if (empty($faq_id)) {
        wp_send_json_error('FAQ ID is required');
    }

    // Load existing FAQs
    $faq_file = plugin_dir_path(dirname(__FILE__)) . 'data/comfort-comm-faqs.json';
    $faqs = json_decode(file_get_contents($faq_file), true);

    if (!is_array($faqs)) {
        wp_send_json_error('Failed to load FAQ database');
    }

    // Find and update the FAQ
    $found = false;
    foreach ($faqs as &$faq) {
        if ($faq['id'] === $faq_id) {
            // Append new keywords to existing ones
            $existing_keywords = isset($faq['keywords']) ? $faq['keywords'] : '';
            $faq['keywords'] = trim($existing_keywords . ' ' . $new_keywords);
            $found = true;
            break;
        }
    }

    if (!$found) {
        wp_send_json_error('FAQ not found: ' . $faq_id);
    }

    // Save back to JSON file
    $result = file_put_contents($faq_file, json_encode($faqs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($result === false) {
        wp_send_json_error('Failed to save FAQ');
    }

    error_log('[Chatbot] FAQ updated: ' . $faq_id);

    wp_send_json_success(array(
        'message' => 'FAQ updated successfully'
    ));
}
add_action('wp_ajax_chatbot_edit_faq', 'chatbot_ajax_edit_faq');
