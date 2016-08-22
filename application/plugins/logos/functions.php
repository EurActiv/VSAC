<?php

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework functions                                                    --//
//----------------------------------------------------------------------------//

/** @see plugins/example-plugin/example_plugin_config_items() */
function logos_config_items()
{
    return array(
        [
            'max_file_size',
            0,
            'The maximum size to allow for file uploads'
        ],
    );
}

/** @see plugins/example-plugin/example_plugin_bootstrap() */
function logos_bootstrap()
{
}


//----------------------------------------------------------------------------//
//-- Plugin functions                                                       --//
//----------------------------------------------------------------------------//

function logos_list_files()
{
    $descriptions = filesystem_files_path() . 'desc/';
    $files = filesystem_files_path() . 'files/';
    $files = filesystem_ls($files);
    $return = array();
    foreach ($files as $file) {
        $desc = $descriptions . $file . '.txt';
        $desc = file_exists($desc) ? file_get_contents($desc) : '';
        $return[$file] = $desc;
    }
    return $return;
}


