<?php

/**
 * This module handles building assets, such as compiling Sass and minifying
 * javascript and CSS.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_config_items() */
function build_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function build_sysconfig()
{
    if (strpos(strtolower(PHP_OS), 'win') === 0) {
        return 'A *nix server is required';
    }
    if (!exec('which uglifyjs')) {
        return 'UglifyJS is not installed (https://github.com/mishoo/UglifyJS2)';
    }
    if (!exec('which compass')) {
        return 'compass not installed (http://compass-style.org/)';
    }
    $extract_version = function ($cmd) {
        exec($cmd, $out);
        $version = array_shift($out);
        return $version ? preg_replace('/[^\.\d]/', '', $version) : '0';
    };
    $uglify_version = $extract_version('uglifyjs --version');
    if (version_compare($uglify_version, '2.4', '<')) {
        return 'UglifyJS >= 2.4 required (https://github.com/mishoo/UglifyJS2)';
    }
    if (version_compare($extract_version('compass version'), '1.0', '<')) {
        return 'compass >= 1.0 required (http://compass-style.org/)';
    }
    return true;
}

/** @see example_module_test() */
function build_test()
{
    return 'No tests to run';
}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//

/**
 * Get the path to a minified file from the non-minified file. The source file
 * must exist and must not already be minified; the minified path may not
 * exist (needs to be created).
 *
 * @param string $abspath the absolute path to the source file
 * @param string $ext the expected file extension
 *
 * @return string the path to the minified file
 */
function build_minified_path($abspath, $ext)
{
    if (!file_exists($abspath)) {
        err('Source file does not exist: '.$abspath, __FILE__.__LINE__);
    }
    if (pathinfo($abspath, PATHINFO_EXTENSION) !== $ext) {
        err('Source file is not '.$ext.': '.$abspath, __FILE__.__LINE__);
    }
    if (preg_match('/-min.'.preg_quote($ext, '/').'$/', $abspath)) {
        err('Source file already has minified file name: '.$abspath, __FILE__.__LINE__);
    }
    return substr($abspath, 0, -1 * (strlen($ext) + 1)).'-min.'.$ext; 
}

/**
 * Minify a js file.
 *
 * @param string $abspath the path to the unminified file
 *
 * @return string the absolute path to the minified file
 */
function build_minify_js($abspath)
{
    $target = build_minified_path($abspath, 'js');
    if (file_exists($target) && filemtime($target) >= filemtime($abspath)) {
        return $target;
    }
    exec("uglifyjs $abspath --comments > $target");
    return $target;
}

/**
 * Minify a css file.
 *
 * @param string $abspath the path to the unminified file
 *
 * @return string the absolute path to the minified file
 */
function build_minify_css($abspath, $base_url = false)
{
    $target = build_minified_path($abspath, 'css');
    if (file_exists($target) && filemtime($target) >= filemtime($abspath)) {
        return $target;
    }
    $dir = dirname($abspath).'/';

    $tempdir = $dir.'minify_css_temp/';
    $tempfile = $tempdir.basename($abspath);
    
    @mkdir($tempdir);
    copy($abspath, substr($tempfile, 0, -3).'scss');
    build_compile_sass($tempdir, $base_url);
    copy(build_minified_path($tempfile, 'css'), $target);
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
function build_compile_sass($dirname, $base_url = false)
{
    build_sass_exec($dirname, true, $base_url);
    foreach(scandir($dirname) as $file) {
        if (preg_match('/\.css$/', $file) && !preg_match('/\-min\.css$/', $file)) {
            $newpath = build_minified_path($dirname.'/'.$file, 'css');
            rename($dirname.'/'.$file, $newpath);
        }
    }
    build_sass_exec($dirname, false, $base_url);
}


//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//


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
function build_sass_exec($dirname, $compressed, $base_url = false)
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
    exec("compass compile $dirname");
    unlink("{$dirname}config.rb");

}

