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

if ($source = request_header('referer')) {
    response_header('X-Forwarded-For', $source);
}

// NOTE: not using the response_redirect function because it does
// not support 307 redirects.
header("Location: {$url}", true, 307);

die();
