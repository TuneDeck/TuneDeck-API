<?php
class Database {
    private PDO $pdo;

    public function __construct(private array $config) {
        $db = $config['db'];
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $db['driver'],
            $db['host'],
            $db['port'],
            $db['database'],
            $db['charset']
        );

        $this->pdo = new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function pdo(): PDO {
        return $this->pdo;
    }

    public function initSchema(string $schemaFile): void {
        $sql = file_get_contents($schemaFile);
        if ($sql === false) {
            throw new RuntimeException('Could not read schema.sql');
        }
        $this->pdo->exec($sql);
    }

    public function startRun(string $mode): int {
        $stmt = $this->pdo->prepare("INSERT INTO crawl_runs (mode) VALUES (?)");
        $stmt->execute([$mode]);
        return (int)$this->pdo->lastInsertId();
    }

    public function finishRun(int $runId, string $status, array $stats, ?string $message = null): void {
        $stmt = $this->pdo->prepare(
            "UPDATE crawl_runs
             SET finished_at = NOW(), status = ?, pages_crawled = ?, detail_pages_crawled = ?, songs_seen = ?, songs_upserted = ?, message = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $status,
            (int)($stats['pages_crawled'] ?? 0),
            (int)($stats['detail_pages_crawled'] ?? 0),
            (int)($stats['songs_seen'] ?? 0),
            (int)($stats['songs_upserted'] ?? 0),
            $message,
            $runId,
        ]);
    }

    public function findSongBySlugOrExternalId(?string $slug, ?string $externalId): ?array {
        if ($externalId) {
            $stmt = $this->pdo->prepare("SELECT * FROM songs WHERE external_id = ? LIMIT 1");
            $stmt->execute([$externalId]);
            $row = $stmt->fetch();
            if ($row) return $row;
        }

        if ($slug) {
            $stmt = $this->pdo->prepare("SELECT * FROM songs WHERE slug = ? LIMIT 1");
            $stmt->execute([$slug]);
            $row = $stmt->fetch();
            if ($row) return $row;
        }

        return null;
    }

    public function upsertSong(array $song): bool {
        $existing = $this->findSongBySlugOrExternalId($song['slug'] ?? null, $song['external_id'] ?? null);
        $hash = hash('sha256', json_encode($song, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $payload = [
            ':external_id' => $song['external_id'] ?? null,
            ':slug' => $song['slug'],
            ':page_url' => $song['page_url'],
            ':title' => $song['title'],
            ':artists_json' => $this->jsonOrNull($song['artists'] ?? null),
            ':artists_text' => $song['artists_text'] ?? null,
            ':genres_json' => $this->jsonOrNull($song['genres'] ?? null),
            ':genres_text' => $song['genres_text'] ?? null,
            ':versions_json' => $this->jsonOrNull($song['versions'] ?? null),
            ':cover_thumb_url' => $song['cover_thumb_url'] ?? null,
            ':cover_medium_url' => $song['cover_medium_url'] ?? null,
            ':cover_large_url' => $song['cover_large_url'] ?? null,
            ':audio_url' => $song['audio_url'] ?? null,
            ':preview_seconds' => $song['preview_seconds'] ?? null,
            ':release_date' => $song['release_date'] ?? null,
            ':description' => $song['description'] ?? null,
            ':attribution_text' => $song['attribution_text'] ?? null,
            ':download_path' => $song['download_path'] ?? null,
            ':youtube_watch_url' => $song['youtube_watch_url'] ?? null,
            ':source_hash' => $hash,
        ];

        if ($existing) {
            if (($existing['source_hash'] ?? '') === $hash) {
                $stmt = $this->pdo->prepare("UPDATE songs SET last_seen_at = NOW() WHERE id = ?");
                $stmt->execute([$existing['id']]);
                return false;
            }

            $payload[':id'] = $existing['id'];
            $sql = "UPDATE songs SET
                external_id = :external_id,
                slug = :slug,
                page_url = :page_url,
                title = :title,
                artists_json = :artists_json,
                artists_text = :artists_text,
                genres_json = :genres_json,
                genres_text = :genres_text,
                versions_json = :versions_json,
                cover_thumb_url = :cover_thumb_url,
                cover_medium_url = :cover_medium_url,
                cover_large_url = :cover_large_url,
                audio_url = :audio_url,
                preview_seconds = :preview_seconds,
                release_date = :release_date,
                description = :description,
                attribution_text = :attribution_text,
                download_path = :download_path,
                youtube_watch_url = :youtube_watch_url,
                source_hash = :source_hash,
                last_seen_at = NOW(),
                updated_at = NOW()
            WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($payload);
            return true;
        }

        $sql = "INSERT INTO songs (
            external_id, slug, page_url, title, artists_json, artists_text, genres_json, genres_text, versions_json,
            cover_thumb_url, cover_medium_url, cover_large_url, audio_url, preview_seconds, release_date,
            description, attribution_text, download_path, youtube_watch_url, source_hash, first_seen_at, last_seen_at
        ) VALUES (
            :external_id, :slug, :page_url, :title, :artists_json, :artists_text, :genres_json, :genres_text, :versions_json,
            :cover_thumb_url, :cover_medium_url, :cover_large_url, :audio_url, :preview_seconds, :release_date,
            :description, :attribution_text, :download_path, :youtube_watch_url, :source_hash, NOW(), NOW()
        )";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($payload);
        return true;
    }

    private function jsonOrNull($value): ?string {
        if ($value === null) return null;
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
