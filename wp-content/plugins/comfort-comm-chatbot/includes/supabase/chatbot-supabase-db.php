<?php
/**
 * Supabase Database Operations
 *
 * Replaces WordPress $wpdb calls with Supabase REST API
 * for conversation logging, interactions, and gap questions.
 *
 * @package chatbot-chatgpt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die();
}

// Ver 2.5.0: Include vector files for PDO connection and embedding generation
$vector_schema_path = plugin_dir_path(__FILE__) . '../vector-search/chatbot-vector-schema.php';
if (file_exists($vector_schema_path)) {
    require_once $vector_schema_path;
}

// Ver 2.5.0: Include migration file for embedding generation function (works via HTTP API even without PDO)
$vector_migration_path = plugin_dir_path(__FILE__) . '../vector-search/chatbot-vector-migration.php';
if (file_exists($vector_migration_path)) {
    require_once $vector_migration_path;
}

/**
 * Check if Supabase is configured
 */
function chatbot_supabase_is_configured() {
    // Check admin settings first, then wp-config.php
    if (function_exists('chatbot_supabase_get_config')) {
        $config = chatbot_supabase_get_config();
        return !empty($config['anon_key']);
    }
    // Fallback to wp-config.php constant
    return defined('CHATBOT_SUPABASE_ANON_KEY') && !empty(CHATBOT_SUPABASE_ANON_KEY);
}

/**
 * Get Supabase anon key from settings or wp-config
 */
function chatbot_supabase_get_anon_key() {
    if (function_exists('chatbot_supabase_get_config')) {
        $config = chatbot_supabase_get_config();
        if (!empty($config['anon_key'])) {
            return $config['anon_key'];
        }
    }
    // Fallback to wp-config.php constant
    return defined('CHATBOT_SUPABASE_ANON_KEY') ? CHATBOT_SUPABASE_ANON_KEY : '';
}

/**
 * Get Supabase REST API base URL
 */
function chatbot_supabase_get_url() {
    // Check admin settings first
    if (function_exists('chatbot_supabase_get_config')) {
        $config = chatbot_supabase_get_config();
        if (!empty($config['project_url'])) {
            return rtrim($config['project_url'], '/') . '/rest/v1';
        }
    }

    // Fallback to wp-config.php constant
    if (defined('CHATBOT_PG_HOST')) {
        // Extract project ref from host (db.xxxxx.supabase.co -> xxxxx)
        $host = CHATBOT_PG_HOST;
        if (preg_match('/db\.([^.]+)\.supabase\.co/', $host, $matches)) {
            return 'https://' . $matches[1] . '.supabase.co/rest/v1';
        }
        // Also handle without db. prefix
        if (preg_match('/([^.]+)\.supabase\.co/', $host, $matches)) {
            return 'https://' . $matches[1] . '.supabase.co/rest/v1';
        }
    }
    return null;
}

/**
 * Make Supabase REST API request
 */
function chatbot_supabase_request($endpoint, $method = 'GET', $data = null, $query_params = []) {
    $base_url = chatbot_supabase_get_url();
    $anon_key = chatbot_supabase_get_anon_key();

    if (!$base_url || empty($anon_key)) {
        return ['success' => false, 'error' => 'Supabase not configured'];
    }

    $url = $base_url . '/' . $endpoint;

    // Add query parameters
    if (!empty($query_params)) {
        $url .= '?' . http_build_query($query_params);
    }

    $headers = [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    switch ($method) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'PATCH':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('[Chatbot Supabase] cURL error: ' . $error);
        return ['success' => false, 'error' => $error];
    }

    $decoded = json_decode($response, true);

    if ($http_code >= 200 && $http_code < 300) {
        return ['success' => true, 'data' => $decoded, 'http_code' => $http_code];
    }

    error_log('[Chatbot Supabase] API error: ' . $response);
    return ['success' => false, 'error' => $decoded['message'] ?? 'Unknown error', 'http_code' => $http_code];
}

// =============================================================================
// CONVERSATION LOGGING (replaces wp_chatbot_chatgpt_conversation_log)
// =============================================================================

/**
 * Append message to conversation log
 */
function chatbot_supabase_log_conversation($session_id, $user_id, $page_id, $user_type, $thread_id, $assistant_id, $assistant_name, $message, $sentiment_score = null) {
    $data = [
        'session_id' => $session_id,
        'user_id' => (string)$user_id,
        'page_id' => (string)$page_id,
        'user_type' => $user_type,
        'thread_id' => $thread_id,
        'assistant_id' => $assistant_id,
        'assistant_name' => $assistant_name,
        'message_text' => $message,
        'interaction_time' => gmdate('c') // ISO 8601 format
    ];

    if ($sentiment_score !== null) {
        $data['sentiment_score'] = $sentiment_score;
    }

    $result = chatbot_supabase_request('chatbot_conversations', 'POST', $data);

    if (!$result['success']) {
        error_log('[Chatbot Supabase] Failed to log conversation: ' . ($result['error'] ?? 'Unknown'));
    }

    return $result['success'];
}

/**
 * Get conversations by session ID
 */
function chatbot_supabase_get_conversations($session_id, $limit = 100) {
    $query_params = [
        'session_id' => 'eq.' . $session_id,
        'order' => 'interaction_time.asc',
        'limit' => $limit
    ];

    $result = chatbot_supabase_request('chatbot_conversations', 'GET', null, $query_params);

    if ($result['success']) {
        return $result['data'];
    }

    return [];
}

/**
 * Get conversations for a specific user (for conversation history shortcode)
 */
function chatbot_supabase_get_user_conversations($user_id, $limit = 1000) {
    $query_params = [
        'user_id' => 'eq.' . $user_id,
        'user_type' => 'in.(Chatbot,Visitor)',
        'order' => 'interaction_time.desc',
        'limit' => $limit
    ];

    $result = chatbot_supabase_request('chatbot_conversations', 'GET', null, $query_params);

    if ($result['success'] && !empty($result['data'])) {
        // Convert to objects for compatibility with existing code
        return array_map(function($c) {
            return (object)array(
                'message_text' => $c['message_text'] ?? '',
                'user_type' => $c['user_type'] ?? '',
                'thread_id' => $c['thread_id'] ?? '',
                'interaction_time' => $c['interaction_time'] ?? '',
                'assistant_id' => $c['assistant_id'] ?? '',
                'assistant_name' => $c['assistant_name'] ?? '',
                'interaction_date' => isset($c['interaction_time']) ? substr($c['interaction_time'], 0, 10) : date('Y-m-d')
            );
        }, $result['data']);
    }

    return [];
}

/**
 * Get recent conversations (for reporting)
 */
function chatbot_supabase_get_recent_conversations($days = 30, $limit = 1000) {
    $since = gmdate('c', strtotime("-{$days} days"));

    $query_params = [
        'interaction_time' => 'gte.' . $since,
        'order' => 'interaction_time.desc',
        'limit' => $limit
    ];

    $result = chatbot_supabase_request('chatbot_conversations', 'GET', null, $query_params);

    if ($result['success']) {
        return $result['data'];
    }

    return [];
}

/**
 * Delete conversations older than X days
 */
function chatbot_supabase_delete_old_conversations($days) {
    $cutoff = gmdate('c', strtotime("-{$days} days"));

    $query_params = [
        'interaction_time' => 'lt.' . $cutoff
    ];

    $result = chatbot_supabase_request('chatbot_conversations', 'DELETE', null, $query_params);

    return $result['success'];
}

