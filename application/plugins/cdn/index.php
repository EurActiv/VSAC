<?php

namespace VSAC;


if (!($path = request_query('path'))) {
    if (!($path = request_server('PATH_INFO'))) {
        response_send_error();
    }
}

if ($minify = (strpos($path, 'min/') === 0)) {
    $path = substr($path, 4);
}

if ($abspath = cdn_get_file($path)) {
    if ($minify) {
        $abspath = cdn_minify($path, $abspath);
    }
    callmap_log(cdn_get_domain($path));
    response_send_file($abspath);
}
response_send_error();











