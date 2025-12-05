<?php
/**
 * Chatbot Supabase - Complete Database Schema
 *
 * All SQL required to create the Supabase/PostgreSQL tables.
 * Used by the Database Setup Wizard.
 *
 * @package comfort-comm-chatbot
 * @since 2.5.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die();
}

/**
 * Get complete SQL schema for all tables
 *
 * @return array Array of table definitions with name, sql, and description
 */
function chatbot_supabase_get_schema() {
    return [
        // 1. FAQs Table (with vector embeddings for semantic search)
        [
            'name' => 'chatbot_faqs',
            'description' => 'FAQ entries with vector embeddings for semantic search',
            'sql' => "
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
                );
            ",
            'indexes' => [
                "CREATE INDEX IF NOT EXISTS idx_faqs_faq_id ON chatbot_faqs(faq_id);",
                "CREATE INDEX IF NOT EXISTS idx_faqs_category ON chatbot_faqs(category);"
            ]
        ],

        // 2. Conversations Table (chat logs)
        [
            'name' => 'chatbot_conversations',
            'description' => 'Conversation logs between users and chatbot',
            'sql' => "
                CREATE TABLE IF NOT EXISTS chatbot_conversations (
                    id SERIAL PRIMARY KEY,
                    session_id VARCHAR(255) NOT NULL,
                    user_id VARCHAR(50) DEFAULT '0',
                    page_id VARCHAR(50) DEFAULT '0',
                    user_type VARCHAR(50) DEFAULT 'Visitor',
                    thread_id VARCHAR(255),
                    assistant_id VARCHAR(255),
                    assistant_name VARCHAR(255),
                    message_text TEXT NOT NULL,
                    interaction_time TIMESTAMPTZ DEFAULT NOW(),
                    sentiment_score DECIMAL(5,4)
                );
            ",
            'indexes' => [
                "CREATE INDEX IF NOT EXISTS idx_conversations_session ON chatbot_conversations(session_id);",
                "CREATE INDEX IF NOT EXISTS idx_conversations_user ON chatbot_conversations(user_id);",
                "CREATE INDEX IF NOT EXISTS idx_conversations_time ON chatbot_conversations(interaction_time DESC);"
            ]
        ],

        // 3. Gap Questions Table (low-confidence questions for analysis)
        [
            'name' => 'chatbot_gap_questions',
            'description' => 'Low-confidence questions that need FAQ coverage',
            'sql' => "
                CREATE TABLE IF NOT EXISTS chatbot_gap_questions (
                    id SERIAL PRIMARY KEY,
                    question_text TEXT NOT NULL,
                    session_id VARCHAR(255),
                    user_id INTEGER DEFAULT 0,
                    page_id INTEGER DEFAULT 0,
                    faq_confidence DECIMAL(5,4),
                    faq_match_id VARCHAR(50),
                    asked_date TIMESTAMPTZ DEFAULT NOW(),
                    is_clustered BOOLEAN DEFAULT FALSE,
                    cluster_id INTEGER,
                    is_resolved BOOLEAN DEFAULT FALSE,
                    embedding vector(1536),
                    quality_score DECIMAL(5,4),
                    validation_flags JSONB,
                    conversation_context TEXT
                );
            ",
            'indexes' => [
                "CREATE INDEX IF NOT EXISTS idx_gap_questions_clustered ON chatbot_gap_questions(is_clustered);",
                "CREATE INDEX IF NOT EXISTS idx_gap_questions_resolved ON chatbot_gap_questions(is_resolved);",
                "CREATE INDEX IF NOT EXISTS idx_gap_questions_date ON chatbot_gap_questions(asked_date DESC);",
                "CREATE INDEX IF NOT EXISTS idx_gap_questions_cluster ON chatbot_gap_questions(cluster_id);"
            ]
        ],

        // 4. Gap Clusters Table (AI-grouped similar questions)
        [
            'name' => 'chatbot_gap_clusters',
            'description' => 'AI-clustered groups of similar unanswered questions',
            'sql' => "
                CREATE TABLE IF NOT EXISTS chatbot_gap_clusters (
                    id SERIAL PRIMARY KEY,
                    cluster_name VARCHAR(255),
                    representative_question TEXT,
                    question_count INTEGER DEFAULT 0,
                    suggested_answer TEXT,
                    suggested_category VARCHAR(255),
                    status VARCHAR(50) DEFAULT 'pending',
                    priority VARCHAR(20) DEFAULT 'medium',
                    avg_quality_score DECIMAL(5,4),
                    created_at TIMESTAMPTZ DEFAULT NOW(),
                    updated_at TIMESTAMPTZ DEFAULT NOW(),
                    resolved_at TIMESTAMPTZ,
                    resolved_by VARCHAR(255),
                    created_faq_id VARCHAR(50)
                );
            ",
            'indexes' => [
                "CREATE INDEX IF NOT EXISTS idx_gap_clusters_status ON chatbot_gap_clusters(status);",
                "CREATE INDEX IF NOT EXISTS idx_gap_clusters_priority ON chatbot_gap_clusters(priority);",
                "CREATE INDEX IF NOT EXISTS idx_gap_clusters_created ON chatbot_gap_clusters(created_at DESC);"
            ]
        ],

        // 5. FAQ Usage Table (analytics for FAQ hits)
        [
            'name' => 'chatbot_faq_usage',
            'description' => 'Tracks how often each FAQ is used',
            'sql' => "
                CREATE TABLE IF NOT EXISTS chatbot_faq_usage (
                    id SERIAL PRIMARY KEY,
                    faq_id VARCHAR(50) NOT NULL UNIQUE,
                    hit_count INTEGER DEFAULT 0,
                    last_asked TIMESTAMPTZ,
                    avg_confidence DECIMAL(5,4),
                    created_at TIMESTAMPTZ DEFAULT NOW(),
                    updated_at TIMESTAMPTZ DEFAULT NOW()
                );
            ",
            'indexes' => [
                "CREATE INDEX IF NOT EXISTS idx_faq_usage_faq ON chatbot_faq_usage(faq_id);",
                "CREATE INDEX IF NOT EXISTS idx_faq_usage_hits ON chatbot_faq_usage(hit_count DESC);"
            ]
        ],

        // 6. Interactions Table (daily interaction counts)
        [
            'name' => 'chatbot_interactions',
            'description' => 'Daily aggregated interaction counts',
            'sql' => "
                CREATE TABLE IF NOT EXISTS chatbot_interactions (
                    id SERIAL PRIMARY KEY,
                    date DATE NOT NULL UNIQUE,
                    count INTEGER DEFAULT 0
                );
            ",
            'indexes' => [
                "CREATE INDEX IF NOT EXISTS idx_interactions_date ON chatbot_interactions(date DESC);"
            ]
        ],

        // 7. Assistants Table (OpenAI assistant configurations)
        [
            'name' => 'chatbot_assistants',
            'description' => 'OpenAI Assistant configurations',
            'sql' => "
                CREATE TABLE IF NOT EXISTS chatbot_assistants (
                    id SERIAL PRIMARY KEY,
                    assistant_id VARCHAR(255) NOT NULL UNIQUE,
                    assistant_name VARCHAR(255),
                    assistant_type VARCHAR(50) DEFAULT 'primary',
                    common_name VARCHAR(255),
                    style VARCHAR(50) DEFAULT 'floating',
                    audience VARCHAR(50) DEFAULT 'all',
                    voice VARCHAR(50) DEFAULT 'alloy',
                    max_num_tokens INTEGER DEFAULT 150,
                    allow_file_uploads VARCHAR(10) DEFAULT 'No',
                    allow_speech_recognition VARCHAR(10) DEFAULT 'No',
                    initial_greeting TEXT,
                    subsequent_greeting TEXT,
                    additional_instructions TEXT,
                    last_used TIMESTAMPTZ,
                    created_at TIMESTAMPTZ DEFAULT NOW(),
                    updated_at TIMESTAMPTZ DEFAULT NOW()
                );
            ",
            'indexes' => [
                "CREATE INDEX IF NOT EXISTS idx_assistants_id ON chatbot_assistants(assistant_id);",
                "CREATE INDEX IF NOT EXISTS idx_assistants_type ON chatbot_assistants(assistant_type);"
            ]
        ]
    ];
}

