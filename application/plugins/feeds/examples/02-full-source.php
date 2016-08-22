<?php

namespace VSAC;

?>

<?php if (!auth_is_authenticated()): ?>
<p>You must be logged in to view this example.</p>
<?php else: ?>

<?php
    $api_key = config('api_key', '');
    $feed_url = function ($handle) use ($api_key) {
        $return = router_plugin_url('full-content.php', true)
                . '?' . http_build_query(array(
                          'feed' => $handle,
                          'api_key' => $api_key,
                        ));
        return $return;
    };
?>
<p>Currently configured feeds:</p>
<table class="table table-bordered">
    <tr><th>Handle (link)</th><th>Original Feed</th></tr>
    <?php foreach (config('full_content_feeds', []) as $feed) { ?>
        <tr>
            <td><a href="<?= $feed_url($feed['handle']) ?>"><?= $feed['handle'] ?></a></td>
            <td><?= $feed['url'] ?></td>
        </tr>
    <?php } ?>
</table>
<?php endif; ?>
