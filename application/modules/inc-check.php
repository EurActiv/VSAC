<?php

/**
 * Functions for checking for system dependencies
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_config_items() */
function check_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function check_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function check_test()
{
    return 'No tests to run';
}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//


/**
 * Check for a dependency, die if it isn't there.
 * @param string $type one of
 *        - 'system': a system executable, found with "which" 
 *        - 'php': a php function
 *        - 'apache_mod': an apache module
 * @param string $dep the dependency
 */
function check_dependency($type, $dep)
{
    switch ($type) {
        case 'system':
            if (!trim(exec('which '.$dep))) {
                err('Application requires program '.$dep, __FILE__.__LINE__);
            }
            break;
        case 'php_function':
            if (!function_exists($dep)) {
                err('Application requires PHP function '.$dep, __FILE__.__LINE__);
            }
            break;
        case 'php_class':
            if (!class_exists($dep)) {
                err('Application requires PHP class '.$dep, __FILE__.__LINE__);
            }
            break;
        case 'apache_module':
            if (!in_array($dep, apache_get_modules())) {
                err('Application requires Apache module '.$dep, __FILE__.__LINE__);
            }
            break;
        default:
            err('Bad dependency type specified', __FILE__.__LINE__);
            break;
    }
}

function check_rewrite_rule($from, $to, $flags = array(), $prefix = '')
{

    if (!framework_config('pretty_urls', false)) {
        return;
    }
    $docroot = request_server('DOCUMENT_ROOT');
    if (substr($docroot, -1) == '/') {
        $docroot = substr($docroot, 0, -1);
    }
    $to_prefix = substr(realpath(__DIR__.'/../'), strlen($docroot));
    $to = $to_prefix.$to;

    $rule = 'RewriteRule '.$from.' '.$to;
    if (!empty($flags)) {
        $rule .= ' ['.implode(',', $flags).']';
    }
    $rule = "\n".$rule."\n";
    if ($prefix) {
        $rule = "\n".$prefix.$rule;
    }

    $htaccess = file($docroot.'/.htaccess');
    $htaccess = array_filter(array_map(function ($line) {
        return preg_replace('/ +/', ' ', trim($line));
    }, $htaccess));

    if (!in_array('RewriteEngine On', $htaccess)) {
        err('URL rewriting is not turned on in .htaccess', __FILE__.__LINE__);
    }

    $htaccess = "\n".implode("\n", $htaccess)."\n";
    if (strpos($htaccess, $rule) === false) {
        err('.htaccess is missing rewrite rule '.$rule, __FILE__.__LINE__);
    }
    
}

function check_plugin_rewrite_rule($from, $to, $flags = array(), $prefix = '')
{
    $p = plugin();
    $from = preg_replace('/^(\\^?)/', '$1'.$p.'/', $from);
    if (substr($to, 0, 1) != '/') {
        $to = '/'.$to;
    }
    $to = '/plugins/'.$p.$to;
    check_rewrite_rule($from, $to, $flags, $prefix); 
}

