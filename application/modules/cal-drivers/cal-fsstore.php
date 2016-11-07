<?php

/**
 * This is the filesystem based cache driver for the Cache Abstraction Layer
 * (cal_*).
 *
 * see module fsstore for advantages and caveats
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//


//-- Framework ---------------------------------------------------------------//

/** @see cal_depends() */
function cal_fsstore_depends()
{
    return array('fsstore');
}

/** @see cal_sysconfig() */
function cal_fsstore_sysconfig()
{
    return true;
}


//-- Utilities ---------------------------------------------------------------//

/** @see cal_size() */
function cal_fsstore_size()
{
    return fsstore_size(cal_fsstore_path());
}

/** @see cal_clean() */
function cal_fsstore_clean($invalidate = 3600, $vacuum =  86400)
{
    return fsstore_clean(
        cal_fsstore_path(),
        function () {},
        $clean, // hourly
        $vacuum    // daily
    );
}


//-- Metadata ----------------------------------------------------------------//

/** @see cal_get_meta() */
function cal_fsstore_get_meta($name)
{
    return fsstore_get_meta(cal_fsstore_path(), $name);
}

/** @see cal_set_meta() */
function cal_fsstore_set_meta($name, $value)
{
    return fsstore_set_meta(cal_fsstore_path(), $name, $value);
}


//-- Top level items ---------------------------------------------------------//

/** @see cal_get_item_meta() */
function cal_fsstore_get_item_meta($identifier)
{
    $id = md5($identifier);
    if ($meta = fsstore_get(cal_fsstore_path(), $id . '/meta', false)) {
        return $meta;
    }
    // will be unlocked on touch
    fsstore_lock(cal_fsstore_path(), $id . '/item');
    return false;
}

/** @see cal_insert_item() */
function cal_fsstore_insert_item($identifier, $content)
{
    $id = md5($identifier);
    $base_path = cal_fsstore_path();
    // will be unlocked on touch
    fsstore_lock($base_path, $id . '/item');

    $dir = $base_path . '/' . $id . '/';
    foreach (scandir($dir) as $file) {
        if (strpos($file, '.') !== 0 && $file != 'item.lock') {
            unlink($dir . $file);
        }
    }
    fsstore_put($base_path, $id . '/item', $content);
    return $id;
}

/** @see cal_touch() */
function cal_fsstore_touch($item_id, $expire = null)
{
    $id = $item_id;
    $base_path = cal_fsstore_path();
    fsstore_lock($base_path, $id . '/item');
    fsstore_put($base_path, $id . '/meta', compact('expire', 'id'));
    fsstore_unlock($base_path, $id . '/item');
    return $id;
}

/** @see cal_get_item_content() */
function cal_fsstore_get_item_content($item_id)
{
    return fsstore_get(cal_fsstore_path(),  $item_id . '/item');
}


//-- Permutations ------------------------------------------------------------//

/** @see cal_get_permutation_content */
function cal_fsstore_get_permutation_content($item_id, $permutation_identifier) {
    $permutation_id = md5($permutation_identifier);
    $base_path = cal_fsstore_path();
    $sub_path = $item_id . '/' . $permutation_id;

    $return = fsstore_get($base_path, $sub_path);
    if (is_null($return)) {
        fsstore_lock($base_path, $sub_path);
    }
    return $return;
}

/** @see cal_insert_permutation */
function cal_fsstore_insert_permutation(
    $item_id,
    $permutation_identifier,
    $permutation
) {
    $permutation_id = md5($permutation_identifier);
    $base_path = cal_fsstore_path();
    $sub_path = $item_id . '/' . $permutation_id;
    fsstore_put($base_path, $sub_path, $permutation);
}


//----------------------------------------------------------------------------//
//-- Driver-specific                                                        --//
//----------------------------------------------------------------------------//

/**
 * Get the path to the cache directory, create it if it does not exist.
 *
 * @private
 *
 * @return string
 */
function cal_fsstore_path()
{
    return filesystem_files_path() . 'cal_fsstore';
}

