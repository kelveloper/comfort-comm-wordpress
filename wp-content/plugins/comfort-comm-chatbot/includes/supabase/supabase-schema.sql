-- Supabase Schema for Chatbot
-- Run this in Supabase SQL Editor: https://supabase.com/dashboard/project/YOUR_PROJECT_ID/sql
-- Replace YOUR_PROJECT_ID with your actual Supabase project ID

-- =============================================================================
-- IMPORTANT: Enable pgvector extension FIRST (required for embeddings)
-- =============================================================================
CREATE EXTENSION IF NOT EXISTS vector;

-- =============================================================================
-- Helper function: Update updated_at timestamp (create first, used by triggers)
-- =============================================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- =============================================================================
-- Table 1: chatbot_faqs (FAQ entries with vector embeddings)
-- =============================================================================
CREATE TABLE IF NOT EXISTS chatbot_faqs (
    id BIGSERIAL PRIMARY KEY,
    faq_id VARCHAR(50) UNIQUE NOT NULL,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(255),
    keywords TEXT,

    -- Vector embeddings (768 dimensions for Gemini text-embedding-004)
    question_embedding vector(768),
    answer_embedding vector(768),
    combined_embedding vector(768),

    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Indexes for FAQs
CREATE INDEX IF NOT EXISTS idx_faqs_faq_id ON chatbot_faqs(faq_id);
CREATE INDEX IF NOT EXISTS idx_faqs_category ON chatbot_faqs(category);
CREATE INDEX IF NOT EXISTS idx_faqs_created_at ON chatbot_faqs(created_at DESC);

-- Vector similarity index (IVFFlat) - for fast semantic search
CREATE INDEX IF NOT EXISTS idx_faqs_combined_embedding ON chatbot_faqs
    USING ivfflat (combined_embedding vector_cosine_ops) WITH (lists = 50);

-- Enable RLS
ALTER TABLE chatbot_faqs ENABLE ROW LEVEL SECURITY;

-- Policy: Allow all operations
DROP POLICY IF EXISTS "Allow all operations" ON chatbot_faqs;
CREATE POLICY "Allow all operations" ON chatbot_faqs
    FOR ALL USING (true) WITH CHECK (true);

-- =============================================================================
-- Table 2: chatbot_conversations (replaces wp_chatbot_chatgpt_conversation_log)
-- =============================================================================
CREATE TABLE IF NOT EXISTS chatbot_conversations (
    id BIGSERIAL PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    user_id VARCHAR(255),
    page_id VARCHAR(255),
    interaction_time TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    user_type VARCHAR(50) NOT NULL CHECK (user_type IN ('Chatbot', 'Visitor', 'Prompt Tokens', 'Completion Tokens', 'Total Tokens')),
    thread_id VARCHAR(255),
    assistant_id VARCHAR(255),
    assistant_name VARCHAR(255),
    message_text TEXT NOT NULL,
    sentiment_score FLOAT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_conversations_session_id ON chatbot_conversations(session_id);
CREATE INDEX IF NOT EXISTS idx_conversations_user_id ON chatbot_conversations(user_id);
CREATE INDEX IF NOT EXISTS idx_conversations_interaction_time ON chatbot_conversations(interaction_time);

-- Enable RLS (Row Level Security)
ALTER TABLE chatbot_conversations ENABLE ROW LEVEL SECURITY;

-- Policy: Allow all operations with anon key (for your plugin)
DROP POLICY IF EXISTS "Allow all operations" ON chatbot_conversations;
CREATE POLICY "Allow all operations" ON chatbot_conversations
    FOR ALL USING (true) WITH CHECK (true);

-- =============================================================================
-- Table 2: chatbot_interactions (replaces wp_chatbot_chatgpt_interactions)
-- =============================================================================
CREATE TABLE IF NOT EXISTS chatbot_interactions (
    id BIGSERIAL PRIMARY KEY,
    date DATE NOT NULL UNIQUE,
    count INTEGER DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Index for date lookups
CREATE INDEX IF NOT EXISTS idx_interactions_date ON chatbot_interactions(date);

-- Enable RLS
ALTER TABLE chatbot_interactions ENABLE ROW LEVEL SECURITY;

-- Policy: Allow all operations
DROP POLICY IF EXISTS "Allow all operations" ON chatbot_interactions;
CREATE POLICY "Allow all operations" ON chatbot_interactions
    FOR ALL USING (true) WITH CHECK (true);

-- =============================================================================
-- Table 3: chatbot_gap_questions (replaces wp_chatbot_gap_questions)
-- =============================================================================
CREATE TABLE IF NOT EXISTS chatbot_gap_questions (
    id BIGSERIAL PRIMARY KEY,
    question_text TEXT NOT NULL,
    session_id VARCHAR(255),
    user_id BIGINT,
    page_id BIGINT,
    faq_confidence FLOAT,
    faq_match_id VARCHAR(50),
    asked_date TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    is_clustered BOOLEAN DEFAULT FALSE,
    cluster_id INTEGER,
    is_resolved BOOLEAN DEFAULT FALSE,
    embedding vector(1536),  -- Vector embedding for semantic clustering
    conversation_context TEXT,  -- Previous Q&A for follow-up questions (Ver 2.5.0)
    quality_score FLOAT,  -- Question quality score for spam filtering (Ver 2.4.8)
    validation_flags JSONB,  -- Validation flags like too_short, off_topic (Ver 2.4.8)
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Migration: Add columns if table already exists
ALTER TABLE chatbot_gap_questions ADD COLUMN IF NOT EXISTS conversation_context TEXT;
ALTER TABLE chatbot_gap_questions ADD COLUMN IF NOT EXISTS quality_score FLOAT;
ALTER TABLE chatbot_gap_questions ADD COLUMN IF NOT EXISTS validation_flags JSONB;

-- Indexes
CREATE INDEX IF NOT EXISTS idx_gap_session_id ON chatbot_gap_questions(session_id);
CREATE INDEX IF NOT EXISTS idx_gap_asked_date ON chatbot_gap_questions(asked_date);
CREATE INDEX IF NOT EXISTS idx_gap_is_resolved ON chatbot_gap_questions(is_resolved);
CREATE INDEX IF NOT EXISTS idx_gap_faq_confidence ON chatbot_gap_questions(faq_confidence);
CREATE INDEX IF NOT EXISTS idx_gap_questions_embedding ON chatbot_gap_questions
    USING ivfflat (embedding vector_cosine_ops) WITH (lists = 50);

-- Enable RLS
ALTER TABLE chatbot_gap_questions ENABLE ROW LEVEL SECURITY;

-- Policy: Allow all operations
DROP POLICY IF EXISTS "Allow all operations" ON chatbot_gap_questions;
CREATE POLICY "Allow all operations" ON chatbot_gap_questions
    FOR ALL USING (true) WITH CHECK (true);

-- =============================================================================
-- Table 4: chatbot_gap_clusters (groups similar gap questions)
-- =============================================================================
CREATE TABLE IF NOT EXISTS chatbot_gap_clusters (
    id BIGSERIAL PRIMARY KEY,
    cluster_name VARCHAR(255),
    cluster_description TEXT,
    question_count INTEGER DEFAULT 0,
    sample_questions JSONB,
    suggested_faq JSONB,
    action_type VARCHAR(20) DEFAULT 'create' CHECK (action_type IN ('create', 'improve')),
    existing_faq_id VARCHAR(50),
    suggested_keywords JSONB,
    priority_score FLOAT,
    status VARCHAR(20) DEFAULT 'new' CHECK (status IN ('new', 'reviewed', 'faq_created', 'dismissed')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_clusters_status ON chatbot_gap_clusters(status);
CREATE INDEX IF NOT EXISTS idx_clusters_priority ON chatbot_gap_clusters(priority_score);
CREATE INDEX IF NOT EXISTS idx_clusters_action_type ON chatbot_gap_clusters(action_type);

ALTER TABLE chatbot_gap_clusters ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Allow all operations" ON chatbot_gap_clusters;
CREATE POLICY "Allow all operations" ON chatbot_gap_clusters
    FOR ALL USING (true) WITH CHECK (true);

-- Trigger for updated_at
DROP TRIGGER IF EXISTS update_clusters_updated_at ON chatbot_gap_clusters;
CREATE TRIGGER update_clusters_updated_at
    BEFORE UPDATE ON chatbot_gap_clusters
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- =============================================================================
-- Table 5: chatbot_faq_usage (tracks FAQ hit counts)
-- =============================================================================
CREATE TABLE IF NOT EXISTS chatbot_faq_usage (
    id BIGSERIAL PRIMARY KEY,
    faq_id VARCHAR(50) NOT NULL,
    hit_count INTEGER DEFAULT 0,
    last_asked TIMESTAMP WITH TIME ZONE,
    avg_confidence FLOAT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_faq_usage_faq_id ON chatbot_faq_usage(faq_id);

ALTER TABLE chatbot_faq_usage ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Allow all operations" ON chatbot_faq_usage;
CREATE POLICY "Allow all operations" ON chatbot_faq_usage
    FOR ALL USING (true) WITH CHECK (true);

-- =============================================================================
-- Table 5: chatbot_assistants (OpenAI assistant configurations)
-- =============================================================================
CREATE TABLE IF NOT EXISTS chatbot_assistants (
    id BIGSERIAL PRIMARY KEY,
    assistant_id VARCHAR(255) NOT NULL,
    common_name VARCHAR(255) NOT NULL,
    style VARCHAR(20) NOT NULL CHECK (style IN ('embedded', 'floating')),
    audience VARCHAR(20) NOT NULL CHECK (audience IN ('all', 'visitors', 'logged-in')),
    voice VARCHAR(20) NOT NULL CHECK (voice IN ('alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer', 'none')),
    allow_file_uploads VARCHAR(5) NOT NULL CHECK (allow_file_uploads IN ('Yes', 'No')),
    allow_transcript_downloads VARCHAR(5) NOT NULL CHECK (allow_transcript_downloads IN ('Yes', 'No')),
    show_assistant_name VARCHAR(5) NOT NULL CHECK (show_assistant_name IN ('Yes', 'No')),
    initial_greeting TEXT NOT NULL,
    subsequent_greeting TEXT NOT NULL,
    placeholder_prompt TEXT NOT NULL,
    additional_instructions TEXT NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_assistants_assistant_id ON chatbot_assistants(assistant_id);

ALTER TABLE chatbot_assistants ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Allow all operations" ON chatbot_assistants;
CREATE POLICY "Allow all operations" ON chatbot_assistants
    FOR ALL USING (true) WITH CHECK (true);

-- =============================================================================
-- Triggers for updated_at columns
-- =============================================================================

-- Trigger for interactions table
DROP TRIGGER IF EXISTS update_interactions_updated_at ON chatbot_interactions;
CREATE TRIGGER update_interactions_updated_at
    BEFORE UPDATE ON chatbot_interactions
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- =============================================================================
-- Verify tables were created
-- =============================================================================
SELECT
    table_name,
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_name = t.table_name) as column_count
FROM information_schema.tables t
WHERE table_schema = 'public'
AND table_name IN (
    'chatbot_faqs',
    'chatbot_conversations',
    'chatbot_interactions',
    'chatbot_gap_questions',
    'chatbot_gap_clusters',
    'chatbot_faq_usage',
    'chatbot_assistants'
)
ORDER BY table_name;
