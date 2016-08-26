<?php

namespace VSAC;

$url = router_add_query(router_plugin_url('feed.php', true), array(
    'feed' => 'http://www.feedforall.com/sample.xml',
    'count' => 5,
    'fields' => 'link,title,comments',
));


$results = http_get($url);

?><pre><code>$ curl <?= $url ?>;
<?= htmlspecialchars($results['body']); ?></code></pre>
