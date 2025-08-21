-- Migration 001: Create initial tables for ContentItem and MarkdownBlock
-- Created: 2024-01-01
-- Description: Basic schema for Phase 1A MVP

-- Enable foreign key constraints
PRAGMA foreign_keys = ON;

-- Create content_items table
CREATE TABLE content_items (
    id TEXT PRIMARY KEY,
    type TEXT NOT NULL DEFAULT 'note',
    title TEXT,
    summary TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

-- Create markdown_blocks table
CREATE TABLE markdown_blocks (
    id TEXT PRIMARY KEY,
    content_id TEXT NOT NULL,
    source TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (content_id) REFERENCES content_items(id) ON DELETE CASCADE
);

-- Create indexes for common queries
CREATE INDEX IF NOT EXISTS idx_content_created ON content_items(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_content_type ON content_items(type);
CREATE INDEX IF NOT EXISTS idx_blocks_content ON markdown_blocks(content_id);

-- Insert a test record to verify schema works
INSERT INTO content_items (id, type, title, created_at, updated_at) 
VALUES ('test-id', 'note', 'Test Content', '2024-01-01T00:00:00Z', '2024-01-01T00:00:00Z');

INSERT INTO markdown_blocks (id, content_id, source, created_at)
VALUES ('test-block-id', 'test-id', '# Test Block', '2024-01-01T00:00:00Z');

-- Verify the foreign key constraint works
SELECT 
    c.id as content_id,
    c.title,
    b.id as block_id,
    b.source
FROM content_items c
JOIN markdown_blocks b ON c.id = b.content_id
WHERE c.id = 'test-id';

-- Clean up test data
DELETE FROM content_items WHERE id = 'test-id';
-- Note: markdown_blocks record should be automatically deleted due to CASCADE