/**
 * Get conversation count by date range
 */
function chatbot_supabase_get_conversation_stats($start_date, $end_date) {
    $query_params = [
        'interaction_time' => 'gte.' . $start_date,
        'and' => '(interaction_time.lte.' . $end_date . ')',
        'select' => 'id'
    ];

    // Use Prefer header for count
    $base_url = chatbot_supabase_get_url();
    $anon_key = chatbot_supabase_get_anon_key();
    $url = $base_url . '/chatbot_conversations?' . http_build_query($query_params);

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
    curl_close($ch);

    // Extract count from Content-Range header
    if (preg_match('/content-range: \d+-\d+\/(\d+)/i', $headers_str, $matches)) {
        return (int)$matches[1];
    }

    return 0;
}

// =============================================================================
// INTERACTION TRACKING (replaces wp_chatbot_chatgpt_interactions)
// =============================================================================

/**
 * Update daily interaction count
 */
function chatbot_supabase_update_interaction_count() {
    $today = gmdate('Y-m-d');

    // First, try to get existing record
    $query_params = ['date' => 'eq.' . $today];
    $result = chatbot_supabase_request('chatbot_interactions', 'GET', null, $query_params);

    if ($result['success'] && !empty($result['data'])) {
        // Update existing record
        $current_count = $result['data'][0]['count'];
        $data = ['count' => $current_count + 1];

        return chatbot_supabase_request('chatbot_interactions', 'PATCH', $data, $query_params);
    } else {
        // Insert new record
        $data = [
            'date' => $today,
            'count' => 1
        ];

        return chatbot_supabase_request('chatbot_interactions', 'POST', $data);
    }
}

/**
 * Get interaction counts for date range
 */
function chatbot_supabase_get_interaction_counts($start_date, $end_date) {
    $query_params = [
        'date' => 'gte.' . $start_date,
        'and' => '(date.lte.' . $end_date . ')',
        'order' => 'date.asc'
    ];

    $result = chatbot_supabase_request('chatbot_interactions', 'GET', null, $query_params);

    if ($result['success']) {
        return $result['data'];
    }

    return [];
}

/**
 * Get total interactions for a period
 */
function chatbot_supabase_get_total_interactions($days = 30) {
    $start_date = gmdate('Y-m-d', strtotime("-{$days} days"));
    $end_date = gmdate('Y-m-d');

    $counts = chatbot_supabase_get_interaction_counts($start_date, $end_date);

    $total = 0;
    foreach ($counts as $row) {
        $total += $row['count'];
    }

    return $total;
}

// =============================================================================
// GAP QUESTIONS (replaces wp_chatbot_gap_questions)
// =============================================================================

/**
 * Log a gap question (unanswered or low confidence)
 * Now includes vector embedding for semantic clustering
 * Updated Ver 2.4.8: Includes quality_score and validation_flags
 * Updated Ver 2.5.0: Added conversation_context for follow-up questions
 *
 * @param string $question_text The question asked
 * @param string $session_id Session ID
 * @param int $user_id User ID
 * @param int $page_id Page ID
 * @param float $faq_confidence Confidence score
 * @param string|null $faq_match_id Matched FAQ ID
 * @param array|null $quality_data Quality score and flags
 * @param string|null $conversation_context Previous Q&A context for follow-ups
 */
function chatbot_supabase_log_gap_question($question_text, $session_id, $user_id, $page_id, $faq_confidence, $faq_match_id = null, $quality_data = null, $conversation_context = null) {
    // Get quality data if validator is available and not already provided
    if ($quality_data === null && function_exists('chatbot_validate_gap_question')) {
        $validation = chatbot_validate_gap_question($question_text, $faq_confidence);
        $quality_data = [
            'quality_score' => $validation['quality_score'] ?? null,
            'validation_flags' => $validation['flags'] ?? []
        ];
    }

    // Try PDO first for proper vector handling (required for embeddings)
    $pdo_function_exists = function_exists('chatbot_vector_get_pg_connection');
    $pdo = $pdo_function_exists ? chatbot_vector_get_pg_connection() : null;

    // Ver 2.5.0: Debug why PDO might be failing
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Chatbot Gap] PDO check: function_exists=' . ($pdo_function_exists ? 'yes' : 'no') . ', pdo=' . ($pdo ? 'connected' : 'null'));
    }

    if ($pdo) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Chatbot Gap] Using PDO path (with embedding support)');
        }
        return chatbot_supabase_log_gap_question_pdo($pdo, $question_text, $session_id, $user_id, $page_id, $faq_confidence, $faq_match_id, $quality_data, $conversation_context);
    }

    // Fallback to REST API - PDO connection failed but we can still generate embeddings via HTTP API
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Chatbot Gap] Using REST API fallback - PDO not available, will generate embedding via HTTP API');
    }

    // Ver 2.5.0: Generate embedding even in REST path (uses wp_remote_post which works without PDO)
    $embedding = null;
    $embedding_str = null;
    if (function_exists('chatbot_vector_generate_embedding')) {
        $embedding = chatbot_vector_generate_embedding($question_text);
        if ($embedding && function_exists('chatbot_vector_to_pg_format')) {
            $embedding_str = chatbot_vector_to_pg_format($embedding);
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($embedding) {
                error_log('[Chatbot Gap] REST path: Embedding generated successfully (' . count($embedding) . ' dimensions)');
            } else {
                error_log('[Chatbot Gap] REST path: WARNING - Embedding generation failed');
            }
        }
    }

    $data = [
        'question_text' => $question_text,
        'session_id' => $session_id,
        'user_id' => (int)$user_id,
        'page_id' => (int)$page_id,
        'faq_confidence' => $faq_confidence,
        'faq_match_id' => $faq_match_id,
        'asked_date' => gmdate('c'),
        'is_clustered' => false,
        'is_resolved' => false
    ];

    // Add embedding if generated (Ver 2.5.0)
    if ($embedding_str) {
        $data['embedding'] = $embedding_str;
    }

    // Add conversation context if available (Ver 2.5.0)
    if (!empty($conversation_context)) {
        $data['conversation_context'] = $conversation_context;
    }

    // Add quality data if available
    if ($quality_data) {
        if (isset($quality_data['quality_score'])) {
            $data['quality_score'] = $quality_data['quality_score'];
        }
        if (!empty($quality_data['validation_flags'])) {
            $data['validation_flags'] = json_encode($quality_data['validation_flags']);
        }
        // Ver 2.5.0: Add relevance_score
        if (isset($quality_data['relevance_score'])) {
            $data['relevance_score'] = $quality_data['relevance_score'];
        }
    }

    $result = chatbot_supabase_request('chatbot_gap_questions', 'POST', $data);

    if (!isset($result['success']) || !$result['success']) {
        error_log('[Chatbot Supabase] Failed to log gap question: ' . ($result['error'] ?? 'Unknown'));
        return false;
    }

    return true;
}

/**
 * Log gap question using PDO (includes embedding for clustering)
 * Updated Ver 2.4.8: Includes quality_score and validation_flags
 * Updated Ver 2.5.0: Added conversation_context, relevance_score for follow-up questions
 */
