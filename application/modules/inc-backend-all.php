<?php

/**
 * Imports all modules that are useful in the backend, typically stuff you don't
 * need for serving files.
 */

namespace VSAC;

use_module('backend');
use_module('docs');
use_module('form');
use_module('build');
use_module('auth');

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_config_items() */
function backend_all_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function backend_all_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function backend_all_test()
{
    return 'No tests to run';
}

