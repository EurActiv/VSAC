<?php

/**
 * The NOOP driver for the shortener
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//

/** @see shortener_depends() */
function shortener_noop_depends()
{
    return array();
}

/** @see shortener_config_items */
function shortener_noop_config_items()
{
    return array();
}

/** @see shortener_test */
function shortener_noop_test()
{
    return true;
}

/** @see shortener_shorten() */
function shortener_noop_shorten($url)
{
    return $url;
}


