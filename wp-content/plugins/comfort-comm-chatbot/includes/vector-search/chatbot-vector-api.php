<?php
/**
 * Chatbot Vector Search - REST API Endpoints
 *
 * Provides REST API endpoints for FAQ vector search.
 *
 * @package comfort-comm-chatbot
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die();
}

// Include dependencies
require_once plugin_dir_path(__FILE__) . 'chatbot-vector-integration.php';

/**
 * Register REST API routes
 */
function chatbot_vector_register_rest_routes() {
    $namespace = 'chatbot/v1';

    // Search FAQs endpoint
    register_rest_route($namespace, '/faq/search', [
        'methods' => 'POST',
        'callback' => 'chatbot_vector_api_search',
        'permission_callback' => 'chatbot_vector_api_permission_check',
        'args' => [
            'query' => [
                'required' => true,
                'type' => 'string',
                'description' => 'The search query',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'threshold' => [
                'required' => false,
                'type' => 'number',
                'default' => 0.40,
                'description' => 'Minimum similarity threshold (0-1)'
            ],
            'limit' => [
                'required' => false,
                'type' => 'integer',
                'default' => 5,
                'description' => 'Maximum number of results'
            ],
            'category' => [
                'required' => false,
                'type' => 'string',
                'description' => 'Filter by category'
            ],
            'include_related' => [
                'required' => false,
                'type' => 'boolean',
                'default' => false,
                'description' => 'Include related questions'
            ]
        ]
    ]);

    // Get single FAQ by ID
    register_rest_route($namespace, '/faq/(?P<id>[a-zA-Z0-9_-]+)', [
        'methods' => 'GET',
        'callback' => 'chatbot_vector_api_get_faq',
        'permission_callback' => 'chatbot_vector_api_permission_check',
        'args' => [
            'id' => [
                'required' => true,
                'type' => 'string',
                'description' => 'FAQ ID'
            ]
        ]
    ]);

    // Get all FAQs (paginated)
    register_rest_route($namespace, '/faq', [
        'methods' => 'GET',
        'callback' => 'chatbot_vector_api_list_faqs',
        'permission_callback' => 'chatbot_vector_api_permission_check',
        'args' => [
            'page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 1
            ],
            'per_page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 20
            ],
            'category' => [
                'required' => false,
                'type' => 'string'
            ]
        ]
    ]);

    // Add new FAQ (admin only)
    register_rest_route($namespace, '/faq', [
        'methods' => 'POST',
        'callback' => 'chatbot_vector_api_add_faq',
        'permission_callback' => 'chatbot_vector_api_admin_check',
        'args' => [
            'question' => [
                'required' => true,
                'type' => 'string'
            ],
            'answer' => [
                'required' => true,
                'type' => 'string'
            ],
            'category' => [
                'required' => false,
                'type' => 'string',
                'default' => ''
            ]
        ]
    ]);

    // Get similar FAQs
    register_rest_route($namespace, '/faq/(?P<id>[a-zA-Z0-9_-]+)/similar', [
        'methods' => 'GET',
        'callback' => 'chatbot_vector_api_similar_faqs',
        'permission_callback' => 'chatbot_vector_api_permission_check',
        'args' => [
            'id' => [
                'required' => true,
                'type' => 'string'
            ],
            'limit' => [
                'required' => false,
                'type' => 'integer',
                'default' => 3
            ]
        ]
    ]);

    // Get categories
    register_rest_route($namespace, '/faq/categories', [
        'methods' => 'GET',
        'callback' => 'chatbot_vector_api_categories',
        'permission_callback' => 'chatbot_vector_api_permission_check'
    ]);

    // Vector search status (admin only)
    register_rest_route($namespace, '/vector/status', [
        'methods' => 'GET',
        'callback' => 'chatbot_vector_api_status',
        'permission_callback' => 'chatbot_vector_api_admin_check'
    ]);

    // Trigger migration (admin only)
    register_rest_route($namespace, '/vector/migrate', [
        'methods' => 'POST',
        'callback' => 'chatbot_vector_api_migrate',
        'permission_callback' => 'chatbot_vector_api_admin_check',
        'args' => [
            'clear_existing' => [
                'required' => false,
                'type' => 'boolean',
                'default' => false
            ]
        ]
    ]);
}
add_action('rest_api_init', 'chatbot_vector_register_rest_routes');

