<?php

namespace VSAC;

$handles = array_column(config('full_content_feeds', []), 'handle');

?>
<p>The Full Content Feed Endpoint is a small API that replaces <code>description</code>
    attribute of an RSS feed with the full content of the linked item. It works
    by:</p>
<ol>
    <li>Opening the feed</li>
    <li>Extracting the <code>link</code> attribute from each feed item</li>
    <li>Loading the linked resource and scraping the content via XPath
        selectors.</li>
    <li>Replacing the <code>description</code> attribute in the feed with the
        scrapted content.</li>
</ol>
<p>This endpoint exists to facilitate the republishing of wire service
    articles. It is available at:</p>

<?= backend_code(router_plugin_url('full-content.php')); ?>

<p>It has the following query parameters:</p>
<ul>
    <li><code>feed</code>: the configured handle for the feed. Available
        handles: <code><?= implode('</code>, <code>', $handles) ?></code>.</li>
    <li><code>api_key</code>: to prevent abuse and copyright issues, the full
        content feed is hidden behind an API key, which must be specified here.
        Check the config file for the key.</li>
</ul>
