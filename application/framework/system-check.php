<?php

namespace VSAC;


use_module('backend-all');

auth_require_authenticated();

$test_module = function ($module, $driver = null) {
    use_module($module);
    if ($driver) {
        driver($module, $driver);
    }
    $fn = request_query('test', 'system') == 'modules' ? '_test' : '_sysconfig';
    $fn = __NAMESPACE__ . '\\' . str_replace('-', '_', $module) . $fn;

    $result = call_user_func($fn);
    if ($driver) {
        driver($module, null);
    }
    force_conf_clear();

    return $result ? $result : 'Unknown failure';
};

$test_app = function () {

    if (!is_writable(data_directory())) {
        return 'Data directory must be writable: ' . data_directory();
    }
    if (version_compare(PHP_VERSION, '5.6', '<')) {
        return 'PHP >= 5.6 required';
    }
    if (!function_exists('apache_request_headers')) {
        return 'Should be running under Apache';
    }
    return true;
};

$results = array(
    'VSAC Core' => $test_app(),
);
foreach (modules() as $module) {
    if ($drivers = drivers($module)) {
        foreach ($drivers as $driver) {
            $results[$module . '.' . $driver] = $test_module($module, $driver);
        }
    } else {
        $results[$module] = $test_module($module);
    }
}

backend_head('System Check');

printf(
    '<p class="text-right">
        <b>Test type:</b>
        <a href="%s" class="btn btn-link %s">System configuration</a>
        <a href="%s" class="btn btn-link %s">Module tests</a>
    </p><hr>',
    router_url('system-check.php?test=system'),
    request_query('test', 'system') == 'system' ? 'disabled' : '',
    router_url('system-check.php?test=modules'),
    request_query('test', 'system') == 'modules' ? 'disabled' : ''
);

backend_collapsible('What to do if a check fails?', function () { ?>
    <p>Failed checks mean that your server is not configured to run the
        application, either in whole or in part.</p>
    <p>Most error messages indicate that a PHP module is missing or that a piece
        of external software is missing. Install it and you'll be good to go. If
        a module <b>driver</b> fails, but another passes, you can most likely
        simply configure the application and all plugins to use the driver that
        passes and you will be fine.</p>
    <p>If the failure is in a module that you will not be using, then you can
        ignore it as well, but be aware that the default plugins use most of
        the modules at least once, so you may have unexpected errors.</p>
    <p>In some cases, error messages may simply indicate that you are running on
        an untested infrastructure. For example, the application is only tested
        on PHP &gt;= 5.6, but it seems to work fine on 5.5. You'll have to try
        it out for your use case, or use the tested infrastructure.</p>
<?php });

?><div class="table-responsive"><table class="table table-bordered">
    <tr><th>Module(.Driver)</th><th>System check status</th></tr>
    <?php foreach ($results as $module => $result) {
        printf(
            '<tr class="%s"><td>%s</td><td>%s</td></tr>',
            $result === true ? '':'danger',
            $module,
            $result === true ? 'OK': $result
        );
    } ?>
</table></div><?php
backend_foot();




