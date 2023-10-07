<?php
if (!defined('main_dir')) exit('No direct script access allowed');

function GetUserIP()
{
    $ip = false;
    // Check for Cloudflare headers
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        foreach ($ips as $address) {
            $address = trim($address);
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ip = $address;
                break;
            }
        }
    }

    // If Cloudflare headers are not present or no IPv6 address found, fallback to reverse proxy headers or remote address
    if (empty($ip)) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($ips as $address) {
                $address = trim($address);
                if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $ip = $address;
                    break;
                }
            }
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    }

    return $ip;
}


function getTokenSignature($channel, $user_agent, $clientID) {
    global $database;
    $result = $database->select('twitch_tokens', ['token', 'sec-ch-ua', 'sec-ch-ua-mobile', 'User-Agent', 'Client-Version', 'sec-ch-ua-platform' , 'Client-Session-Id' , 'Client-Id' , 'X-Device-Id'], ['id' => 1]);
    // print_r($result);
    $token_data = $result[0];
    $headers = [
        "Accept: application/json",
        "Connection: keep-alive",
        "Accept-Language: en-US",
        "Client-Id: " . $token_data['Client-Id'],
        // "Client-Id: ue6666qo983tsx6so1t0vnawi233wa",
        "Client-Integrity: " . $token_data['token'],
        "Client-Session-Id: " . $token_data['Client-Session-Id'],
        "Client-Version: " . $token_data['Client-Version'],
        "Connection: keep-alive",
        "Content-Type: text/plain;charset=UTF-8",
        "Origin: https://www.twitch.tv",
        "Referer: https://www.twitch.tv/",
        "sec-ch-ua: " . $token_data['sec-ch-ua'],
        "sec-ch-ua-mobile: " . $token_data['sec-ch-ua-mobile'],
        "sec-ch-ua-platform: " . $token_data['sec-ch-ua-platform'],
        "Sec-Fetch-Dest: empty",
        "Sec-Fetch-Mode: cors",
        "Sec-Fetch-Site: same-site",
        "User-Agent: " . $token_data['User-Agent'],
        "X-Device-Id: " . $token_data['X-Device-Id'],
    ];


    #$post = '{"operationName":"PlaybackAccessToken_Template","query":"query PlaybackAccessToken_Template($login: String!, $isLive: Boolean!, $vodID: ID!, $isVod: Boolean!, $playerType: String!) {  streamPlaybackAccessToken(channelName: $login, params: {platform: \"web\", playerBackend: \"mediaplayer\", playerType: $playerType}) @include(if: $isLive) {    value    signature    __typename  }  videoPlaybackAccessToken(id: $vodID, params: {platform: \"web\", playerBackend: \"mediaplayer\", playerType: $playerType}) @include(if: $isVod) {    value    signature    __typename  }}","variables":{"isLive":true,"login":"' . $channel . '","isVod":false,"vodID":"","playerType":"site"}}';
    $post = '{"query":"{streamPlaybackAccessToken(channelName:\"'. $channel .'\", params:{platform:\\"android\\",playerType:\\"mobile\\"}){value signature}}"}';
    // $post = '{"operationName":"StreamAccessTokenQuery","variables":{"channelName":"'. $channel .'","params":{"platform":"android","playerType":"mobile_player"}},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"4aeb12b75899a00ed245a3bd272047407f60a15f473caaa8a8e64719b6f6a8d5"}}}';
    // $post = '[{"extensions":{"persistedQuery":{"version":1,"sha256Hash":"0828119ded1c13477966434e15800ff57ddacf13ba1911c129dc2200705b0712"}},"operationName":"PlaybackAccessToken","variables":{"isLive":true,"login":"'. $channel .'","isVod":false,"vodID":"","playerType":"frontpage"}}]';
    
    $response = curl_request("https://gql.twitch.tv/gql", [
        "headers" => $headers,
        'form_params' => $post
    ]);

    try{
        // print_r($response);
        if(!empty($response)){
            $data = json_decode($response, true);
        }

        if(empty($data) || empty($data['data']) || empty($data['data']['streamPlaybackAccessToken']))
        {
            return false;
        }
        return $data['data']['streamPlaybackAccessToken'];
    } catch(\Exception $e) {
    }
    echo false;
}

function curl_request($url, $data, $return = false)
{
    $curl = curl_init($url);

    if(!empty($data['headers']))
    {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $data['headers']);
    }

    if(!empty($data['form_params']))
    {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data['form_params']);
    }

    if(!empty($data['patch']))
    {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data['patch']);
    }

    try {
        curl_setopt($curl, CURLOPT_HTTPPROXYTUNNEL, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        // curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($curl, CURLOPT_COOKIESESSION, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 5);
        if (empty($return)) {
            curl_setopt($curl, CURLOPT_HEADER, false);
        } else {
            $headers = [];
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_HEADERFUNCTION,
                function($curl, $header) use (&$headers)
                {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) // ignore invalid headers
                    return $len;

                    $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                    return $len;
                }
            );
        }
        // $headerSent = curl_getinfo($curl, CURLINFO_HEADER_OUT );
        // print_r($headerSent);
        @$content = curl_exec($curl);
        curl_close($curl);
        if (!empty($return)) {
            return $headers;
        }
        
        return $content;
    }  catch (\Exception $e) {
        return $e;
    }
}

function CleanQualityLinks($userID) {
    global $database;
    $database->update("streamers", [
        "quality_url" => '',
        "master_playlist" => '',
        "master_playlist_original" => ''
    ], [
        "id" => $userID
    ]);
}

function SetLastMasterCheck($userID) {
    global $database;
    $database->update("streamers", [
        "master_lastcheck" => date("Y-m-d H:i:s")
    ], [
        "id" => $userID
    ]);
}

function GetCleanInput($input) {
    if(empty($input))
    {
        return false;
    }

    return htmlspecialchars(strip_tags($input), ENT_QUOTES);
}

function isApple() {
    try{
        preg_match("/iPhone|Android|iPad|iPod|webOS/", $_SERVER['HTTP_USER_AGENT'], $matches);
        $os = current($matches);

        switch($os){
            case 'iPhone': return true; break;
            case 'iPad': return true; break;
            case 'iPod': return true; break;
            case 'webOS': return true; break;
        }
    } catch(Exception $e) {}

    return false;
}

function respondWithJSON($message)
{
    header('Content-Type: application/json');
    exit(json_encode(['message' => $message]));
}