#!/bin/bash
# =============================================================================
# Quick Setup Script for Chatbot Vector Search
# =============================================================================
#
# This script sets up PostgreSQL with pgvector using Docker.
# Run this from the vector-search directory.
#
# Usage: ./setup.sh
#
# =============================================================================

set -e

# Configuration
CONTAINER_NAME="chatbot-pgvector"
PG_USER="chatbot"
PG_PASSWORD="chatbot_secure_2024"
PG_DATABASE="chatbot_vectors"
PG_PORT="5432"

echo "=============================================="
echo "Chatbot Vector Search - PostgreSQL Setup"
echo "=============================================="
echo ""

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed."
    echo ""
    echo "Please install Docker first:"
    echo "  - macOS: brew install --cask docker"
    echo "  - Or download from: https://www.docker.com/products/docker-desktop"
    exit 1
fi

# Check if Docker is running
if ! docker info &> /dev/null; then
    echo "❌ Docker is not running. Please start Docker Desktop."
    exit 1
fi

echo "✓ Docker is installed and running"

# Check if container already exists
if docker ps -a --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo ""
    echo "Container '${CONTAINER_NAME}' already exists."
    read -p "Do you want to remove it and start fresh? (y/n): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "Stopping and removing existing container..."
        docker stop ${CONTAINER_NAME} 2>/dev/null || true
        docker rm ${CONTAINER_NAME} 2>/dev/null || true
    else
        echo "Keeping existing container. Checking if it's running..."
        if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
            echo "Starting container..."
            docker start ${CONTAINER_NAME}
        fi
        echo "✓ Container is running"
        echo ""
        echo "Connection details (add to wp-config.php):"
        echo "=============================================="
        echo "define('CHATBOT_PG_HOST', 'localhost');"
        echo "define('CHATBOT_PG_PORT', '${PG_PORT}');"
        echo "define('CHATBOT_PG_DATABASE', '${PG_DATABASE}');"
        echo "define('CHATBOT_PG_USER', '${PG_USER}');"
        echo "define('CHATBOT_PG_PASSWORD', '${PG_PASSWORD}');"
        echo "=============================================="
        exit 0
    fi
fi

# Pull the pgvector image
echo ""
echo "Pulling pgvector Docker image..."
docker pull pgvector/pgvector:pg16

# Create and start the container
echo ""
echo "Creating PostgreSQL container with pgvector..."
docker run -d \
    --name ${CONTAINER_NAME} \
    -e POSTGRES_USER=${PG_USER} \
    -e POSTGRES_PASSWORD=${PG_PASSWORD} \
    -e POSTGRES_DB=${PG_DATABASE} \
    -p ${PG_PORT}:5432 \
    -v chatbot_pgdata:/var/lib/postgresql/data \
    pgvector/pgvector:pg16

# Wait for PostgreSQL to be ready
echo ""
echo "Waiting for PostgreSQL to start..."
sleep 5

# Check if container is running
MAX_TRIES=30
TRIES=0
while ! docker exec ${CONTAINER_NAME} pg_isready -U ${PG_USER} &> /dev/null; do
    TRIES=$((TRIES + 1))
    if [ $TRIES -ge $MAX_TRIES ]; then
        echo "❌ PostgreSQL failed to start within ${MAX_TRIES} seconds"
        exit 1
    fi
    sleep 1
    echo -n "."
done
echo ""
echo "✓ PostgreSQL is ready"

# Run the setup SQL
echo ""
echo "Running database setup..."
docker exec -i ${CONTAINER_NAME} psql -U ${PG_USER} -d ${PG_DATABASE} << 'EOSQL'
-- Enable pgvector extension
CREATE EXTENSION IF NOT EXISTS vector;

-- Create FAQs table
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

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_faqs_faq_id ON chatbot_faqs(faq_id);
CREATE INDEX IF NOT EXISTS idx_faqs_category ON chatbot_faqs(category);
CREATE INDEX IF NOT EXISTS idx_faqs_created_at ON chatbot_faqs(created_at DESC);

-- Verify
SELECT 'pgvector version: ' || extversion FROM pg_extension WHERE extname = 'vector';
EOSQL

echo ""
echo "✓ Database setup complete"

# Display connection info
echo ""
echo "=============================================="
echo "✓ Setup Complete!"
echo "=============================================="
echo ""
echo "Add these lines to your wp-config.php:"
echo ""
echo "// PostgreSQL Vector Search Configuration"
echo "define('CHATBOT_PG_HOST', 'localhost');"
echo "define('CHATBOT_PG_PORT', '${PG_PORT}');"
echo "define('CHATBOT_PG_DATABASE', '${PG_DATABASE}');"
echo "define('CHATBOT_PG_USER', '${PG_USER}');"
echo "define('CHATBOT_PG_PASSWORD', '${PG_PASSWORD}');"
echo ""
echo "=============================================="
echo ""
echo "Next steps:"
echo "1. Add the above config to wp-config.php"
echo "2. Add the vector search loader to your plugin:"
echo "   require_once 'includes/vector-search/chatbot-vector-loader.php';"
echo "3. Go to WordPress admin and run the FAQ migration"
echo ""
echo "To stop the database:  docker stop ${CONTAINER_NAME}"
echo "To start the database: docker start ${CONTAINER_NAME}"
echo "To view logs:          docker logs ${CONTAINER_NAME}"
echo ""
