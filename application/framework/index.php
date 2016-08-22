<?php

namespace VSAC;


use_module('backend-all');
//use_module('check');

//check_dependency('php_function' , 'apache_get_modules');
//check_dependency('php_function' , 'exec'              );
//check_dependency('php_function' , 'curl_init'         );
//check_dependency('php_class'    , 'Imagick'           );
//check_dependency('apache_module', 'mod_rewrite'       );
//check_dependency('apache_module', 'mod_headers'       );
//check_dependency('system'       , 'compass'           );
//check_dependency('system'       , 'uglifyjs'          );

//check_rewrite_rule('^(stats|login|index).html$', '/$1.php', array('L'));

$plugins = plugins();
uasort($plugins, function ($a, $b) {
    if ($a['priority'] == $b['priority']) return 0;
    return ($a['priority'] < $b['priority']) ? -1 : 1;
});

foreach(array_keys($plugins) as $p) {
//    check_rewrite_rule('^'.$p.'/(.*)$', '/plugins/'.$p.'/$1', array('L'));
}

backend_head('Static Asset Manager');

?>
<p class="well text-center"><?= framework_config('description', ''); ?></p>

<h3>Directory</h3>

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




