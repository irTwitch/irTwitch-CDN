<?php
@define("main_dir", __DIR__ . '/');
require_once('./masterking_app/loader.php');

if(empty($_SERVER['HTTP_HOST']) || empty($_SERVER['REQUEST_SCHEME']))
{
    respondWithJSON('Request is not valid!');
}

$seconds_to_cache = 300;
$ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
header("Expires: $ts");
header("Pragma: cache");
header("Cache-Control: max-age=$seconds_to_cache");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: GET");
header("Vary: Origin");
header('Access-Control-Max-Age: 86400');

if(empty($_GET['ts']) || is_array($_GET['ts']) || !preg_match('/^[0-9a-zA-Z_]+\.ts/', $_GET['ts']))
{
    respondWithJSON('File not exits!');
}

$result = $database->select('stream_ts_cache', '*', ['file_name' => GetCleanInput($_GET['ts'])]);
if(empty($result[0]['file_name']))
{
    respondWithJSON('File not exits!');
}

$file_path = main_dir . 'segments/' . $result[0]['file_name'];

if(!empty($result[0]['downloaded']) && file_exists($file_path) && $result[0]['downloaded'] == 2)
{
    header("Content-type: application/octet-stream");
    readfile($file_path);
    exit();
}

if(!empty($result[0]['downloaded']))
{
    usleep(rand(300000,500000));
    $result = $database->select('stream_ts_cache', '*', ['file_name' => GetCleanInput($_GET['ts'])]);
    if(!empty($result[0]['downloaded']) && file_exists($file_path) && $result[0]['downloaded'] == 2)
    {
        header("Content-type: application/octet-stream");
        readfile($file_path);
        exit();
    }
}
$database->update('stream_ts_cache', ['downloaded' => 1], ['file_name' => $result[0]['file_name']]);
$ts_response = curl_request($result[0]['url'], ["headers" => ['User-Agent: ' . user_agent]]);
if(empty($ts_response) || strpos($ts_response, 'not_found') !== false)
{
    respondWithJSON('File not exits!');
}

header("Content-type: application/octet-stream");
echo $ts_response;
try{
    
    $result = $database->select('stream_ts_cache', '*', ['file_name' => GetCleanInput($_GET['ts'])]);
    if(!empty($result[0]['downloaded']) && $result[0]['downloaded'] == 2)
    {
        exit();
    }
    $database->update('stream_ts_cache', ['downloaded' => 2], ['file_name' => $result[0]['file_name']]);

    if(file_exists($file_path))
    {
        exit();
    }

    $file = fopen($file_path, 'wb');
    if ($file === false) {
        exit();
    }
    
    fwrite($file, $ts_response);
    fclose($file);
} catch(Exception $e) {
    
}