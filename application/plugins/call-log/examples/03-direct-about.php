<?php

namespace VSAC;

?>
<p>Direct logging is the normal way to log <b>private, server-to-server</b> API
    calls. It has the advantage of not interfering with the original call in any
    way; however some care must be taken to protect the API key and not expose
    it to clients.  An leaked API key is not catastrohpic, but it can cause
    your call log to be filled with garbage.</p>

<p>To use direct logging, have either the consumer or provider (or both) make
    a GET request to the following URL:</p>

<?= backend_code(router_plugin_url('direct.php')) ?>

<p>It requires the following query parameters:</p>
<ul>
    <li><code>api_key</code>: the API key for this service.</li>
    <li><code>consumer</code>: the label (eg, URL) of the consumer service (ie,
        the application making the call).</li>
    <li><code>provider</code>: the label (eg, URL) of the provider service (ie,
        the application answering the call).</li>
</ul>

<p>The endpoint will return a JSON object with the following offsets:</p>
<ul>
    <li><code>logged</code> (bool): whether the call was successfully logged</li>
    <li><code>error</code> (string): if there was an error, the error message</li>
</ul>
