<?php
$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

require __DIR__ . '/lib/Logger.php';
require __DIR__ . '/lib/HttpClient.php';
require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/Html.php';
require __DIR__ . '/lib/NcsParser.php';
require __DIR__ . '/lib/NcsCrawler.php';

$logger = new Logger($config['app']['log_file']);
$db = new Database($config);
$http = new HttpClient($config);
$parser = new NcsParser($config);
$crawler = new NcsCrawler($config, $http, $db, $parser, $logger);
