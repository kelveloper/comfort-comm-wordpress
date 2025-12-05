<?php
/**
 * Chatbot Vector Search - Database Schema
 *
 * PostgreSQL + pgvector schema for semantic FAQ search.
 * Uses OpenAI text-embedding-3-small (1536 dimensions).
 *
 * @package comfort-comm-chatbot
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die();
}

/**
 * SQL to create the FAQs table with vector embeddings.
 *
 * Run this in your PostgreSQL database:
 *
 * -- First, enable the pgvector extension
 * CREATE EXTENSION IF NOT EXISTS vector;
 *
 * -- Create the FAQs table with embeddings
 * CREATE TABLE IF NOT EXISTS chatbot_faqs (
 *     id SERIAL PRIMARY KEY,
 *     faq_id VARCHAR(50) UNIQUE NOT NULL,
 *     question TEXT NOT NULL,
 *     answer TEXT NOT NULL,
 *     category VARCHAR(255),
 *     keywords TEXT,
 *     question_embedding vector(1536),
 *     answer_embedding vector(1536),
 *     combined_embedding vector(1536),
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * );
 *
 * -- Create index for fast cosine similarity search
 * CREATE INDEX IF NOT EXISTS idx_faqs_combined_embedding
 * ON chatbot_faqs USING ivfflat (combined_embedding vector_cosine_ops)
 * WITH (lists = 100);
 *
 * -- Create index on faq_id for lookups
 * CREATE INDEX IF NOT EXISTS idx_faqs_faq_id ON chatbot_faqs(faq_id);
 *
 * -- Create index on category for filtering
 * CREATE INDEX IF NOT EXISTS idx_faqs_category ON chatbot_faqs(category);
 */

/**
 * PostgreSQL connection configuration.
 * Add these constants to wp-config.php:
 *
 * define('CHATBOT_PG_HOST', 'localhost');
 * define('CHATBOT_PG_PORT', '5432');
 * define('CHATBOT_PG_DATABASE', 'chatbot_vectors');
 * define('CHATBOT_PG_USER', 'your_username');
 * define('CHATBOT_PG_PASSWORD', 'your_password');
 */

/**
 * Get PostgreSQL PDO connection (if pdo_pgsql is available)
 *
 * @return PDO|null PostgreSQL PDO instance or null on failure
 */
