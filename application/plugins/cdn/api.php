<?php

namespace VSAC;

require_once __DIR__.'/../../framework.php';
bootstrap(__FILE__);
cdn_bootstrap();

$response = array(
    'original_url' => '',
    'cdn_url'      => '',
    'error'        => '',
);

if (!($url = request_query('url']))) {
    $response['error'] = 'URL not set';
    response_send_json($response);
}
$response['original_url'] = $response['cdn_url'] = $url;

if (!apikey_is_valid()) {
    $response['error'] = 'Invalid API key';
    response_send_json($response);
}

// TODO: complete this file

$response['cdn_url'] = router_use_rewriting()
                     ? router_plugin_url($filename)
                     : router_plugin_url('index.php?path='.urlencode($filename));
                     ;
response_send_json($response);











