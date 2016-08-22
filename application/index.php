<?php

/**
 * The default web front controller for the application.
 * Do not change the variable names or how they are used, that is how
 * the generator knows what to change. Do not remove the comments.
 *
 */

// Begin auto-generator replacements
#
$include_path = __DIR__;
$data_directory = sys_get_temp_dir() . '/' . hash('crc32', __DIR__);
$vendor_dirs = array();
#
// End auto-generator replacements

set_include_path($include_path);
require_once "application.php";
VSAC\set_data_directory($data_directory);
foreach ($vendor_dirs as $vendor_dir) {
    VSAC\add_include_path($vendor_dir);
}

VSAC\bootstrap_web($debug = false);
VSAC\front_controller_dispatch();

