<?php

namespace VSAC;

use_module('backend-all');

if (auth_is_authenticated()) {
    build_minify_js(__DIR__.'/feeds.js');
}


backend_head('Feed Fetcher and API');

?>
<p>This is API provides a couple of functions for working with RSS feeds.
    It exists because we were having uptime issues with the Google Feeds
    API. It fetches feeds, caches them for a while to reduce stress on
    the source server, and provides a simple javascript function to
    fetch, format and display the feeds.</p>
<?php http_examples_in_config('www.feedforall.com'); ?>
<?php docs_examples() ?>
<hr><h3>System Status</h3><br>
<?php

cal_status('clean-cache.php');

backend_config_table();

backend_foot();

