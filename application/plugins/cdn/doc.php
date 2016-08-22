<?php

namespace VSAC;

use_module('backend-all');


backend_head('CSS/JS Library CDN');
?>
<p>This resource allows you to load common Javascript and CSS libraries
    from a single source. It exists because we were experiencing
    significant downtime with the common public CDN providers. While
    none of them were experiencing huge downtime, it never overlapped
    with our own and therefore caused problems.</p>
<p>The idea here is to maximize the convenience of the public CDN
    providers, so this resource is essentially a caching proxy of those
    providers.</p>

<p><b>Performance Note:</b> This CDN is almost guaranteed to have worse
    uptime than the source CDNs.  It also is pretty much guaranteed
    that visitor's won't have the files cached on first visit. It only
    makes sense to use it if you are also using one of the other assets
    on this server.</p>

<hr>

<?php

docs_examples();
backend_config_table();
backend_foot();
