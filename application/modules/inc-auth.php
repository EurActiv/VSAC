<?php

/**
 * Handles AUTHenticating admin users and AUTHorizing a few actions
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function auth_depends()
{
    return array('backend', 'request', 'response', 'router');
}


/** @see example_module_config_items() */
function auth_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function auth_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function auth_test()
{
    return true;
}

//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//

//-- Authentication state management ----------------------------------------//

/**
 * Check if the current user is authenticated
 *
 * @return bool
 */
function auth_is_authenticated()
{
    auth_start_session();
    $s = superglobal('session');
    return isset($s['auth_authenticated']) && $s['auth_authenticated'];
}

/**
 * Set the current authentication state
 *
 * @param bool $authenticated
 * @param bool $redirect redirect to what the app thinks is the best place to go
 * @return void
 */
function auth_set_authenticated($authenticated)
{
    auth_start_session();
    $s = &superglobal('session');
    $s['auth_authenticated'] = (bool) $authenticated;
}

/**
 * Set the location that auth_redirect will redirect to
 *
 * @param string $url the URL to redirect to
 *
 * @return void
 */
function auth_set_redirect($url)
{
    auth_start_session();
    $s = &superglobal('session');
    $s['auth_redirect'] = $url;
}

/**
 * Redirect the user to the best page for them to see after an auth status change.
 *
 * @return void
 */
function auth_redirect()
{
    if (fn_exists('backend_flashbag')) {
        backend_flashbag(auth_is_authenticated() ? 'You are logged in' : 'You are logged out');
    }
    auth_start_session();
    $s = &superglobal('session');
    if (isset($s['auth_redirect'])) {
        $url = $s['auth_redirect'];
        $url = router_base_url() . preg_replace('#^(https?:)?//([^/]*/)?#', '', $url);
        unset($s['auth_redirect']);
    } else {
        $url = router_base_url();
    }
    response_redirect($url);
}

/**
 * Require authentication to view a current resource, redirect to login form as
 * needed.
 *
 * @return void
 */
function auth_require_authenticated()
{
    if (auth_is_authenticated()) {
        return;
    }
    if (fn_exists('backend_flashbag')) {
        backend_flashbag('Please log in to view this resource');
    }
    auth_set_redirect(request_url());
    response_redirect(auth_login_url());

}


//-- Login/logout helpers ----------------------------------------------------//

/**
 * Generate a url to the login form, with a backurl to this page
 *
 * @param array $query_params any query parameters to add to the url
 *
 * @return string
 */
function auth_login_url($query_params = array())
{
    return router_add_query(router_url('login.php'), $query_params);
}

/**
 * Generate a url to log out
 *
 * @param array $query_params any query parameters to add to the url
 *
 * @return string
 */
function auth_logout_url($query_params = array())
{
    $query_params['logout'] = true;
    return auth_login_url($query_params);
}

/**
 * Generate an html link to login/log out
 *
 * @return string
 */
function auth_login_btn($text = '', $button = '')
{
    if (auth_is_authenticated()) {
        $url = auth_logout_url();
        $btn = 'Log out';
    } else {
        $url = auth_login_url(array('redirect'=> request_url()));
        $btn = 'Log in';
    }
    return $text . ' <a href="'.$url.'">'.($button ? $button : $btn).'</a>';
}

//-- CSRF protection ---------------------------------------------------------//

/**
 * Get a CSRF token for the current session
 *
 * @return string
 */
function auth_get_csrf_token()
{
    auth_start_session();
    $s = &superglobal('session');
    if (!isset($s['csrf_token'])) {
        $s['csrf_token'] = sha1(microtime(true));
    }
    return $s['csrf_token'];
}

/**
 * Check that the CSRF token was set with a POST request, raise an error if it
 * was not set or if it is invalid
 *
 * @return void
 */
function auth_check_csrf_token()
{
    $token = auth_get_csrf_token();
    if (request_post('_token', '') !== $token) {
        err('CSRF token mismatch.');
    }
}

/**
 * Output a csrf token in a form
 *
 * @return void
 */
function auth_csrf_token_input()
{
   ?><input
        type="hidden"
        name="_token"
        value="<?= auth_get_csrf_token() ?>"
    ><?php
}


//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

/**
 * Start a session if there isn't one. Avoid using in frontend to because it sets
 * a cookie and will break browser/intermediate caching.
 *
 * @private
 *
 * @return void
 */
function auth_start_session()
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}
