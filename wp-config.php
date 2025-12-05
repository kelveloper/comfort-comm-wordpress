<?php
/**
 * WordPress Configuration for Render Deployment
 * Uses environment variables for sensitive data
 */

// ** Database settings ** //
define( 'DB_NAME', getenv('WORDPRESS_DB_NAME') ?: 'wordpress' );
define( 'DB_USER', getenv('WORDPRESS_DB_USER') ?: 'wordpress' );
define( 'DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: '' );
define( 'DB_HOST', getenv('WORDPRESS_DB_HOST') ?: 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

/**
 * PostgreSQL Vector Search Configuration (Supabase)
 * Used for semantic FAQ search with pgvector embeddings
 */
define( 'CHATBOT_PG_HOST', getenv('CHATBOT_PG_HOST') ?: 'db.tlpvjrbmxxggubnjmdhe.supabase.co' );
define( 'CHATBOT_PG_PORT', getenv('CHATBOT_PG_PORT') ?: '5432' );
define( 'CHATBOT_PG_DATABASE', getenv('CHATBOT_PG_DATABASE') ?: 'postgres' );
define( 'CHATBOT_PG_USER', getenv('CHATBOT_PG_USER') ?: 'postgres' );
define( 'CHATBOT_PG_PASSWORD', getenv('CHATBOT_PG_PASSWORD') ?: '' );
define( 'CHATBOT_SUPABASE_ANON_KEY', getenv('CHATBOT_SUPABASE_ANON_KEY') ?: '' );

/**
 * Authentication unique keys and salts.
 * Generate new ones at: https://api.wordpress.org/secret-key/1.1/salt/
 */
define( 'AUTH_KEY',         getenv('AUTH_KEY') ?: 'put-your-unique-phrase-here' );
define( 'SECURE_AUTH_KEY',  getenv('SECURE_AUTH_KEY') ?: 'put-your-unique-phrase-here' );
define( 'LOGGED_IN_KEY',    getenv('LOGGED_IN_KEY') ?: 'put-your-unique-phrase-here' );
define( 'NONCE_KEY',        getenv('NONCE_KEY') ?: 'put-your-unique-phrase-here' );
define( 'AUTH_SALT',        getenv('AUTH_SALT') ?: 'put-your-unique-phrase-here' );
define( 'SECURE_AUTH_SALT', getenv('SECURE_AUTH_SALT') ?: 'put-your-unique-phrase-here' );
define( 'LOGGED_IN_SALT',   getenv('LOGGED_IN_SALT') ?: 'put-your-unique-phrase-here' );
define( 'NONCE_SALT',       getenv('NONCE_SALT') ?: 'put-your-unique-phrase-here' );

/** WordPress database table prefix */
$table_prefix = 'wp_';

/** Debugging - disable in production */
define( 'WP_DEBUG', getenv('WP_DEBUG') === 'true' );
define( 'WP_DEBUG_LOG', getenv('WP_DEBUG') === 'true' );
define( 'WP_DEBUG_DISPLAY', false );

/** Force HTTPS on Render */
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

/** Site URL - auto-detect or use environment variable */
if (getenv('RENDER_EXTERNAL_URL')) {
    define( 'WP_HOME', getenv('RENDER_EXTERNAL_URL') );
    define( 'WP_SITEURL', getenv('RENDER_EXTERNAL_URL') );
}

/** Absolute path to the WordPress directory */
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files */
require_once ABSPATH . 'wp-settings.php';
