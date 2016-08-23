<?php

/**
 * Functions for sending an http response. Note that sending a response will
 * die(), so any code after a response_send* function will not execute.
 *
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_config_items() */
function response_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function response_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function response_test()
{
    return true;
}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//


//-- Headers -----------------------------------------------------------------//

/**
 * Send a response header. Has a couble of formatting control measures built in.
 *
 * - will make sure that the colon spacing is correct;
 * - if the header should be a datetime formatted, ensures the proper format (eg
 *   you can pass the "Date" header as a timestamp or anything that strtotime
 *   can parse and it'll be formatted correctly before sending
 *
 * @param string $name the header name, or the entire header string
 * @param mixed $value the value the value to assign to this header
 *
 * @return void
 */ 
function response_header($name, $value = null)
{
    if (!$value && strpos($name, ':')) {
        list($name, $value) = explode(':', $name, 2);
    }
    if (!$value) {
        return header($name);
    }
    $time_headers = array('Date', 'Expires', 'Last-Modified');
    if (in_array($name, $time_headers)) {
        $value = is_numeric($value) ? (int) $value : strtotime($value);
        $value =  gmdate('D, d M Y H:i:s T', $value);
    }
    return header($name . ': ' . $value);
}

/**
 * Send a bunch of headers at once.
 *
 * @param array $headers Either: a key=>value map where the key is the header
 * name and the value is the header value; or a simple numeric array where the
 * values are the entire header string; or a mixture of the two
 *
 * @return void
 */
function response_headers($headers)
{
    foreach ($headers as $name => $value) {
        if (is_numeric($name)) {
            response_header($value);
        } else {
            response_header($name, $value);
        }
    }
}

//-- Sending responses. These will all die() ---------------------------------//

/**
 * Send a generic response, caller is responsible for ensuring all of the
 * the headers (especially content type) are properly set.
 *
 * @param string $body the response body
 * @param array $headers the headers to send, @see response_headers();
 */
function response_send($body, array $headers)
{
    $etag = $headers['ETag'] = '"'.md5($body).'"';
    if (response_is_304($headers)) {
        return response_send_304($headers);
    }
    $headers['Content-Length'] = strlen($body);
    response_headers($headers);
    echo $body;
    die();
}

/**
 * Send a 304 response
 *
 * @param array $headers the response headers, only the necessary ones will
 * actually be send.
 */
function response_send_304(array $headers)
{
    $_headers = array('Cache-Control', 'ETag', 'Last-Modified');
    foreach ($_headers as $_header) {
        if (isset($headers[$_header])) {
            response_header($_header, $headers[$_header]);
        }
    }
    http_response_code(304);
    die();
}

/**
 * Send a JSON response, will convert to JSONP if the callback query parameter
 * is set in the request.
 *
 * @param mixed $response anything that can be JSON encoded
 * @param integer $expires the response expires in this many seconds
 */
function response_send_json($response, $expires = false)
{
    $headers = array();
    $body = json_encode($response, JSON_PRETTY_PRINT);

    if ($expires) {
        $headers['Cache-Control'] = 'max-age=' . $expires;
        $headers['Expires'] = time() + $expires;
    }

    if ($cb = request_query('callback')) {
        $headers['Content-Type'] =  'application/javascript';
        $cb = preg_match('/^[a-z_][a-z0-9_]*$/i', $cb) ? $cb : 'callback';
        $body = sprintf('/* jsonp */ %s(%s);', $cb, $body);
    } else {
        $headers['Content-Type'] = 'application/json';
    }
    response_send($body, $headers);

}

/**
 * Send an HTTP error response.
 *
 * @param integer $code any of the HTTP status codes >= 400
 * @param string $msg set an error message header for debugging
 */
function response_send_error($code = 404, $msg = '')
{
    if ($msg) {
        response_header('X-VSAC-error-msg', $msg);
    }
    http_response_code($code);
    die();
}

/**
 * Send a file on disk.
 *
 * @param string $abspath the absolute path to the file
 * @param int $expires the resource expires in this many seconds
 */
function response_send_file($abspath, $expires = false)
{
    if (!($abspath = filesystem_realpath($abspath, true))) {
        response_send_error();
    }
    $length = filesize($abspath);
    $mtime  = filemtime($abspath);
    $headers = array(
        'Access-Control-Allow-Origin' => '*',
        'Content-Length' => $length,
        'Last-Modified'  => $mtime,
        'ETag'           => '"' . md5($abspath . $length . $mtime) . '"'
    );

    if ($expires) {
        $headers['Cache-Control'] = 'max-age=' . $expires;
        $headers['Expires'] = time() + $expires;
    } else {
        $headers['Cache-Control'] = 'max-age=31536000';
    }


    $ext = pathinfo($abspath, PATHINFO_EXTENSION);
    $headers['Content-Type'] = response_ext_to_mime($ext);

    if (response_is_304($headers)) {
        return response_send_304($headers);
    }
    response_headers($headers);
    readfile($abspath);
    die();
}

function response_redirect($url, $permanent = true)
{
    if (!preg_match('/^(https?:)?\/\//', $url)) {
        $url = router_base_url() . $url;
    }
    if (strpos($url, '//') === 0) {
        $url = router_scheme() . $url;
    }
    if (!headers_sent()) {
        header("Location: {$url}", true, $permanent ? 301 : 302);
    } else {
        // in case we're inside of a tag for some reason
        echo '"\'>"\'></canvas></embed></frame></noscript></object></style></script></textarea>';
        printf(
            '<meta http-equiv="refresh" content="10;URL=\'%s\'" />',
            htmlspecialchars($url)
        );
        printf(
            '<script>document.location.%s(%s)</script>',
            $permanent ? 'replace' : 'assign',
            json_encod($url)
        );
    }
    die();
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

/**
 * Check the response headers against the request headers to see if this should
 * be a 304 no change response.
 *
 * @param array $headers the response headers
 *
 * @return bool true if it's a 304, or false if not.
 */
function response_is_304(array $headers)
{
    $ifset = function ($array, $offset) {
        return isset($array[$offset]) ? $array[$offset] : false;
    };
    $ifset_time = function ($array, $offset) use ($ifset) {
        if ($time = $ifset($array, $offset)) {
            return is_numeric($time) ? $time : strtotime($time);
        }
        return false;
    };
    $server = superglobal('server');

    $if_none_match = $ifset($server, 'HTTP_IF_NONE_MATCH');
    $etag = $ifset($headers, 'ETag');

    if ($if_none_match && $if_none_match === $etag) {
        return true;
    }

    $if_modified_since = $ifset_time($server, 'HTTP_IF_MODIFIED_SINCE');
    $last_modified = $ifset_time($headers, 'Last-Modified');
    if ($if_modified_since && $last_modified && $if_modified_since < $last_modified) {
        return true;
    }

    return false;

}

/**
 * Figure out what the response MIME should be based on the a file extension.
 *
 * @param string $ext the extension
 *
 * @return string the mime
 */
function response_ext_to_mime($ext)
{
    $mimes = array(
        'eot'   => 'application/vnd.ms-fontobject',
        'otf'   => 'application/font-otf',
        'ttf'   => 'application/x-font-ttf',
        'woff'  => 'application/x-font-woff',
        'woff2' => 'application/font-woff2',
        'svg'   => 'image/svg+xml',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'png'   => 'image/png',
        'css'   => 'text/css',
        'js'    => 'application/javascript',
    );
    $ext = strtolower($ext);
    return isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream';

}
