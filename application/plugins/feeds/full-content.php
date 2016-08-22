<?php

namespace VSAC;


function feeds_full_content_info($handle)
{
    $feeds = config('full_content_feeds', array());
    foreach ($feeds as $feed) {
        if ($feed['handle'] == $handle) {
            if (!isset($feed['feed_curl_options'])) {
                $feed['feed_curl_options'] = array();
            }
            if (!isset($feed['item_curl_options'])) {
                $feed['item_curl_options'] = array();
            }
            return $feed;
        }
    }
    return false;
}

function feeds_full_content_fetch_item($url, $feed)
{
    if(!http_get($url, $body, $e, true, $feed['item_curl_options'])) {
        return false;
    }
    $body = htmlspecialchars_decode(@htmlentities($body, ENT_COMPAT|ENT_HTML401, mb_detect_encoding($body)));
    $tidy = tidy_parse_string($body, array(
        'clean'       => 'yes',
        'output-html' => 'yes',
    ), 'utf8');
    $tidy->cleanRepair();

    $dom = new \DOMDocument();
    @$dom->loadHTML($tidy);
    while (($r = $dom->getElementsByTagName("script")) && $r->length) {
        $r->item(0)->parentNode->removeChild($r->item(0));
    }

    $xpath = new \DOMXPath($dom);
    $elements = $xpath->query($feed['xpath']);
    $full_content = array();
    foreach ($elements as $tag) {
        $full_content[] = trim($tag->c14n());
    }
    return preg_replace("/[\s]+/" , ' ', implode(" ", $full_content));
    
}



if (!apikey_is_valid()) {
    response_send_json(array('error' =>  'invalid api_key'));
}

if (!($feed = request_query('feed'))) {
    response_send_json(array('error' =>  'no feed specified'));
}

if (!($info = feeds_full_content_info($feed))) {
    response_send_json(array('error' =>  'unknown feed'));
}

$content = cal_get_item($feed, function () use ($info) {

    if(!http_get($info['url'], $body, $e, false, $info['feed_curl_options'])) {
        return null;
    }

    if (!($xml = @simplexml_load_string($body, null, LIBXML_NOCDATA))) {
        return null;
    }

    foreach($xml->channel->item as $item) {
        $link = (string) $item->link;
        if ($full = feeds_full_content_fetch_item($link, $info)) {
            $item->description = $full;
        }
    }
    return $xml->asXml();
});

response_send($content, array('Content-Type' => 'text/xml; charset=utf-8'));


