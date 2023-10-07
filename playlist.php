<?php
@define("main_dir", __DIR__ . '/');
require_once('./masterking_app/loader.php');

use Chrisyue\PhpM3u8\Facade\ParserFacade;
use Chrisyue\PhpM3u8\Stream\TextStream;

if(empty($_SERVER['HTTP_HOST']) || empty($_SERVER['REQUEST_SCHEME']))
{
    respondWithJSON('Request is not valid!');
}

header("Access-Control-Allow-Origin: https://{$_SERVER['HTTP_HOST']}");
header("Access-Control-Allow-Headers: X-Requested-With");
header('Access-Control-Max-Age: 86400');

$isApple = isApple();

if(empty($_GET['username']) || is_array($_GET['username']) || empty($_GET['quality']) || is_array($_GET['quality']) || !preg_match('/^[0-9a-zA-Z]+$/', $_GET['quality'])) {
    respondWithJSON('Stream not exits!');
}

$username = GetCleanInput(strtolower($_GET['username']));

$main_m3u8 = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/live_streams/stream/' . $username . '.php?playlist=' . GetCleanInput($_GET['quality']);
$main_m3u8 = $isApple ? str_replace('.php', '.m3u8', $main_m3u8) : $main_m3u8;
$current_page = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/live_streams/playlist/' . $username . '.php?quality=' . GetCleanInput($_GET['quality']);
$current_page = $isApple ? str_replace('.php', '.m3u8', $current_page) : $current_page;

$result = $database->select('streamers', ['id', 'username', 'is_relay', 'isLive', 'quality_url', 'playlist_checksum'], ['username' => $username]);
if(empty($result[0]['id']) || empty($result[0]['is_relay']) || empty($result[0]['quality_url']) || empty($result[0]['isLive']))
{
    header('location: ' . $main_m3u8);
    exit();
}

$user_data = $result[0];
$username = $user_data['username'];
$qualities = json_decode($user_data['quality_url'], true);

if(empty($qualities[$_GET['quality']]))
{
    respondWithJSON('Stream not exits!');
}

$quality = $qualities[GetCleanInput($_GET['quality'])];

if(!empty($user_data['playlist_time']) && strtotime($user_data['playlist_time']) >= time() && !empty($user_data['playlist_cache']))
{
    $playlist_cache = $isApple ? str_replace('.php', '.ts', $user_data['playlist_cache']) : $user_data['playlist_cache'];
    echo $playlist_cache;
    exit(); 
}
$playlist_response = curl_request($quality, ["headers" => ['User-Agent: ' . user_agent]]);
if(empty($playlist_response) || strlen($playlist_response) < 50 || strpos($playlist_response, 'transcode_does_not_exist') > 0) {
    CleanQualityLinks($user_data['id']);
    header('location: ' . $main_m3u8);
    exit();
}
$check_sum = md5($playlist_response);
if(!empty($user_data['playlist_checksum']) && $check_sum == $user_data['playlist_checksum'] && !empty($user_data['playlist_cache']))
{
    $playlist_cache = $isApple ? str_replace('.php', '.ts', $user_data['playlist_cache']) : $user_data['playlist_cache'];
    echo $playlist_cache;
    exit();
}

try{
    $parser = new ParserFacade();
    $media_meta =  $parser->parse(new TextStream($playlist_response));
} catch(Exception $e) {
    header('location: ' . $current_page);
    exit();
}

if(empty($media_meta['EXT-X-TARGETDURATION']) || empty($media_meta['EXT-X-MEDIA-SEQUENCE']) || empty($media_meta['EXT-X-VERSION']) || empty($media_meta['mediaSegments']))
{
    header('location: ' . $current_page);
    exit();
}

$new_m3u8 = '#EXTM3U' . "\n";
$new_m3u8 .= '#EXT-X-VERSION:' . $media_meta['EXT-X-VERSION'];
$new_m3u8 .= "\n" . '#EXT-X-TARGETDURATION:' . $media_meta['EXT-X-TARGETDURATION'];
$new_m3u8 .= "\n" . '#EXT-X-MEDIA-SEQUENCE:' . $media_meta['EXT-X-MEDIA-SEQUENCE'];
$new_m3u8 .= "\n" . '#EXT-X-MASTERKING-INFO:SERVICE-BY:"Amin.MasterkinG"';

foreach($media_meta['mediaSegments'] as $key => $segment) {
    if(empty($segment['EXTINF']) || empty($segment['EXT-X-PROGRAM-DATE-TIME']) || empty($segment['uri']))
    {
        header('location: ' . $current_page);
        exit();
    }

    $new_m3u8 .= "\n" . '#EXTINF:' . $segment['EXTINF']->getDuration() . ',live';
    $timezone = $segment['EXT-X-PROGRAM-DATE-TIME']->format('P');
    $time = str_replace('+00:00', 'Z', sprintf('%s%s', substr($segment['EXT-X-PROGRAM-DATE-TIME']->format('Y-m-d\TH:i:s.u'), 0, -3), $timezone));
    $new_m3u8 .= "\n" . '#EXT-X-PROGRAM-DATE-TIME:'. $time;
    $new_filename = md5($username) . '_' . GetCleanInput(strtolower($_GET['quality'])) . '_' . md5($segment['uri']) . '.ts';
    $new_filename_b = $new_filename;
    $new_filename = $isApple ? $new_filename : str_replace('.ts', '.php', $new_filename);
    $new_m3u8 .= "\n" . cdn_url[rand(0, count(cdn_url)-1)] . 'live_streams/segments/' . $new_filename;
    if(!$database->has('stream_ts_cache', ['file_name' => $new_filename_b])){
        $database->insert('stream_ts_cache', ['url' => $segment['uri'], 'file_name' => $new_filename_b, 'create_time' => date("Y-m-d H:i:s")]);
    }
}

$database->update("streamers", [
	"playlist_time" => date("Y-m-d H:i:s"),
	"playlist_checksum" => $check_sum,
	"playlist_cache" => $new_m3u8
], [
	"id" => $user_data['id']
]);

header("Content-type: application/vnd.apple.mpegurl");
$new_m3u8 = $isApple ? str_replace('.php', '.ts', $new_m3u8) : $new_m3u8;
echo $new_m3u8;