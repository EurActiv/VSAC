<?php

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework functions                                                    --//
//----------------------------------------------------------------------------//

/** @see plugins/example-plugin/example_plugin_config_items() */
function call_log_config_items()
{
    return array();
}

/** @see plugins/example-plugin/example_plugin_bootstrap() */
function call_log_bootstrap()
{
    use_module('http');
    use_module('apikey');
}
