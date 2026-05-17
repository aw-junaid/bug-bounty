-- Slack Clone Database Schema
-- VULN: Weak root password, world-readable grants

CREATE DATABASE IF NOT EXISTS slack_clone;
USE slack_clone;

-- VULN: No encryption at rest, passwords stored as MD5
CREATE TABLE IF NOT EXISTS users (
    user_id VARCHAR(36) NOT NULL DEFAULT (UUID()),
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,  -- VULN: stored as MD5 hash
    session_token VARCHAR(255) DEFAULT NULL,
    api_token VARCHAR(255) DEFAULT NULL,
    role ENUM('admin','user','guest') NOT NULL DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS channels (
    channel_id VARCHAR(36) NOT NULL DEFAULT (UUID()),
    channel_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    created_by VARCHAR(36) NOT NULL,
    is_private TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (channel_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS channel_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES channels(channel_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    message_content TEXT NOT NULL,  -- VULN: no sanitization
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0,
    FOREIGN KEY (channel_id) REFERENCES channels(channel_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS files (
    file_id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT DEFAULT NULL,
    user_id VARCHAR(36) NOT NULL,
    file_path VARCHAR(500) NOT NULL,  -- VULN: stores raw path
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(message_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- VULN: Default admin credentials admin/admin (MD5: 21232f297a57a5a743894a0e4a801fc3)
INSERT IGNORE INTO users (user_id, username, email, password_hash, role)
VALUES (
    'aaaaaaaa-0000-0000-0000-000000000001',
    'admin',
    'admin@slack.local',
    '21232f297a57a5a743894a0e4a801fc3',
    'admin'
);

-- Default general channel
INSERT IGNORE INTO channels (channel_id, channel_name, description, created_by)
VALUES (
    'bbbbbbbb-0000-0000-0000-000000000001',
    'general',
    'General discussion',
    'aaaaaaaa-0000-0000-0000-000000000001'
);


-- Extended seed data for application walkthrough and testing
INSERT IGNORE INTO users (user_id, username, email, password_hash, role)
VALUES
    ('aaaaaaaa-0000-0000-0000-000000000002', 'alice',   'alice@slack.local',   '482c811da5d5b4bc6d497ffa98491e38', 'user'),
    ('aaaaaaaa-0000-0000-0000-000000000003', 'bob',     'bob@slack.local',     '482c811da5d5b4bc6d497ffa98491e38', 'user'),
    ('aaaaaaaa-0000-0000-0000-000000000004', 'charlie', 'charlie@slack.local', '482c811da5d5b4bc6d497ffa98491e38', 'user'),
    ('aaaaaaaa-0000-0000-0000-000000000005', 'dana',    'dana@slack.local',    '482c811da5d5b4bc6d497ffa98491e38', 'user'),
    ('aaaaaaaa-0000-0000-0000-000000000006', 'eric',    'eric@slack.local',    '482c811da5d5b4bc6d497ffa98491e38', 'user'),
    ('aaaaaaaa-0000-0000-0000-000000000007', 'fatima',  'fatima@slack.local',  '482c811da5d5b4bc6d497ffa98491e38', 'user'),
    ('aaaaaaaa-0000-0000-0000-000000000008', 'george',  'george@slack.local',  '482c811da5d5b4bc6d497ffa98491e38', 'user'),
    ('aaaaaaaa-0000-0000-0000-000000000009', 'hana',    'hana@slack.local',    '482c811da5d5b4bc6d497ffa98491e38', 'user'),
    ('aaaaaaaa-0000-0000-0000-000000000010', 'ivan',    'ivan@slack.local',    '482c811da5d5b4bc6d497ffa98491e38', 'user'),
    ('aaaaaaaa-0000-0000-0000-000000000011', 'julia',   'julia@slack.local',   '482c811da5d5b4bc6d497ffa98491e38', 'user'),
    ('aaaaaaaa-0000-0000-0000-000000000012', 'kevin',   'kevin@slack.local',   '482c811da5d5b4bc6d497ffa98491e38', 'guest');

INSERT IGNORE INTO channels (channel_id, channel_name, description, created_by, is_private)
VALUES
    ('bbbbbbbb-0000-0000-0000-000000000002', 'engineering', 'Engineering daily updates', 'aaaaaaaa-0000-0000-0000-000000000001', 0),
    ('bbbbbbbb-0000-0000-0000-000000000003', 'product',     'Product planning and roadmap', 'aaaaaaaa-0000-0000-0000-000000000001', 0),
    ('bbbbbbbb-0000-0000-0000-000000000004', 'support',     'Customer issues and incident chat', 'aaaaaaaa-0000-0000-0000-000000000001', 0),
    ('bbbbbbbb-0000-0000-0000-000000000005', 'marketing',   'Campaign notes and drafts', 'aaaaaaaa-0000-0000-0000-000000000001', 0),
    ('bbbbbbbb-0000-0000-0000-000000000006', 'security',    'Security and pentest discussion', 'aaaaaaaa-0000-0000-0000-000000000001', 1),
    ('bbbbbbbb-0000-0000-0000-000000000007', 'design',      'Design reviews and assets', 'aaaaaaaa-0000-0000-0000-000000000001', 0),
    ('bbbbbbbb-0000-0000-0000-000000000008', 'random',      'Random conversations and social chat', 'aaaaaaaa-0000-0000-0000-000000000001', 0);

INSERT INTO channel_members (channel_id, user_id)
SELECT seed.channel_id, seed.user_id
FROM (
    SELECT 'bbbbbbbb-0000-0000-0000-000000000001' AS channel_id, 'aaaaaaaa-0000-0000-0000-000000000001' AS user_id UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000001', 'aaaaaaaa-0000-0000-0000-000000000002' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000001', 'aaaaaaaa-0000-0000-0000-000000000003' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000001', 'aaaaaaaa-0000-0000-0000-000000000004' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000001', 'aaaaaaaa-0000-0000-0000-000000000005' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000001', 'aaaaaaaa-0000-0000-0000-000000000006' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000001', 'aaaaaaaa-0000-0000-0000-000000000007' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000001', 'aaaaaaaa-0000-0000-0000-000000000008' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000001', 'aaaaaaaa-0000-0000-0000-000000000009' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000001', 'aaaaaaaa-0000-0000-0000-000000000010' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000002', 'aaaaaaaa-0000-0000-0000-000000000001' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000002', 'aaaaaaaa-0000-0000-0000-000000000002' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000002', 'aaaaaaaa-0000-0000-0000-000000000003' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000002', 'aaaaaaaa-0000-0000-0000-000000000004' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000002', 'aaaaaaaa-0000-0000-0000-000000000005' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000002', 'aaaaaaaa-0000-0000-0000-000000000006' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000002', 'aaaaaaaa-0000-0000-0000-000000000010' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000003', 'aaaaaaaa-0000-0000-0000-000000000001' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000003', 'aaaaaaaa-0000-0000-0000-000000000004' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000003', 'aaaaaaaa-0000-0000-0000-000000000007' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000003', 'aaaaaaaa-0000-0000-0000-000000000008' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000003', 'aaaaaaaa-0000-0000-0000-000000000011' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000004', 'aaaaaaaa-0000-0000-0000-000000000001' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000004', 'aaaaaaaa-0000-0000-0000-000000000005' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000004', 'aaaaaaaa-0000-0000-0000-000000000006' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000004', 'aaaaaaaa-0000-0000-0000-000000000009' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000004', 'aaaaaaaa-0000-0000-0000-000000000012' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000005', 'aaaaaaaa-0000-0000-0000-000000000001' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000005', 'aaaaaaaa-0000-0000-0000-000000000003' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000005', 'aaaaaaaa-0000-0000-0000-000000000007' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000005', 'aaaaaaaa-0000-0000-0000-000000000010' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000006', 'aaaaaaaa-0000-0000-0000-000000000001' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000006', 'aaaaaaaa-0000-0000-0000-000000000002' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000006', 'aaaaaaaa-0000-0000-0000-000000000006' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000006', 'aaaaaaaa-0000-0000-0000-000000000011' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000007', 'aaaaaaaa-0000-0000-0000-000000000001' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000007', 'aaaaaaaa-0000-0000-0000-000000000004' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000007', 'aaaaaaaa-0000-0000-0000-000000000008' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000007', 'aaaaaaaa-0000-0000-0000-000000000010' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000008', 'aaaaaaaa-0000-0000-0000-000000000001' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000008', 'aaaaaaaa-0000-0000-0000-000000000002' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000008', 'aaaaaaaa-0000-0000-0000-000000000005' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000008', 'aaaaaaaa-0000-0000-0000-000000000009' UNION ALL
    SELECT 'bbbbbbbb-0000-0000-0000-000000000008', 'aaaaaaaa-0000-0000-0000-000000000012'
) AS seed
WHERE NOT EXISTS (
    SELECT 1
    FROM channel_members cm
    WHERE cm.channel_id = seed.channel_id
      AND cm.user_id = seed.user_id
);

INSERT INTO messages (channel_id, user_id, message_content, timestamp, is_deleted)
SELECT
    CASE MOD(seq.n, 8)
        WHEN 0 THEN 'bbbbbbbb-0000-0000-0000-000000000001'
        WHEN 1 THEN 'bbbbbbbb-0000-0000-0000-000000000002'
        WHEN 2 THEN 'bbbbbbbb-0000-0000-0000-000000000003'
        WHEN 3 THEN 'bbbbbbbb-0000-0000-0000-000000000004'
        WHEN 4 THEN 'bbbbbbbb-0000-0000-0000-000000000005'
        WHEN 5 THEN 'bbbbbbbb-0000-0000-0000-000000000006'
        WHEN 6 THEN 'bbbbbbbb-0000-0000-0000-000000000007'
        ELSE      'bbbbbbbb-0000-0000-0000-000000000008'
    END AS channel_id,
    CASE MOD(seq.n, 10)
        WHEN 0 THEN 'aaaaaaaa-0000-0000-0000-000000000002'
        WHEN 1 THEN 'aaaaaaaa-0000-0000-0000-000000000003'
        WHEN 2 THEN 'aaaaaaaa-0000-0000-0000-000000000004'
        WHEN 3 THEN 'aaaaaaaa-0000-0000-0000-000000000005'
        WHEN 4 THEN 'aaaaaaaa-0000-0000-0000-000000000006'
        WHEN 5 THEN 'aaaaaaaa-0000-0000-0000-000000000007'
        WHEN 6 THEN 'aaaaaaaa-0000-0000-0000-000000000008'
        WHEN 7 THEN 'aaaaaaaa-0000-0000-0000-000000000009'
        WHEN 8 THEN 'aaaaaaaa-0000-0000-0000-000000000010'
        ELSE      'aaaaaaaa-0000-0000-0000-000000000011'
    END AS user_id,
    CONCAT(
        'Seed message #', seq.n,
        ' - channel conversation text for demo, search, and testing data.'
    ) AS message_content,
    DATE_SUB(NOW(), INTERVAL MOD(seq.n, 360) MINUTE) AS timestamp,
    0 AS is_deleted
FROM (
    -- Generates 120 messages distributed across 8 channels via MOD-based routing.
    SELECT (ones.n + tens.n * 10 + 1) AS n
    FROM (
        SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
    ) ones
    CROSS JOIN (
        SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
        UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
        UNION ALL SELECT 10 UNION ALL SELECT 11
    ) tens
    WHERE (ones.n + tens.n * 10 + 1) <= 120
) AS seq
WHERE NOT EXISTS (
    SELECT 1
    FROM messages m
    WHERE m.message_content = CONCAT(
        'Seed message #', seq.n,
        ' - channel conversation text for demo, search, and testing data.'
    )
);

INSERT INTO files (message_id, user_id, file_path, original_name, mime_type, file_size)
SELECT seed.message_id, seed.user_id, seed.file_path, seed.original_name, seed.mime_type, seed.file_size
FROM (
    SELECT NULL AS message_id, 'aaaaaaaa-0000-0000-0000-000000000002' AS user_id, '/public/uploads/seed_architecture.png' AS file_path, 'architecture.png' AS original_name, 'image/png' AS mime_type, 40960 AS file_size UNION ALL
    SELECT NULL, 'aaaaaaaa-0000-0000-0000-000000000003', '/public/uploads/seed_release_notes.pdf', 'release_notes.pdf', 'application/pdf', 102400 UNION ALL
    SELECT NULL, 'aaaaaaaa-0000-0000-0000-000000000004', '/public/uploads/seed_backlog.csv', 'backlog.csv', 'text/csv', 8192 UNION ALL
    SELECT NULL, 'aaaaaaaa-0000-0000-0000-000000000005', '/public/uploads/seed_oncall_rota.xlsx', 'oncall_rota.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 92160 UNION ALL
    SELECT NULL, 'aaaaaaaa-0000-0000-0000-000000000006', '/public/uploads/seed_incident_report.docx', 'incident_report.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 65536 UNION ALL
    SELECT NULL, 'aaaaaaaa-0000-0000-0000-000000000007', '/public/uploads/seed_campaign_draft.txt', 'campaign_draft.txt', 'text/plain', 2048 UNION ALL
    SELECT NULL, 'aaaaaaaa-0000-0000-0000-000000000008', '/public/uploads/seed_brand_assets.zip', 'brand_assets.zip', 'application/zip', 204800 UNION ALL
    SELECT NULL, 'aaaaaaaa-0000-0000-0000-000000000009', '/public/uploads/seed_ui_mockups.fig', 'ui_mockups.fig', 'application/octet-stream', 307200 UNION ALL
    SELECT NULL, 'aaaaaaaa-0000-0000-0000-000000000010', '/public/uploads/seed_weekly_status.md', 'weekly_status.md', 'text/markdown', 4096 UNION ALL
    SELECT NULL, 'aaaaaaaa-0000-0000-0000-000000000011', '/public/uploads/seed_security_checklist.txt', 'security_checklist.txt', 'text/plain', 3072
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM files f WHERE f.file_path = seed.file_path
);
