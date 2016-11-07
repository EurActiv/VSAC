<?php

/**
 * Utilities for interacting with the filesystem
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function filesystem_depends()
{
    return array();
}

/** @see example_module_config_items() */
function filesystem_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function filesystem_sysconfig()
{
    if (strpos(strtolower(PHP_OS), 'win') === 0) {
        return 'A *nix server is required';
    }
    return true;
}

/** @see example_module_test() */
function filesystem_test()
{
    return true;
}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//


/**
 * Replace realpath with stream_resolve_include_path. It's slow, but fast enough
 * for what we're doing.
 *
 * @param string $path
 * @param bool $prevent_traversal prevent "/../" from showing files outside of
 * of configured include paths
 *
 * @return string the resolved path, or false if the file does not exist
 */
function filesystem_realpath($path, $prevent_traversal = false)
{
    $path = stream_resolve_include_path($path);
    if (!$path || !$prevent_traversal) {
        return $path;
    }
    if (strpos($path, data_directory()) === 0) {
        return $path;
    }
    foreach (get_include_paths() as $include_path) {
        if (strpos($path, $include_path . '/') === 0) {
            return $path;
        }
    }
    return false;
}

/**
 * Generate a safe file name from, for example, a URL. It should generate file
 * names that give some clue as to their original source, but are safe to use
 * with the file system. It's a utility that should not be trusted with
 * unsanitized user input.
 *
 * @param string $name the name to make safe
 *
 * @return string the safe file name, with problem characters replaced with
 * underscore, a hash of the original file name and a hash of characters that
 * would cause the filename to be longer than 128 characters.
 */
function filesystem_safename($name)
{
    $crc_full = hash('crc32', $name);
    $safename = preg_replace('/[\*\"\/\\\\[\]\:\;\|\=]+/', '_', $name);
    if (strlen($safename) > 110) {
        $prefix = hash('crc32', substr($safename, 0, strlen($safename) - 110));
        $safename = substr($safename, 110);
    } else {
        $prefix = 'safename';
    }
    return $prefix . '-' . $crc_full . '-' . $safename;
}

/**
 * Get the path to a minified file from the non-minified file. The source file
 * must exist and must not already be minified; the minified path may not
 * exist (needs to be created).
 *
 * @param string $abspath the absolute path to the source file
 *
 * @return string the path to the (perhaps notional) minified file
 */
function filesystem_minified_path($path, $expected_ext = false)
{
    if (!($abspath = filesystem_realpath($path))) {
        err('Source file does not exist: '.$path);
    }
    if (!is_file($abspath) || !($ext = pathinfo($abspath, PATHINFO_EXTENSION))) {
        err('Source file is not minifiable: '.$abspath);
    }
    if ($expected_ext && strtolower($ext) != strtolower($expected_ext)) {
        err("Source file is not .{$expected_ext}: " . $path);
    }
    $base = substr($abspath, 0, (-1 * (strlen($ext) + 1)));
    if (substr($base, -4) == '-min') {
        err('Path is already a minified path: '.$path);
    }
    return $base . '-min.' . $ext;
}

/**
 * Recursively glob a pattern.
 * @see http://stackoverflow.com/a/17161106/1459873
 *
 * @return array
 */
function filesystem_rglob($pattern, $flags = 0)
{
    $files = glob($pattern, $flags); 
    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        $files = array_merge($files, filesystem_rglob($dir.'/'.basename($pattern), $flags));
    }
    return $files;
}

/**
 * List files in a directory
 *
 * @param string $dir the directory to list
 * @param callable $filter the function to filter items by, defaults to remove
 * hidden files, "." and ".."
 *
 * @return array
 */
function filesystem_ls($dir, callable $filter = null)
{
    if (is_null($filter)) {
        $filter = function ($f) {
            return strpos($f, '.') !== 0;
        };
    }
    if (!is_dir($dir)) {
        err("Directory $dir does not exist");
    }
    return array_filter(scandir($dir), $filter);
}

/**
 * Check a directory for existence, try to make it if it does not exist or raise
 * an error if it fails we can't
 *
 * @param string $dir the directory to check for
 * @return string $dir the realpath to the directory, with trailing slash
 */
function filesystem_mkdir($dir)
{
    if (!is_dir($dir)) {
        mkdir($dir);
    }
    if (!is_dir($dir)) {
        err("Could not create directory {$dir}");
    }
    return realpath($dir) . '/';
}

