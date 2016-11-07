<?php

/**
 * This is the noop driver for the cache abstraction layer
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//

//-- Framework ---------------------------------------------------------------//

/** @see cal_depends() */
function cal_noop_depends()
{
    return array();
}


/** @see cal_sysconfig() */
function cal_noop_sysconfig()
{
    return true;
}


//-- Utilities ---------------------------------------------------------------//

/** @see cal_size() */
function cal_noop_size()
{
    return 0.0;
}

/** @see cal_clean() */
function cal_noop_clean($invalidate = 3600, $vacuum =  86400)
{
    $last_clean = $last_vacuum = time();
    $cleaned = $vacuumed = true;
    return compact('last_clean', 'last_vacuum', 'cleaned', 'vacuumed');
}

//-- Metadata ----------------------------------------------------------------//


/** @see cal_get_meta() */
function cal_noop_get_meta($name)
{
    $meta = call_noop_meta();
    return isset($meta[$name]) ? $meta[$name] : null;
}

/** @see call_set_meta */
function cal_noop_set_meta($name, $value)
{
    $meta = &call_noop_meta();
    $meta[$name] = $value;
}


//-- Top level items ---------------------------------------------------------//

/** @see cal_get_item_meta() */
function cal_noop_get_item_meta($identifier)
{
    $id = md5($identifier);
    $items = cal_noop_items();
    if (isset($items[$id])) {
        return array('expire' => $items[$id]['expire'], 'id' => $id);
    }
    return false;
}


/** @see cal_insert_item() */
function cal_noop_insert_item($identifier, $content)
{
    $id = md5($identifier);
    $items = &cal_noop_items();
    $items[$id] = array('item'=>$content, 'id' => $id, 'expire' => 0);
    return $id;
}

/** @see cal_touch() */
function cal_noop_touch($item_id, $expire)
{
    $items = &cal_noop_items();
    if (!empty($items[$item_id])) {
        $items[$item_id]['expire'] = $expire; 
    }
    return $item_id;
}


/** @see call_get_item_content() */
function cal_noop_get_item_content($item_id)
{
    $items = &cal_noop_items();
    if (!empty($items[$item_id])) {
        return $items[$item_id]['item']; 
    }
    return null;
}


//-- Permutations ------------------------------------------------------------//


/** @see cal_get_permutation_content */
function cal_noop_get_permutation_content($item_id, $permutation_identifier)
{
    $items = &cal_noop_items();
    if (!isset($items[$item_id]) || !isset($items[$item_id]['item'])) {
        return null;
    }
    $pid = md5($permutation_identifier);
    if (isset($items[$item_id][$pid])) {
        return $items[$item_id][$pid];
    }
    return null;
}

/** @see cal_insert_permutation */
function cal_noop_insert_permutation(
    $item_id,
    $permutation_identifier,
    $permutation
) {
    $items = &cal_noop_items();
    $pid = md5($permutation_identifier);
    $items[$item_id][$pid] = $permutation;
}


//----------------------------------------------------------------------------//
//-- Driver-specific                                                        --//
//----------------------------------------------------------------------------//


/**
 * A collector for the meta items to hold it for the duration of the current
 * request. Necessary to pass tests
 *
 * @return array();
 */
function &cal_noop_items()
{
    static $items = array();
    return $items;
}

/**
 * A collector for the meta data to hold it for the duration of the current
 * request. Necessary for tests.
 *
 * @return array
 */
function &cal_noop_meta()
{
    static $meta = array();
    return $meta;
}


