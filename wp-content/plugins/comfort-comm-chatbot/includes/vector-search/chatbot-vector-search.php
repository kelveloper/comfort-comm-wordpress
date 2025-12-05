<?php
/**
 * Chatbot Vector Search - Semantic Search Function
 *
 * Performs semantic similarity search using cosine similarity
 * on vector embeddings in PostgreSQL with pgvector.
 *
 * NO FALLBACK - Vector search is required.
 *
 * @package comfort-comm-chatbot
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die();
}

// Include dependencies
require_once plugin_dir_path(__FILE__) . 'chatbot-vector-schema.php';
require_once plugin_dir_path(__FILE__) . 'chatbot-vector-migration.php';

/**
 * Default similarity threshold settings
 */
define('CHATBOT_VECTOR_THRESHOLD_VERY_HIGH', 0.85);  // Very confident match
define('CHATBOT_VECTOR_THRESHOLD_HIGH', 0.75);       // High confidence
define('CHATBOT_VECTOR_THRESHOLD_MEDIUM', 0.65);     // Medium confidence
define('CHATBOT_VECTOR_THRESHOLD_LOW', 0.50);        // Low confidence
define('CHATBOT_VECTOR_THRESHOLD_MIN', 0.40);        // Minimum to return

// Ver 2.5.0: Threshold for tiered search fallback
define('CHATBOT_VECTOR_FALLBACK_THRESHOLD', 0.65);   // If question_embedding score < this, try combined_embedding

/**
 * Search FAQs using vector similarity
 *
 * @param string $query The user's question
 * @param array $options Search options:
 *   - threshold: Minimum similarity score (0-1), default 0.40
 *   - limit: Maximum results to return, default 5
 *   - category: Filter by category (optional)
 *   - return_scores: Include similarity scores in results, default true
 * @return array Search results with similarity scores
 */
function chatbot_vector_search($query, $options = []) {
    // Default options
    $defaults = [
        'threshold' => CHATBOT_VECTOR_THRESHOLD_MIN,
        'limit' => 5,
        'category' => null,
        'return_scores' => true,
        'use_tiered_search' => true  // Ver 2.5.0: Enable tiered search by default
    ];
    $options = array_merge($defaults, $options);

    // Generate embedding for the query first
    $query_embedding = chatbot_vector_generate_embedding($query);

    if (!$query_embedding) {
        error_log('[Chatbot Vector] Failed to generate query embedding. Check API key (Gemini or OpenAI).');
        return [
            'success' => false,
            'error' => 'Failed to generate embedding. Check API configuration.',
            'results' => [],
            'count' => 0,
            'search_type' => 'error'
        ];
    }

    // Try PDO connection first (faster), fall back to REST API
    $pdo = chatbot_vector_get_pg_connection();

    if ($pdo) {
        // Ver 2.5.0: TIERED SEARCH - Standard RAG approach
        // Tier 1: Try question_embedding first (strict, accurate)
        $result = chatbot_vector_search_pdo($query_embedding, $options, $pdo, 'question');

        // Check if we got a good match
        $best_score = 0;
        if ($result['success'] && !empty($result['results'])) {
            $best_score = $result['results'][0]['similarity'] ?? 0;
        }

        // Tier 2: If score < fallback threshold, try combined_embedding (broader, topical)
        if ($options['use_tiered_search'] && $best_score < CHATBOT_VECTOR_FALLBACK_THRESHOLD) {
            error_log('[Chatbot Vector] Tier 1 (question_embedding) score=' . round($best_score, 2) . ' < ' . CHATBOT_VECTOR_FALLBACK_THRESHOLD . ', trying Tier 2 (combined_embedding)');

            $combined_result = chatbot_vector_search_pdo($query_embedding, $options, $pdo, 'combined');

            $combined_best_score = 0;
            if ($combined_result['success'] && !empty($combined_result['results'])) {
                $combined_best_score = $combined_result['results'][0]['similarity'] ?? 0;
            }

            // Use combined result if it's better
            if ($combined_best_score > $best_score) {
                error_log('[Chatbot Vector] Tier 2 (combined_embedding) score=' . round($combined_best_score, 2) . ' is better, using combined result');
                $combined_result['search_type'] = 'tiered_combined';
                return $combined_result;
            } else {
                error_log('[Chatbot Vector] Tier 1 (question_embedding) score=' . round($best_score, 2) . ' is better or equal, keeping question result');
            }
        }

        $result['search_type'] = 'tiered_question';
        return $result;
    } else {
        return chatbot_vector_search_rest($query_embedding, $options);
    }
}

