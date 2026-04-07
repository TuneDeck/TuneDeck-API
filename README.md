# NCS Metadata API (PHP)

PHP crawler + JSON API for `ncs.io` that stores only metadata and external URLs.

## Features
- Crawls the NCS list pages (`https://ncs.io`, `?page=2`, ...)
- Extracts metadata from list cards and enriches with song detail pages
- Stores only metadata and external URLs in MySQL/MariaDB
- Exposes a JSON API for Electron or any frontend
- Central config in a single `config.php`

## Endpoints
- `GET /api.php?action=health`
- `GET /api.php?action=songs&page=1&limit=50`
- `GET /api.php?action=song&slug=FAVELA`
- `GET /api.php?action=song&id=dd8b733e-d94a-4a9e-8349-f2674acb21ed`
- `GET /api.php?action=search&q=favela`
- `GET /api.php?action=recent&limit=20`
- `GET /api.php?action=stats`

## CLI
- `php crawl.php init-db`
- `php crawl.php daily`
- `php crawl.php test-url "https://ncs.io/FAVELA"`
