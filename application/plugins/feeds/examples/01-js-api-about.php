<?php

namespace VSAC;

$url = router_plugin_url('feeds.js');
$url_min = router_plugin_url('feeds-min.js');
?>

<p>First, include the script:</p>
<?= backend_code("<script src='{$url_min}'></script>"); ?>
<p>Uncompressed:</p>
<?= backend_code("<script src='{$url}'></script>"); ?>

<p><b>Note:</b> the script relies on jQuery being loaded first.</p>

<p>This creates a function <code>window.VSAC.feeds</code>. It accepts the
    following parameters:</p>
<ul>
    <li><code>feed_url</code>: the URL to the feed. Required. It must be one of
        the whitelisted domains in <code>http_allowed_domains</code> or
        <code>http_allowed_url</code>.</li>
    <li><code>callback</code>: the callback to call with the fetched feed data.
        Will receive the response object as the first parameter (see Endpoint,
        below).</li> 
    <li><code>count</code>: the number of entries to fetch, 1 to 100. Will not
        return more entries than are currently in the feed no matter how high
        the number is. Optional, default 3. </li>
    <li><code>fields</code>: the fields in each item to fetch, as an array.
        Optional, default <code>['link','title']</code>.</li>
    <li><code>strip_tags</code>: strip HTML tags from the result on the server
        side. Highly recommended, default true.</li>
</ul>

