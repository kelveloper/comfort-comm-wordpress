<?php
/**
 * Chatbot Vector Search - Migration Script
 *
 * Converts JSON FAQ data to vector embeddings using OpenAI's
 * text-embedding-3-small model and populates the PostgreSQL database.
 *
 * @package comfort-comm-chatbot
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die();
}

// Include the schema file
require_once plugin_dir_path(__FILE__) . 'chatbot-vector-schema.php';

/**
 * Generate embedding using OpenAI API
 *
 * @param string $text Text to generate embedding for
 * @param string $model Model to use (default: text-embedding-3-small)
 * @return array|null Embedding vector (1536 dimensions) or null on failure
 */
function chatbot_vector_generate_embedding($text, $model = 'text-embedding-004') {
    // Try Gemini API first (since user is using Gemini for chatbot)
    $api_key = get_option('chatbot_gemini_api_key', '');
    $use_gemini = !empty($api_key);

    // Fall back to OpenAI if no Gemini key
    if (empty($api_key)) {
        $api_key = get_option('chatbot_chatgpt_api_key', '');
        $use_gemini = false;
    }

    if (empty($api_key)) {
        error_log('[Chatbot Vector] No API key configured (tried Gemini and OpenAI)');
        return null;
    }

    // Decrypt the API key if it's encrypted (contains iv and encrypted fields)
    if (function_exists('chatbot_chatgpt_decrypt_api_key')) {
        $decrypted = chatbot_chatgpt_decrypt_api_key($api_key);
        if (!empty($decrypted)) {
            $api_key = $decrypted;
        }
    }

    // Clean and prepare text
    $text = trim($text);
    if (empty($text)) {
        return null;
    }

    // Truncate if too long
    if (strlen($text) > 30000) {
        $text = substr($text, 0, 30000);
    }

    if ($use_gemini) {
        return chatbot_vector_generate_embedding_gemini($text, $api_key, $model);
    } else {
        return chatbot_vector_generate_embedding_openai($text, $api_key, 'text-embedding-3-small');
    }
}

/**
 * Generate embedding using Google Gemini API
 */
function chatbot_vector_generate_embedding_gemini($text, $api_key, $model = 'text-embedding-004') {
    // Note: Gemini embedding model produces 768 dimensions by default
    // We'll need to update schema or pad to 1536 dimensions
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':embedContent?key=' . $api_key;

    $body = json_encode([
        'model' => 'models/' . $model,
        'content' => [
            'parts' => [
                ['text' => $text]
            ]
        ]
    ]);

    $response = wp_remote_post($url, [
        'timeout' => 30,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => $body
    ]);

    if (is_wp_error($response)) {
        error_log('[Chatbot Vector] Gemini API request failed: ' . $response->get_error_message());
        return null;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status_code !== 200) {
        $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
        error_log('[Chatbot Vector] Gemini API error: ' . $error_msg);
        return null;
    }

    if (!isset($data['embedding']['values'])) {
        error_log('[Chatbot Vector] No embedding in Gemini response');
        return null;
    }

    $embedding = $data['embedding']['values'];

    // Gemini text-embedding-004 produces 768 dimensions
    // Pad to 1536 dimensions for consistency with our schema
    // (or we could resize the schema, but padding is simpler)
    while (count($embedding) < 1536) {
        $embedding[] = 0.0;
    }

    return $embedding;
}

/**
 * Generate embedding using OpenAI API
 */
function chatbot_vector_generate_embedding_openai($text, $api_key, $model = 'text-embedding-3-small') {
    $url = 'https://api.openai.com/v1/embeddings';

    $body = json_encode([
        'model' => $model,
        'input' => $text,
        'encoding_format' => 'float'
    ]);

    $response = wp_remote_post($url, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => $body
    ]);

    if (is_wp_error($response)) {
        error_log('[Chatbot Vector] OpenAI API request failed: ' . $response->get_error_message());
        return null;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status_code !== 200) {
        $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
        error_log('[Chatbot Vector] OpenAI API error: ' . $error_msg);
        return null;
    }

    if (!isset($data['data'][0]['embedding'])) {
        error_log('[Chatbot Vector] No embedding in response');
        return null;
    }

    return $data['data'][0]['embedding'];
}

/**
 * Convert embedding array to PostgreSQL vector format
 *
 * @param array $embedding Array of floats
 * @return string PostgreSQL vector string format
 */
function chatbot_vector_to_pg_format($embedding) {
    if (!is_array($embedding)) {
        return null;
    }

    return '[' . implode(',', $embedding) . ']';
}

