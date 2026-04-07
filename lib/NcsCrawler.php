<?php
class NcsCrawler {
    public function __construct(
        private array $config,
        private HttpClient $http,
        private Database $db,
        private NcsParser $parser,
        private Logger $logger,
    ) {}

    public function runDaily(): array {
        $runId = $this->db->startRun('daily');
        $stats = [
            'pages_crawled' => 0,
            'detail_pages_crawled' => 0,
            'songs_seen' => 0,
            'songs_upserted' => 0,
            'detail_pages_skipped' => 0,
        ];

        try {
            $base = $this->config['crawler']['start_url'];
            $maxPages = (int)$this->config['crawler']['max_pages'];
            $emptyPages = 0;
            $maxPageHint = 1;

            for ($page = 1; $page <= $maxPages; $page++) {
                $url = $page === 1 ? $base : $base . '?' . $this->config['crawler']['page_param'] . '=' . $page;
                $this->logger->info('Fetching list page', ['page' => $page, 'url' => $url]);
                $response = $this->http->get($url);
                $parsed = $this->parser->parseListPage($response['body']);
                $songs = $parsed['songs'];
                $maxPageHint = max($maxPageHint, (int)$parsed['max_page_hint']);
                $stats['pages_crawled']++;

                if (!$songs) {
                    $emptyPages++;
                    if ($emptyPages >= (int)$this->config['crawler']['stop_after_consecutive_empty_pages']) {
                        break;
                    }
                    continue;
                }

                $emptyPages = 0;
                foreach ($songs as $song) {
                    $stats['songs_seen']++;

                    if (!$this->isValidSongPageUrl($song['page_url'] ?? null)) {
                        $stats['detail_pages_skipped']++;
                        $this->logger->info('Skipping invalid song page URL', [
                            'page_url' => $song['page_url'] ?? null,
                            'slug' => $song['slug'] ?? null,
                            'external_id' => $song['external_id'] ?? null,
                        ]);

                        if ($this->hasUsableSongData($song) && $this->db->upsertSong($song)) {
                            $stats['songs_upserted']++;
                        }
                        continue;
                    }

                    $existing = $this->db->findSongBySlugOrExternalId($song['slug'] ?? null, $song['external_id'] ?? null);
                    $needsDetail = (bool)$this->config['crawler']['crawl_detail_pages_for_new_or_changed_tracks'];
                    $merged = $song;

                    if ($needsDetail && $existing) {
                        $needsDetail = $this->songNeedsDetailRefresh($song, $existing);
                    }

                    if ($needsDetail) {
                        usleep((int)$this->config['crawler']['delay_ms_between_requests'] * 1000);

                        try {
                            $detail = $this->http->get($song['page_url']);
                            $detailData = $this->parser->parseDetailPage($detail['body']);
                            $merged = array_merge($song, array_filter($detailData, fn($v) => $v !== null && $v !== ''));
                            $stats['detail_pages_crawled']++;
                        } catch (Throwable $e) {
                            $stats['detail_pages_skipped']++;
                            $this->logger->info('Skipping detail page after fetch/parsing error', [
                                'page_url' => $song['page_url'],
                                'slug' => $song['slug'] ?? null,
                                'external_id' => $song['external_id'] ?? null,
                                'error' => $e->getMessage(),
                            ]);

                            $merged = $song;
                        }
                    }

                    if ($this->hasUsableSongData($merged) && $this->db->upsertSong($merged)) {
                        $stats['songs_upserted']++;
                    }
                }

                if ($page >= $maxPageHint) {
                    break;
                }

                usleep((int)$this->config['crawler']['delay_ms_between_requests'] * 1000);
            }

            $this->db->finishRun($runId, 'success', $stats, 'Daily crawl completed');
            return ['ok' => true, 'stats' => $stats];
        } catch (Throwable $e) {
            $this->logger->error('Crawler failed', ['error' => $e->getMessage()]);
            $this->db->finishRun($runId, 'failed', $stats, $e->getMessage());
            throw $e;
        }
    }

    public function testUrl(string $url): array {
        $response = $this->http->get($url);
        if (preg_match('~\?page=\d+$~', $url) || rtrim($url, '/') === rtrim($this->config['crawler']['start_url'], '/')) {
            return $this->parser->parseListPage($response['body']);
        }
        return $this->parser->parseDetailPage($response['body']);
    }

    private function hasUsableSongData(array $song): bool {
        return !empty($song['slug']) || !empty($song['external_id']) || !empty($song['audio_url']) || !empty($song['title']);
    }

    private function isValidSongPageUrl(?string $url): bool {
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if (!in_array($host, ['ncs.io', 'www.ncs.io'], true)) {
            return false;
        }

        if (!preg_match('#^/[A-Za-z0-9_\\-!]+$#', $path)) {
            return false;
        }

        $blockedPrefixes = [
            '/artist',
            '/artists',
            '/track',
            '/usage-policy',
            '/about',
            '/contact',
            '/privacy',
            '/facebook',
            '/google',
            '/spotify',
            '/log-in',
            '/forgot-password',
            '/music-search-proxy',
        ];

        foreach ($blockedPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return false;
            }
        }

        return true;
    }

    private function songNeedsDetailRefresh(array $song, array $existing): bool {
        $fields = [
            'audio_url',
            'cover_large_url',
            'cover_url',
            'description',
            'attribution_text',
            'youtube_url',
            'download_path',
        ];

        foreach ($fields as $field) {
            $newVal = trim((string)($song[$field] ?? ''));
            $oldVal = trim((string)($existing[$field] ?? ''));
            if ($newVal !== '' && $newVal !== $oldVal) {
                return true;
            }
        }

        foreach (['cover_large_url', 'description', 'download_path'] as $field) {
            if (trim((string)($existing[$field] ?? '')) === '') {
                return true;
            }
        }

        return false;
    }
}
