<?php

/**
 * The NOOP driver for the shortener
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//

/** @see shortener_shorten() */
function noshorten_shorten($url)
{
    return $url;
}


