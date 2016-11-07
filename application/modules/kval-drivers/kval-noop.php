<?php

/**
 * This is the sqlite driver for the Key-Value Abstraction Layer (kval).
 *
 * Primary limitation of this driver is database size. Namely, some combinations
 * of OS and filesystem (32 bit linux, simfs) will only allow you to have files
 * of 2.1GB in size before they just crash.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//


//-- Framework ---------------------------------------------------------------//

/** @see kval_depends() */
function kval_noop_depends()
{
    return array();
}

/** @see kval_sysconfig() */
function kval_noop_sysconfig()
{
    return true;
}


//-- Utilities ---------------------------------------------------------------//

/** @see kval_size() */
function kval_noop_size()
{
    return 0.0;
}

/** @see kval_clean() */
function kval_noop_clean($clean = 3600, $vacuum =  86400)
{    
    $last_clean = $last_vacuum = time();
    $vacuumed = $cleaned = true;
    return compact('last_clean', 'last_vacuum', 'vacuumed', 'cleaned');
}

/** @see kval_key() */
function kval_noop_key($key)
{
    return (string) $key;
}


//-- Getting/Setting items from the store ------------------------------------//

/** @see kval_get_item() */
function kval_noop_get_item($key)
{
    $items = kval_noop_items();
    return isset($items[$key]) ? $items[$key] : null;
}

/** @see kval_set_item() */
function kval_noop_set_item($key, $item)
{
    $items = &kval_noop_items();
    $items[$key] = $item;
}

/** @see kval_delete */
function kval_noop_delete($key)
{
    $items = &kval_noop_items();
    if (isset($items[$key])) {
        unset($items[$key]);
    }
}


//----------------------------------------------------------------------------//
//-- Driver-specific                                                        --//
//----------------------------------------------------------------------------//


/**
 * A data layer simulator for the noop driver
 *
 * @return array
 */
function &kval_noop_items()
{
    static $items = array();
    return $items;
}