/**
 * Get SQL to enable pgvector extension
 */
function chatbot_supabase_get_pgvector_sql() {
    return "CREATE EXTENSION IF NOT EXISTS vector;";
}

/**
 * Get SQL for Row Level Security (RLS) policies
 */
function chatbot_supabase_get_rls_sql() {
    $tables = [
        'chatbot_faqs',
        'chatbot_conversations',
        'chatbot_gap_questions',
        'chatbot_gap_clusters',
        'chatbot_faq_usage',
        'chatbot_interactions',
        'chatbot_assistants'
    ];

    $sql = [];
    foreach ($tables as $table) {
        // Enable RLS
        $sql[] = "ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;";

        // Allow anon to read
        $sql[] = "CREATE POLICY IF NOT EXISTS \"Allow anon read {$table}\" ON {$table} FOR SELECT TO anon USING (true);";

        // Allow anon to insert
        $sql[] = "CREATE POLICY IF NOT EXISTS \"Allow anon insert {$table}\" ON {$table} FOR INSERT TO anon WITH CHECK (true);";

        // Allow anon to update
        $sql[] = "CREATE POLICY IF NOT EXISTS \"Allow anon update {$table}\" ON {$table} FOR UPDATE TO anon USING (true);";

        // Allow anon to delete
        $sql[] = "CREATE POLICY IF NOT EXISTS \"Allow anon delete {$table}\" ON {$table} FOR DELETE TO anon USING (true);";
    }

    return $sql;
}

/**
 * Get complete setup SQL as a single script
 */
function chatbot_supabase_get_full_setup_sql() {
    $sql = [];

    // 1. Enable pgvector
    $sql[] = "-- Enable pgvector extension for semantic search";
    $sql[] = chatbot_supabase_get_pgvector_sql();
    $sql[] = "";

    // 2. Create tables
    $schema = chatbot_supabase_get_schema();
    foreach ($schema as $table) {
        $sql[] = "-- Table: {$table['name']}";
        $sql[] = "-- {$table['description']}";
        $sql[] = trim($table['sql']);
        $sql[] = "";

        // Add indexes
        foreach ($table['indexes'] as $index) {
            $sql[] = $index;
        }
        $sql[] = "";
    }

    // 3. RLS policies (commented out by default - enable if needed)
    $sql[] = "-- Row Level Security (RLS) Policies";
    $sql[] = "-- Uncomment and run these if you need RLS enabled:";
    foreach (chatbot_supabase_get_rls_sql() as $rls) {
        $sql[] = "-- " . $rls;
    }

    return implode("\n", $sql);
}