/**
 * Permission check - allow public access for search
 */
function chatbot_vector_api_permission_check($request) {
    // Allow public access to search endpoints
    // You can add nonce verification or API key check here if needed
    return true;
}

/**
 * Admin permission check
 */
function chatbot_vector_api_admin_check($request) {
    return current_user_can('manage_options');
}

/**
 * Search FAQs endpoint handler
 *
 * POST /wp-json/chatbot/v1/faq/search
 *
 * Request body:
 * {
 *   "query": "How do I pay my bill?",
 *   "threshold": 0.40,
 *   "limit": 5,
 *   "category": "Billing",
 *   "include_related": true
 * }
 */
function chatbot_vector_api_search($request) {
    $query = $request->get_param('query');
    $threshold = (float) $request->get_param('threshold');
    $limit = (int) $request->get_param('limit');
    $category = $request->get_param('category');
    $include_related = (bool) $request->get_param('include_related');

    if (empty($query)) {
        return new WP_Error('invalid_query', 'Query parameter is required', ['status' => 400]);
    }

    // Get FAQ response with confidence information
    $response = chatbot_get_faq_response($query, [
        'include_related' => $include_related
    ]);

    // Also get top N matches for multi-result scenarios
    $search_options = [
        'threshold' => $threshold,
        'limit' => $limit,
        'return_scores' => true
    ];

    if (!empty($category)) {
        $search_options['category'] = $category;
    }

    $search_results = chatbot_vector_search($query, $search_options);

    return rest_ensure_response([
        'success' => true,
        'query' => $query,
        'best_match' => $response['found'] ? [
            'faq_id' => $response['faq_id'],
            'question' => $response['question'],
            'answer' => $response['answer'],
            'category' => $response['category'],
            'confidence' => $response['confidence'],
            'score' => $response['score'],
            'strategy' => $response['strategy'] ?? 'unknown'
        ] : null,
        'all_results' => $search_results['results'],
        'total_results' => $search_results['count'],
        'search_type' => $search_results['search_type'],
        'related_questions' => $response['related_questions'] ?? [],
        'use_ai' => $response['use_ai'] ?? true,
        'ai_context' => $response['use_ai'] ? chatbot_build_faq_context($query, 3) : null
    ]);
}

/**
 * Get single FAQ by ID
 *
 * GET /wp-json/chatbot/v1/faq/{id}
 */
function chatbot_vector_api_get_faq($request) {
    $faq_id = $request->get_param('id');

    $pdo = chatbot_vector_get_pg_connection();

    if ($pdo) {
        // Try vector database first
        try {
            $stmt = $pdo->prepare('
                SELECT faq_id, question, answer, category, keywords, created_at
                FROM chatbot_faqs
                WHERE faq_id = ?
            ');
            $stmt->execute([$faq_id]);
            $faq = $stmt->fetch();

            if ($faq) {
                return rest_ensure_response([
                    'success' => true,
                    'faq' => $faq
                ]);
            }
        } catch (PDOException $e) {
            // Fall through to JSON lookup
        }
    }

    // Fallback to JSON file
    $faq = chatbot_faq_get_by_id($faq_id);

    if ($faq) {
        return rest_ensure_response([
            'success' => true,
            'faq' => $faq
        ]);
    }

    return new WP_Error('not_found', 'FAQ not found', ['status' => 404]);
}

/**
 * List all FAQs (paginated)
 *
 * GET /wp-json/chatbot/v1/faq?page=1&per_page=20&category=Billing
 */
function chatbot_vector_api_list_faqs($request) {
    $page = max(1, (int) $request->get_param('page'));
    $per_page = min(100, max(1, (int) $request->get_param('per_page')));
    $category = $request->get_param('category');

    $pdo = chatbot_vector_get_pg_connection();
    $faqs = [];
    $total = 0;

    if ($pdo) {
        try {
            // Count total
            $count_sql = 'SELECT COUNT(*) FROM chatbot_faqs';
            $params = [];

            if (!empty($category)) {
                $count_sql .= ' WHERE category = ?';
                $params[] = $category;
            }

            $stmt = $pdo->prepare($count_sql);
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();

            // Get page of results
            $offset = ($page - 1) * $per_page;
            $sql = 'SELECT faq_id, question, answer, category FROM chatbot_faqs';

            if (!empty($category)) {
                $sql .= ' WHERE category = ?';
            }

            $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
            $params[] = $per_page;
            $params[] = $offset;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $faqs = $stmt->fetchAll();

        } catch (PDOException $e) {
            // Fall through to JSON
        }
    }

    // Fallback to JSON file
    if (empty($faqs)) {
        $all_faqs = chatbot_faq_load();

        if (!empty($category)) {
            $all_faqs = array_filter($all_faqs, function($faq) use ($category) {
                return ($faq['category'] ?? '') === $category;
            });
        }

        $total = count($all_faqs);
        $offset = ($page - 1) * $per_page;
        $faqs = array_slice($all_faqs, $offset, $per_page);
    }

    return rest_ensure_response([
        'success' => true,
        'faqs' => $faqs,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => ceil($total / $per_page)
        ]
    ]);
}