/**
 * Search using PDO (direct PostgreSQL connection)
 *
 * @param array $query_embedding The query embedding vector
 * @param array $options Search options
 * @param PDO $pdo Database connection
 * @param string $embedding_type Which embedding to search: 'question' or 'combined' (Ver 2.5.0)
 */
function chatbot_vector_search_pdo($query_embedding, $options, $pdo, $embedding_type = 'question') {
    $embedding_str = chatbot_vector_to_pg_format($query_embedding);

    // Ver 2.5.0: Select embedding column based on type
    // - 'question': Compare user question against FAQ question (strict, accurate)
    // - 'combined': Compare against question+answer mix (broader, topical)
    $embedding_column = ($embedding_type === 'combined') ? 'combined_embedding' : 'question_embedding';

    try {
        $sql = '
            SELECT
                faq_id,
                question,
                answer,
                category,
                1 - (' . $embedding_column . ' <=> ?::vector) AS similarity
            FROM chatbot_faqs
            WHERE ' . $embedding_column . ' IS NOT NULL
        ';

        $params = [$embedding_str];

        if (!empty($options['category'])) {
            $sql .= ' AND category = ?';
            $params[] = $options['category'];
        }

        $sql .= ' AND 1 - (' . $embedding_column . ' <=> ?::vector) >= ?';
        $params[] = $embedding_str;
        $params[] = $options['threshold'];

        $sql .= ' ORDER BY similarity DESC LIMIT ?';
        $params[] = (int) $options['limit'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        return chatbot_vector_process_results($results, $options);

    } catch (PDOException $e) {
        error_log('[Chatbot Vector] PDO search failed: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Database query failed: ' . $e->getMessage(),
            'results' => [],
            'count' => 0,
            'search_type' => 'error'
        ];
    }
}

/**
 * Search using question-only embeddings (for duplicate detection)
 * This compares question vs question for more accurate matching
 *
 * @param string $query The question to search for
 * @param array $options Search options
 * @return array Search results
 */
function chatbot_vector_search_by_question($query, $options = []) {
    $defaults = [
        'threshold' => 0.70,
        'limit' => 5,
        'return_scores' => true
    ];
    $options = array_merge($defaults, $options);

    // Generate embedding for the query
    $query_embedding = chatbot_vector_generate_embedding($query);

    if (!$query_embedding) {
        return [
            'success' => false,
            'error' => 'Failed to generate embedding',
            'results' => [],
            'count' => 0
        ];
    }

    $config = chatbot_vector_get_supabase_config();

    if (!$config || !$config['anon_key']) {
        return [
            'success' => false,
            'error' => 'Supabase not configured',
            'results' => [],
            'count' => 0
        ];
    }

    // Call the search_faqs_by_question function via RPC
    $url = $config['url'] . '/rest/v1/rpc/search_faqs_by_question';

    $body = [
        'query_embedding' => $query_embedding,
        'match_threshold' => $options['threshold'],
        'match_count' => $options['limit']
    ];

    $response = wp_remote_post($url, [
        'headers' => [
            'apikey' => $config['anon_key'],
            'Authorization' => 'Bearer ' . $config['anon_key'],
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($body),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        return [
            'success' => false,
            'error' => $response->get_error_message(),
            'results' => [],
            'count' => 0
        ];
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $results = json_decode($body, true);

    if ($status >= 400) {
        $error = is_array($results) && isset($results['message']) ? $results['message'] : 'API error';
        return [
            'success' => false,
            'error' => $error,
            'results' => [],
            'count' => 0
        ];
    }

    if (!is_array($results)) {
        return [
            'success' => true,
            'results' => [],
            'count' => 0
        ];
    }

    return chatbot_vector_process_results($results, $options);
}

/**
 * Search using Supabase REST API (no PHP extensions required)
 */
function chatbot_vector_search_rest($query_embedding, $options) {
    $config = chatbot_vector_get_supabase_config();

    if (!$config || !$config['anon_key']) {
        error_log('[Chatbot Vector] Supabase REST API not configured');
        return [
            'success' => false,
            'error' => 'Vector search not configured. Add CHATBOT_SUPABASE_ANON_KEY to wp-config.php',
            'results' => [],
            'count' => 0,
            'search_type' => 'error'
        ];
    }

    // Ver 2.5.0: Use search_faqs_by_question for better accuracy
    // This compares user question against FAQ question_embedding (not combined)
    $url = $config['url'] . '/rest/v1/rpc/search_faqs_by_question';

    $body = [
        'query_embedding' => $query_embedding,
        'match_threshold' => $options['threshold'],
        'match_count' => $options['limit']
    ];

    $response = wp_remote_post($url, [
        'headers' => [
            'apikey' => $config['anon_key'],
            'Authorization' => 'Bearer ' . $config['anon_key'],
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($body),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        error_log('[Chatbot Vector] Supabase REST request failed: ' . $response->get_error_message());
        return [
            'success' => false,
            'error' => 'API request failed: ' . $response->get_error_message(),
            'results' => [],
            'count' => 0,
            'search_type' => 'error'
        ];
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $results = json_decode($body, true);

    if ($status >= 400) {
        $error = is_array($results) && isset($results['message']) ? $results['message'] : 'Unknown API error';
        error_log('[Chatbot Vector] Supabase API error: ' . $error . ' (Status: ' . $status . ')');
        return [
            'success' => false,
            'error' => 'API error: ' . $error,
            'results' => [],
            'count' => 0,
            'search_type' => 'error'
        ];
    }

    if (!is_array($results)) {
        return [
            'success' => true,
            'results' => [],
            'count' => 0,
            'search_type' => 'vector_rest'
        ];
    }

    return chatbot_vector_process_results($results, $options);
}

/**
 * Process search results into standard format
 */
function chatbot_vector_process_results($results, $options) {
    $processed_results = [];

    foreach ($results as $row) {
        $result = [
            'faq_id' => $row['faq_id'],
            'question' => $row['question'],
            'answer' => $row['answer'],
            'category' => $row['category']
        ];

        if ($options['return_scores']) {
            $result['similarity'] = round((float) $row['similarity'], 4);
            $result['confidence'] = chatbot_vector_get_confidence_level($row['similarity']);
        }

        $processed_results[] = $result;
    }

    return [
        'success' => true,
        'results' => $processed_results,
        'count' => count($processed_results),
        'search_type' => 'vector'
    ];
}

/**
 * Find the best matching FAQ for a query
 *
 * @param string $query The user's question
 * @param float $threshold Minimum similarity threshold (default 0.40)
 * @return array|null Best match with confidence info, or null if no good match
 */
function chatbot_vector_find_best_match($query, $threshold = CHATBOT_VECTOR_THRESHOLD_MIN) {
    $results = chatbot_vector_search($query, [
        'threshold' => $threshold,
        'limit' => 1,
        'return_scores' => true
    ]);

    if (!$results['success'] || empty($results['results'])) {
        return null;
    }

    $best = $results['results'][0];

    return [
        'match' => [
            'id' => $best['faq_id'],
            'question' => $best['question'],
            'answer' => $best['answer'],
            'category' => $best['category']
        ],
        'score' => $best['similarity'],
        'confidence' => $best['confidence'],
        'search_type' => $results['search_type']
    ];
}

/**
 * Get confidence level string from similarity score
 *
 * @param float $similarity Similarity score (0-1)
 * @return string Confidence level: 'very_high', 'high', 'medium', 'low', 'none'
 */
function chatbot_vector_get_confidence_level($similarity) {
    if ($similarity >= CHATBOT_VECTOR_THRESHOLD_VERY_HIGH) {
        return 'very_high';
    } elseif ($similarity >= CHATBOT_VECTOR_THRESHOLD_HIGH) {
        return 'high';
    } elseif ($similarity >= CHATBOT_VECTOR_THRESHOLD_MEDIUM) {
        return 'medium';
    } elseif ($similarity >= CHATBOT_VECTOR_THRESHOLD_LOW) {
        return 'low';
    }
    return 'none';
}

/**
 * Search with hybrid approach (vector + keyword boost)
 *
 * Combines vector similarity with keyword matching for better results.
 *
 * @param string $query The user's question
 * @param array $options Search options
 * @return array Search results with combined scoring
 */
function chatbot_vector_hybrid_search($query, $options = []) {
    $defaults = [
        'threshold' => CHATBOT_VECTOR_THRESHOLD_MIN,
        'limit' => 5,
        'vector_weight' => 0.8,  // Weight for vector similarity
        'keyword_weight' => 0.2, // Weight for keyword matching
        'category' => null
    ];
    $options = array_merge($defaults, $options);

    // Get vector search results
    $vector_results = chatbot_vector_search($query, [
        'threshold' => $options['threshold'] * 0.8, // Lower threshold for hybrid
        'limit' => $options['limit'] * 2, // Get more results to rerank
        'category' => $options['category'],
        'return_scores' => true
    ]);

    if (!$vector_results['success'] || empty($vector_results['results'])) {
        return $vector_results;
    }

    // Generate query keywords
    $query_keywords = chatbot_faq_generate_keywords($query);
    $query_words = array_filter(explode(' ', $query_keywords));

    // Rerank results with keyword boost
    $reranked = [];
    foreach ($vector_results['results'] as $result) {
        $vector_score = $result['similarity'];

        // Calculate keyword overlap
        $faq_keywords = chatbot_faq_generate_keywords($result['question']);
        $faq_words = array_filter(explode(' ', $faq_keywords));

        $keyword_matches = 0;
        foreach ($query_words as $qw) {
            foreach ($faq_words as $fw) {
                if ($qw === $fw || (strlen($qw) > 4 && strpos($fw, $qw) !== false)) {
                    $keyword_matches++;
                    break;
                }
            }
        }

        $keyword_score = count($query_words) > 0
            ? $keyword_matches / count($query_words)
            : 0;

        // Combined score
        $combined_score = ($vector_score * $options['vector_weight'])
                        + ($keyword_score * $options['keyword_weight']);

        $result['similarity'] = round($combined_score, 4);
        $result['vector_score'] = round($vector_score, 4);
        $result['keyword_score'] = round($keyword_score, 4);
        $result['confidence'] = chatbot_vector_get_confidence_level($combined_score);

        $reranked[] = $result;
    }

    // Sort by combined score
    usort($reranked, function($a, $b) {
        return $b['similarity'] <=> $a['similarity'];
    });

    // Filter by threshold and limit
    $filtered = array_filter($reranked, function($r) use ($options) {
        return $r['similarity'] >= $options['threshold'];
    });

    $final_results = array_slice(array_values($filtered), 0, $options['limit']);

    return [
        'success' => true,
        'results' => $final_results,
        'count' => count($final_results),
        'search_type' => 'hybrid'
    ];
}

/**
 * Main FAQ search function - replaces chatbot_faq_search()
 *
 * This is the main entry point that should be used throughout the plugin.
 * Uses vector search ONLY - no fallback.
 *
 * @param string $query The user's question
 * @param bool $return_score Whether to return score information
 * @param string|null $session_id Session ID for analytics
 * @param int|null $user_id User ID for analytics
 * @param int|null $page_id Page ID for analytics
 * @return array|null Match result or null if no match
 */
function chatbot_vector_faq_search($query, $return_score = false, $session_id = null, $user_id = null, $page_id = null) {
    // Vector search only - no fallback
    $result = chatbot_vector_find_best_match($query, CHATBOT_VECTOR_THRESHOLD_MIN);

    // Ver 2.5.0: Build conversation context string for gap question logging
    $context_string = null;
    if (function_exists('chatbot_vector_get_conversation_context')) {
        $context = chatbot_vector_get_conversation_context();
        if (!empty($context['history'])) {
            $context_parts = [];
            foreach ($context['history'] as $pair) {
                $context_parts[] = 'Q: ' . substr($pair['question'], 0, 100) . ' | A: ' . substr($pair['answer'], 0, 150);
            }
            $context_string = implode(' || ', $context_parts);
        }
    }

    // No match found
    if (!$result) {
        // Log as gap question with context
        if (function_exists('chatbot_log_gap_question')) {
            chatbot_log_gap_question($query, null, 0, 'none', $session_id, $user_id, $page_id, $context_string);
        }

        if ($return_score) {
            return ['match' => null, 'score' => 0, 'confidence' => 'none'];
        }
        return null;
    }

    // Track FAQ usage
    if (function_exists('chatbot_track_faq_usage') && isset($result['match']['id'])) {
        chatbot_track_faq_usage($result['match']['id'], $result['score']);
    }

    // Log gap questions for low confidence matches (< 0.6)
    if ($result['score'] < 0.6) {
        if (function_exists('chatbot_log_gap_question')) {
            chatbot_log_gap_question(
                $query,
                $result['match']['id'] ?? null,
                $result['score'],
                $result['confidence'],
                $session_id,
                $user_id,
                $page_id,
                $context_string // Ver 2.5.0: Include conversation context
            );
        }
    }

    if ($return_score) {
        return [
            'match' => $result['match'],
            'score' => $result['score'],
            'confidence' => $result['confidence'],
            'match_type' => $result['search_type']
        ];
    }

    return $result['match'];
}

/**
 * Get similar FAQs to a given FAQ (for "related questions" feature)
 *
 * @param string $faq_id The FAQ ID to find similar items for
 * @param int $limit Maximum number of similar FAQs to return
 * @return array Array of similar FAQs
 */
function chatbot_vector_get_similar_faqs($faq_id, $limit = 3) {
    $pdo = chatbot_vector_get_pg_connection();

    if (!$pdo) {
        error_log('[Chatbot Vector] Cannot get similar FAQs - no database connection');
        return [];
    }

    try {
        // Get the embedding of the source FAQ
        $stmt = $pdo->prepare('
            SELECT combined_embedding, category
            FROM chatbot_faqs
            WHERE faq_id = ?
        ');
        $stmt->execute([$faq_id]);
        $source = $stmt->fetch();

        if (!$source || !$source['combined_embedding']) {
            return [];
        }

        // Find similar FAQs (excluding the source)
        $stmt = $pdo->prepare('
            SELECT
                faq_id,
                question,
                answer,
                category,
                1 - (combined_embedding <=> ?::vector) AS similarity
            FROM chatbot_faqs
            WHERE faq_id != ?
            AND combined_embedding IS NOT NULL
            ORDER BY similarity DESC
            LIMIT ?
        ');
        $stmt->execute([
            $source['combined_embedding'],
            $faq_id,
            $limit
        ]);

        return $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log('[Chatbot Vector] Get similar FAQs failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Search FAQs within a specific category
 *
 * @param string $query Search query
 * @param string $category Category to search in
 * @param int $limit Maximum results
 * @return array Search results
 */
function chatbot_vector_search_by_category($query, $category, $limit = 5) {
    return chatbot_vector_search($query, [
        'category' => $category,
        'limit' => $limit,
        'threshold' => CHATBOT_VECTOR_THRESHOLD_LOW
    ]);
}

// ============================================
// Context-Aware Search Functions (Ver 2.5.0)
// ============================================

/**
 * Detect if a question is a follow-up that needs context
 *
 * @param string $query The user's question
 * @return array ['is_followup' => bool, 'reason' => string]
 */
function chatbot_vector_detect_followup($query) {
    $query_lower = strtolower(trim($query));
    $word_count = str_word_count($query_lower);

    // Pattern 1: Pronouns referring to previous topic
    $pronoun_patterns = [
        '/\b(them|they|it|that|those|these|this)\b/',
        '/\b(he|she|his|her|their)\b/',
    ];

    foreach ($pronoun_patterns as $pattern) {
        if (preg_match($pattern, $query_lower)) {
            // Short questions with pronouns are likely follow-ups
            if ($word_count <= 8) {
                return ['is_followup' => true, 'reason' => 'pronoun_reference'];
            }
        }
    }

    // Pattern 2: Common follow-up phrases
    $followup_patterns = [
        '/^(can you|could you|would you) (name|list|tell me|show me|give me)/',
        '/^(tell me|show me|give me) (more|about)/',
        '/^(what|how) about/',
        '/^(and|but|so|also) /',
        '/^(yes|no|sure|okay|ok|yeah|yep|nope)[\s,\.!\?]*/',
        '/^(more|another|other|else)[\s,\.!\?]/',
        '/\bmore (info|information|details|about)\b/',
        '/^why[\s\?]*$/',
        '/^how[\s\?]*$/',
        '/^which one/',
        '/^(the|a) (first|second|last|best|cheapest)/',
    ];

    foreach ($followup_patterns as $pattern) {
        if (preg_match($pattern, $query_lower)) {
            return ['is_followup' => true, 'reason' => 'followup_phrase'];
        }
    }

    // Pattern 3: Very short questions (1-3 words) are often follow-ups
    if ($word_count <= 3 && !preg_match('/^(hi|hello|hey|thanks|thank you|bye|goodbye)/', $query_lower)) {
        // Check if it's a complete question on its own
        $standalone_short = [
            '/^what (is|are) .+/',
            '/^how (do|does|can|much|many) .+/',
            '/^where (is|are|do|can) .+/',
            '/^who (is|are) .+/',
        ];

        $is_standalone = false;
        foreach ($standalone_short as $pattern) {
            if (preg_match($pattern, $query_lower)) {
                $is_standalone = true;
                break;
            }
        }

        if (!$is_standalone) {
            return ['is_followup' => true, 'reason' => 'short_question'];
        }
    }

    return ['is_followup' => false, 'reason' => 'standalone'];
}

/**
 * Get conversation context from transient
 * Updated Ver 2.5.0: Now captures up to last 3 Q&A pairs for better context
 *
 * @return array ['last_question' => string, 'last_answer' => string, 'topic' => string, 'history' => array]
 */
function chatbot_vector_get_conversation_context() {
    $context_history = get_transient('chatbot_chatgpt_context_history');

    // Ver 2.5.0: Debug what's in the transient
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Chatbot Context] Raw transient: ' . (empty($context_history) ? 'EMPTY' : print_r($context_history, true)));
    }

    if (empty($context_history) || !is_array($context_history)) {
        return ['last_question' => '', 'last_answer' => '', 'topic' => '', 'history' => []];
    }

    // Get up to last 6 entries (3 Q&A pairs max)
    $recent = array_slice($context_history, -6);

    $last_question = '';
    $last_answer = '';
    $history = []; // Store all Q&A pairs found

    // Ver 2.5.0: Entries alternate between user message and bot response (no prefix)
    // Even indices (0, 2, 4) are user messages, odd indices (1, 3, 5) are bot responses
    for ($i = 0; $i < count($recent) - 1; $i += 2) {
        $user_msg = isset($recent[$i]) ? $recent[$i] : '';
        $bot_msg = isset($recent[$i + 1]) ? $recent[$i + 1] : '';

        if (!empty($user_msg) && !empty($bot_msg)) {
            $history[] = ['question' => $user_msg, 'answer' => $bot_msg];
        }
    }

    // Get the most recent Q&A for backwards compatibility
    if (!empty($history)) {
        $last_pair = end($history);
        $last_question = $last_pair['question'];
        $last_answer = $last_pair['answer'];
    }

    // Extract topic keywords from all Q&A pairs
    $topic = '';
    if (!empty($history)) {
        $combined = '';
        foreach ($history as $pair) {
            $combined .= ' ' . $pair['question'] . ' ' . $pair['answer'];
        }
        $topic = chatbot_vector_extract_topic($combined);
    }

    return [
        'last_question' => $last_question,
        'last_answer' => $last_answer,
        'topic' => $topic,
        'history' => $history
    ];
}

/**
 * Extract main topic from text for context enrichment
 *
 * @param string $text The text to extract topic from
 * @return string Extracted topic keywords
 */
function chatbot_vector_extract_topic($text) {
    // Remove common words and extract key terms
    $text = strtolower($text);

    // Common stop words to remove
    $stop_words = ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
        'may', 'might', 'must', 'can', 'to', 'of', 'in', 'for', 'on', 'with', 'at',
        'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after', 'above',
        'below', 'between', 'under', 'again', 'further', 'then', 'once', 'here',
        'there', 'when', 'where', 'why', 'how', 'all', 'each', 'few', 'more', 'most',
        'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so',
        'than', 'too', 'very', 'just', 'and', 'but', 'if', 'or', 'because', 'until',
        'while', 'about', 'against', 'i', 'you', 'your', 'we', 'our', 'they', 'their',
        'what', 'which', 'who', 'whom', 'this', 'that', 'these', 'those', 'am', 'it',
        'its', 'yes', 'no', 'okay', 'ok', 'sure', 'please', 'thanks', 'thank'];

    // Split into words
    $words = preg_split('/\s+/', preg_replace('/[^\w\s]/', '', $text));

    // Filter out stop words and short words
    $keywords = array_filter($words, function($word) use ($stop_words) {
        return strlen($word) > 2 && !in_array($word, $stop_words);
    });

    // Return top keywords (limit to avoid too long query)
    $keywords = array_slice(array_unique($keywords), 0, 5);

    return implode(' ', $keywords);
}

/**
 * Enrich a follow-up query with conversation context
 *
 * @param string $query The user's follow-up question
 * @param array $context Conversation context from chatbot_vector_get_conversation_context()
 * @return string Enriched query for vector search
 */
function chatbot_vector_enrich_query($query, $context) {
    if (empty($context['topic']) && empty($context['last_question'])) {
        return $query; // No context available
    }

    // Build enriched query
    $enriched_parts = [];

    // Add topic context
    if (!empty($context['topic'])) {
        $enriched_parts[] = 'Topic: ' . $context['topic'];
    }

    // Add the original query
    $enriched_parts[] = 'Question: ' . $query;

    // If the last question provides better context, include it
    if (!empty($context['last_question']) && strlen($context['last_question']) > 10) {
        $enriched_parts[] = 'Context: ' . substr($context['last_question'], 0, 100);
    }

    return implode('. ', $enriched_parts);
}

/**
 * Context-aware FAQ search - handles follow-up questions
 *
 * This is the main entry point for context-aware search.
 * It detects follow-up questions and enriches them with conversation context.
 * Updated Ver 2.5.0: Logs gap questions WITH conversation context for better analysis
 *
 * @param string $query The user's question
 * @param bool $return_score Whether to return score information
 * @param string|null $session_id Session ID for analytics
 * @param int|null $user_id User ID for analytics
 * @param int|null $page_id Page ID for analytics
 * @return array|null Match result or null if no match
 */
function chatbot_vector_context_aware_search($query, $return_score = false, $session_id = null, $user_id = null, $page_id = null) {
    // Step 1: Detect if this is a follow-up question
    $followup_check = chatbot_vector_detect_followup($query);

    $search_query = $query;
    $used_context = false;
    $context = null;
    $context_string = null;

    // Ver 2.5.0: ALWAYS get conversation context for gap question logging
    // Even if it's not a follow-up, we want to know what they were discussing
    $context = chatbot_vector_get_conversation_context();

    // Build context string for gap question logging (Ver 2.5.0)
    // Include up to 2 Q&A pairs for conversation context
    if (!empty($context['history'])) {
        $context_parts = [];
        $pair_num = 1;
        $max_pairs = 2; // Limit to 2 pairs to avoid clutter
        foreach (array_slice($context['history'], 0, $max_pairs) as $pair) {
            if (!empty($pair['question'])) {
                $context_parts[] = 'Q' . $pair_num . ': ' . substr($pair['question'], 0, 100);
            }
            if (!empty($pair['answer'])) {
                $context_parts[] = 'A' . $pair_num . ': ' . substr($pair['answer'], 0, 120);
            }
            $pair_num++;
        }
        if (!empty($context_parts)) {
            $context_string = implode(' | ', $context_parts);
        }
    }

    // Step 2: If follow-up, enrich with context for better search
    if ($followup_check['is_followup']) {
        if (!empty($context['topic']) || !empty($context['last_question'])) {
            $search_query = chatbot_vector_enrich_query($query, $context);
            $used_context = true;

            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Chatbot Context] Follow-up detected (' . $followup_check['reason'] . '): "' . $query . '"');
                error_log('[Chatbot Context] Enriched query: "' . $search_query . '"');
            }
        }
    }

    // Debug: Log context availability
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Chatbot Context] Context for gap logging: ' . ($context_string ? '"' . substr($context_string, 0, 150) . '..."' : 'none (first message or cleared)'));
    }

    // Step 3: Perform the search with (potentially enriched) query
    $result = chatbot_vector_find_best_match($search_query, CHATBOT_VECTOR_THRESHOLD_MIN);

    // Step 4: If enriched search failed, try original query as fallback
    if (!$result && $used_context) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Chatbot Context] Enriched search failed, trying original query');
        }
        $result = chatbot_vector_find_best_match($query, CHATBOT_VECTOR_THRESHOLD_MIN);
        $used_context = false; // Mark that we fell back
    }

    // No match found - log as gap question WITH context (Ver 2.5.0)
    if (!$result) {
        if (function_exists('chatbot_log_gap_question')) {
            // For follow-ups, include the conversation context so we know what they were asking about
            chatbot_log_gap_question($query, null, 0, 'none', $session_id, $user_id, $page_id, $context_string);

            if (defined('WP_DEBUG') && WP_DEBUG && $context_string) {
                error_log('[Chatbot Context] Gap question logged WITH context: "' . $query . '"');
            }
        }

        if ($return_score) {
            return [
                'match' => null,
                'score' => 0,
                'confidence' => 'none',
                'used_context' => $used_context,
                'is_followup' => $followup_check['is_followup']
            ];
        }
        return null;
    }

    // Track FAQ usage
    if (function_exists('chatbot_track_faq_usage') && isset($result['match']['id'])) {
        chatbot_track_faq_usage($result['match']['id'], $result['score']);
    }

    // Log gap questions for low confidence matches - include context (Ver 2.5.0)
    if ($result['score'] < 0.6) {
        if (function_exists('chatbot_log_gap_question')) {
            chatbot_log_gap_question(
                $query,
                $result['match']['id'] ?? null,
                $result['score'],
                $result['confidence'],
                $session_id,
                $user_id,
                $page_id,
                $context_string // Include context for better gap analysis
            );
        }
    }

    if ($return_score) {
        return [
            'match' => $result['match'],
            'score' => $result['score'],
            'confidence' => $result['confidence'],
            'match_type' => $result['search_type'],
            'used_context' => $used_context,
            'is_followup' => $followup_check['is_followup']
        ];
    }

    return $result['match'];
}
