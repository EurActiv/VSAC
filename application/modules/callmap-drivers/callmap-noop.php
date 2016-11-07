<?php

/**
 * This is the noop driver for the Key-Value Abstraction Layer (kval).
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//

//-- Framework ---------------------------------------------------------------//

/** @see callmap_depends() */
function callmap_noop_depends()
{
    return array();
}


/** @see callmap_sysconfig() */
function callmap_noop_sysconfig()
{
    return true;
}


//-- Logging and reading -----------------------------------------------------//

/** @see callmap_log_hit */
function callmap_noop_log_hit($provider, $consumer, $gateway)
{
}

/** @see callmap_get_node_id */
function callmap_noop_get_node_id($label)
{
}

/** @see callmap_clean() */
function callmap_noop_clean()
{
}

/** @see callmap_dump() */
function callmap_noop_dump()
{
    return array('nodes' => array(), 'edges' => array());
}