function filesystem_path_in_dir($subpath, $base_abspath)
{
    $path = strpos($subpath, $base_abspath) === 0
          ? $subpath
          : $base_abspath . $subpath;
    if (!($subpath = realpath($subpath))) {
        return false;
    }
    if (strpos($subpath, $base_abspath) !== 0) {
        return false;
    }
    return $path;
}

/**
 * Get the path to a plugin's files directory
 *
 * @return string the absolute path to the directory, with trailing slash
 */
function filesystem_files_path()
{
    return filesystem_mkdir(data_directory() . '/' . plugin());
}

/**
 * Get the path to a plugin's code directory
 *
 * @return string the absolute path to the directory, with trailing slash
 */
function filesystem_plugin_path()
{
    if (!($dir = filesystem_realpath('plugins/' . plugin()))) {
        err('Could not find plugin directory');
    }
    return $dir . '/';
}

/**
 * Remove a directory in the plugins files (data) directory. Will raise an error
 * if the directory to remove is not within the files directory.
 *
 * @param string $path the absolute path to the directory cannot be the root itself.
 * @param bool $tested the code path leading to this function has been tested
 *
 * @return bool
 */
function filesystem_files_rmdir($dir, $tested = false)
{
    if (!($dir = realpath($dir)) || !is_dir($dir)) {
        return false;
    }
    $fpath = filesystem_files_path();
    if (strpos($dir, $fpath) !== 0) {
        err("Error removing directory: '{$dir}' is not in allowed path '{$fpath}'");
    }
    return filesystem_rmdir($dir, $tested ? "I've really tested this" : false);
}

/**
 * Remove a directory in the plugins code directory. Will raise an error if th
 * directory to remove is not within code directory.
 *
 * @param string $path the absolute path to the directory cannot be the root itself.
 * @param bool $tested the code path leading to this function has been tested
 *
 * @return bool
 */
function filesystem_plugin_rmdir($dir, $tested = false)
{
    if (!($dir = realpath($dir)) || !is_dir($dir)) {
        return false;
    }
    $ppath = filesystem_plugin_path();
    if (strpos($dir, $ppath) !== 0) {
        err("Error removing directory: '{$dir}' is not in allowed path '{$ppath}'");
    }
    return filesystem_rmdir($dir, $tested ? "I've really tested this" : false);
}

/**
 * Remove a directory anywhere the application can write to. Dangerous.
 *
 * @param string $dir the absolute path to the directory to remove.
 * @param string $i_tested_this if set to "I've really tested this", then the
 * removal will happen. Otherwise, it will print the files that would have been
 * removed.
 * @return bool true on success, false on failure
 */
function filesystem_rmdir($dir, $i_tested_this = false)
{
    if (!($dir = realpath($dir)) || !is_dir($dir)) {
        return false;
    }
    $is_tested = $i_tested_this === "I've really tested this";

    $files = scandir($dir);
    foreach($files as $file) {
        if ($file == '.' || $file == '..') continue;
        if (is_dir($dir . '/' . $file)) {
            filesystem_rmdir($dir . '/' . $file, $i_tested_this);
        } elseif ($is_tested) {
            unlink($dir . '/' . $file);
        } else {
            echo "\n<br>unlink {$dir}/{$file}";
        }
    }
    if ($is_tested) {
        return rmdir($dir);
    }
    echo "\n<br>rmdir {$dir}";
    return true;
}

/**
 * Copy a directory.
 *
 * @param string $src_abspath the absolute path of the directory to copy
 * @param string $dest_abspath the absolute path to do to, must not exist
 * @param bool $merge merge into existing directory
 * @param bool $overwrite overwrite existing files on merge
 *
 * @return void
 */
function filesystem_cpdir($src_abspath, $dest_abspath, $merge = false, $overwrite = false)
{
    $src = realpath($src_abspath);
    if (!$src) {
        err('directory does not exist: '.$src_abspath);
    }
    $src .= '/';
    if (substr($dest_abspath, -1) != '/') {
        $dest_abspath .= '/';
    }
    if (realpath($dest_abspath)) {
        if (!$merge) {
            err('directory already exists: '.$dest_abspath);
        }
    } else {
        mkdir($dest_abspath);
    }
    $files = scandir($src);
    foreach ($files as $file) {
        if ($file == '..' || $file == '.') continue;
        if (is_file($src.$file)) {
            if ($overwrite || !realpath($dest_abspath . $file)) {
                copy($src . $file, $dest_abspath . $file);
            }
        } elseif (is_dir($src.$file)) {
            filesystem_cpdir($src . $file, $dest_abspath . $file, $merge, $overwrite);
        }
    }
}
