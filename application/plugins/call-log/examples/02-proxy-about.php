<?php

namespace VSAC;

$proxy_url = router_plugin_url('proxy.php');
$provider_url = 'http://httpbin.org/get?a=1&b=2&c=3';
$example_url = router_add_query($proxy_url, [
    'provider' => $provider_url
]);

?>
<p>Proxy logging is an alternative way to log <b>public, client-side</b> API
    calls. As opposed to the forward logging method, the proxy will make the
    user's request on their behalf and send the response directly back to the
    user rather than forwarding.</p>

<p>The proxy endpoint can be useful for situations where CORS headers interfere
    with the normal redirect flow or where redirecting is causing too much
    request overhead. However, beware of two things:</p>
<ul>
    <li>The proxy logger will cause greater load on webserver hosting VSAC
        because it may need to make lots of requests.</li>
    <li>For services that use IP-based throttling (such as the FaceBook graph
        API), the VSAC server will probably get banned quickly if you've got any
        traffic.</li>
</ul>

<p>To use the proxy API, reconfigure the consumer application to call the
    following URL:</p>

<?= backend_code($proxy_url); ?>

<p>It accepts a single query parameter: <code>provider</code>. This
    parameter is the location that the proxy will make its request to.</p>

<p>For example, imagine you want to make an API call to the following
    endpoint:</p>

<?= backend_code($provider_url); ?>

<p>You would configure the calling application to use this URL instead:</p>

<?= backend_code($example_url); ?>

<p>That's it. You can <a href="<?= $example_url ?>">click here</a> to see it in
action.</p>

<p><b>Note:</b> the URL specified in <code>provider</code> must be whitelisted
    in the <code>http_allowed_*</code> settings.</p>
    



