<?php

/**
 * This is the error handler driver that tries to behave like PHP would. No
 * logging.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Implementation                                                         --//
//----------------------------------------------------------------------------//

//-- Framework ---------------------------------------------------------------//

/** @see kval_sysconfig() */
function nooperror_sysconfig()
{
    return true;
}


//-- Driver callbacks --------------------------------------------------------//

/** @see error_driver_limitations() */
function nooperror_driver_limitations()
{
    return '<p><b class="text-danger">Important:</b> The application is
            configured to use the "nooperror" error driver. This driver does not
            log anything. Consider changing the <code>error_driver</code>
            configuration setting in  <code>config/_framework.php</code> to
            <code>sqliteerror</code>.</p>';
}

/** @see error_log() */
function nooperror_log($errno, $errstr, $errfile, $errline, $trace)
{
    if (!ini_get('display_errors')) {
        return;
    }
    printf(
        "\n<b>Error [%s]:</b> %s (%s:%s)<br>\n",
        error_format_errcode($errno),
        htmlspecialchars($errstr),
        error_shorten_filename($errfile),
        (string) $errline
    );
}

/** @see error_list */
function nooperror_list($page = 0)
{
    return array();
}

/** @see error_get */
function nooperror_get($err_key)
{
    return false;
}

/** @see error_resolve */
function nooperror_resolve($err_key)
{
    return false;
}








