<?php

namespace VSAC;

$forward_url = router_plugin_url('forward.php');
$provider_url = 'http://httpbin.org/get?a=1&b=2&c=3';
$example_url = router_add_query($forward_url, [
    'provider' => $provider_url
]);

?>
<p>Forwarding logging is the normal way to log <b>public, client-side</b> API
    calls. To use it, reconfigure the client application to make its call to the
    forwarding controller instead of the actual API endpoint. The URL to the
    forwarding logger service is:</p>

<?= backend_code($forward_url); ?>

<p>It accepts a single query parameter: <code>provider</code>. This
    parameter is the location that the client will forward to after the
    call is logged.</p>

<p>For example, imagine you want to make an API call to the following
    endpoint:</p>

<?= backend_code($provider_url); ?>

<p>You would configure the calling application to use this URL instead:</p>

<?= backend_code($example_url); ?>

<p>That's it. You can <a href="<?= $example_url ?>">click here</a> to see it in
action.</p>

<p><b>Note:</b> the URL specified in <code>provider</code> must be whitelisted
    in the <code>http_allowed_*</code> settings.</p>
    