function chatbot_vector_get_pg_connection() {
    static $pdo = null;
    static $connection_attempted = false;

    // Return cached connection if we have one
    if ($pdo !== null) {
        return $pdo;
    }

    // Don't retry if we already failed once this request
    if ($connection_attempted) {
        return null;
    }
    $connection_attempted = true;

    // Check if PDO PostgreSQL extension is available
    if (!extension_loaded('pdo_pgsql')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Chatbot Vector] PDO pgsql extension not loaded');
        }
        return null;
    }

    // Check if PostgreSQL config is defined
    if (!defined('CHATBOT_PG_HOST') || !defined('CHATBOT_PG_DATABASE')) {
        error_log('[Chatbot Vector] PostgreSQL configuration not found in wp-config.php');
        return null;
    }

    $host = CHATBOT_PG_HOST;
    $port = defined('CHATBOT_PG_PORT') ? CHATBOT_PG_PORT : '5432';
    $dbname = CHATBOT_PG_DATABASE;
    $user = defined('CHATBOT_PG_USER') ? CHATBOT_PG_USER : 'postgres';
    $password = defined('CHATBOT_PG_PASSWORD') ? CHATBOT_PG_PASSWORD : '';

    try {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Chatbot Vector] PostgreSQL connected successfully to ' . $host);
        }

        return $pdo;

    } catch (PDOException $e) {
        error_log('[Chatbot Vector] PostgreSQL connection failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get Supabase configuration for REST API
 *
 * @return array|null Supabase config or null if not configured
 */
function chatbot_vector_get_supabase_config() {
    if (!defined('CHATBOT_PG_HOST')) {
        return null;
    }

    // Extract project ref from host (e.g., db.xxxxx.supabase.co -> xxxxx)
    $host = CHATBOT_PG_HOST;
    if (preg_match('/db\.([a-z0-9]+)\.supabase\.co/', $host, $matches)) {
        $project_ref = $matches[1];
        return [
            'url' => 'https://' . $project_ref . '.supabase.co',
            'anon_key' => defined('CHATBOT_SUPABASE_ANON_KEY') ? CHATBOT_SUPABASE_ANON_KEY : null,
            'service_key' => defined('CHATBOT_SUPABASE_SERVICE_KEY') ? CHATBOT_SUPABASE_SERVICE_KEY : null,
        ];
    }

    return null;
}

/**
 * Make a Supabase REST API request
 *
 * @param string $endpoint The API endpoint (e.g., '/rest/v1/chatbot_faqs')
 * @param string $method HTTP method (GET, POST, etc.)
 * @param array $params Query parameters or body data
 * @param bool $use_service_key Use service key instead of anon key
 * @return array|null Response data or null on failure
 */
function chatbot_vector_supabase_request($endpoint, $method = 'GET', $params = [], $use_service_key = false) {
    $config = chatbot_vector_get_supabase_config();

    if (!$config) {
        error_log('[Chatbot Vector] Supabase configuration not found');
        return null;
    }

    $api_key = $use_service_key ? $config['service_key'] : $config['anon_key'];
    if (!$api_key) {
        error_log('[Chatbot Vector] Supabase API key not configured');
        return null;
    }

    $url = $config['url'] . $endpoint;

    $headers = [
        'apikey' => $api_key,
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json',
        'Prefer' => 'return=representation',
    ];

    $args = [
        'method' => $method,
        'headers' => $headers,
        'timeout' => 30,
    ];

    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
    } elseif (!empty($params)) {
        $args['body'] = json_encode($params);
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        error_log('[Chatbot Vector] Supabase request failed: ' . $response->get_error_message());
        return null;
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status >= 400) {
        $error = isset($data['message']) ? $data['message'] : 'Unknown error';
        error_log('[Chatbot Vector] Supabase API error: ' . $error);
        return null;
    }

    return $data;
}

/**
 * Initialize the vector database schema
 *
 * @return array Result with success status and message
 */
function chatbot_vector_init_schema() {
    $pdo = chatbot_vector_get_pg_connection();

    if (!$pdo) {
        return [
            'success' => false,
            'message' => 'Could not connect to PostgreSQL database'
        ];
    }

    try {
        // Enable pgvector extension
        $pdo->exec('CREATE EXTENSION IF NOT EXISTS vector');

        // Create FAQs table with vector columns
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS chatbot_faqs (
                id SERIAL PRIMARY KEY,
                faq_id VARCHAR(50) UNIQUE NOT NULL,
                question TEXT NOT NULL,
                answer TEXT NOT NULL,
                category VARCHAR(255),
                keywords TEXT,
                question_embedding vector(1536),
                answer_embedding vector(1536),
                combined_embedding vector(1536),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Create IVFFlat index for fast similarity search
        // Note: IVFFlat requires at least some data to be present before creating
        // For small datasets (<1000), you might skip this and use exact search
        $pdo->exec('
            CREATE INDEX IF NOT EXISTS idx_faqs_faq_id ON chatbot_faqs(faq_id)
        ');

        $pdo->exec('
            CREATE INDEX IF NOT EXISTS idx_faqs_category ON chatbot_faqs(category)
        ');

        return [
            'success' => true,
            'message' => 'Vector database schema initialized successfully'
        ];

    } catch (PDOException $e) {
        error_log('[Chatbot Vector] Schema initialization failed: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Schema initialization failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Create the IVFFlat index for faster similarity search
 * Call this AFTER populating the table with data
 *
 * @param int $lists Number of lists for IVFFlat (default 100, use sqrt(n) as guideline)
 * @return array Result with success status and message
 */
function chatbot_vector_create_search_index($lists = 10) {
    $pdo = chatbot_vector_get_pg_connection();

    if (!$pdo) {
        return [
            'success' => false,
            'message' => 'Could not connect to PostgreSQL database'
        ];
    }

    try {
        // Drop existing index if it exists
        $pdo->exec('DROP INDEX IF EXISTS idx_faqs_combined_embedding');

        // Create IVFFlat index for cosine similarity
        // For 66 FAQs, lists = 10 is appropriate (sqrt(66) ≈ 8)
        $pdo->exec("
            CREATE INDEX idx_faqs_combined_embedding
            ON chatbot_faqs USING ivfflat (combined_embedding vector_cosine_ops)
            WITH (lists = {$lists})
        ");

        return [
            'success' => true,
            'message' => "Search index created with {$lists} lists"
        ];

    } catch (PDOException $e) {
        error_log('[Chatbot Vector] Index creation failed: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Index creation failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Check if vector search is available
 *
 * @return bool True if PostgreSQL with pgvector is configured and accessible
 */
function chatbot_vector_is_available() {
    // Check PDO connection first
    $pdo = chatbot_vector_get_pg_connection();

    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    // Fall back to checking Supabase REST API config
    $config = chatbot_vector_get_supabase_config();
    return ($config && !empty($config['anon_key']));
}

/**
 * Get vector database statistics
 *
 * @return array Stats including FAQ count, index status, etc.
 */
function chatbot_vector_get_stats() {
    $pdo = chatbot_vector_get_pg_connection();

    if (!$pdo) {
        return [
            'available' => false,
            'error' => 'PostgreSQL connection not available'
        ];
    }

    try {
        $stats = [
            'available' => true,
            'connection' => 'ok'
        ];

        // Get FAQ count
        $stmt = $pdo->query('SELECT COUNT(*) FROM chatbot_faqs');
        $stats['faq_count'] = (int) $stmt->fetchColumn();

        // Get count of FAQs with embeddings
        $stmt = $pdo->query('SELECT COUNT(*) FROM chatbot_faqs WHERE combined_embedding IS NOT NULL');
        $stats['faqs_with_embeddings'] = (int) $stmt->fetchColumn();

        // Get category breakdown
        $stmt = $pdo->query('
            SELECT category, COUNT(*) as count
            FROM chatbot_faqs
            GROUP BY category
            ORDER BY count DESC
        ');
        $stats['categories'] = $stmt->fetchAll();

        // Check if index exists
        $stmt = $pdo->query("
            SELECT 1 FROM pg_indexes
            WHERE indexname = 'idx_faqs_combined_embedding'
        ");
        $stats['search_index_exists'] = $stmt->fetchColumn() !== false;

        return $stats;

    } catch (PDOException $e) {
        return [
            'available' => false,
            'error' => $e->getMessage()
        ];
    }
}
