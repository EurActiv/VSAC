<?php

/**
 * Make HTTP requests
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_config_items() */
function http_config_items()
{
    return array(
        [
            'http_allowed_domains',
            [],
            'Allowed domains for fetching assets or generating URLs',
            true,
        ],
        [
            'http_allowed_urls',
            [],
            'Specified URLs or regular expressions of specified URLs match for
            fetching assets or generating URLs',
            true,
        ],
    );
}

/** @see example_module_sysconfig() */
function http_sysconfig()
{
    if (!function_exists('curl_init')) {
        return 'cURL PHP module is not installed';
    }
    return true;
}

/** @see example_module_test() */
function http_test()
{
    $url = 'https://httpbin.org/get?hello=world';
    if (!http_get($url, $body)) {
        return 'Could not make a GET request';
    }
    $json = json_decode($body, true);
    if ($json['args']['hello'] != 'world') {
        return 'Query parameters were not passed';
    }
    $url = 'https://httpbin.org/post';
    $data = array('hello' => 'world');
    if (!http_post($url, $data, false, $body)) {
        return 'Could not make a POST request';
    }
    $json = json_decode($body, true);
    if ($json['form']['hello'] != 'world') {
        return 'Post data was not passed';
    }
    return true;
}

//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//


/**
 * Check a requested url against the configured domain and url whitelists
 *
 * @param string $url
 * @param string $error an error message will be stored here
 *
 * @return bool|string the normalized URL if authorized, false if not authorized
 */
function http_uri_is_authorized($url, &$error = '')
{
    $error = '';

    // fix a common urlencoding issues
    if (strpos($url, '/') === 0) {
        $url = 'http:' . $url;
    }
    $url = preg_replace('/(http:\/)([^\/])/', '$1/$2', $url);

    // invalid urls should never pass
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'URL not valid';
        return false;
    }

    // domains first
    if (!($domain = parse_url($url, PHP_URL_HOST))) {
        return false;
    }

    $domains = config('http_allowed_domains', []);

    if (in_array($domain, $domains)) {
        return $url;
    }
    // check for subdomains
    $rdomain = strrev($domain);
    foreach($domains as $d) {
        if (strpos($rdomain, strrev($d) . '.') === 0) {
            return $url;
        }
    }

    // now specific urls
    $urls = config('http_allowed_urls', []);
    foreach($urls as $u) {
        if ($url === $u) {
            return $url;
        }
        if (@preg_match($u, null) !== false && preg_match($u, $url)) {
            return $url;
        }
    }
    $error = 'URL not authorized';
    return false;

}

/**
 * Use cURL to fetch a foreign asset.
 *
 * @param string $url the url to the asset
 * @param string $body the body of the request will be stored here
 * @param string $error any curl errors will be stored here
 * @return bool true on success, false on failure
 */
function http_get($url, &$body = '', &$error = '', $googlebot = false, $options = array())
{
    $error = '';
    $body = '';

    $ch = http_get_curl_handle($url, $googlebot, $options);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false || $status > 399) {
        $error = $status . ': '  . curl_error($ch);
        return false;
    }

    return true;
}


/**
 * Use cURL to post to a foreign asset
 *
 * @param string $url the url to the asset
 * @param mixed $data the post data
 * @param bool $multipart sent as multipart data. Only use this if you're really
 * sending binary data as nested array data might be messed up otherwise.
 * @param string $body the body of the request will be stored here
 * @param string $error any curl errors will be stored here
 * @return bool true on success, false on failure
 */
function http_post($url, $data, $multipart, &$body = '', &$error = '')
{
    $error = $body = '';

    $ch = http_get_curl_handle($url);
    curl_setopt($ch, CURLOPT_POST, 1);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $data : http_build_query($data));

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false || $status > 399) {
        $error = $status . ': '  . curl_error($ch);
        return false;
    }

    return true;
}

/**
 * A helper for showing examples in the backend. Checks that a set of domains
 * or URLs is in the current module's configuration. Useful if the example needs
 * to fetch a resource that must be authorized to work.
 *
 * Prints an error message to the screen if the required domains/urls are not
 * in the config.
 *
 * @param array $domains the domains that should be in the config
 * @param array $urls the URLs that should be in the config
 *
 * @return void
 */
function http_examples_in_config($domains = array(), $urls = array())
{
    $in_config = function ($items, $config_key) {
        if (empty($items)) {
            return;
        }
        if (!is_array($items)) {
            $items = array($items);
        }
        $config = config($config_key, []);
        $missing = array_diff($items, $config);
        if (empty($missing)) {
            return;
        }
        $missing = array_map(function ($item) {
            return '<code>' . htmlspecialchars($item) . '</code>';
        }, $missing);
        ?><p class="well"><strong class="text-danger">Note:</strong> The
            examples for this page may not work. The configuration setting
            <code><?= $config_key ?></code> is missing the entries:
            <?= implode(', ', $missing) ?>.</p>
        <hr>
        <?php
    };
    $in_config($domains, 'http_allowed_domains');
    $in_config($urls, 'http_allowed_urls');
}


//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

/**
 * Fetch a curl handle with common settings already in place
 *
 * @param string $url the URL for the handle
 * @param bool $googlebot set the useragent to googlebot
 * @param array $options any additional options
 *
 * @return curl resource
 */
function http_get_curl_handle($url, $googlebot = false, $options = array())
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // limit time messing around with transfer
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    // limit size of transfer to 32MB
    // see http://stackoverflow.com/a/17642638/1459873
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 128);
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($ds, $dl, $us, $ul) {
        return ($dl > (32 * 1024 * 1024)) ? 1 : 0;
    });

    // prevent curl from using local http cache
    // see http://stackoverflow.com/a/15493829
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array( 
        'Cache-Control: no-cache', 
    ));

    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    foreach($options as $key => $value) {
        curl_setopt($ch, $key, $value);
    }


    $ua = $googlebot
        ? 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
        : 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13'
        ;

    curl_setopt($ch, CURLOPT_USERAGENT, $ua);

    return $ch;
}

