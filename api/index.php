<?php
// ============================================
// MAIN API ROUTER
// Endpoints:
//   POST /api/index.php?action=register
//   POST /api/index.php?action=login
//   POST /api/index.php?action=ping
//   POST /api/index.php?action=logout
//   POST /api/index.php?action=save_score
//   GET  /api/index.php?action=leaderboard
//   GET  /api/index.php?action=online_count
//   GET  /api/index.php?action=stats
//   POST /api/index.php?action=event
// ============================================

require_once __DIR__ . '/config.php';

$db = getDB();
cleanStaleSessions($db);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ---- PLAYER REGISTRATION ----
    case 'register':
        $input = getInput();
        $playerId = $input['player_id'] ?? '';
        $name = trim($input['name'] ?? '');

        if (empty($playerId) || empty($name)) {
            respond(['error' => 'player_id and name required'], 400);
        }

        if (strlen($name) > 50) $name = substr($name, 0, 50);

        $stmt = $db->prepare("INSERT INTO players (player_id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), last_active = NOW()");
        $stmt->execute([$playerId, $name]);

        respond(['ok' => true, 'player_id' => $playerId]);
        break;

    // ---- PLAYER LOGIN (reconnect) ----
    case 'login':
        $input = getInput();
        $playerId = $input['player_id'] ?? '';

        if (empty($playerId)) {
            respond(['error' => 'player_id required'], 400);
        }

        $stmt = $db->prepare("SELECT player_id, name, created_at FROM players WHERE player_id = ?");
        $stmt->execute([$playerId]);
        $player = $stmt->fetch();

        if (!$player) {
            respond(['error' => 'Player not found'], 404);
        }

        $db->prepare("UPDATE players SET last_active = NOW(), is_active = 1 WHERE player_id = ?")->execute([$playerId]);
        respond(['ok' => true, 'player' => $player]);
        break;

    // ---- SESSION HEARTBEAT ----
    case 'ping':
        $input = getInput();
        $playerId = $input['player_id'] ?? '';
        $sessionToken = $input['session_token'] ?? '';
        $level = $input['level'] ?? 1;
        $score = $input['score'] ?? 0;

        if (empty($playerId)) {
            respond(['error' => 'player_id required'], 400);
        }

        // Upsert session
        $stmt = $db->prepare("
            INSERT INTO sessions (player_id, session_token, ip_address, user_agent, is_active)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE last_ping = NOW(), is_active = 1
        ");
        $stmt->execute([$playerId, $sessionToken ?: bin2hex(random_bytes(32)), getClientIP(), $_SERVER['HTTP_USER_AGENT'] ?? '']);

        // Get online count
        $online = $db->query("SELECT COUNT(DISTINCT player_id) as cnt FROM sessions WHERE is_active = 1 AND last_ping > DATE_SUB(NOW(), INTERVAL 30 SECOND)")->fetch();

        respond(['ok' => true, 'online' => $online['cnt']]);
        break;

    // ---- LOGOUT ----
    case 'logout':
        $input = getInput();
        $sessionToken = $input['session_token'] ?? '';
        $playerId = $input['player_id'] ?? '';

        if ($sessionToken) {
            $db->prepare("UPDATE sessions SET is_active = 0 WHERE session_token = ?")->execute([$sessionToken]);
        }
        if ($playerId) {
            $db->prepare("UPDATE players SET is_active = 0 WHERE player_id = ?")->execute([$playerId]);
        }

        respond(['ok' => true]);
        break;

    // ---- SAVE SCORE ----
    case 'save_score':
        $input = getInput();
        $playerId = $input['player_id'] ?? '';
        $level = intval($input['level'] ?? 1);
        $score = intval($input['score'] ?? 0);
        $pearls = intval($input['pearls'] ?? 0);
        $playTime = intval($input['play_time'] ?? 0);
        $completed = intval($input['completed'] ?? 0);

        if (empty($playerId)) {
            respond(['error' => 'player_id required'], 400);
        }

        $stmt = $db->prepare("
            INSERT INTO scores (player_id, level_reached, score, pearls_collected, play_time_seconds, completed)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$playerId, $level, $score, $pearls, $playTime, $completed]);

        // Update daily stats
        $today = date('Y-m-d');
        $db->prepare("
            INSERT INTO daily_stats (stat_date, games_completed, total_score)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                games_completed = games_completed + VALUES(games_completed),
                total_score = total_score + VALUES(total_score)
        ")->execute([$today, $completed, $score]);

        respond(['ok' => true, 'score_id' => $db->lastInsertId()]);
        break;

    // ---- LEADERBOARD ----
    case 'leaderboard':
        $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
        $offset = max(0, intval($_GET['offset'] ?? 0));

        $stmt = $db->prepare("
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
            ORDER BY high_score DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $leaderboard = $stmt->fetchAll();

        $total = $db->query("SELECT COUNT(DISTINCT player_id) as cnt FROM scores")->fetch()['cnt'];

        respond(['ok' => true, 'leaderboard' => $leaderboard, 'total_players' => $total]);
        break;

    // ---- ONLINE COUNT ----
    case 'online_count':
        $online = $db->query("SELECT COUNT(DISTINCT player_id) as cnt FROM sessions WHERE is_active = 1 AND last_ping > DATE_SUB(NOW(), INTERVAL 30 SECOND)")->fetch();
        respond(['ok' => true, 'online' => $online['cnt']]);
        break;

    // ---- SITE STATS ----
    case 'stats':
        $totalPlayers = $db->query("SELECT COUNT(*) as cnt FROM players")->fetch()['cnt'];
        $totalGames = $db->query("SELECT COUNT(*) as cnt FROM scores")->fetch()['cnt'];
        $totalScore = $db->query("SELECT COALESCE(SUM(score),0) as total FROM scores")->fetch()['total'];
        $avgLevel = $db->query("SELECT COALESCE(AVG(level_reached),0) as avg FROM scores")->fetch()['avg'];
        $todayVisits = $db->query("SELECT COALESCE(total_visits,0) as v FROM daily_stats WHERE stat_date = CURDATE()")->fetch()['v'] ?? 0;
        $online = $db->query("SELECT COUNT(DISTINCT player_id) as cnt FROM sessions WHERE is_active = 1 AND last_ping > DATE_SUB(NOW(), INTERVAL 30 SECOND)")->fetch()['cnt'];

        respond([
            'ok' => true,
            'stats' => [
                'total_players' => $totalPlayers,
                'total_games' => $totalGames,
                'total_score' => $totalScore,
                'avg_level' => round($avgLevel, 1),
                'today_visits' => $todayVisits,
                'online_now' => $online
            ]
        ]);
        break;

    // ---- TRACK EVENT ----
    case 'event':
        $input = getInput();
        $eventType = $input['event'] ?? '';
        $playerId = $input['player_id'] ?? null;
        $eventData = $input['data'] ?? null;

        if (empty($eventType)) {
            respond(['error' => 'event type required'], 400);
        }

        $stmt = $db->prepare("INSERT INTO analytics (event_type, player_id, event_data, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$eventType, $playerId, $eventData ? json_encode($eventData) : null, getClientIP()]);

        // Update daily visit count
        if ($eventType === 'visit') {
            $today = date('Y-m-d');
            $db->prepare("
                INSERT INTO daily_stats (stat_date, total_visits, unique_visitors)
                VALUES (?, 1, 1)
                ON DUPLICATE KEY UPDATE total_visits = total_visits + 1
            ")->execute([$today]);
        }

        respond(['ok' => true]);
        break;

    default:
        respond(['error' => 'Unknown action', 'available' => [
            'register', 'login', 'ping', 'logout',
            'save_score', 'leaderboard', 'online_count', 'stats', 'event'
        ]], 400);
}
