<?php

/**
 * This module offers enhanced functions for working with MIME types, avoids
 * having to install mime magic on host and/or installing the fileinfo extension
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function mime_depends()
{
    return array('filesystem');
}

/** @see example_module_config_items() */
function mime_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function mime_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function mime_test()
{
    $check = function ($fn, $arg, $should_be) {
        $is = call_user_func(__NAMESPACE__ . '\\' . $fn, $arg);
        if ($should_be == $is) {
            return;
        }
        $msg = 'Error in "%s", "%s" should have given "%s", gave "%s"';
        return sprintf($msg, $fn, $arg, $should_be, $is);
    };

    mime_build_magic(true);
    mime_get_magic(true);

    if ($err = $check('mime_from_ext', 'jpg', 'image/jpeg')) {
        return $err;
    }
    if ($err = $check('mime_to_ext', 'text/plain', 'txt')) {
        return $err;
    }

    if ($err = $check('mime_detect_file', mime_build_magic(), 'application/json')) {
        return $err;
    }
    if ($err = $check('mime_detect_file', __FILE__, 'application/octet-stream')) {
        return $err;
    }
    return true;
}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//

/**
 * Get the MIME type from a file extension
 *
 * @param string $ext the extension, without the preceeding "."
 * @return string the mime type, or "application/octet-stream" if not found
 */
function mime_from_ext($ext)
{
    $ext = strtolower($ext);
    $magic = mime_get_magic();
    return isset($magic[$ext])
         ? $magic[$ext]
         : 'application/octet-stream'
         ;
}

/**
 * Convert a mime type to a file extension
 *
 * @param string $mime the mime type
 * @return string the extension
 */
function mime_to_ext($mime)
{
    $mime = strtolower($mime);
    $magic = mime_get_magic();
    if ($ext = array_search($mime, $magic)) {
        return $ext;    
    }
    $ext = preg_replace('#.*/#', '', $mime);
    $ext = str_replace(['+', '-'], ['.', '.'], $ext);
    return $ext;
}


/**
 * Get the mime type of a file. The file does not have to exist, but it requires
 * an extension.
 *
 * @param string $path the path to the file
 * @return string the mime type, or "application/octet-stream" if not found
 */
function mime_detect_file($path)
{
    if ($ext = pathinfo($path, PATHINFO_EXTENSION)) {
        return mime_from_ext($ext);
    }
    return 'application/octet-stream';
}



//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

/**
 * Get load the magic file into a key=>value array where key is the extension
 * and the value is the corresponding MIME type
 *
 * @param bool $reload reload the array, usually used in conjunction with
 * mime_build_magic($rebuild = true);
 *
 * @return array
 */
function mime_get_magic($reload = false)
{
    static $magic = null;
    if (!is_null($magic) && !$reload) {
        return $magic;
    }
    $path = mime_build_magic();
    $magic = json_decode(file_get_contents($path), true);
    return $magic;
}


/**
 * Build the local mime magic database (json) using the one from apache
 *
 * @param bool $rebuild delete the local database and rebuild it with the new
 * one from apache.
 *
 * @return string the absolute path to the database file.
 */
function mime_build_magic($rebuild = false)
{
    $path = realpath(filesystem_files_path() . '/../') . '/mime-magic.json';
    if (file_exists($path) && !$rebuild) {
        return $path;
    } 

    $magic_url = 'http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types';
    $magic_downloaded = explode("\n", file_get_contents($magic_url));
    $magic = array();

    array_walk($magic_downloaded, function ($line) use (&$magic) {
        if (empty($line) || strpos($line, '#') === 0) {
            return;
        }
        $line = trim(preg_replace('/\s+/', ' ', $line));
        $line = array_filter(explode(' ', $line));
        $mime = array_shift($line);
        if (strpos($mime, '/') === false) {
            return;
        }
        foreach ($line as $ext) {
            if (preg_match('/^[a-z0-9]+$/i', $ext)) {
                $magic[$ext] = $mime;
            }
        }
    });
    file_put_contents($path, json_encode($magic, JSON_PRETTY_PRINT));

    return $path;

}



