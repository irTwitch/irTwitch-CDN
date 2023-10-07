<?php

date_default_timezone_set('Asia/Tehran');
header('X-Powered-Framework:MasterkinG-Framework');
header('X-Powered-CMS:MasterkinG-CMS');

use Medoo\Medoo;

define('cdn_url', array('https://cdn.xxxxxxxxxxxxxx.ink/', 'https://cdn.xxxxxxxxxxxxxx.click/', 'https://cdn.xxxxxxxxxxxxxx.click/', 'https://cdn.xxxxxxxxxxxxxx.click/', 'https://cdn.xxxxxxxxxxxxxx.click/', 'https://cdn.xxxxxxxxxxxxxx.click/'));
define('TWITCH_ORIGINAL_CLIENTID', 'kimne78kx3ncx6brgo4mv6wki5h1ko');

define('user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/114.0');

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_USER_NOTICE);
$database = new Medoo([
	// [required]
	'type' => 'mysql',
	'host' => '127.0.0.1',
	'database' => 'irtwitch',
	'username' => 'root',
	'password' => 'xxxxxxxxxxxxxx',
 
	'charset' => 'utf8mb4',
	'collation' => 'utf8mb4_general_ci',
	'port' => 3306,
	'logging' => false,
	'error' => PDO::ERRMODE_SILENT,
]);
