<?php
return [
    'app' => [
        'name' => 'NCS Metadata API',
        'base_url' => 'https://your-domain.example',
        'timezone' => 'Europe/Berlin',
        'cors_allow_origin' => '*',
        'user_agent' => 'Mozilla/5.0 (compatible; NCSMetadataCrawler/2.0; +https://your-domain.example)',
        'log_file' => __DIR__ . '/storage/logs/app.log',
    ],

    'db' => [
        'driver' => 'mysql', // mysql
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => '',
        'username' => '',
        'password' => '',
        'charset' => 'utf8mb4',
    ],

    'crawler' => [
        'base_url' => 'https://ncs.io',
        'start_url' => 'https://ncs.io',
        'page_param' => 'page',
        'max_pages' => 150,
        'timeout_seconds' => 25,
        'connect_timeout_seconds' => 10,
        'delay_ms_between_requests' => 350,
        'crawl_detail_pages_for_new_or_changed_tracks' => true,
        'verify_audio_url_with_head' => false,
        'stop_after_consecutive_empty_pages' => 2,
    ],

    'selectors' => [
        // List page
        'track_card_xpath' => "//div[contains(concat(' ', normalize-space(@class), ' '), ' item ')]",
        'pagination_xpath' => "//ul[contains(@class,'pagination')]//a[contains(@class,'page-link') and contains(@href,'page=')]",

        // Detail page
        'canonical_xpath' => "//link[@rel='canonical']/@href",
        'description_xpath' => "//meta[@name='description']/@content",
        'og_image_xpath' => "//meta[@property='og:image']/@content",
        'waveform_xpath' => "//*[@id='player' and @data-url]",
        'title_h2_xpath' => "//main//h2[1]",
        'artist_links_in_h2_xpath' => "//main//h2[1]//span//a",
        'attribution_xpath' => "//*[@id='panel-copy2']",
        'download_link_xpath' => "//a[contains(@href,'/track/download/')]",
    ],
];