function chatbot_supabase_log_gap_question_pdo($pdo, $question_text, $session_id, $user_id, $page_id, $faq_confidence, $faq_match_id = null, $quality_data = null, $conversation_context = null) {
    try {
        // Generate embedding for the question
        $embedding = null;
        if (function_exists('chatbot_vector_generate_embedding')) {
            $embedding = chatbot_vector_generate_embedding($question_text);
            // Ver 2.5.0: Debug logging for embedding generation
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if ($embedding) {
                    error_log('[Chatbot Gap] Embedding generated successfully for: "' . substr($question_text, 0, 50) . '..." (' . count($embedding) . ' dimensions)');
                } else {
                    error_log('[Chatbot Gap] WARNING: Embedding generation FAILED for: "' . substr($question_text, 0, 50) . '..."');
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Chatbot Gap] WARNING: chatbot_vector_generate_embedding function not available');
            }
        }

        // Prepare quality data - Ver 2.5.0: Added relevance_score
        $quality_score = isset($quality_data['quality_score']) ? $quality_data['quality_score'] : null;
        $validation_flags = !empty($quality_data['validation_flags']) ? json_encode($quality_data['validation_flags']) : null;
        $relevance_score = isset($quality_data['relevance_score']) ? $quality_data['relevance_score'] : null;

        if ($embedding) {
            // Insert with embedding and quality data (Ver 2.5.0: added conversation_context, relevance_score)
            $embedding_str = chatbot_vector_to_pg_format($embedding);
            $stmt = $pdo->prepare('
                INSERT INTO chatbot_gap_questions
                (question_text, session_id, user_id, page_id, faq_confidence, faq_match_id, asked_date, is_clustered, is_resolved, embedding, quality_score, relevance_score, validation_flags, conversation_context)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), false, false, ?::vector, ?, ?, ?::jsonb, ?)
            ');
            $stmt->execute([
                $question_text,
                $session_id,
                (int)$user_id,
                (int)$page_id,
                $faq_confidence,
                $faq_match_id,
                $embedding_str,
                $quality_score,
                $relevance_score,
                $validation_flags,
                $conversation_context
            ]);
        } else {
            // Insert without embedding but with quality data (Ver 2.5.0: added conversation_context, relevance_score)
            $stmt = $pdo->prepare('
                INSERT INTO chatbot_gap_questions
                (question_text, session_id, user_id, page_id, faq_confidence, faq_match_id, asked_date, is_clustered, is_resolved, quality_score, relevance_score, validation_flags, conversation_context)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), false, false, ?, ?, ?::jsonb, ?)
            ');
            $stmt->execute([
                $question_text,
                $session_id,
                (int)$user_id,
                (int)$page_id,
                $faq_confidence,
                $faq_match_id,
                $quality_score,
                $relevance_score,
                $validation_flags,
                $conversation_context
            ]);
        }

        // Ver 2.5.0: Log success with details
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Chatbot Gap] SUCCESS: Logged gap question - Q: "' . substr($question_text, 0, 40) . '..." | confidence: ' . $faq_confidence . ' | has_embedding: ' . ($embedding ? 'yes' : 'no') . ' | has_context: ' . ($conversation_context ? 'yes' : 'no'));
        }

        return true;
    } catch (PDOException $e) {
        error_log('[Chatbot Supabase] PDO failed to log gap question: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get gap questions (unresolved)
 * Default limit 200 for small business cost efficiency
 */
function chatbot_supabase_get_gap_questions($limit = 200, $include_resolved = false, $include_clustered = false) {
    $query_params = [
        'order' => 'asked_date.desc',
        'limit' => $limit
    ];

    // Build 'or' conditions for nullable boolean fields
    $or_conditions = [];

    if (!$include_resolved) {
        $or_conditions[] = 'is_resolved.eq.false';
        $or_conditions[] = 'is_resolved.is.null';
    }

    // By default, only get questions that haven't been clustered yet
    // This prevents re-analyzing the same questions
    if (!$include_clustered) {
        // We need a separate AND condition for is_clustered
        // Supabase doesn't easily combine AND/OR, so we filter is_resolved first
        // then add is_clustered as a simple filter
    }

    // For now, just filter out clustered questions with eq.false (most common case)
    // The UPDATE query should have set them to false, not null
    if (!$include_clustered) {
        $query_params['is_clustered'] = 'eq.false';
    }

    if (!$include_resolved) {
        $query_params['is_resolved'] = 'eq.false';
    }

    $result = chatbot_supabase_request('chatbot_gap_questions', 'GET', null, $query_params);

    if ($result['success']) {
        return $result['data'];
    }

    return [];
}

/**
 * Get gap questions count
 * Returns count of unclustered, unresolved questions by default
 */
function chatbot_supabase_get_gap_questions_count($include_resolved = false, $include_clustered = false) {
    $base_url = chatbot_supabase_get_url();
    $anon_key = chatbot_supabase_get_anon_key();
    $url = $base_url . '/chatbot_gap_questions?select=id';

    if (!$include_resolved) {
        $url .= '&is_resolved=eq.false';
    }

    // By default, only count questions that haven't been clustered yet
    if (!$include_clustered) {
        $url .= '&is_clustered=eq.false';
    }

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
    curl_close($ch);

    if (preg_match('/content-range: \d+-\d+\/(\d+)/i', $headers_str, $matches)) {
        return (int)$matches[1];
    }

    return 0;
}

/**
 * Mark gap question as resolved
 */
function chatbot_supabase_resolve_gap_question($id) {
    $query_params = ['id' => 'eq.' . $id];
    $data = ['is_resolved' => true];

    $result = chatbot_supabase_request('chatbot_gap_questions', 'PATCH', $data, $query_params);

    return $result['success'];
}

/**
 * Delete gap question
 */
function chatbot_supabase_delete_gap_question($id) {
    $query_params = ['id' => 'eq.' . $id];

    $result = chatbot_supabase_request('chatbot_gap_questions', 'DELETE', null, $query_params);

    return $result['success'];
}

/**
 * Get gap questions by confidence range (for analysis)
 */
function chatbot_supabase_get_gap_questions_by_confidence($min_confidence = 0, $max_confidence = 0.6) {
    $query_params = [
        'faq_confidence' => 'gte.' . $min_confidence,
        'and' => '(faq_confidence.lte.' . $max_confidence . ')',
        'is_resolved' => 'eq.false',
        'order' => 'asked_date.desc',
        'limit' => 100
    ];

    $result = chatbot_supabase_request('chatbot_gap_questions', 'GET', null, $query_params);

    if ($result['success']) {
        return $result['data'];
    }

    return [];
}

// =============================================================================
// GAP QUESTION VECTOR CLUSTERING
// =============================================================================

/**
 * Find similar gap questions using vector similarity
 *
 * @param int $question_id The question ID to find similar questions for
 * @param float $threshold Minimum similarity (0-1), default 0.70
 * @param int $limit Maximum results
 * @return array Array of similar questions with similarity scores
 */
function chatbot_supabase_find_similar_gap_questions($question_id, $threshold = 0.70, $limit = 10) {
    $pdo = function_exists('chatbot_vector_get_pg_connection') ? chatbot_vector_get_pg_connection() : null;

    if (!$pdo) {
        return [];
    }

    try {
        // Find similar questions based on embedding
        $stmt = $pdo->prepare('
            SELECT
                g2.id,
                g2.question_text,
                g2.faq_confidence,
                g2.asked_date,
                g2.is_resolved,
                1 - (g1.embedding <=> g2.embedding) AS similarity
            FROM chatbot_gap_questions g1
            CROSS JOIN chatbot_gap_questions g2
            WHERE g1.id = ?
            AND g2.id != g1.id
            AND g1.embedding IS NOT NULL
            AND g2.embedding IS NOT NULL
            AND 1 - (g1.embedding <=> g2.embedding) >= ?
            ORDER BY similarity DESC
            LIMIT ?
        ');
        $stmt->execute([$question_id, $threshold, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[Chatbot Supabase] Find similar gaps failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Cluster gap questions by semantic similarity
 * Returns groups of similar questions without manual clustering
 *
 * @param float $threshold Similarity threshold for grouping (0-1)
 * @param int $min_cluster_size Minimum questions per cluster
 * @return array Array of clusters, each containing similar questions
 */
function chatbot_supabase_cluster_gap_questions($threshold = 0.70, $min_cluster_size = 2) {
    $pdo = function_exists('chatbot_vector_get_pg_connection') ? chatbot_vector_get_pg_connection() : null;

    if (!$pdo) {
        return ['success' => false, 'error' => 'No database connection'];
    }

    try {
        // Get all unresolved gap questions with embeddings
        $stmt = $pdo->query('
            SELECT id, question_text, faq_confidence, asked_date, embedding
            FROM chatbot_gap_questions
            WHERE is_resolved = false
            AND embedding IS NOT NULL
            ORDER BY asked_date DESC
            LIMIT 200
        ');
        $questions = $stmt->fetchAll();

        if (count($questions) < $min_cluster_size) {
            return ['success' => true, 'clusters' => [], 'message' => 'Not enough questions to cluster'];
        }

        // Simple agglomerative clustering using SQL
        // Find pairs of similar questions
        $stmt = $pdo->prepare('
            SELECT
                g1.id as id1,
                g1.question_text as q1,
                g2.id as id2,
                g2.question_text as q2,
                1 - (g1.embedding <=> g2.embedding) AS similarity
            FROM chatbot_gap_questions g1
            INNER JOIN chatbot_gap_questions g2 ON g1.id < g2.id
            WHERE g1.is_resolved = false
            AND g2.is_resolved = false
            AND g1.embedding IS NOT NULL
            AND g2.embedding IS NOT NULL
            AND 1 - (g1.embedding <=> g2.embedding) >= ?
            ORDER BY similarity DESC
            LIMIT 500
        ');
        $stmt->execute([$threshold]);
        $pairs = $stmt->fetchAll();

        // Build clusters using union-find approach
        $clusters = [];
        $question_to_cluster = [];

        foreach ($pairs as $pair) {
            $id1 = $pair['id1'];
            $id2 = $pair['id2'];

            $cluster1 = $question_to_cluster[$id1] ?? null;
            $cluster2 = $question_to_cluster[$id2] ?? null;

            if ($cluster1 === null && $cluster2 === null) {
                // Both new - create new cluster
                $new_cluster_id = count($clusters);
                $clusters[$new_cluster_id] = [
                    'questions' => [
                        ['id' => $id1, 'text' => $pair['q1']],
                        ['id' => $id2, 'text' => $pair['q2']]
                    ],
                    'similarity' => $pair['similarity']
                ];
                $question_to_cluster[$id1] = $new_cluster_id;
                $question_to_cluster[$id2] = $new_cluster_id;
            } elseif ($cluster1 !== null && $cluster2 === null) {
                // Add id2 to cluster1
                $clusters[$cluster1]['questions'][] = ['id' => $id2, 'text' => $pair['q2']];
                $question_to_cluster[$id2] = $cluster1;
            } elseif ($cluster1 === null && $cluster2 !== null) {
                // Add id1 to cluster2
                $clusters[$cluster2]['questions'][] = ['id' => $id1, 'text' => $pair['q1']];
                $question_to_cluster[$id1] = $cluster2;
            } elseif ($cluster1 !== $cluster2) {
                // Merge clusters
                foreach ($clusters[$cluster2]['questions'] as $q) {
                    $clusters[$cluster1]['questions'][] = $q;
                    $question_to_cluster[$q['id']] = $cluster1;
                }
                unset($clusters[$cluster2]);
            }
        }

        // Filter by minimum cluster size and deduplicate
        $result_clusters = [];
        foreach ($clusters as $cluster) {
            // Deduplicate questions in cluster
            $seen_ids = [];
            $unique_questions = [];
            foreach ($cluster['questions'] as $q) {
                if (!isset($seen_ids[$q['id']])) {
                    $seen_ids[$q['id']] = true;
                    $unique_questions[] = $q;
                }
            }

            if (count($unique_questions) >= $min_cluster_size) {
                $result_clusters[] = [
                    'questions' => $unique_questions,
                    'count' => count($unique_questions),
                    'representative' => $unique_questions[0]['text']
                ];
            }
        }

        // Sort by cluster size (largest first)
        usort($result_clusters, fn($a, $b) => $b['count'] - $a['count']);

        return [
            'success' => true,
            'clusters' => $result_clusters,
            'total_clustered' => array_sum(array_column($result_clusters, 'count')),
            'cluster_count' => count($result_clusters)
        ];

    } catch (PDOException $e) {
        error_log('[Chatbot Supabase] Cluster gap questions failed: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get gap question clusters for admin display
 * Groups similar questions and suggests potential FAQ topics
 *
 * @return array Clusters with suggested topic summaries
 */
function chatbot_supabase_get_gap_clusters_for_admin() {
    $result = chatbot_supabase_cluster_gap_questions(0.70, 2);

    if (!$result['success'] || empty($result['clusters'])) {
        return $result;
    }

    // Enhance clusters with stats
    $enhanced = [];
    foreach ($result['clusters'] as $index => $cluster) {
        // Extract common words for topic suggestion
        $all_text = implode(' ', array_column($cluster['questions'], 'text'));
        $words = array_count_values(str_word_count(strtolower($all_text), 1));
        arsort($words);

        // Filter common stopwords
        $stopwords = ['what', 'how', 'why', 'when', 'where', 'is', 'are', 'the', 'a', 'an', 'to', 'for', 'of', 'and', 'in', 'on', 'do', 'does', 'can', 'i', 'my', 'you', 'your'];
        $keywords = array_diff_key($words, array_flip($stopwords));
        $top_keywords = array_slice(array_keys($keywords), 0, 5);

        $enhanced[] = [
            'cluster_id' => $index + 1,
            'question_count' => $cluster['count'],
            'questions' => $cluster['questions'],
            'representative_question' => $cluster['representative'],
            'extracted_keywords' => $top_keywords, // For internal use only, not stored
            'suggested_topic' => ucfirst(implode(' ', array_slice($top_keywords, 0, 3)))
        ];
    }

    return [
        'success' => true,
        'clusters' => $enhanced,
        'total_questions' => $result['total_clustered'],
        'cluster_count' => $result['cluster_count']
    ];
}

/**
 * Generate embeddings for existing gap questions that don't have them
 *
 * @param int $batch_size How many to process at once
 * @return array Results of the migration
 */
function chatbot_supabase_migrate_gap_embeddings($batch_size = 20) {
    $pdo = function_exists('chatbot_vector_get_pg_connection') ? chatbot_vector_get_pg_connection() : null;

    if (!$pdo) {
        return ['success' => false, 'error' => 'No database connection'];
    }

    try {
        // Get questions without embeddings
        $stmt = $pdo->prepare('
            SELECT id, question_text
            FROM chatbot_gap_questions
            WHERE embedding IS NULL
            AND question_text IS NOT NULL
            AND LENGTH(question_text) > 3
            LIMIT ?
        ');
        $stmt->execute([$batch_size]);
        $questions = $stmt->fetchAll();

        if (empty($questions)) {
            return ['success' => true, 'migrated' => 0, 'message' => 'All questions already have embeddings'];
        }

        $migrated = 0;
        $errors = 0;

        foreach ($questions as $q) {
            // Generate embedding
            $embedding = null;
            if (function_exists('chatbot_vector_generate_embedding')) {
                $embedding = chatbot_vector_generate_embedding($q['question_text']);
            }

            if ($embedding) {
                $embedding_str = chatbot_vector_to_pg_format($embedding);
                $stmt = $pdo->prepare('UPDATE chatbot_gap_questions SET embedding = ?::vector WHERE id = ?');
                $stmt->execute([$embedding_str, $q['id']]);
                $migrated++;
            } else {
                $errors++;
            }

            // Small delay to avoid rate limiting
            usleep(100000); // 100ms
        }

        return [
            'success' => true,
            'migrated' => $migrated,
            'errors' => $errors,
            'remaining' => count($questions) - $migrated
        ];

    } catch (PDOException $e) {
        error_log('[Chatbot Supabase] Migrate gap embeddings failed: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// =============================================================================
// FAQ USAGE TRACKING
// =============================================================================

/**
 * Track FAQ usage in Supabase
 */
function chatbot_supabase_track_faq_usage($faq_id, $confidence_score) {
    if (empty($faq_id)) {
        return false;
    }

    // First, check if record exists
    $query_params = ['faq_id' => 'eq.' . $faq_id];
    $result = chatbot_supabase_request('chatbot_faq_usage', 'GET', null, $query_params);

    if ($result['success'] && !empty($result['data'])) {
        // Update existing record
        $existing = $result['data'][0];
        $new_hit_count = intval($existing['hit_count']) + 1;

        // Calculate new average confidence (running average)
        $old_avg = floatval($existing['avg_confidence'] ?? 0);
        $old_count = intval($existing['hit_count']);
        $new_avg = (($old_avg * $old_count) + floatval($confidence_score)) / $new_hit_count;

        $data = [
            'hit_count' => $new_hit_count,
            'last_asked' => gmdate('c'),
            'avg_confidence' => $new_avg
        ];

        $result = chatbot_supabase_request('chatbot_faq_usage', 'PATCH', $data, $query_params);
    } else {
        // Insert new record
        $data = [
            'faq_id' => $faq_id,
            'hit_count' => 1,
            'last_asked' => gmdate('c'),
            'avg_confidence' => floatval($confidence_score)
        ];

        $result = chatbot_supabase_request('chatbot_faq_usage', 'POST', $data);
    }

    return $result['success'] ?? false;
}

/**
 * Get FAQ usage stats
 */
function chatbot_supabase_get_faq_usage($limit = 100) {
    $query_params = [
        'order' => 'hit_count.desc',
        'limit' => $limit
    ];

    $result = chatbot_supabase_request('chatbot_faq_usage', 'GET', null, $query_params);

    if ($result['success']) {
        return $result['data'];
    }

    return [];
}

/**
 * Get top FAQ questions with their question text
 * Ver 2.5.0: For dashboard visualization
 */
function chatbot_supabase_get_top_faqs_with_details($limit = 5) {
    // Get FAQ usage stats
    $usage = chatbot_supabase_get_faq_usage($limit);

    if (empty($usage)) {
        return [];
    }

    // Get FAQ details for each
    $result = [];
    foreach ($usage as $u) {
        $faq_id = $u['faq_id'] ?? '';
        if (empty($faq_id)) continue;

        // Get FAQ question text
        $faq_result = chatbot_supabase_request('chatbot_faqs', 'GET', null, ['faq_id' => 'eq.' . $faq_id]);

        if ($faq_result['success'] && !empty($faq_result['data'])) {
            $faq = $faq_result['data'][0];
            $result[] = [
                'faq_id' => $faq_id,
                'question' => $faq['question'] ?? 'Unknown',
                'category' => $faq['category'] ?? 'General',
                'hit_count' => intval($u['hit_count'] ?? 0),
                'avg_confidence' => floatval($u['avg_confidence'] ?? 0)
            ];
        }
    }

    return $result;
}

/**
 * Get deflection rate and KB vs AI usage stats
 * Ver 2.5.0: For dashboard metrics
 *
 * Deflection = questions answered from KB (high confidence) without needing human/AI fallback
 */
function chatbot_supabase_get_deflection_stats($period = 'Week') {
    // Calculate date range based on period
    $now = new DateTime('now', new DateTimeZone('UTC'));

    switch ($period) {
        case 'Today':
            $start_date = $now->format('Y-m-d') . 'T00:00:00Z';
            break;
        case 'Week':
            $start_date = $now->modify('-7 days')->format('Y-m-d') . 'T00:00:00Z';
            break;
        case 'Month':
            $start_date = $now->modify('-30 days')->format('Y-m-d') . 'T00:00:00Z';
            break;
        case 'Quarter':
            $start_date = $now->modify('-90 days')->format('Y-m-d') . 'T00:00:00Z';
            break;
        case 'Year':
            $start_date = $now->modify('-365 days')->format('Y-m-d') . 'T00:00:00Z';
            break;
        default:
            $start_date = $now->modify('-7 days')->format('Y-m-d') . 'T00:00:00Z';
    }

    $base_url = chatbot_supabase_get_url();
    $anon_key = chatbot_supabase_get_anon_key();

    if (!$base_url || !$anon_key) {
        return [
            'total_questions' => 0,
            'kb_answered' => 0,
            'ai_fallback' => 0,
            'deflection_rate' => 0,
            'kb_percentage' => 0,
            'ai_percentage' => 0
        ];
    }

    // Get conversations in period - visitor messages only
    $url = $base_url . '/chatbot_conversations?user_type=eq.Visitor&interaction_time=gte.' . urlencode($start_date) . '&select=confidence_score';

    $headers = [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (!is_array($data)) {
        return [
            'total_questions' => 0,
            'kb_answered' => 0,
            'ai_fallback' => 0,
            'deflection_rate' => 0,
            'kb_percentage' => 0,
            'ai_percentage' => 0
        ];
    }

    $total = count($data);
    $kb_answered = 0;
    $ai_fallback = 0;

    foreach ($data as $conv) {
        $confidence = floatval($conv['confidence_score'] ?? 0);
        // High confidence (>= 0.6) = answered from KB
        // Low confidence (< 0.6) = AI fallback was used
        if ($confidence >= 0.6) {
            $kb_answered++;
        } else {
            $ai_fallback++;
        }
    }

    $deflection_rate = $total > 0 ? round(($kb_answered / $total) * 100, 1) : 0;
    $kb_percentage = $total > 0 ? round(($kb_answered / $total) * 100, 1) : 0;
    $ai_percentage = $total > 0 ? round(($ai_fallback / $total) * 100, 1) : 0;

    return [
        'total_questions' => $total,
        'kb_answered' => $kb_answered,
        'ai_fallback' => $ai_fallback,
        'deflection_rate' => $deflection_rate,
        'kb_percentage' => $kb_percentage,
        'ai_percentage' => $ai_percentage
    ];
}

/**
 * Get NPS data from conversations
 * Ver 2.5.0: Net Promoter Score implementation
 *
 * NPS = % Promoters (9-10) - % Detractors (0-6)
 * Scale: -100 to +100
 */
function chatbot_supabase_get_nps_stats() {
    // NPS is stored in chatbot_chatgpt_nps_data option
    $nps_data = get_option('chatbot_chatgpt_nps_data', [
        'responses' => [],
        'total' => 0
    ]);

    $responses = $nps_data['responses'] ?? [];
    $total = count($responses);

    if ($total === 0) {
        return [
            'nps_score' => 0,
            'total_responses' => 0,
            'promoters' => 0,
            'passives' => 0,
            'detractors' => 0,
            'promoter_percent' => 0,
            'passive_percent' => 0,
            'detractor_percent' => 0
        ];
    }

    $promoters = 0;   // 9-10
    $passives = 0;    // 7-8
    $detractors = 0;  // 0-6

    foreach ($responses as $r) {
        $score = intval($r['score'] ?? 0);
        if ($score >= 9) {
            $promoters++;
        } elseif ($score >= 7) {
            $passives++;
        } else {
            $detractors++;
        }
    }

    $promoter_percent = round(($promoters / $total) * 100, 1);
    $detractor_percent = round(($detractors / $total) * 100, 1);
    $passive_percent = round(($passives / $total) * 100, 1);

    // NPS = % Promoters - % Detractors
    $nps_score = round($promoter_percent - $detractor_percent);

    return [
        'nps_score' => $nps_score,
        'total_responses' => $total,
        'promoters' => $promoters,
        'passives' => $passives,
        'detractors' => $detractors,
        'promoter_percent' => $promoter_percent,
        'passive_percent' => $passive_percent,
        'detractor_percent' => $detractor_percent
    ];
}

// =============================================================================
// GAP CLUSTERS MANAGEMENT
// =============================================================================

/**
 * Get all gap clusters from Supabase
 */
function chatbot_supabase_get_gap_clusters($status = null, $limit = 100) {
    $query_params = [
        'order' => 'priority_score.desc',
        'limit' => $limit
    ];

    if ($status) {
        $query_params['status'] = 'eq.' . $status;
    }

    $result = chatbot_supabase_request('chatbot_gap_clusters', 'GET', null, $query_params);

    if (isset($result['success']) && $result['success']) {
        return $result['data'] ?? [];
    }

    return [];
}

/**
 * Get gap cluster by ID
 */
function chatbot_supabase_get_gap_cluster($id) {
    $query_params = ['id' => 'eq.' . $id];
    $result = chatbot_supabase_request('chatbot_gap_clusters', 'GET', null, $query_params);

    if (isset($result['success']) && $result['success'] && !empty($result['data'])) {
        return $result['data'][0];
    }

    return null;
}

/**
 * Get gap cluster by name
 */
function chatbot_supabase_get_gap_cluster_by_name($name) {
    $query_params = ['cluster_name' => 'eq.' . $name];
    $result = chatbot_supabase_request('chatbot_gap_clusters', 'GET', null, $query_params);

    if (isset($result['success']) && $result['success'] && !empty($result['data'])) {
        return $result['data'][0];
    }

    return null;
}

/**
 * Create a new gap cluster
 * @return array|false Returns the created cluster data or false on failure
 */
function chatbot_supabase_create_gap_cluster($data) {
    $insert_data = [
        'cluster_name' => $data['cluster_name'] ?? '',
        'cluster_description' => $data['cluster_description'] ?? '',
        'question_count' => intval($data['question_count'] ?? 0),
        'sample_questions' => $data['sample_questions'] ?? '[]',
        'sample_contexts' => $data['sample_contexts'] ?? '[]', // Ver 2.5.0: Conversation context for follow-ups
        'suggested_faq' => $data['suggested_faq'] ?? '{}',
        'action_type' => $data['action_type'] ?? 'create',
        'existing_faq_id' => $data['existing_faq_id'] ?? '',
        'suggested_answer' => $data['suggested_answer'] ?? '', // Ver 2.5.0: For improve action type
        'priority_score' => floatval($data['priority_score'] ?? 0),
        'status' => $data['status'] ?? 'new'
    ];

    $result = chatbot_supabase_request('chatbot_gap_clusters', 'POST', $insert_data);

    if (isset($result['success']) && $result['success'] && !empty($result['data'])) {
        return $result['data'][0];
    }

    error_log('[Chatbot Supabase] Failed to create gap cluster: ' . json_encode($result));
    return false;
}

/**
 * Update a gap cluster
 */
function chatbot_supabase_update_gap_cluster($id, $data) {
    $query_params = ['id' => 'eq.' . $id];

    $update_data = [];
    $allowed_fields = ['cluster_name', 'cluster_description', 'question_count', 'sample_questions',
                       'sample_contexts', 'suggested_faq', 'action_type', 'existing_faq_id',
                       'suggested_answer', 'priority_score', 'status'];

    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_data[$field] = $data[$field];
        }
    }

    $result = chatbot_supabase_request('chatbot_gap_clusters', 'PATCH', $update_data, $query_params);

    return isset($result['success']) && $result['success'];
}

/**
 * Update gap cluster status
 */
function chatbot_supabase_update_gap_cluster_status($id, $status) {
    return chatbot_supabase_update_gap_cluster($id, ['status' => $status]);
}

/**
 * Delete a gap cluster
 */
function chatbot_supabase_delete_gap_cluster($id) {
    $query_params = ['id' => 'eq.' . $id];

    $result = chatbot_supabase_request('chatbot_gap_clusters', 'DELETE', null, $query_params);

    return isset($result['success']) && $result['success'];
}

/**
 * Get gap clusters summary (counts by status)
 */
function chatbot_supabase_get_gap_clusters_summary() {
    $all_clusters = chatbot_supabase_get_gap_clusters(null, 1000);

    $summary = [
        'total' => count($all_clusters),
        'new' => 0,
        'reviewed' => 0,
        'faq_created' => 0,
        'dismissed' => 0,
        'total_questions' => 0
    ];

    foreach ($all_clusters as $cluster) {
        $status = $cluster['status'] ?? 'new';
        if (isset($summary[$status])) {
            $summary[$status]++;
        }
        $summary['total_questions'] += intval($cluster['question_count'] ?? 0);
    }

    return $summary;
}

/**
 * Mark gap questions as clustered
 */
function chatbot_supabase_mark_questions_clustered($question_ids, $cluster_id) {
    if (empty($question_ids)) {
        return false;
    }

    // Supabase doesn't support IN directly, so we update one by one
    foreach ($question_ids as $qid) {
        $query_params = ['id' => 'eq.' . intval($qid)];
        $data = [
            'is_clustered' => true,
            'cluster_id' => intval($cluster_id)
        ];
        chatbot_supabase_request('chatbot_gap_questions', 'PATCH', $data, $query_params);
    }

    return true;
}

// =============================================================================
// ASSISTANTS MANAGEMENT
// =============================================================================

/**
 * Get all assistants from Supabase
 */
function chatbot_supabase_get_assistants() {
    $query_params = [
        'order' => 'id.asc'
    ];

    $result = chatbot_supabase_request('chatbot_assistants', 'GET', null, $query_params);

    if ($result['success']) {
        return $result['data'];
    }

    return [];
}

/**
 * Get assistant by ID
 */
function chatbot_supabase_get_assistant($id) {
    $query_params = ['id' => 'eq.' . $id];
    $result = chatbot_supabase_request('chatbot_assistants', 'GET', null, $query_params);

    if ($result['success'] && !empty($result['data'])) {
        return $result['data'][0];
    }

    return null;
}

/**
 * Get assistant by assistant_id (OpenAI ID)
 */
function chatbot_supabase_get_assistant_by_assistant_id($assistant_id) {
    $query_params = ['assistant_id' => 'eq.' . $assistant_id];
    $result = chatbot_supabase_request('chatbot_assistants', 'GET', null, $query_params);

    if ($result['success'] && !empty($result['data'])) {
        return $result['data'][0];
    }

    return null;
}

/**
 * Add new assistant
 */
function chatbot_supabase_add_assistant($data) {
    $result = chatbot_supabase_request('chatbot_assistants', 'POST', $data);

    if ($result['success'] && !empty($result['data'])) {
        return ['success' => true, 'id' => $result['data'][0]['id']];
    }

    return ['success' => false, 'message' => $result['error'] ?? 'Failed to add assistant'];
}

/**
 * Update assistant
 */
function chatbot_supabase_update_assistant($id, $data) {
    $query_params = ['id' => 'eq.' . $id];
    $result = chatbot_supabase_request('chatbot_assistants', 'PATCH', $data, $query_params);

    return ['success' => $result['success'], 'message' => $result['error'] ?? ''];
}

/**
 * Delete assistant
 */
function chatbot_supabase_delete_assistant($id) {
    $query_params = ['id' => 'eq.' . $id];
    $result = chatbot_supabase_request('chatbot_assistants', 'DELETE', null, $query_params);

    return $result['success'];
}

/**
 * Get assistant count
 */
function chatbot_supabase_get_assistant_count() {
    $result = chatbot_supabase_get_assistants();
    return count($result);
}

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

// Note: chatbot_supabase_test_connection() is now defined in chatbot-settings-supabase.php

/**
 * Get all table counts for diagnostics
 */
function chatbot_supabase_get_diagnostics() {
    $tables = ['chatbot_faqs', 'chatbot_conversations', 'chatbot_interactions', 'chatbot_gap_questions', 'chatbot_faq_usage', 'chatbot_assistants'];
    $diagnostics = [];
    $anon_key = chatbot_supabase_get_anon_key();

    foreach ($tables as $table) {
        $base_url = chatbot_supabase_get_url();
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

        if ($http_code >= 200 && $http_code < 300) {
            if (preg_match('/content-range: \d+-\d+\/(\d+)/i', $headers_str, $matches)) {
                $diagnostics[$table] = (int)$matches[1];
            } else {
                $diagnostics[$table] = 0;
            }
        } else {
            $diagnostics[$table] = 'Error: ' . $http_code;
        }
    }

    return $diagnostics;
}

// =============================================================================
// ANALYTICS FUNCTIONS FOR SUPABASE
// =============================================================================

/**
 * Get time-based conversation counts from Supabase
 * This replaces kognetiks_analytics_get_time_based_conversation_counts for Supabase
 */
function chatbot_supabase_get_time_based_conversation_counts($period = 'Week') {
    // Define period ranges
    $periods = array(
        'Today' => array(
            'current_start' => gmdate('Y-m-d\T00:00:00\Z'),
            'current_end' => gmdate('Y-m-d\T23:59:59\Z'),
            'previous_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('-1 day')),
            'previous_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('-1 day')),
            'current_label' => 'Today',
            'previous_label' => 'Yesterday'
        ),
        'Week' => array(
            'current_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('monday this week')),
            'current_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('sunday this week')),
            'previous_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('monday last week')),
            'previous_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('sunday last week')),
            'current_label' => 'This Week',
            'previous_label' => 'Last Week'
        ),
        'Month' => array(
            'current_start' => gmdate('Y-m-01\T00:00:00\Z'),
            'current_end' => gmdate('Y-m-t\T23:59:59\Z'),
            'previous_start' => gmdate('Y-m-01\T00:00:00\Z', strtotime('first day of last month')),
            'previous_end' => gmdate('Y-m-t\T23:59:59\Z', strtotime('last day of last month')),
            'current_label' => 'This Month',
            'previous_label' => 'Last Month'
        ),
        'Quarter' => array(
            'current_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('first day of ' . ceil(date('n')/3)*3-2 . ' month this year')),
            'current_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('last day of ' . ceil(date('n')/3)*3 . ' month this year')),
            'previous_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('-3 months', strtotime('first day of ' . ceil(date('n')/3)*3-2 . ' month this year'))),
            'previous_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('-3 months', strtotime('last day of ' . ceil(date('n')/3)*3 . ' month this year'))),
            'current_label' => 'This Quarter',
            'previous_label' => 'Last Quarter'
        ),
        'Year' => array(
            'current_start' => gmdate('Y-01-01\T00:00:00\Z'),
            'current_end' => gmdate('Y-12-31\T23:59:59\Z'),
            'previous_start' => gmdate('Y-01-01\T00:00:00\Z', strtotime('-1 year')),
            'previous_end' => gmdate('Y-12-31\T23:59:59\Z', strtotime('-1 year')),
            'current_label' => 'This Year',
            'previous_label' => 'Last Year'
        )
    );

    $period_info = $periods[$period] ?? $periods['Week'];

    // Get current period conversations
    $current_data = chatbot_supabase_get_conversations_in_range(
        $period_info['current_start'],
        $period_info['current_end']
    );

    // Get previous period conversations
    $previous_data = chatbot_supabase_get_conversations_in_range(
        $period_info['previous_start'],
        $period_info['previous_end']
    );

    return array(
        'current' => $current_data,
        'previous' => $previous_data,
        'current_period_label' => $period_info['current_label'],
        'previous_period_label' => $period_info['previous_label']
    );
}

/**
 * Get conversations in a date range from Supabase
 */
function chatbot_supabase_get_conversations_in_range($start_date, $end_date) {
    if (!chatbot_supabase_is_configured()) {
        return array('total' => 0, 'unique_visitors' => 0);
    }

    $base_url = chatbot_supabase_get_url();
    $anon_key = chatbot_supabase_get_anon_key();

    // Query for all conversations in range
    $query_params = http_build_query([
        'select' => 'session_id,user_type',
        'interaction_time' => 'gte.' . $start_date,
        'order' => 'interaction_time.asc'
    ]);

    // Add the end date filter
    $url = $base_url . '/chatbot_conversations?' . $query_params . '&interaction_time=lte.' . $end_date;

    $headers = [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $data = json_decode($response, true);
        if (is_array($data)) {
            $unique_sessions = array_unique(array_column($data, 'session_id'));
            return array(
                'total' => count($unique_sessions),
                'unique_visitors' => count($unique_sessions)
            );
        }
    }

    return array('total' => 0, 'unique_visitors' => 0);
}

/**
 * Get message statistics from Supabase
 */
function chatbot_supabase_get_message_statistics($period = 'Week') {
    // Define period ranges (same as above)
    $periods = array(
        'Today' => array(
            'current_start' => gmdate('Y-m-d\T00:00:00\Z'),
            'current_end' => gmdate('Y-m-d\T23:59:59\Z'),
            'previous_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('-1 day')),
            'previous_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('-1 day')),
            'current_label' => 'Today',
            'previous_label' => 'Yesterday'
        ),
        'Week' => array(
            'current_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('monday this week')),
            'current_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('sunday this week')),
            'previous_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('monday last week')),
            'previous_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('sunday last week')),
            'current_label' => 'This Week',
            'previous_label' => 'Last Week'
        ),
        'Month' => array(
            'current_start' => gmdate('Y-m-01\T00:00:00\Z'),
            'current_end' => gmdate('Y-m-t\T23:59:59\Z'),
            'previous_start' => gmdate('Y-m-01\T00:00:00\Z', strtotime('first day of last month')),
            'previous_end' => gmdate('Y-m-t\T23:59:59\Z', strtotime('last day of last month')),
            'current_label' => 'This Month',
            'previous_label' => 'Last Month'
        ),
        'Quarter' => array(
            'current_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('first day of ' . ceil(date('n')/3)*3-2 . ' month this year')),
            'current_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('last day of ' . ceil(date('n')/3)*3 . ' month this year')),
            'previous_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('-3 months', strtotime('first day of ' . ceil(date('n')/3)*3-2 . ' month this year'))),
            'previous_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('-3 months', strtotime('last day of ' . ceil(date('n')/3)*3 . ' month this year'))),
            'current_label' => 'This Quarter',
            'previous_label' => 'Last Quarter'
        ),
        'Year' => array(
            'current_start' => gmdate('Y-01-01\T00:00:00\Z'),
            'current_end' => gmdate('Y-12-31\T23:59:59\Z'),
            'previous_start' => gmdate('Y-01-01\T00:00:00\Z', strtotime('-1 year')),
            'previous_end' => gmdate('Y-12-31\T23:59:59\Z', strtotime('-1 year')),
            'current_label' => 'This Year',
            'previous_label' => 'Last Year'
        )
    );

    $period_info = $periods[$period] ?? $periods['Week'];

    // Get current period messages
    $current_data = chatbot_supabase_get_messages_in_range(
        $period_info['current_start'],
        $period_info['current_end']
    );

    // Get previous period messages
    $previous_data = chatbot_supabase_get_messages_in_range(
        $period_info['previous_start'],
        $period_info['previous_end']
    );

    return array(
        'current' => $current_data,
        'previous' => $previous_data,
        'current_period_label' => $period_info['current_label'],
        'previous_period_label' => $period_info['previous_label']
    );
}

/**
 * Get message counts in a date range from Supabase
 */
function chatbot_supabase_get_messages_in_range($start_date, $end_date) {
    if (!chatbot_supabase_is_configured()) {
        return array('total_messages' => 0, 'visitor_messages' => 0, 'chatbot_messages' => 0);
    }

    $base_url = chatbot_supabase_get_url();
    $anon_key = chatbot_supabase_get_anon_key();

    // Query for all messages in range
    $query_params = http_build_query([
        'select' => 'user_type',
        'interaction_time' => 'gte.' . $start_date,
        'order' => 'interaction_time.asc'
    ]);

    $url = $base_url . '/chatbot_conversations?' . $query_params . '&interaction_time=lte.' . $end_date;

    $headers = [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $data = json_decode($response, true);
        if (is_array($data)) {
            $total = count($data);
            $visitor_messages = count(array_filter($data, function($msg) {
                return ($msg['user_type'] ?? '') === 'Visitor';
            }));
            $chatbot_messages = count(array_filter($data, function($msg) {
                return ($msg['user_type'] ?? '') === 'Chatbot';
            }));
            return array(
                'total_messages' => $total,
                'visitor_messages' => $visitor_messages,
                'chatbot_messages' => $chatbot_messages
            );
        }
    }

    return array('total_messages' => 0, 'visitor_messages' => 0, 'chatbot_messages' => 0);
}

/**
 * Get sentiment statistics from Supabase
 */
function chatbot_supabase_get_sentiment_statistics($period = 'Week') {
    // Define period ranges
    $periods = array(
        'Today' => array(
            'current_start' => gmdate('Y-m-d\T00:00:00\Z'),
            'current_end' => gmdate('Y-m-d\T23:59:59\Z'),
            'previous_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('-1 day')),
            'previous_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('-1 day')),
            'current_label' => 'Today',
            'previous_label' => 'Yesterday'
        ),
        'Week' => array(
            'current_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('monday this week')),
            'current_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('sunday this week')),
            'previous_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('monday last week')),
            'previous_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('sunday last week')),
            'current_label' => 'This Week',
            'previous_label' => 'Last Week'
        ),
        'Month' => array(
            'current_start' => gmdate('Y-m-01\T00:00:00\Z'),
            'current_end' => gmdate('Y-m-t\T23:59:59\Z'),
            'previous_start' => gmdate('Y-m-01\T00:00:00\Z', strtotime('first day of last month')),
            'previous_end' => gmdate('Y-m-t\T23:59:59\Z', strtotime('last day of last month')),
            'current_label' => 'This Month',
            'previous_label' => 'Last Month'
        ),
        'Quarter' => array(
            'current_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('first day of ' . ceil(date('n')/3)*3-2 . ' month this year')),
            'current_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('last day of ' . ceil(date('n')/3)*3 . ' month this year')),
            'previous_start' => gmdate('Y-m-d\T00:00:00\Z', strtotime('-3 months', strtotime('first day of ' . ceil(date('n')/3)*3-2 . ' month this year'))),
            'previous_end' => gmdate('Y-m-d\T23:59:59\Z', strtotime('-3 months', strtotime('last day of ' . ceil(date('n')/3)*3 . ' month this year'))),
            'current_label' => 'This Quarter',
            'previous_label' => 'Last Quarter'
        ),
        'Year' => array(
            'current_start' => gmdate('Y-01-01\T00:00:00\Z'),
            'current_end' => gmdate('Y-12-31\T23:59:59\Z'),
            'previous_start' => gmdate('Y-01-01\T00:00:00\Z', strtotime('-1 year')),
            'previous_end' => gmdate('Y-12-31\T23:59:59\Z', strtotime('-1 year')),
            'current_label' => 'This Year',
            'previous_label' => 'Last Year'
        )
    );

    $period_info = $periods[$period] ?? $periods['Week'];

    // Get current period sentiment
    $current_data = chatbot_supabase_get_sentiment_in_range(
        $period_info['current_start'],
        $period_info['current_end']
    );

    // Get previous period sentiment
    $previous_data = chatbot_supabase_get_sentiment_in_range(
        $period_info['previous_start'],
        $period_info['previous_end']
    );

    return array(
        'current' => $current_data,
        'previous' => $previous_data,
        'current_period_label' => $period_info['current_label'],
        'previous_period_label' => $period_info['previous_label']
    );
}

/**
 * Get sentiment data in a date range from Supabase
 */
function chatbot_supabase_get_sentiment_in_range($start_date, $end_date) {
    if (!chatbot_supabase_is_configured()) {
        return array('avg_score' => 0, 'positive_percent' => 0);
    }

    $base_url = chatbot_supabase_get_url();
    $anon_key = chatbot_supabase_get_anon_key();

    // Query for messages with sentiment scores
    $query_params = http_build_query([
        'select' => 'sentiment_score',
        'interaction_time' => 'gte.' . $start_date,
        'sentiment_score' => 'not.is.null'
    ]);

    $url = $base_url . '/chatbot_conversations?' . $query_params . '&interaction_time=lte.' . $end_date;

    $headers = [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $data = json_decode($response, true);
        if (is_array($data) && count($data) > 0) {
            $scores = array_filter(array_column($data, 'sentiment_score'), function($s) {
                return $s !== null && $s !== '';
            });

            if (count($scores) > 0) {
                $avg_score = array_sum($scores) / count($scores);
                $positive = count(array_filter($scores, function($s) { return floatval($s) > 0; }));
                $positive_percent = ($positive / count($scores)) * 100;

                return array(
                    'avg_score' => round($avg_score, 2),
                    'positive_percent' => round($positive_percent, 1)
                );
            }
        }
    }

    return array('avg_score' => 0, 'positive_percent' => 0);
}
