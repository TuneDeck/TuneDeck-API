<?php
class NcsParser {
    public function __construct(private array $config) {}

    public function parseListPage(string $html): array {
        $xp = Html::dom($html);
        $cards = $xp->query($this->config['selectors']['track_card_xpath']);
        $songs = [];

        foreach ($cards as $card) {
            if (!$card instanceof DOMElement) continue;

            $linkNode = $xp->query(".//a[@href][1]", $card)?->item(0);
            $href = $linkNode instanceof DOMElement ? Html::attr($linkNode, 'href') : null;
            $slug = $href ? ltrim(parse_url($href, PHP_URL_PATH) ?? '', '/') : null;
            if (!$slug || in_array($slug, ['artists', 'about', 'contact', 'usage-policy'], true)) {
                continue;
            }

            $play = $xp->query(".//a[contains(concat(' ', normalize-space(@class), ' '), ' player-play ')]", $card)?->item(0);
            if (!$play instanceof DOMElement) {
                continue;
            }

            $title = Html::attr($play, 'data-track') ?: Html::firstString($xp, ".//div[contains(@class,'bottom')]//p/strong", $card);
            if (!$title) continue;

            $artistsText = Html::attr($play, 'data-artistraw') ?: Html::firstString($xp, ".//div[contains(@class,'bottom')]//*[contains(@class,'tags')]", $card);
            $artists = $this->splitCsv($artistsText);

            $genresText = Html::attr($play, 'data-genre');
            $genres = $this->splitCsv($genresText);

            $versionsText = Html::attr($play, 'data-versions');
            $versions = $this->splitCsv($versionsText);

            $releaseDate = Html::firstAttr($xp, ".//div[contains(@class,'options')]//p[@title]/@title", $card);
            $bgNode = $xp->query(".//*[contains(@class,'img') and @style][1]", $card)?->item(0);
            $bgStyle = $bgNode instanceof DOMElement ? Html::attr($bgNode, 'style') : null;
            $coverMedium = Html::styleBackgroundUrl($bgStyle);
            $coverThumb = Html::attr($play, 'data-cover') ?: $coverMedium;

            $songs[] = [
                'external_id' => Html::attr($play, 'data-tid'),
                'slug' => $slug,
                'page_url' => Html::absoluteUrl($this->config['crawler']['base_url'], '/' . $slug),
                'title' => $title,
                'artists' => $artists,
                'artists_text' => $artistsText,
                'genres' => $genres,
                'genres_text' => $genresText,
                'versions' => $versions,
                'cover_thumb_url' => $coverThumb,
                'cover_medium_url' => $coverMedium,
                'audio_url' => Html::attr($play, 'data-url'),
                'preview_seconds' => $this->toInt(Html::attr($play, 'data-preview')),
                'release_date' => $this->normalizeDate($releaseDate),
            ];
        }

        $paginationNodes = $xp->query($this->config['selectors']['pagination_xpath']);
        $pages = [];
        foreach ($paginationNodes as $node) {
            if (!$node instanceof DOMElement) continue;
            $href = Html::attr($node, 'href');
            if (!$href) continue;
            parse_str((string)parse_url($href, PHP_URL_QUERY), $qs);
            if (isset($qs[$this->config['crawler']['page_param']])) {
                $pages[] = (int)$qs[$this->config['crawler']['page_param']];
            }
        }

        return [
            'songs' => $songs,
            'max_page_hint' => $pages ? max($pages) : 1,
        ];
    }

    public function parseDetailPage(string $html): array {
        $xp = Html::dom($html);
        $canonical = Html::firstAttr($xp, $this->config['selectors']['canonical_xpath']);
        $slug = $canonical ? ltrim(parse_url($canonical, PHP_URL_PATH) ?? '', '/') : null;
        $description = Html::firstAttr($xp, $this->config['selectors']['description_xpath']);
        $coverLarge = Html::firstAttr($xp, $this->config['selectors']['og_image_xpath']);
        $waveform = $xp->query($this->config['selectors']['waveform_xpath'])?->item(0);

        $audioUrl = $waveform instanceof DOMElement ? Html::attr($waveform, 'data-url') : null;
        $externalId = $waveform instanceof DOMElement ? Html::attr($waveform, 'data-tid') : null;

        $title = null;
        $artists = [];
        $artistLinks = $xp->query($this->config['selectors']['artist_links_in_h2_xpath']);
        if ($artistLinks && $artistLinks->length > 0) {
            foreach ($artistLinks as $link) {
                $name = Html::text($link);
                if ($name) $artists[] = $name;
            }
        }

        $h2Node = $xp->query($this->config['selectors']['title_h2_xpath'])?->item(0);
        if ($h2Node instanceof DOMNode) {
            $title = $this->extractTitleFromH2($h2Node);
        }

        $downloadNode = $xp->query($this->config['selectors']['download_link_xpath'])?->item(0);
        $downloadPath = $downloadNode instanceof DOMElement ? Html::attr($downloadNode, 'href') : null;

        $attribution = Html::firstString($xp, $this->config['selectors']['attribution_xpath']);
        $youtube = null;
        if ($attribution && preg_match('~Watch:\s*(https?://\S+)~i', $attribution, $m)) {
            $youtube = trim($m[1]);
        }

        $genres = [];
        $moods = [];
        if ($description && preg_match('~Listen to\s+.+?\s+on\s+NCS\s+-\s+(.+)$~i', $description, $m)) {
            $parts = array_map('trim', explode(',', $m[1]));
            if ($parts) {
                $genres[] = array_shift($parts);
                $moods = array_values(array_filter($parts));
            }
        }

        return array_filter([
            'external_id' => $externalId,
            'slug' => $slug,
            'page_url' => $canonical,
            'title' => $title,
            'artists' => $artists ?: null,
            'artists_text' => $artists ? implode(', ', $artists) : null,
            'genres' => $genres ?: null,
            'genres_text' => $genres ? implode(', ', $genres) : null,
            'moods' => $moods ?: null,
            'cover_large_url' => $coverLarge,
            'audio_url' => $audioUrl,
            'description' => $description,
            'attribution_text' => $attribution,
            'download_path' => $downloadPath,
            'youtube_watch_url' => $youtube,
        ], fn($v) => $v !== null && $v !== '');
    }

    private function splitCsv(?string $value): array {
        if ($value === null) return [];
        $parts = array_map('trim', explode(',', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        return array_values(array_filter($parts, fn($v) => $v !== ''));
    }

    private function normalizeDate(?string $value): ?string {
        if (!$value) return null;
        $dt = date_create($value);
        return $dt ? $dt->format('Y-m-d') : null;
    }

    private function toInt(?string $value): ?int {
        if ($value === null || $value === '') return null;
        return (int)$value;
    }

    private function extractTitleFromH2(DOMNode $h2): ?string {
        $title = '';
        foreach ($h2->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'span') {
                break;
            }
            $title .= $child->textContent;
        }
        $title = trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $title !== '' ? $title : null;
    }
}
