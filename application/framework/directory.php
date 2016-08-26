<?php

namespace VSAC;


use_module('backend-all');


$plugins = plugins();
uasort($plugins, function ($a, $b) {
    if ($a['priority'] == $b['priority']) return 0;
    return ($a['priority'] < $b['priority']) ? -1 : 1;
});

backend_head(framework_config('app_name', ''));

?>
<p class="well text-center"><?= framework_config('description', ''); ?></p>

<h3>Plugins</h3>

<div class="table-responsive"><table class="table">

<?php foreach($plugins as $name=>$plugin) { ?>
    <tr>
        <th><a href="<?= router_url($name . '/' .$plugin['doc_file']); ?>"><?= $plugin['name'] ?></a></th>
        <td><?= $plugin['description'] ?></td>
    </tr>
<?php } ?>
</table></div>

<p class="well text-center text-danger"><?= framework_config('legal', ''); ?></p>

<?php

backend_foot();




