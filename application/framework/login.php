<?php

namespace VSAC;

use_module('backend-all');

request_force_https();

auth_start_session();


if (request_query('logout')) {
    auth_set_authenticated(false);
    auth_redirect();
}


if ($redirect = request_query('redirect')) {
    auth_set_redirect($redirect);
    $url = request_url();
    list($_url, $_query) = explode('?', $url, 2);
    parse_str($_query, $query);
    unset($query['redirect']);
    $url = router_add_query($_url, $query);
    response_redirect($url);
}


backend_head(framework_config('app_name', '') . ' Login');

?>
<div class="row">
<div class="col-xs-12 col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3 col-lg-4 col-lg-offset-4">
    <?php
    form_form(
        array('method' => 'post'),
        function ($message) {
            if ($message) {
                printf(
                   '<br><blockquote class="bg-warning">%s</blockquote>',
                    htmlspecialchars($message)
                );
            }
            form_textbox('', 'Username', 'username', 'username');
            form_textbox('', 'Password', 'password', 'password', 'password');
            form_submit();
        },
        function () {
            $user = request_post('username');
            $pass = request_post('password');
            $users = framework_config('users', array());
            
            if ($user && isset($users[$user]) && $users[$user] === $pass) {
                auth_set_authenticated(true);
                auth_redirect();
            } else {
                return 'Invalid username or password.';
            }
        }
    );
    ?>
</div>
</div>

<?php

backend_foot();





