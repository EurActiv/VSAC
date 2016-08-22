<?php

namespace VSAC;


if (!($feed = request_query('feed'))) {
    response_send_json(array('error' =>  'no feed specified'));
}

if (!($feed = http_uri_is_authorized($feed))) {
    response_send_json(array('error' =>  'feed not allowed'));
}

$count = (int) request_query('count', 3);

$fields = explode(',', request_query('fields', 'title,link'));

$strip_tags = (bool) request_query('strip_tags', true);

$content = cal_get_permutation(
    $feed,
    function () use ($feed) {
        if(!http_get($feed, $body, $e, 1)) {
            return null;
        }
        if (!($xml = @simplexml_load_string($body, null, LIBXML_NOCDATA))) {
            return null;
        }
        if (!$xml->channel || !$xml->channel->item) {
            return null;
        }
        $items = array();
        foreach ($xml->channel->item as $entry) {
            $item = json_decode(json_encode($entry), TRUE);
            $namespaces = $entry->getNameSpaces(true);
            if (isset($namespaces['dc']) && $dc = $entry->children($namespaces['dc'])) {
                $dc = json_decode(json_encode($dc), TRUE);
                $item = array_merge($item, $dc);
            }
            $items[] = $item;
        }
        return $items;
    },
    implode('-', array($count, implode(',', $fields), $strip_tags)),
    function ($feed_content) use ($count, $fields, $strip_tags) {
        $return = array();
        foreach ($feed_content as $item) {
            $formatted = array();
            foreach($fields as $field) {
                $formatted[$field] = empty($item[$field]) ? '' : $item[$field];
            }
            if ($strip_tags) {
                $formatted = array_map('strip_tags', $formatted);
                $formatted = array_map('html_entity_decode', $formatted);
            }
            $return[] = $formatted;
            if (count($return) >= $count) {
                break;
            }
        }
        return $return;
    }
);

if (!$content) {
    response_send_json(array('error' => 'unknown error'));
}
response_send_json($content, 60 * 60 * 24 * 31);


