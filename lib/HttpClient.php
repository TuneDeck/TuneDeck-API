<?php
class HttpClient {
    public function __construct(private array $config) {}

    public function get(string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => (int)$this->config['crawler']['connect_timeout_seconds'],
            CURLOPT_TIMEOUT => (int)$this->config['crawler']['timeout_seconds'],
            CURLOPT_USERAGENT => $this->config['app']['user_agent'],
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.8,de;q=0.7',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status >= 400) {
            throw new RuntimeException("HTTP GET failed for {$url} (status {$status}) {$error}");
        }

        return [
            'status' => $status,
            'url' => $effectiveUrl,
            'body' => $body,
        ];
    }
}
