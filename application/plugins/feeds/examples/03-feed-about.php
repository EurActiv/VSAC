<?php

namespace VSAC;


?>
<p><b class='text-danger'>Internal Use Only!</b> This section refers to the
    API's internal workings and should not be used directly. It is for
    documentation only.</p>

<p>The API end point is located at:</p>
<?= backend_code(router_plugin_url('feed.php')) ?>

<p>It accepts the following query parameters:</p>
<ul>

    <li><code>feed</code>: the URL to the feed. Required. It must be one of the
        whitelisted domains in <code>http_allowed_domains</code> or
        <code>http_allowed_url</code>.</li>
    <li><code>count</code>: the number of entries to fetch, 1 to 100. Will not
        return more entries than are currently in the feed no matter how high
        the number is. Optional, default 3. </li>
    <li><code>fields</code>: the fields in each item to fetch, as a comma
        separated list. Optional, default <code>'link,title'</code>.</li>
    <li><code>strip_tags</code>: strip HTML tags from the returned results.
        Optional, default true.</li>
</ul>

