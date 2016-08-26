<?php

/**
 * This API endpoint exists for storing and sending facebook counts. It cannot
 * be done entirely on the server side because FaceBook has a really low
 * throttle on the share count API (something like 200 URLs an hour). To get
 * around this, we use clients to fetch the share count using the API and
 * pseudo-post it back here for temporary storage. So we're only allowing
 * FaceBook to invade privacy for a small percentage of our users.
 */

namespace VSAC;

// not an authorized URL. Go away.
if (!($url = request_query('url'))) {
    response_send_error(400, 'URL not specified');
}
if (!($url = http_uri_is_authorized($url))) {
    response_send_error(400, 'Invalid or unauthorized URL');
}

$count = kval_get($url);
if (!is_null($count)) {
    response_send_json(array(
        'shares' => (int) $count,
        'cached' => true
    ));
}

// Client is requesting share count, but there isn't one. Sent back the storage
// key so the client can get it from FB and use it.
if (!($key = request_query('key'))) {
    response_send_json(array('key' => social_get_key($url)));    
}


// Client fetched from facebook and is pseudo-posting back here
if (social_validate_key($key, $url)) {
    $shares = request_query('shares');
    if (preg_match('/^\d+$/', $shares)) {
        callmap_log('facebook.com');
        kval_set($url, (int) $shares);
        response_send_json(array('shares' => (int) $shares));
    } else {
        response_send_json(array('error' => 'Invalid share count'));
    }
} else {
    response_send_json(array('error'=>'Invalid key'));
}