/**
 * Insert or update a single FAQ with embeddings
 *
 * @param array $faq FAQ data array
 * @param bool $generate_embeddings Whether to generate new embeddings
 * @return array Result with success status
 */
function chatbot_vector_upsert_faq($faq, $generate_embeddings = true) {
    $pdo = chatbot_vector_get_pg_connection();

    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    $faq_id = $faq['id'] ?? uniqid();
    $question = $faq['question'] ?? '';
    $answer = $faq['answer'] ?? '';
    $category = $faq['category'] ?? '';
    $keywords = $faq['keywords'] ?? '';

    if (empty($question) || empty($answer)) {
        return ['success' => false, 'message' => 'Question and answer are required'];
    }

    $question_embedding = null;
    $answer_embedding = null;
    $combined_embedding = null;

    if ($generate_embeddings) {
        // Generate embedding for question
        $question_embedding = chatbot_vector_generate_embedding($question);

        // Generate embedding for answer
        $answer_embedding = chatbot_vector_generate_embedding($answer);

        // Generate combined embedding (question + answer for better semantic matching)
        $combined_text = $question . ' ' . $answer;
        $combined_embedding = chatbot_vector_generate_embedding($combined_text);

        if (!$combined_embedding) {
            return ['success' => false, 'message' => 'Failed to generate embeddings'];
        }
    }

    try {
        // Check if FAQ exists
        $stmt = $pdo->prepare('SELECT id FROM chatbot_faqs WHERE faq_id = ?');
        $stmt->execute([$faq_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing FAQ
            if ($generate_embeddings) {
                $stmt = $pdo->prepare('
                    UPDATE chatbot_faqs SET
                        question = ?,
                        answer = ?,
                        category = ?,
                        keywords = ?,
                        question_embedding = ?::vector,
                        answer_embedding = ?::vector,
                        combined_embedding = ?::vector,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE faq_id = ?
                ');
                $stmt->execute([
                    $question,
                    $answer,
                    $category,
                    $keywords,
                    chatbot_vector_to_pg_format($question_embedding),
                    chatbot_vector_to_pg_format($answer_embedding),
                    chatbot_vector_to_pg_format($combined_embedding),
                    $faq_id
                ]);
            } else {
                $stmt = $pdo->prepare('
                    UPDATE chatbot_faqs SET
                        question = ?,
                        answer = ?,
                        category = ?,
                        keywords = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE faq_id = ?
                ');
                $stmt->execute([$question, $answer, $category, $keywords, $faq_id]);
            }
        } else {
            // Insert new FAQ
            if ($generate_embeddings) {
                $stmt = $pdo->prepare('
                    INSERT INTO chatbot_faqs
                    (faq_id, question, answer, category, keywords, question_embedding, answer_embedding, combined_embedding)
                    VALUES (?, ?, ?, ?, ?, ?::vector, ?::vector, ?::vector)
                ');
                $stmt->execute([
                    $faq_id,
                    $question,
                    $answer,
                    $category,
                    $keywords,
                    chatbot_vector_to_pg_format($question_embedding),
                    chatbot_vector_to_pg_format($answer_embedding),
                    chatbot_vector_to_pg_format($combined_embedding)
                ]);
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO chatbot_faqs (faq_id, question, answer, category, keywords)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $stmt->execute([$faq_id, $question, $answer, $category, $keywords]);
            }
        }

        return ['success' => true, 'faq_id' => $faq_id];

    } catch (PDOException $e) {
        error_log('[Chatbot Vector] Upsert failed: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Migrate all FAQs from JSON file to vector database
 *
 * @param bool $clear_existing Whether to clear existing entries first
 * @return array Migration result with stats
 */
function chatbot_vector_migrate_all_faqs($clear_existing = false) {
    $pdo = chatbot_vector_get_pg_connection();

    if (!$pdo) {
        return [
            'success' => false,
            'message' => 'Database connection failed'
        ];
    }

    // Initialize schema if needed
    $schema_result = chatbot_vector_init_schema();
    if (!$schema_result['success']) {
        return $schema_result;
    }

    // Load FAQs from JSON file
    $faqs = chatbot_faq_load();

    if (empty($faqs)) {
        return [
            'success' => false,
            'message' => 'No FAQs found in JSON file'
        ];
    }

    // Clear existing entries if requested
    if ($clear_existing) {
        try {
            $pdo->exec('TRUNCATE TABLE chatbot_faqs RESTART IDENTITY');
        } catch (PDOException $e) {
            error_log('[Chatbot Vector] Failed to clear table: ' . $e->getMessage());
        }
    }

    $total = count($faqs);
    $success_count = 0;
    $error_count = 0;
    $errors = [];

    error_log("[Chatbot Vector] Starting migration of {$total} FAQs...");

    foreach ($faqs as $index => $faq) {
        $result = chatbot_vector_upsert_faq($faq, true);

        if ($result['success']) {
            $success_count++;
            error_log("[Chatbot Vector] Migrated FAQ " . ($index + 1) . "/{$total}: " . substr($faq['question'], 0, 50));
        } else {
            $error_count++;
            $errors[] = [
                'faq_id' => $faq['id'] ?? 'unknown',
                'question' => substr($faq['question'], 0, 50),
                'error' => $result['message']
            ];
            error_log("[Chatbot Vector] Failed to migrate FAQ: " . $result['message']);
        }

        // Add a small delay to avoid rate limiting
        usleep(100000); // 100ms delay
    }

    // Create search index after migration
    if ($success_count > 0) {
        // For 66 FAQs, use lists = 10
        $lists = max(1, (int) sqrt($success_count));
        chatbot_vector_create_search_index($lists);
    }

    $message = "Migration complete: {$success_count}/{$total} FAQs migrated successfully.";
    if ($error_count > 0) {
        $message .= " {$error_count} errors occurred.";
    }

    error_log("[Chatbot Vector] " . $message);

    return [
        'success' => $error_count === 0,
        'message' => $message,
        'total' => $total,
        'migrated' => $success_count,
        'errors' => $error_count,
        'error_details' => $errors
    ];
}

/**
 * Add a single FAQ with embedding (legacy function - use chatbot_faq_add instead)
 *
 * @param string $question The FAQ question
 * @param string $answer The FAQ answer
 * @param string $category The category
 * @return array Result with success status
 * @deprecated Use chatbot_faq_add() from chatbot-vector-faq-crud.php instead
 */
function chatbot_vector_add_faq($question, $answer, $category = '') {
    // This function is deprecated - use chatbot_faq_add() instead
    // Keeping for backwards compatibility only
    if (function_exists('chatbot_faq_add')) {
        return chatbot_faq_add($question, $answer, $category);
    }

    // Fallback to PDO method if CRUD functions not loaded
    $faq = [
        'id' => uniqid('cc'),
        'question' => $question,
        'answer' => $answer,
        'category' => $category,
    ];

    return chatbot_vector_upsert_faq($faq, true);
}

/**
 * Update embeddings for a single FAQ
 *
 * @param string $faq_id The FAQ ID to update
 * @return array Result with success status
 */
function chatbot_vector_update_embedding($faq_id) {
    $pdo = chatbot_vector_get_pg_connection();

    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        // Get current FAQ data
        $stmt = $pdo->prepare('SELECT * FROM chatbot_faqs WHERE faq_id = ?');
        $stmt->execute([$faq_id]);
        $faq = $stmt->fetch();

        if (!$faq) {
            return ['success' => false, 'message' => 'FAQ not found'];
        }

        // Regenerate embeddings
        $question_embedding = chatbot_vector_generate_embedding($faq['question']);
        $answer_embedding = chatbot_vector_generate_embedding($faq['answer']);
        $combined_embedding = chatbot_vector_generate_embedding($faq['question'] . ' ' . $faq['answer']);

        if (!$combined_embedding) {
            return ['success' => false, 'message' => 'Failed to generate embeddings'];
        }

        // Update embeddings
        $stmt = $pdo->prepare('
            UPDATE chatbot_faqs SET
                question_embedding = ?::vector,
                answer_embedding = ?::vector,
                combined_embedding = ?::vector,
                updated_at = CURRENT_TIMESTAMP
            WHERE faq_id = ?
        ');
        $stmt->execute([
            chatbot_vector_to_pg_format($question_embedding),
            chatbot_vector_to_pg_format($answer_embedding),
            chatbot_vector_to_pg_format($combined_embedding),
            $faq_id
        ]);

        return ['success' => true, 'message' => 'Embedding updated successfully'];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * AJAX handler for running migration
 */
function chatbot_vector_ajax_migrate() {
    check_ajax_referer('chatbot_vector_migrate', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $clear_existing = isset($_POST['clear_existing']) && $_POST['clear_existing'] === '1';

    // This can take a while, increase time limit
    set_time_limit(300); // 5 minutes

    $result = chatbot_vector_migrate_all_faqs($clear_existing);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_chatbot_vector_migrate', 'chatbot_vector_ajax_migrate');

/**
 * AJAX handler for checking migration status
 */
function chatbot_vector_ajax_status() {
    check_ajax_referer('chatbot_vector_status', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $stats = chatbot_vector_get_stats();
    wp_send_json_success($stats);
}
add_action('wp_ajax_chatbot_vector_status', 'chatbot_vector_ajax_status');
