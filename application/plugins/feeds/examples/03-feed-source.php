<?php

namespace VSAC;

$url = router_add_query(router_plugin_url('feed.php', true), array(
    'feed' => 'http://www.feedforall.com/sample.xml',
    'count' => 5,
    'fields' => 'link,title,comments',
));

http_get($url, $results);

?><pre><code>$ curl <?= $url ?>;
<?= htmlspecialchars($results); ?></code></pre>
