<?php

/**
 * This module provides some tools for working CSS. Pretty much just a PHP
 * frontend for Compass.
 */

namespace VSAC;

//---------------------------------------------------------------------------//
//-- Framework required functions                                          --//
//---------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function csstools_depends()
{
    return array('filesystem', 'http', 'image');
}


/** @see example_module_sysconfig() */
function csstools_sysconfig()
{
    if (strpos(strtolower(PHP_OS), 'win') === 0) {
        return 'A *nix server is required';
    }
    if (!exec('which compass')) {
        return 'compass not installed (http://compass-style.org/)';
    }
    $version = extract_version('compass version');
    if (version_compare($version, '1.0', '<')) {
        return 'compass >= 1.0 required (http://compass-style.org/)';
    }
    return true;
}

/** @see example_module_config_options() */
function csstools_config_options()
{
    return array();
}

/** @see example_module_test() */
function csstools_test()
{
    return true;
}


//----------------------------------------------------------------------------//
//--  Public API                                                            --//
//----------------------------------------------------------------------------//

/**
 * Minify a css file.
 *
 * @param string $path the path to the unminified file
 *
 * @return string the absolute path to the minified file
 */
function csstools_minify($path, $base_url = false)
{
    $target = filesystem_minified_path($path, 'css');
    $source = filesystem_realpath($path);
    if (file_exists($target) && filemtime($target) >= filemtime($source)) {
        return $target;
    }
    $dir = dirname($source).'/';

    $tempdir = $dir . 'minify_css_temp_' . uniqid() . '/';
    $tempfile = $tempdir . pathinfo($source, PATHINFO_FILENAME);
    mkdir($tempdir);
    copy($source, $tempfile . '.scss');
    csstools_compile_sass($tempdir, $base_url);
    copy(filesystem_minified_path($tempfile . '.css'), $target);
    filesystem_rmdir($tempdir, "I've really tested this");
    return $target;
}

/**
 * Compile a sass directory in a temporary protected directory. Produces both
 * minified and unminified versions of the files.
 *
 * @param string $dirname
 *
 * @return string the absolute path to the minified file
 */
function csstools_compile_sass($dirname, $base_url = false)
{
    csstools_sass_exec($dirname, true, $base_url);
    foreach(scandir($dirname) as $file) {
        if (preg_match('/\.css$/', $file) && !preg_match('/\-min\.css$/', $file)) {
            $newpath = filesystem_minified_path($dirname . '/' . $file);
            rename($dirname.'/'.$file, $newpath);
        }
    }
    csstools_sass_exec($dirname, false, $base_url);
}

//---------------------------------------------------------------------------//
//-- Private methods                                                       --//
//---------------------------------------------------------------------------//


/**
 * Execute a Sass build on a directory.
 *
 * @private
 *
 * @param string $dirname the absolute path to the directory to build
 * @param bool $compress minify the output
 *
 * @return void
 */
function csstools_sass_exec($dirname, $compressed, $base_url = false)
{
    if (!$base_url) {
        $base_url = router_plugin_url($dirname);
    }
    if (substr($dirname, -1) !== '/') {
        $dirname .= '/';
    }
    if (file_exists("{$dirname}config.rb")) {
        unlink("{$dirname}config.rb");
    }
    $config = "http_path = \"#url#\"\n"
            . "css_dir = \"..\"\n"
            . "css_path = \"#path#\"\n"
            . "sass_dir = \"..\"\n"
            . "sass_path = \"#path#\"\n"
            . "images_dir = \"..\"\n"
            . "javascripts_dir = \"..\"\n"
            . "output_style = ".($compressed?':compressed':':expanded');
    $config = str_replace('#path#', $dirname, $config);
    $config = str_replace('#url#', $base_url . '/' , $config);
    file_put_contents("{$dirname}config.rb", $config);
    exec(sprintf(
        'compass compile %s',
        escapeshellarg($dirname)
    ));
    if (file_exists("{$dirname}config.rb")) {
        unlink("{$dirname}config.rb");
    }
}

