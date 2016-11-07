<?php

namespace VSAC;


use_module('backend-all');

backend_head('WebCron');

auth_require_authenticated();

$scheme = request_scheme();
$host = request_host();
$base_url = router_base_url(true);
$wp_cron = 'https://codex.wordpress.org/Function_Reference/wp_cron';
$wiki_cron = 'https://en.wikipedia.org/wiki/Cron';
$current_user = get_current_user();
$cron_url = router_add_query(
    router_plugin_url('cron.php', true),
    array('api_key' => config('api_key', ''))
);

?>
<p>The webcron callback is a single place to ensure that systems that rely on
    a web cron call (such as <a href="<?= $wp_cron ?>">wp_cron</a> or the cron
    jobs in this system) get called regardless of server configuration.</p>

<p>The webcron endpoint <b>must</b> be called every minute. Be sure that your
    system crontab has the following entry:</p>
<pre>* * * * * <?= get_current_user() ?> wget -qO- <?= $cron_url ?> &> /dev/null 2&> /dev/null</pre>

<h3>Cron job format</h3>

<p>Cron jobs are defined in the <code>cron_jobs</code> configuration setting
    for this plugin. Each entry should be in a simplified <a href="<?= $wiki_cron ?>">cron
    schedule</a> format: </p>

<pre>┌─────────────────────────────── minute (0 - 59)
│ ┌─────────────────────────────── hour (0 - 23)
│ │ ┌─────────────────────────────── date in month (1 - 31)
│ │ │ ┌─────────────────────────────── month (1 - 12)
│ │ │ │ ┌─────────────────────────────── day of week (1 [Monday] - 7 [Sunday])
│ │ │ │ │ ┌─────────────────────────────── The URL to call
│ │ │ │ │ │                               ┌─ JSON encoded cURL options (optional)
* * * * * http://example.com/callback.php {"CURLOPT_USERPWD": "user:pass"}</pre>  

<p>Each of the time entries can be a comma-separated list. More advanced
   formatting (eg, <code>FRI</code> instead of <code>5</code> in the day of
   week column or <code>*/15</code> instead of <code>0,15,30,45</code> in the
   minute column) and macros (eg, <code>@yearly</code>)are not supported.</p>
<p>The URLs should be fully qualified, with a couple of exceptions:</p>
<ul>
    <li>URLs without schemes will have a scheme appended, eg:
        <code>//example.com/callback.php</code> becomes
        <code><?= $scheme ?>//example.com/callback.php</code>.</li>
    <li>URLs that begin with a slash will be considered as relative to the
        current VSAC server, eg <code>/path/to/callback.php</code> becomes
        <code><?=  $scheme ?>//<?= $host ?>/path/to/callback.php</code>.</li>
    <li>Relative URLs will have the path to the VSAC application prepended, eg:
        <code>path/to/callback.php</code> becomes
        <code><?= $base_url ?>/path/to/callback.php</code>.</li>
</ul>
        
<hr>


<?php

log_file_viewer();

backend_config_table();



backend_foot();






