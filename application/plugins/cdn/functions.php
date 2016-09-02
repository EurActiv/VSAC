<?php

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework functions                                                    --//
//----------------------------------------------------------------------------//

/** @see plugins/example-plugin/example_plugin_config_items() */
function cdn_config_items()
{
    return array(
        [
            'example_file',
            '',
            'A file to use for the "Quick Start" example'
        ], [
            'domain_map',
            [],
            'The maps of files to the source CDN domain. The format is an array
            of arrays, where each item has the following entries:<ul>
                <li><code>name</code>: the human readable name for the CDN</li>
                <li><code>regex</code>: the regular expression to test requested
                    files against to match this CDN</li>
                <li><code>domain</code>: the scheme/domain to prepend in front
                    of the requested file to create the fully-qualified URL to
                    the source file.</li>
            </ul>',
            true
        ]

    );
}

/** @see plugins/example-plugin/example_plugin_bootstrap() */
function cdn_bootstrap()
{
    use_module('http');
    use_module('apikey');
}

//----------------------------------------------------------------------------//
//-- Plugin functions                                                       --//
//----------------------------------------------------------------------------//

function cdn_url($filename)
{
    return router_use_rewriting()
            ? router_plugin_url($filename)
            : router_add_query(router_plugin_url('index.php', ['path' => $filename]));
            ;
}



function cdn_get_domain($filename)
{
    $map = config('domain_map', array());
    foreach($map as $item) {
        if (preg_match($item['regex'], $filename)) {
            return $item['domain'];
        }
    }
    return false;
}

/**
 * Get a file from an online resource if it doesn't exist, store it locally
 *
 * @param string $filename the requested filename
 * @return string the absolute path to the local file, or false if not found
 */
function cdn_get_file($filename)
{
    if (substr($filename, 0, 1) == '/') {
        $filename = substr($filename, 1);
    }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $path = filesystem_safename($filename);
    $abspath = filesystem_files_path() . $path;
    if (file_exists($abspath)) {
        return $abspath;
    }
    if (!($domain = cdn_get_domain($filename))) {
        return false;
    }
    $response = http_get($domain.$filename);

    if (!$response['body'] || $response['error']) {
        return false;
    }
    file_put_contents($abspath, $response['body']);
    return $abspath;
}

/**
 * Minify a previously fetched file. Only works on javascript and css.
 *
 * @param string $abspath the path to the locally stored file
 * @return string the absolute path to the minified file
 */
function cdn_minify($orig_file_name, $abspath)
{
    $ext = strtolower(pathinfo($abspath, PATHINFO_EXTENSION));
    if (!in_array($ext, array('js', 'css'))) {
        return $abspath;
    }
    use_module('build');
    $minpath = build_minified_path($abspath, $ext);
    if (file_exists($minpath)) {
        return $minpath;
    }
    $base_url = dirname(router_plugin_url($orig_file_name)) . '/';
    $abspath = $ext == 'css'
             ? build_minify_css($abspath, $base_url)
             : build_minify_js($abspath)
             ;
    return $abspath;
}
