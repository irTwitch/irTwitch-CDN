<?php
// session_start();
@define("main_dir", __DIR__ . '/');
require_once __DIR__ . '/masterking_app/loader.php';
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

if(empty($_GET['username']) || is_array($_GET['username']))
{
    respondWithJSON('User not exits!');
}

$username = strtolower(GetCleanInput($_GET['username']));
$result = $database->select('streamers', ['id', 'is_relay', 'username', 'master_playlist_original', 'master_lastcheck', 'quality_url' , 'isLive'], ['username' => $username]);

if (empty($result[0]['is_relay']) || empty($result[0]['isLive'])) {
    respondWithJSON('Streamer is not live or does not exist!');
}

$user_data = $result[0];
$username = GetCleanInput(strtolower($user_data['username']));

if(!empty($user_data['quality_url']) && !empty($user_data['master_playlist_original']))
{   
    $ip = GetUserIP();
    if(!empty($ip) /*&& !empty($_SESSION['imRead'])*/ && !$database->has('stream_viewers', ['user_ip' => $ip , 'streamer' => $username]))
    {
        $database->insert('stream_viewers', ['attempt' => 1, 'check_date' => date("Y-m-d H:i:s"), 'streamer' => $username, 'user_ip' => $ip]);
    }

    header("Content-type: application/vnd.apple.mpegurl");
    $m3u8_url = str_replace("IRTWDOMAIN", $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'], $user_data['master_playlist_original']);
    echo $isApple ? str_replace('.php', '.m3u8', $m3u8_url) : $m3u8_url;
    exit();
}

if(!empty($user_data['master_lastcheck']) && strtotime($user_data['master_lastcheck']) + 30 > time())
{
    respondWithJSON('Streamer is not live!');
}

SetLastMasterCheck($user_data['id']);

$token_resp = getTokenSignature($username, user_agent, TWITCH_ORIGINAL_CLIENTID);

if(empty($token_resp) || empty($token_resp['value']) || empty($token_resp['signature'])) {
    respondWithJSON('Streamer is not live!');
}

$streamPlaybackAccessToken=  urlencode(str_replace('\\', '', rtrim(trim(json_encode($token_resp['value']), '"'), '"')));
$signature = $token_resp['signature'];
$twitch_master_m8u3 = "https://usher.ttvnw.net/api/channel/hls/" . $username. ".m3u8?allow_source=true&fast_bread=true&p=" . time()*10 ."&play_session_id=" . md5(time() . rand(1,9999999999)) . "&player_backend=mediaplayer&playlist_include_framerate=true&reassignments_supported=true&sig=" . $signature . "&supported_codecs=avc1&token=" . $streamPlaybackAccessToken . "&cdm=wv&player_version=1.11.0";

$master_response = curl_request($twitch_master_m8u3, ["headers" => ['User-Agent: ' . user_agent]]);
if(empty($master_response) || strlen($master_response) < 50 || strpos($master_response, 'transcode_does_not_exist') > 0) {
    // $database->update("streamers", ['isLive' => 0, 'twitch_viewers' => 0, 'stream_viewers' => 0, 'quality_url' => '', 'master_playlist_original' => '', 'playlist_cache' => ''], [
    //     "id" => $user_data['id']
    // ]);

    respondWithJSON('Streamer is not live!');
}

try{
    $parser = new ParserFacade();
    $media_meta =  $parser->parse(new TextStream($master_response));
} catch(Exception $e) {
    respondWithJSON('Streamer is not live!');
}

if(empty($media_meta['EXT-X-MEDIA']))
{
    respondWithJSON('Streamer is not live!');
}

$new_m3u8 = '#EXTM3U' . "\n";
$new_m3u8 .= '#EXT-X-MASTERKING-INFO:SERVICE-BY:"Amin.MasterkinG"';
$QUALITY_URL = [];
if(count($media_meta['EXT-X-MEDIA']) == 1)
{
    $quality = $media_meta['EXT-X-MEDIA'][0];
    $new_m3u8 .= "\n" . '#EXT-X-MEDIA:TYPE=VIDEO,GROUP-ID="' . $quality['GROUP-ID'] . '",NAME="' . $quality['NAME'] . '",AUTOSELECT=YES,DEFAULT=YES';
    $array_ID = 0;
    $BANDWIDTH = $media_meta['EXT-X-STREAM-INF'][$array_ID]['BANDWIDTH'];
    $CODECS = $media_meta['EXT-X-STREAM-INF'][$array_ID]['CODECS'];
    $VIDEO = $media_meta['EXT-X-STREAM-INF'][$array_ID]['VIDEO'];
    $FRAMERATE = $media_meta['EXT-X-STREAM-INF'][$array_ID]['FRAME-RATE'];
    $RESOLUTION = $media_meta['EXT-X-STREAM-INF'][$array_ID]['RESOLUTION']->__toString();
    $QUALITY_URL[$VIDEO] = $media_meta['EXT-X-STREAM-INF'][$array_ID]['uri'];
    $new_m3u8 .= "\n" . "#EXT-X-STREAM-INF:BANDWIDTH=" . $BANDWIDTH . ',RESOLUTION=' . $RESOLUTION . ',CODECS="' . $CODECS . '",VIDEO="' . $VIDEO  . '",FRAME-RATE=' . $FRAMERATE;
    $new_m3u8 .= "\n" . $media_meta['EXT-X-STREAM-INF'][$array_ID]['uri'];
} else {
    $exists_quality = [];
    foreach($media_meta['EXT-X-MEDIA'] as $array_ID => $quality) {
        $exists_quality[$media_meta['EXT-X-STREAM-INF'][$array_ID]['RESOLUTION']->getHeight()] = 1;
        $new_m3u8 .= "\n" . '#EXT-X-MEDIA:TYPE=VIDEO,GROUP-ID="' . $quality['GROUP-ID'] . '",NAME="' . $quality['NAME'] . '",AUTOSELECT=YES,DEFAULT=YES';
        $BANDWIDTH = $media_meta['EXT-X-STREAM-INF'][$array_ID]['BANDWIDTH'];
        $CODECS = $media_meta['EXT-X-STREAM-INF'][$array_ID]['CODECS'];
        $VIDEO = $media_meta['EXT-X-STREAM-INF'][$array_ID]['VIDEO'];
        $FRAMERATE = $media_meta['EXT-X-STREAM-INF'][$array_ID]['FRAME-RATE'];
        $RESOLUTION = $media_meta['EXT-X-STREAM-INF'][$array_ID]['RESOLUTION']->__toString();
        $QUALITY_URL[$VIDEO] = $media_meta['EXT-X-STREAM-INF'][$array_ID]['uri'];
        $new_m3u8 .= "\n" . "#EXT-X-STREAM-INF:BANDWIDTH=" . $BANDWIDTH . ',RESOLUTION=' . $RESOLUTION . ',CODECS="' . $CODECS . '",VIDEO="' . $VIDEO  . '",FRAME-RATE=' . $FRAMERATE;
        $new_m3u8 .= "\n" . $media_meta['EXT-X-STREAM-INF'][$array_ID]['uri'];
    }
}

$database->update("streamers", [
	"quality_url" => json_encode($QUALITY_URL),
	"master_playlist_original" => $new_m3u8
], [
	"id" => $user_data['id']
]);

if(!empty($_GET['playlist']))
{
    header('location: ' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/live_streams/playlist/' . $username . '.php?quality=' . GetCleanInput($_GET['playlist']));
    exit();
}
header("Content-type: application/vnd.apple.mpegurl");

$m3u8_url = str_replace("IRTWDOMAIN", $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'], $new_m3u8);
echo $isApple ? str_replace('.php', '.m3u8', $m3u8_url) : $m3u8_url;

$ip = GetUserIP();
if(!empty($ip) /*&& !empty($_SESSION['imRead'])*/ && !$database->has('stream_viewers', ['user_ip' => $ip , 'streamer' => $username]))
{
    $database->insert('stream_viewers', ['attempt' => 1, 'check_date' => date("Y-m-d H:i:s"), 'streamer' => $username, 'user_ip' => $ip]);
}
