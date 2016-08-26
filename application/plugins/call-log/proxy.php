<?php

/**
 * This controller logs a request with the callmap and then sends the
 * user on to his/her destination
 */

namespace VSAC;

$provider = request_query('provider');
if (!$provider) {
    response_send_error(400, 'missing "provider" query parameter');
}
if (!($url = http_uri_is_authorized($provider))) {
    response_send_error(401, 'Destination not authorized');
}

callmap_log($url);

$headers = apache_request_headers();

$headers['Host'] = parse_url($url,  PHP_URL_HOST);
// TODO: worth it to unset cookies? sends session ids to providers

if ($source = request_header('referer')) {
    $headers['X-Forwarded-For'] = $source;
}
$_headers = array();
foreach ($_headers as $k => $v) {
    $_headers[] = $k . ':' . $v;
}

$response = call_user_func(
    __NAMESPACE__ . '\\' . ((request_method() =='post')?'http_post':'http_get'),
    $url,
    false,
    array(CURLOPT_HTTPHEADER => $_headers)
);

if ($response['error']) {
    $code = $response['status'] < 400 ? 500 : $response['status'];
    response_send_error($code, $response['error']);
}

http_response_code($response['status']);
response_send($response['body'], $response['headers']);

