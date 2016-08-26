<?php

namespace VSAC;

use_module('backend-all');


backend_head('Call Map Logger');
?>
<p>This resource allows you to log calls to external APIs without writing a full
    plugin to proxy the requests</p>
<hr>

<?php
http_examples_in_config('httpbin.org');
docs_examples();
backend_config_table();
backend_foot();
