<?php

namespace VSAC;

$response = array(
    'logged' => false,
    'error'  => '',
);

if (!apikey_is_valid()) {
    $response['error'] = 'invalid api key';
    response_send_json($response);
}

if (!$provider = request_query('provider')) {
    $response['error'] = '"provider" query parameter not set';
    response_send_json($response);

}

if (!$consumer = request_query('consumer')) {
    $response['error'] = '"consumer" query parameter not set';
}

callmap_log($provider, $consumer);
$response['logged'] = true;

response_send_json($response);
