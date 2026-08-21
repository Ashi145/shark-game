-- ============================================
-- SHARK TANK - DATABASE SETUP
-- Run this in cPanel > phpMyAdmin > Import
-- Make sure to select sharktank_db first
-- ============================================

-- Players table
CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_player_id (player_id),
    INDEX idx_name (name),
    INDEX idx_active (is_active),
    INDEX idx_last_active (last_active)
) ENGINE=InnoDB;

-- Scores / progress table
CREATE TABLE IF NOT EXISTS scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id VARCHAR(64) NOT NULL,
    level_reached INT DEFAULT 1,
    score INT DEFAULT 0,
    pearls_collected INT DEFAULT 0,
    sharks_dodged INT DEFAULT 0,
    play_time_seconds INT DEFAULT 0,
    completed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_player_id (player_id),
    INDEX idx_score (score DESC),
    INDEX idx_level (level_reached DESC),
    INDEX idx_created (created_at),
    INDEX idx_player_score (player_id, score DESC)
) ENGINE=InnoDB;

-- Active sessions (tracks who is online)
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id VARCHAR(64) NOT NULL,
    session_token VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_token (session_token),
    INDEX idx_active (is_active),
    INDEX idx_last_ping (last_ping),
    INDEX idx_player_active (player_id, is_active)
) ENGINE=InnoDB;

-- Analytics events
CREATE TABLE IF NOT EXISTS analytics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(32) NOT NULL,
    player_id VARCHAR(64) DEFAULT NULL,
    event_data JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_created (created_at),
    INDEX idx_event_player (event_type, player_id)
) ENGINE=InnoDB;

-- Daily stats (aggregated)
CREATE TABLE IF NOT EXISTS daily_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stat_date DATE NOT NULL UNIQUE,
    total_visits INT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    games_started INT DEFAULT 0,
    games_completed INT DEFAULT 0,
    total_score BIGINT DEFAULT 0,
    avg_level DECIMAL(4,2) DEFAULT 0,
    peak_online INT DEFAULT 0,
    INDEX idx_date (stat_date DESC)
) ENGINE=InnoDB;

-- ============================================
-- VIEWS for quick queries
-- ============================================

-- Online players count
CREATE OR REPLACE VIEW v_online_count AS
SELECT COUNT(DISTINCT player_id) as online_count
FROM sessions
WHERE is_active = 1
AND last_ping > DATE_SUB(NOW(), INTERVAL 30 SECOND);

-- Top leaderboard
CREATE OR REPLACE VIEW v_leaderboard AS
SELECT
    p.player_id,
    p.name,
    MAX(s.score) as high_score,
    MAX(s.level_reached) as highest_level,
    COUNT(s.id) as games_played,
    MAX(s.created_at) as last_played
FROM players p
JOIN scores s ON p.player_id = s.player_id
GROUP BY p.player_id, p.name
ORDER BY high_score DESC;
