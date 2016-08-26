<?php

/**
 * This is the noop driver for the Key-Value Abstraction Layer (kval).
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//


/** @see callmap_sysconfig() */
function noopcallmap_sysconfig()
{
    return true;
}



/** @see callmap_log_hit */
function noopcallmap_log_hit($provider, $consumer, $gateway)
{
}

/** @see callmap_clean() */
function noopcallmap_clean()
{
}

/** @see callmap_dump() */
function noopcallmap_dump()
{
    return array('nodes' => array(), 'edges' => array());
}

