<?php
require __DIR__ . '/bootstrap.php';

$command = $argv[1] ?? null;

try {
    switch ($command) {
        case 'init-db':
            $db->initSchema(__DIR__ . '/schema.sql');
            echo "Database schema created successfully.\n";
            break;

        case 'daily':
            $result = $crawler->runDaily();
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            break;

        case 'test-url':
            $url = $argv[2] ?? '';
            if (!$url) {
                throw new InvalidArgumentException('Usage: php crawl.php test-url "https://ncs.io/FAVELA"');
            }
            $result = $crawler->testUrl($url);
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            break;

        default:
            echo "Usage:\n";
            echo "  php crawl.php init-db\n";
            echo "  php crawl.php daily\n";
            echo "  php crawl.php test-url \"https://ncs.io/FAVELA\"\n";
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
