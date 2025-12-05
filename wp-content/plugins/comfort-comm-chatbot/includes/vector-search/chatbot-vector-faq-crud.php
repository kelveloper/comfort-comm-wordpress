<?php
/**
 * Chatbot Vector Search - FAQ CRUD Operations via Supabase REST API
 *
 * This file handles all FAQ Create/Read/Update/Delete operations
 * using the Supabase REST API (no PDO required).
 *
 * @package comfort-comm-chatbot
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die();
}

/**
 * Load all FAQs from Supabase
 *
 * @return array Array of FAQ objects
 */
function chatbot_faq_load() {
    $config = chatbot_vector_get_supabase_config();

    if (!$config || empty($config['anon_key'])) {
        error_log('[Chatbot FAQ] Supabase not configured');
        return [];
    }

    $url = $config['url'] . '/rest/v1/chatbot_faqs?select=faq_id,question,answer,category,created_at&order=created_at.asc';

    $response = wp_remote_get($url, [
        'headers' => [
            'apikey' => $config['anon_key'],
            'Authorization' => 'Bearer ' . $config['anon_key'],
        ],
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        error_log('[Chatbot FAQ] Failed to load FAQs: ' . $response->get_error_message());
        return [];
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status >= 400 || !is_array($data)) {
        error_log('[Chatbot FAQ] API error loading FAQs: ' . $body);
        return [];
    }

    // Map faq_id to id for compatibility with existing code
    return array_map(function($faq) {
        return [
            'id' => $faq['faq_id'],
            'question' => $faq['question'],
            'answer' => $faq['answer'],
            'category' => $faq['category'] ?? '',
            'created_at' => $faq['created_at'] ?? ''
        ];
    }, $data);
}

/**
 * Get FAQ count from Supabase
 *
 * @return int Number of FAQs
 */
function chatbot_faq_get_count() {
    $config = chatbot_vector_get_supabase_config();

    if (!$config || empty($config['anon_key'])) {
        return 0;
    }

    $url = $config['url'] . '/rest/v1/chatbot_faqs?select=faq_id';

    $response = wp_remote_get($url, [
        'headers' => [
            'apikey' => $config['anon_key'],
            'Authorization' => 'Bearer ' . $config['anon_key'],
            'Prefer' => 'count=exact',
        ],
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        return 0;
    }

    // Get count from content-range header
    $content_range = wp_remote_retrieve_header($response, 'content-range');
    if ($content_range && preg_match('/\/(\d+)$/', $content_range, $matches)) {
        return (int) $matches[1];
    }

    // Fallback: count the results
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    return is_array($data) ? count($data) : 0;
}

/**
 * Get all FAQ entries as objects (for admin UI compatibility)
 *
 * @return array Array of FAQ objects
 */
function chatbot_faq_get_all() {
    $faqs = chatbot_faq_load();

    // Convert to objects for compatibility with existing UI
    return array_map(function($faq) {
        return (object) $faq;
    }, $faqs);
}

/**
 * Get single FAQ by ID from Supabase
 *
 * @param string $id FAQ ID
 * @return array|null FAQ data or null if not found
 */
function chatbot_faq_get_by_id($id) {
    $config = chatbot_vector_get_supabase_config();

    if (!$config || empty($config['anon_key'])) {
        return null;
    }

    $url = $config['url'] . '/rest/v1/chatbot_faqs?faq_id=eq.' . urlencode($id) . '&select=faq_id,question,answer,category,created_at';

    $response = wp_remote_get($url, [
        'headers' => [
            'apikey' => $config['anon_key'],
            'Authorization' => 'Bearer ' . $config['anon_key'],
        ],
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data) || empty($data)) {
        return null;
    }

    $faq = $data[0];
    return [
        'id' => $faq['faq_id'],
        'question' => $faq['question'],
        'answer' => $faq['answer'],
        'category' => $faq['category'] ?? '',
        'created_at' => $faq['created_at'] ?? ''
    ];
}

/**
 * Check for similar existing FAQs
 *
 * @param string $question The question to check
 * @param float $threshold Similarity threshold (default 0.85 = 85% similar)
 * @param string|null $exclude_id FAQ ID to exclude (for updates)
 * @return array|null Similar FAQ if found, null otherwise
 */
function chatbot_faq_find_similar($question, $threshold = 0.75, $exclude_id = null) {
    // Search for similar FAQs using question-only embeddings
    // This compares question vs question for more accurate duplicate detection
    // Get top 3 matches so user can see multiple potential duplicates
    $results = chatbot_vector_search_by_question($question, [
        'threshold' => $threshold,
        'limit' => 3,
        'return_scores' => true
    ]);

    if (!$results['success'] || empty($results['results'])) {
        return null;
    }

    // Load all FAQs once for number lookup
    $all_faqs = chatbot_faq_load();

    // Process matches
    $similar_faqs = [];
    foreach ($results['results'] as $match) {
        // Check if similarity meets threshold
        if (!isset($match['similarity']) || $match['similarity'] < $threshold) {
            continue;
        }

        // Exclude the FAQ being updated
        if ($exclude_id && isset($match['faq_id']) && $match['faq_id'] === $exclude_id) {
            continue;
        }

        // Find the FAQ number
        $faq_number = 0;
        foreach ($all_faqs as $index => $faq) {
            if (isset($faq['id']) && isset($match['faq_id']) && $faq['id'] === $match['faq_id']) {
                $faq_number = $index + 1;
                break;
            }
        }

        $similar_faqs[] = [
            'faq_id' => $match['faq_id'] ?? null,
            'faq_number' => $faq_number,
            'question' => $match['question'],
            'similarity' => $match['similarity'],
            'similarity_pct' => round($match['similarity'] * 100)
        ];
    }

    if (empty($similar_faqs)) {
        return null;
    }

    // Return first match for backward compatibility, but include all matches
    $result = $similar_faqs[0];
    $result['all_matches'] = $similar_faqs;
    return $result;
}

/**
 * Add new FAQ to Supabase with embedding
 * Includes duplicate detection - warns if similar FAQ exists
 *
 * @param string $question The question
 * @param string $answer The answer
 * @param string $category The category
 * @param bool $force_add Skip duplicate check if true
 * @return array Result with success status
 */
function chatbot_faq_add($question, $answer, $category = '', $force_add = false) {
    if (empty($question) || empty($answer)) {
        return ['success' => false, 'message' => 'Question and answer are required'];
    }

    $config = chatbot_vector_get_supabase_config();

    if (!$config || empty($config['anon_key'])) {
        return ['success' => false, 'message' => 'Supabase not configured'];
    }

    // Check for similar existing FAQ (unless forced)
    // Using 75% threshold with question-only embeddings for accurate duplicate detection
    if (!$force_add) {
        error_log('[Chatbot FAQ] Checking for duplicates of: ' . substr($question, 0, 50));
        $similar = chatbot_faq_find_similar($question, 0.75); // 75% threshold (question vs question)
        if ($similar) {
            error_log('[Chatbot FAQ] Found ' . count($similar['all_matches']) . ' similar FAQs');

            // Build message showing all matches
            $message_parts = ["Similar FAQs found:\n"];
            foreach ($similar['all_matches'] as $match) {
                $faq_ref = $match['faq_number'] > 0 ? '#' . $match['faq_number'] : '';
                $message_parts[] = '• FAQ ' . $faq_ref . ' (' . $match['similarity_pct'] . '%): "' . substr($match['question'], 0, 60) . '..."';
            }

            return [
                'success' => false,
                'duplicate' => true,
                'message' => implode("\n", $message_parts),
                'similar_faq' => $similar
            ];
        } else {
            error_log('[Chatbot FAQ] No duplicate found above 75% threshold');
        }
    }

    // Generate FAQ ID
    $faq_id = 'cc' . uniqid();

    // Generate embedding for question only (for duplicate detection)
    $question_embedding = chatbot_vector_generate_embedding($question);

    if (!$question_embedding) {
        return ['success' => false, 'message' => 'Failed to generate question embedding. Check API configuration.'];
    }

    // Generate embedding for the combined question + answer (for search)
    $combined_text = $question . ' ' . $answer;
    $combined_embedding = chatbot_vector_generate_embedding($combined_text);

    if (!$combined_embedding) {
        return ['success' => false, 'message' => 'Failed to generate combined embedding. Check API configuration.'];
    }

    // Prepare FAQ data with both embeddings
    $faq_data = [
        'faq_id' => $faq_id,
        'question' => $question,
        'answer' => $answer,
        'category' => $category,
        'question_embedding' => $question_embedding,
        'combined_embedding' => $combined_embedding
    ];

    // Insert via REST API
    $url = $config['url'] . '/rest/v1/chatbot_faqs';

    $response = wp_remote_post($url, [
        'headers' => [
            'apikey' => $config['anon_key'],
            'Authorization' => 'Bearer ' . $config['anon_key'],
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
        ],
        'body' => json_encode($faq_data),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        return ['success' => false, 'message' => 'API request failed: ' . $response->get_error_message()];
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($status >= 400) {
        $error = json_decode($body, true);
        $message = isset($error['message']) ? $error['message'] : 'Unknown error';
        return ['success' => false, 'message' => 'Failed to add FAQ: ' . $message];
    }

    return ['success' => true, 'message' => 'FAQ added successfully', 'faq_id' => $faq_id];
}

/**
 * Update existing FAQ in Supabase
 *
 * @param string $id FAQ ID
 * @param string $question The question
 * @param string $answer The answer
 * @param string $category The category
 * @return array Result with success status
 */
function chatbot_faq_update($id, $question, $answer, $category = '') {
    if (empty($question) || empty($answer)) {
        return ['success' => false, 'message' => 'Question and answer are required'];
    }

    $config = chatbot_vector_get_supabase_config();

    if (!$config || empty($config['anon_key'])) {
        return ['success' => false, 'message' => 'Supabase not configured'];
    }

    // Generate new embedding for the updated content
    $combined_text = $question . ' ' . $answer;
    $embedding = chatbot_vector_generate_embedding($combined_text);

    if (!$embedding) {
        return ['success' => false, 'message' => 'Failed to generate embedding. Check API configuration.'];
    }

    // Prepare update data
    $update_data = [
        'question' => $question,
        'answer' => $answer,
        'category' => $category,
        'combined_embedding' => $embedding,
        'updated_at' => gmdate('Y-m-d\TH:i:s\Z')
    ];

    // Update via REST API
    $url = $config['url'] . '/rest/v1/chatbot_faqs?faq_id=eq.' . urlencode($id);

    $response = wp_remote_request($url, [
        'method' => 'PATCH',
        'headers' => [
            'apikey' => $config['anon_key'],
            'Authorization' => 'Bearer ' . $config['anon_key'],
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
        ],
        'body' => json_encode($update_data),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        return ['success' => false, 'message' => 'API request failed: ' . $response->get_error_message()];
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($status >= 400) {
        $error = json_decode($body, true);
        $message = isset($error['message']) ? $error['message'] : 'Unknown error';
        return ['success' => false, 'message' => 'Failed to update FAQ: ' . $message];
    }

    return ['success' => true, 'message' => 'FAQ updated successfully'];
}

/**
 * Delete FAQ from Supabase
 *
 * @param string $id FAQ ID to delete
 * @return bool True on success
 */
function chatbot_faq_delete($id) {
    $config = chatbot_vector_get_supabase_config();

    if (!$config || empty($config['anon_key'])) {
        return false;
    }

    $url = $config['url'] . '/rest/v1/chatbot_faqs?faq_id=eq.' . urlencode($id);

    $response = wp_remote_request($url, [
        'method' => 'DELETE',
        'headers' => [
            'apikey' => $config['anon_key'],
            'Authorization' => 'Bearer ' . $config['anon_key'],
        ],
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        error_log('[Chatbot FAQ] Delete failed: ' . $response->get_error_message());
        return false;
    }

    $status = wp_remote_retrieve_response_code($response);
    return $status < 400;
}

/**
 * Get top N categories by FAQ count
 *
 * @param int $limit Maximum categories to return
 * @return array Categories with counts
 */
function chatbot_faq_get_top_categories($limit = 4) {
    $faqs = chatbot_faq_load();

    if (empty($faqs)) {
        return [];
    }

    // Count FAQs per category
    $category_counts = [];
    foreach ($faqs as $faq) {
        $category = !empty($faq['category']) ? $faq['category'] : 'General';
        if (!isset($category_counts[$category])) {
            $category_counts[$category] = 0;
        }
        $category_counts[$category]++;
    }

    // Sort by count descending
    arsort($category_counts);

    // Get top N categories
    $top_categories = array_slice($category_counts, 0, $limit, true);

    // Format as array with name and count
    $result = [];
    foreach ($top_categories as $name => $count) {
        $result[] = [
            'name' => $name,
            'count' => $count
        ];
    }

    return $result;
}

/**
 * Get top N questions for a specific category
 *
 * @param string $category Category name
 * @param int $limit Maximum questions to return
 * @return array Questions in category
 */
function chatbot_faq_get_category_questions($category, $limit = 3) {
    $faqs = chatbot_faq_load();

    if (empty($faqs)) {
        return [];
    }

    // Filter by category
    $category_faqs = array_filter($faqs, function($faq) use ($category) {
        $faq_category = !empty($faq['category']) ? $faq['category'] : 'General';
        return $faq_category === $category;
    });

    // Get top N questions
    $category_faqs = array_slice($category_faqs, 0, $limit);

    // Return just question and answer
    $result = [];
    foreach ($category_faqs as $faq) {
        $result[] = [
            'question' => $faq['question'],
            'answer' => $faq['answer']
        ];
    }

    return $result;
}

/**
 * Get category buttons data for frontend
 *
 * @return array Category buttons with questions
 */
function chatbot_faq_get_buttons_data() {
    $categories = chatbot_faq_get_top_categories(4);

    $buttons_data = [];
    foreach ($categories as $category) {
        $questions = chatbot_faq_get_category_questions($category['name'], 3);
        $buttons_data[] = [
            'name' => $category['name'],
            'count' => $category['count'],
            'questions' => $questions
        ];
    }

    return $buttons_data;
}

/**
 * Generate keywords from question text (kept for compatibility)
 *
 * @param string $text Text to extract keywords from
 * @return string Space-separated keywords
 */
function chatbot_faq_generate_keywords($text) {
    // Convert to lowercase
    $text = strtolower($text);

    // Remove punctuation
    $text = preg_replace('/[^\w\s]/', '', $text);

    // Split into words
    $words = preg_split('/\s+/', $text);

    // Remove common stop words
    $stop_words = ['a', 'an', 'the', 'is', 'are', 'was', 'were', 'what', 'how', 'why',
                   'when', 'where', 'who', 'which', 'do', 'does', 'did', 'can', 'could',
                   'would', 'should', 'i', 'you', 'your', 'my', 'me', 'we', 'they', 'it',
                   'to', 'for', 'of', 'in', 'on', 'at', 'by', 'with', 'and', 'or', 'but'];

    $keywords = array_diff($words, $stop_words);
    $keywords = array_filter($keywords, function($word) {
        return strlen($word) > 2;
    });

    return implode(' ', $keywords);
}

// ============================================
// AJAX Handlers for Admin UI
// ============================================

/**
 * AJAX: Add FAQ
 */
function chatbot_faq_ajax_add() {
    check_ajax_referer('chatbot_faq_manage', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $question = sanitize_textarea_field($_POST['question'] ?? '');
    $answer = sanitize_textarea_field($_POST['answer'] ?? '');
    $category = sanitize_text_field($_POST['category'] ?? '');
    $force_add = isset($_POST['force_add']) && $_POST['force_add'] === '1';

    $result = chatbot_faq_add($question, $answer, $category, $force_add);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        // Return duplicate info for confirmation dialog
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_chatbot_faq_add', 'chatbot_faq_ajax_add');

/**
 * AJAX: Update FAQ
 */
function chatbot_faq_ajax_update() {
    check_ajax_referer('chatbot_faq_manage', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $id = sanitize_text_field($_POST['id'] ?? '');
    $question = sanitize_textarea_field($_POST['question'] ?? '');
    $answer = sanitize_textarea_field($_POST['answer'] ?? '');
    $category = sanitize_text_field($_POST['category'] ?? '');

    $result = chatbot_faq_update($id, $question, $answer, $category);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_chatbot_faq_update', 'chatbot_faq_ajax_update');

/**
 * AJAX: Delete FAQ
 */
function chatbot_faq_ajax_delete() {
    check_ajax_referer('chatbot_faq_manage', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $id = sanitize_text_field($_POST['id'] ?? '');

    if (chatbot_faq_delete($id)) {
        wp_send_json_success(['message' => 'FAQ deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete FAQ']);
    }
}
add_action('wp_ajax_chatbot_faq_delete', 'chatbot_faq_ajax_delete');

/**
 * AJAX: Get FAQ by ID
 */
function chatbot_faq_ajax_get() {
    check_ajax_referer('chatbot_faq_manage', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $id = sanitize_text_field($_POST['id'] ?? '');
    $faq = chatbot_faq_get_by_id($id);

    if ($faq) {
        wp_send_json_success(['faq' => $faq]);
    } else {
        wp_send_json_error(['message' => 'FAQ not found']);
    }
}
add_action('wp_ajax_chatbot_faq_get', 'chatbot_faq_ajax_get');
