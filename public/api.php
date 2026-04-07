<?php
require dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($config['app']['cors_allow_origin'] ?? '*'));
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = $_GET['action'] ?? 'health';
$pdo = $db->pdo();

function respond($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function rowOut(array $row): array {
    $row['artists'] = $row['artists_json'] ? json_decode($row['artists_json'], true) : [];
    $row['genres'] = $row['genres_json'] ? json_decode($row['genres_json'], true) : [];
    $row['versions'] = $row['versions_json'] ? json_decode($row['versions_json'], true) : [];
    unset($row['artists_json'], $row['genres_json'], $row['versions_json'], $row['source_hash']);
    return $row;
}

try {
    switch ($action) {
        case 'health':
            respond([
                'ok' => true,
                'app' => $config['app']['name'],
                'time' => date(DATE_ATOM),
            ]);
            break;

        case 'songs':
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
            $offset = ($page - 1) * $limit;
            $genre = trim((string)($_GET['genre'] ?? ''));
            $artist = trim((string)($_GET['artist'] ?? ''));
            $where = [];
            $params = [];
            if ($genre !== '') {
                $where[] = 'genres_text LIKE ?';
                $params[] = '%' . $genre . '%';
            }
            if ($artist !== '') {
                $where[] = 'artists_text LIKE ?';
                $params[] = '%' . $artist . '%';
            }
            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM songs {$whereSql}");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $sql = "SELECT * FROM songs {$whereSql} ORDER BY release_date DESC, id DESC LIMIT {$limit} OFFSET {$offset}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = array_map('rowOut', $stmt->fetchAll());

            respond([
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'rows' => $rows,
            ]);
            break;

        case 'song':
            $slug = trim((string)($_GET['slug'] ?? ''));
            $id = trim((string)($_GET['id'] ?? ''));
            if ($slug !== '') {
                $stmt = $pdo->prepare('SELECT * FROM songs WHERE slug = ? LIMIT 1');
                $stmt->execute([$slug]);
            } elseif ($id !== '') {
                $stmt = $pdo->prepare('SELECT * FROM songs WHERE external_id = ? LIMIT 1');
                $stmt->execute([$id]);
            } else {
                respond(['ok' => false, 'error' => 'Provide slug or id'], 400);
            }
            $row = $stmt->fetch();
            if (!$row) respond(['ok' => false, 'error' => 'Song not found'], 404);
            respond(rowOut($row));
            break;

        case 'search':
            $q = trim((string)($_GET['q'] ?? ''));
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
            if ($q === '') respond(['rows' => []]);
            $like = '%' . $q . '%';
            $stmt = $pdo->prepare(
                'SELECT * FROM songs WHERE title LIKE ? OR artists_text LIKE ? OR genres_text LIKE ? OR slug LIKE ? ORDER BY release_date DESC, id DESC LIMIT ' . $limit
            );
            $stmt->execute([$like, $like, $like, $like]);
            $rows = array_map('rowOut', $stmt->fetchAll());
            respond(['rows' => $rows]);
            break;

        case 'recent':
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $stmt = $pdo->query('SELECT * FROM songs ORDER BY release_date DESC, id DESC LIMIT ' . $limit);
            $rows = array_map('rowOut', $stmt->fetchAll());
            respond(['rows' => $rows]);
            break;

        case 'stats':
            $songs = (int)$pdo->query('SELECT COUNT(*) FROM songs')->fetchColumn();
            $lastRun = $pdo->query('SELECT * FROM crawl_runs ORDER BY id DESC LIMIT 1')->fetch();
            respond([
                'songs' => $songs,
                'last_run' => $lastRun ?: null,
            ]);
            break;

        default:
            respond(['ok' => false, 'error' => 'Unknown action'], 404);
    }
} catch (Throwable $e) {
    respond(['ok' => false, 'error' => $e->getMessage()], 500);
}
