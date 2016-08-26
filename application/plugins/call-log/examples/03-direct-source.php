<?php

namespace VSAC;

if (!auth_is_authenticated()) {
    echo '<p>please log in to view this example</p>';
} else {

$direct_url = router_plugin_url('direct.php', true);
$params = array(
    'api_key'  => config('api_key', ''),
    'consumer' => 'http://example.com',
    'provider' => 'http://httpbin.org/', 
);
$example_url = router_add_query($direct_url, $params);

$response = http_get($example_url);

?><pre>&lt;?php

$params = <?php var_export($params) ?>;
$logger_url = '<?= $direct_url ?>?' . http_build_query($params);
/* $logger_url contains: '<?= $example_url ?>' */
$response = file_get_contents($logger_url);
/* $response contains: <?php var_export($response['body']) ?> */

</pre><?php
}
