<?php

namespace VSAC;

?>

<p>The API will convert image tags in a string of HTML into the markup necessary
    to make this lazy loader work. It is designed for use by client applications
    on the server side. The endpoint is:</p>

<?= backend_code(router_plugin_url('api.php')); ?>


<p>It accepts the following parameters:</p>
<ul>
    <li><code>api_key</code>: (string) the API key.</li>
    <li><code>image</code> (string) is either:
        <ol>
            <li>The URL to the original image that should be resized. It can be
                a relative path, an absolute path or a fully qualified url.</li>
            <li>A bit of HTML markup containing IMG tags that will be rewritten
                to be properly marked up for the lazy loader</li>
        </ol>
    </li>
    <li><code>strategy</code>: (string) see "Setting up a client", below.</li>
    <li><code>aspect</code>: (string) the aspect ratio, such as "16x9".</li>
    <li><code>inline</code>: (bool) inline a low quality base-64 encoded image instead
        of the placeholder.</li>
    <li><code>preserve</code>: (bool) preserve aspect ratio, bypassing resize service.</li>
</ul>
<p>The response will be a JSON encoded object, with the following entries:
<ul>
    <li><code>image</code>: the image, as submitted, for error fallback</li>
    <li><code>lazyload</code>: the new HTML markup, set up for lazy loading</li>
</ul>