/**
 * Add new FAQ
 *
 * POST /wp-json/chatbot/v1/faq
 *
 * Request body:
 * {
 *   "question": "What is your return policy?",
 *   "answer": "We offer 30-day returns...",
 *   "category": "Policies"
 * }
 */
function chatbot_vector_api_add_faq($request) {
    $question = sanitize_textarea_field($request->get_param('question'));
    $answer = sanitize_textarea_field($request->get_param('answer'));
    $category = sanitize_text_field($request->get_param('category'));

    if (empty($question) || empty($answer)) {
        return new WP_Error('invalid_input', 'Question and answer are required', ['status' => 400]);
    }

    // Add to vector database (also adds to JSON as backup)
    $result = chatbot_vector_add_faq($question, $answer, $category);

    if ($result['success']) {
        return rest_ensure_response([
            'success' => true,
            'message' => 'FAQ added successfully',
            'faq_id' => $result['faq_id'] ?? null
        ]);
    }

    return new WP_Error('add_failed', $result['message'], ['status' => 500]);
}

/**
 * Get similar FAQs
 *
 * GET /wp-json/chatbot/v1/faq/{id}/similar?limit=3
 */
function chatbot_vector_api_similar_faqs($request) {
    $faq_id = $request->get_param('id');
    $limit = (int) $request->get_param('limit');

    $similar = chatbot_vector_get_similar_faqs($faq_id, $limit);

    return rest_ensure_response([
        'success' => true,
        'faq_id' => $faq_id,
        'similar' => $similar
    ]);
}

/**
 * Get all categories
 *
 * GET /wp-json/chatbot/v1/faq/categories
 */
function chatbot_vector_api_categories($request) {
    $categories = chatbot_faq_get_top_categories(20);

    return rest_ensure_response([
        'success' => true,
        'categories' => $categories
    ]);
}

/**
 * Get vector search status
 *
 * GET /wp-json/chatbot/v1/vector/status
 */
function chatbot_vector_api_status($request) {
    $available = chatbot_vector_is_available();
    $stats = $available ? chatbot_vector_get_stats() : null;

    return rest_ensure_response([
        'success' => true,
        'vector_search_available' => $available,
        'enabled' => get_option('chatbot_vector_search_enabled', 'yes') === 'yes',
        'stats' => $stats
    ]);
}

/**
 * Trigger FAQ migration
 *
 * POST /wp-json/chatbot/v1/vector/migrate
 *
 * Request body:
 * {
 *   "clear_existing": false
 * }
 */
function chatbot_vector_api_migrate($request) {
    $clear_existing = (bool) $request->get_param('clear_existing');

    // Increase time limit for long-running migration
    set_time_limit(300);

    $result = chatbot_vector_migrate_all_faqs($clear_existing);

    if ($result['success']) {
        return rest_ensure_response([
            'success' => true,
            'message' => $result['message'],
            'migrated' => $result['migrated'],
            'total' => $result['total']
        ]);
    }

    return new WP_Error('migration_failed', $result['message'], [
        'status' => 500,
        'errors' => $result['error_details'] ?? []
    ]);
}
