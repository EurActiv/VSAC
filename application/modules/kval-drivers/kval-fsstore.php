<?php

/**
 * This is the filesystem based key:value driver for the Key-Value Abstraction
 * Layer (kval_*). It tries to cache items in folders on disk. It's a bit of a
 * kludge, but it works for most things. It's useful for systems that can't
 * handle big sqlite databases.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//


//-- Framework ---------------------------------------------------------------//

/** @see kval_depends() */
function kval_fsstore_depends()
{
    return array('filesystem', 'fsstore');
}

/** @see kval_sysconfig */
function kval_fsstore_sysconfig() {
    return true;
}


//-- Utilities ---------------------------------------------------------------//

/** @see kval_size() */
function kval_fsstore_size()
{
    return fsstore_size(kval_fsstore_path());

}

/** @see kval_clean() */
function kval_fsstore_clean($invalidate = 3600, $vacuum =  86400)
{
    return fsstore_clean(kval_fsstore_path(), function () {}, $clean, $vacuum);
}

/** @see kval_key() */
function kval_fsstore_key($key)
{
    $key = strtolower($key);
    $key = preg_replace('/[^a-z0-9\-_]/', '_', $key);
    if (strlen($key) > 100) { // to fit in a terminal window
        $suffix = md5($key);
        $prefix = substr($key, 0, (-1 * strlen($key)));
        $key = $suffix . $prefix;
    }
    return $key;
}


//-- Getting/Setting items from the store ------------------------------------//

/** @see kval_get_item() */
function kval_fsstore_get_item($key)
{
    return fsstore_get(kval_fsstore_path(), $key);
}


/** @see kval_set_item() */
function kval_fsstore_set_item($key, $item)
{
    return fsstore_put(kval_fsstore_path(), $key, $item);
}


/** @see kval_delete() */
function kval_fsstore_delete($key)
{
    return fsstore_delete(kval_fsstore_path(), $key);
}


//----------------------------------------------------------------------------//
//-- Driver-specific                                                        --//
//----------------------------------------------------------------------------//


/**
 * Get the path to the storage directory, create it if it does not exist.
 *
 * @private
 *
 * @return string
 */
function kval_fsstore_path()
{
    return filesystem_files_path() . 'kval';
}


