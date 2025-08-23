-- Migration 001: Create initial table for ContentItem with JSON blocks
-- Created: 2024-01-01
-- Description: Basic schema for Phase 1A MVP with JSON block storage

-- Create content_items table with JSON blocks column
CREATE TABLE content_items (
    id TEXT PRIMARY KEY,
    type TEXT NOT NULL DEFAULT 'note',
    title TEXT,
    summary TEXT,
    blocks TEXT NOT NULL DEFAULT '[]',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

-- Create indexes for common queries
CREATE INDEX IF NOT EXISTS idx_content_created ON content_items(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_content_type ON content_items(type);

-- Insert a test record to verify schema works
INSERT INTO content_items (id, type, title, blocks, created_at, updated_at)
VALUES (
    'test-id',
    'note',
    'Test Content',
    '[{"id":"test-block-id","type":"markdown","source":"# Test Block","created_at":"2024-01-01T00:00:00Z"}]',
    '2024-01-01T00:00:00Z',
    '2024-01-01T00:00:00Z'
);

-- Verify the JSON structure works
SELECT
    id,
    title,
    json_extract(blocks, '$[0].type') as first_block_type,
    json_extract(blocks, '$[0].source') as first_block_source
FROM content_items
WHERE id = 'test-id';

-- Clean up test data
DELETE FROM content_items WHERE id = 'test-id';
