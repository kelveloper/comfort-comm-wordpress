-- =============================================================================
-- PostgreSQL + pgvector Setup Script for Chatbot FAQ Vector Search
-- =============================================================================
--
-- Run this script to set up your PostgreSQL database for vector search.
--
-- Prerequisites:
--   1. PostgreSQL 14+ installed
--   2. pgvector extension installed
--
-- Installation (macOS with Homebrew):
--   brew install postgresql@16
--   brew install pgvector
--
-- Installation (Ubuntu/Debian):
--   sudo apt install postgresql postgresql-contrib
--   sudo apt install postgresql-16-pgvector
--
-- Installation (Docker - RECOMMENDED):
--   docker run -d --name chatbot-pgvector \
--     -e POSTGRES_USER=chatbot \
--     -e POSTGRES_PASSWORD=your_secure_password \
--     -e POSTGRES_DB=chatbot_vectors \
--     -p 5432:5432 \
--     pgvector/pgvector:pg16
--
-- After running this script, add to wp-config.php:
--   define('CHATBOT_PG_HOST', 'localhost');
--   define('CHATBOT_PG_PORT', '5432');
--   define('CHATBOT_PG_DATABASE', 'chatbot_vectors');
--   define('CHATBOT_PG_USER', 'chatbot');
--   define('CHATBOT_PG_PASSWORD', 'your_secure_password');
--
-- =============================================================================

-- Connect to your PostgreSQL server first, then run:

-- Step 1: Create database (run as postgres superuser)
-- CREATE DATABASE chatbot_vectors;

-- Step 2: Create user (run as postgres superuser)
-- CREATE USER chatbot WITH PASSWORD 'your_secure_password';
-- GRANT ALL PRIVILEGES ON DATABASE chatbot_vectors TO chatbot;

-- Step 3: Connect to chatbot_vectors database, then run the rest:
-- \c chatbot_vectors

-- =============================================================================
-- Enable pgvector extension
-- =============================================================================
CREATE EXTENSION IF NOT EXISTS vector;

-- =============================================================================
-- Create FAQs table with vector columns
-- =============================================================================
CREATE TABLE IF NOT EXISTS chatbot_faqs (
    id SERIAL PRIMARY KEY,

    -- FAQ content
    faq_id VARCHAR(50) UNIQUE NOT NULL,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(255),
    keywords TEXT,

    -- Vector embeddings (1536 dimensions for OpenAI text-embedding-3-small)
    question_embedding vector(1536),
    answer_embedding vector(1536),
    combined_embedding vector(1536),  -- question + answer combined

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- Create indexes for fast lookups
-- =============================================================================

-- Index on faq_id for direct lookups
CREATE INDEX IF NOT EXISTS idx_faqs_faq_id ON chatbot_faqs(faq_id);

-- Index on category for filtering
CREATE INDEX IF NOT EXISTS idx_faqs_category ON chatbot_faqs(category);

-- Index on created_at for ordering
CREATE INDEX IF NOT EXISTS idx_faqs_created_at ON chatbot_faqs(created_at DESC);

-- =============================================================================
-- Create vector similarity index (IVFFlat)
-- =============================================================================
-- Note: For best results, create this index AFTER importing your FAQs
-- The 'lists' parameter should be approximately sqrt(n) where n = number of rows
-- For 66 FAQs, lists = 10 is appropriate
-- For 1000 FAQs, lists = 32 would be better
-- For 10000 FAQs, lists = 100 would be better

-- For small datasets (<100 rows), exact search is fast enough
-- Uncomment this after migration:
-- CREATE INDEX idx_faqs_combined_embedding
-- ON chatbot_faqs USING ivfflat (combined_embedding vector_cosine_ops)
-- WITH (lists = 10);

-- =============================================================================
-- Create function to update timestamp
-- =============================================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create trigger to auto-update updated_at
DROP TRIGGER IF EXISTS update_chatbot_faqs_updated_at ON chatbot_faqs;
CREATE TRIGGER update_chatbot_faqs_updated_at
    BEFORE UPDATE ON chatbot_faqs
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- =============================================================================
-- Grant permissions to chatbot user
-- =============================================================================
GRANT ALL PRIVILEGES ON TABLE chatbot_faqs TO chatbot;
GRANT USAGE, SELECT ON SEQUENCE chatbot_faqs_id_seq TO chatbot;

-- =============================================================================
-- Verify setup
-- =============================================================================
DO $$
BEGIN
    -- Check pgvector extension
    IF EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'vector') THEN
        RAISE NOTICE '✓ pgvector extension is installed';
    ELSE
        RAISE EXCEPTION '✗ pgvector extension is NOT installed';
    END IF;

    -- Check table exists
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'chatbot_faqs') THEN
        RAISE NOTICE '✓ chatbot_faqs table exists';
    ELSE
        RAISE EXCEPTION '✗ chatbot_faqs table does NOT exist';
    END IF;

    RAISE NOTICE '';
    RAISE NOTICE '=== Setup Complete ===';
    RAISE NOTICE 'Next steps:';
    RAISE NOTICE '1. Add PostgreSQL config to wp-config.php';
    RAISE NOTICE '2. Run FAQ migration from WordPress admin';
END $$;

-- =============================================================================
-- Useful queries for debugging
-- =============================================================================

-- Check FAQ count:
-- SELECT COUNT(*) FROM chatbot_faqs;

-- Check FAQs with embeddings:
-- SELECT COUNT(*) FROM chatbot_faqs WHERE combined_embedding IS NOT NULL;

-- Check categories:
-- SELECT category, COUNT(*) FROM chatbot_faqs GROUP BY category ORDER BY COUNT(*) DESC;

-- Test similarity search (replace embedding with actual query embedding):
-- SELECT faq_id, question, 1 - (combined_embedding <=> '[...]'::vector) AS similarity
-- FROM chatbot_faqs
-- WHERE combined_embedding IS NOT NULL
-- ORDER BY similarity DESC
-- LIMIT 5;
