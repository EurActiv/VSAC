<?php

/**
 * This module handles building assets, such as compiling Sass and minifying
 * javascript and CSS.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function build_depends()
{
    return array('csstools', 'filesystem', 'jstools');
}


/** @see example_module_config_items() */
function build_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function build_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function build_test()
{
    return true;
}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//

/**
 * Get the path to a minified file from the non-minified file. The source file
 * must exist and must not already be minified; the minified path may not
 * exist (needs to be created).
 *
 * @param string $path the path to the source file
 * @param string $ext the expected file extension
 *
 * @return string the path to the minified file
 */
function build_minified_path($path, $ext)
{
    return filesystem_minified_path($path, $ext);
}

/**
 * Minify a js file.
 *
 * @param string $path the path to the unminified file
 *
 * @return string the absolute path to the minified file
 */
function build_minify_js($path)
{
    return jstools_minify($path);
}

/** @see csstools_minify_css() */
function build_minify_css($abspath, $base_url = false)
{
    return csstools_minify($abspath, $base_url);
}

/** @see csstools_compile_sass() */
function build_compile_sass($dirname, $base_url = false)
{
    return csstools_compile_sass($dirname, $base_url);
}


//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//




