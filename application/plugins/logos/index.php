<?php

namespace VSAC;


if (!($path = request_query('path'))) {
    response_send_error();
}

$files_path = filesystem_files_path() . 'files/';
$abspath = realpath($files_path . $path);

if (!$abspath || strpos($abspath, $files_path) !== 0) {
    response_send_error();
}

callmap_log(basename($abspath));
response_send_file($abspath);


