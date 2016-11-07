<?php

/**
 * This module provides some tools for working CSS.
 */

namespace VSAC;

//---------------------------------------------------------------------------//
//-- Framework required functions                                          --//
//---------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function jstools_depends()
{
    return array('filesystem');
}

/** @see example_module_sysconfig() */
function jstools_sysconfig()
{
    if (strpos(strtolower(PHP_OS), 'win') === 0) {
        return 'A *nix server is required';
    }
    if (!exec('which uglifyjs')) {
        return 'UglifyJS is not installed (https://github.com/mishoo/UglifyJS2)';
    }
    if (version_compare(extract_version('uglifyjs --version'), '2.4', '<')) {
        return 'UglifyJS >= 2.4 required (https://github.com/mishoo/UglifyJS2)';
    }
    return true;
}

/** @see example_module_config_options() */
function jstools_config_options()
{
    return array();
}

/** @see example_module_test() */
function jstools_test()
{
    return true;
}


//---------------------------------------------------------------------------//
//--  Public API                                                           --//
//---------------------------------------------------------------------------//

/**
 * Minify a css file.
 *
 * @param string $path the path to the unminified file
 *
 * @return string the absolute path to the minified file
 */
function jstools_minify($path, $base_url = false)
{
    $target = filesystem_minified_path($path, 'js');
    $source = filesystem_realpath($path);
    if (file_exists($target) && filemtime($target) >= filemtime($source)) {
        return $target;
    }
    exec(sprintf(
        'uglifyjs %s --comments > %s',
        escapeshellarg($source),
        escapeshellarg($target)
    ));
    return $target;

}


