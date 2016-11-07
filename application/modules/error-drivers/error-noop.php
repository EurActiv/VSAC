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

/** @see error_depends() */
function error_noop_depends()
{
    return array();
}

/** @see error_sysconfig() */
function error_noop_sysconfig()
{
    return true;
}


//-- Driver callbacks --------------------------------------------------------//

/** @see error_driver_limitations() */
function error_noop_driver_limitations()
{
    return '<p><b class="text-danger">Important:</b> The application is
            configured to use the "noop" error driver. This driver does not
            log anything. Consider changing the <code>error_driver</code>
            configuration setting in  <code>config/_framework.php</code> to
            <code>sqliteerror</code>.</p>';
}

/** @see error_log() */
function error_noop_log($errno, $errstr, $errfile, $errline, $trace)
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
function error_noop_list($page = 0)
{
    return array();
}

/** @see error_get */
function error_noop_get($err_key)
{
    return false;
}

/** @see error_resolve */
function error_noop_resolve($err_key)
{
    return false;
}








