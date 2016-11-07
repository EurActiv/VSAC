<?php

/**
 * Functions for generating common HTML snippets in the backend or documentation
 * pages.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function backend_depends()
{
    return array('auth', 'router');
}


/** @see example_module_config_items() */
function backend_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function backend_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function backend_test()
{
    return true;
}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//


function backend_head($title, array $options = array(), callable $more = null)
{
    $defaults = array(
        'bootswatch'  => framework_config('bootswatch', ''),
        'gaq_account' => framework_config('gaq_account', ''),
        'title'       => framework_config('app_name', ''),
    );
    $options = array_merge($defaults, $options);
    extract($options, EXTR_SKIP); // bootswatch, 

    ?><!DOCTYPE html><html lang="en" class="js">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars($title . ' | ' . framework_config('app_name', '')) ?></title>
        <?php backend_assets($bootswatch) ?>
        <?php backend_gaq($gaq_account) ?>
        <?php if ($more) { call_user_func($more); } ?>
    </head>
    <body>
        <div class="container">
            <div class="pull-right" style="margin-top:23px">
                <?php backend_main_menu() ?>
            </div>
            <h1>
                <?= $title ?>
            </h1>
            <hr>
            <?php backend_read_flashbag(); ?>
    <?php

}

function backend_foot(callable $more = null)
{
    if ($more) {
        call_user_func($more);
    }
    ?></div><!-- /.container -->
    </body></html><?php

}


function backend_flashbag($class, $content = null)
{
    $s = &superglobal('session');
    if (empty($s['backend_flashbag'])) {
        $s['backend_flashbag'] = array();
    }
    if (is_null($content)) {
        $content = $class;
        $class = 'info';
    }
    $s['backend_flashbag'][] = compact('class', 'content');
}


function example_item($title, $about, $example)
{
    static $js = false, $count = 0;
    if (!$js) {
        $js = true;
        ?><script>$(function () {
            $('.panel-heading a').on('click', function () {
                setTimeout(function () {
                    $(window).trigger('resize');
                }, 500);
            });
        });</script><?php
    }
    $count += 1;
    ?><div class="panel panel-default">
        <div class="panel-heading" role="tab" id="heading<?php echo $count ?>">
            <h4 class="panel-title"><a 
                data-toggle="collapse"
                data-parent="#accordion"
                href="#collapse<?php echo $count ?>"
            ><?= htmlspecialchars($title) ?></a></h4>
        </div>
        <div id="collapse<?php echo $count ?>"
             class="panel-collapse collapse"
             role="tabpanel">
            <div class="panel-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="active"><a href="#about-<?= $count ?>" data-toggle="tab">About</a></li>
                    <li><a href="#example-<?= $count ?>" data-toggle="tab">Example</a></li>
                    <li><a href="#source-<?= $count ?>" data-toggle="tab">Example Source</a></li>
                </ul>
                <br>
                <div class="tab-content">
                    <div class="tab-pane active" id="about-<?= $count ?>"><?= $about ?></div>
                    <div class="tab-pane" id="example-<?= $count ?>"><?= $example ?></div>
                    <div class="tab-pane" id="source-<?= $count ?>"><pre><code><?= htmlspecialchars($example) ?></code></pre></div>
                </div>
            </div>
        </div>
    </div><?php

}

function backend_tabs(array $tabs)
{
    static $count = 0;
    $count += 1;

    $ul = '<ul class="nav nav-tabs" role="tablist">';
    foreach (array_keys($tabs) as $offset => $title) {
        $ul .= sprintf(
            '<li class="%s"><a href="#tabs-%d-%d" data-toggle="tab">%s</a></li>',
            $offset > 0 ? '' : 'active',
            $count,
            $offset,
            $title
        );
    }
    $ul .= '</ul>';

    $content = '<div class="tab-content">';
    foreach (array_values($tabs) as $offset => $body) {
        $content .= sprintf(
            '<div class="%s" id="tabs-%d-%d">%s</div>',
            $offset > 0 ? 'tab-pane' : 'tab-pane active',
            $count,
            $offset,
            $body
        );
    }
    $content .= '</div>';
    return $ul . '<br>' . $content;
}

function backend_accordion_item($title, $content, $parent_id)
{
    static $count = 0;
    $count += 1;

    // heading
    $title = sprintf(
        '<a data-toggle="collapse" data-parent="#%s" href="#%s-%d">%s</a>',
        $parent_id,
        $parent_id,
        $count,
        $title
    );
    $title = sprintf('<h4 class="panel-title">%s</h4>', $title);
    $title = sprintf(
        '<div class="panel-heading" role="tab" id="heading-%d">%s</div>',
        $count,
        $title
    );

    $content = sprintf(
        '<div id="%s-%d" class="panel-collapse collapse" role="tabpanel">%s</div>',
        $parent_id,
        $count,
        sprintf('<div class="panel-body">%s</div>', $content)
    );
    return sprintf('<div class="panel panel-default">%s %s</div>', $title, $content);

}

function backend_accordion($items)
{
    static $count = 0;
    $count += 1;
    $id = 'accordion-' . $count;
    $accordion = sprintf('<div class="panel-group" id="%s" role="tablist">', $id);
    foreach ($items as $title => $content) {
        $accordion .= backend_accordion_item($title, $content, $id);
    }
    $accordion .= '</div>';
    return $accordion;
}


function backend_display_files($base_path, $files, $descriptions, $show_preview = true)
{
    echo '<div class="table-responsive" style="font-size:12px">';
    echo '<table class="table table-bordered">';
    echo '<tr><th>File</td><th>Description</th><th>Preview</th></tr>';
    foreach ($files as $file) {
        $description = empty($descriptions[$file]) ? '' : $descriptions[$file];
        backend_display_file($base_path . $file, $description, $show_preview); 
    }
    echo '</table></div>';
}

function backend_display_file($abspath, $description = '', $show_preview = true)
{
    if (strpos($abspath, router_base_url()) === 0) {
        $url = $abspath;
    } elseif (is_file($abspath)) {
        $url = router_any_file_url($abspath);
    } else {
        return;
    }

    $is_img = in_array(
        strtolower(pathinfo($abspath, PATHINFO_EXTENSION)),
        array('png', 'jpg', 'jpeg', 'gif', 'ico')
    );

    printf(
        '<tr><th>%s</th><td><p>%s</p>%s</td><td>%s</td></tr>',
        pathinfo($abspath, PATHINFO_BASENAME),
        $description ? $description : 'No description available',
        sprintf('<a href="%s">%s</a>', $url, $url),
        $show_preview && $is_img ? "<img style='max-width:150px' src='{$url}'>" : '-'
    );

}

function backend_collapsible($title, $content_cb, $hl_tag = 'h4')
{
    static $count = 0;
    if ($count == 0) {
        ?><script>(function ($) {
            $(function () {
                $('.backend-collapsible').on('click', function () {
                    $(this).siblings('i')
                           .toggleClass('fa-angle-double-right')
                           .toggleClass('fa-angle-double-down');
                });
            });
        }(jQuery));</script><?php
    }
    $count += 1;
    ?><div>
        <<?= $hl_tag ?>>
            <i class="fa fa-angle-double-right"></i>
            <a
                class="backend-collapsible"
                type="button"
                style="color:inherit"
                data-toggle="collapse"
                href="#collapsible<?= $count ?>"
            ><?= htmlspecialchars($title) ?></a>
        </<?= $hl_tag ?>>
        <div class="collapse" id="collapsible<?= $count ?>">
            <?= call_user_func($content_cb) ?>
        </div>
        <hr>
    </div>
    <?php


}

function backend_config_table()
{
    $auth = fn_exists('auth_is_authenticated') && auth_is_authenticated();
    $config = array();
    $get_config = function ($add) use (&$config) {
        $fn = __NAMESPACE__ . '\\' . str_replace('-', '_', $add) . '_config_items';
        if (function_exists($fn)) {
            $config = array_merge($config, call_user_func($fn));
        }
    };
    $print_config_item = function ($item) use ($auth) {
        while (count($item) < 4) $item[] = false;
        list($name, $default, $description, $redact) = $item;
        printf(
            '<tr><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td></tr>',
            htmlspecialchars($name),
            gettype($default),
            $description,
            ($redact && !$auth) ? 'Login to view this setting' : printR(config($name, $default), true)
        );
    };
    $print_config_table = function () use (&$config, $print_config_item) {
        echo '<table class="table table-condensed table-bordered">';
        echo '<tr><th>Name</th><th>Type</th><th>Description</th><th>Current Setting</th></tr>';
        if (empty($config)) {
            echo '<tr><td colspan="4">This plugin has no configuration settings</td></tr>';
        } else {
            foreach ($config as $item) {
                $print_config_item($item);
            }
        }
        echo '</table>';
    };
    $print_config_section = function () use ($auth, $print_config_table) {
        $c_path = $auth ? conf_file(plugin()) : '/config/' . plugin() . '.php';
        ?>
        <p>Configuration is located in the file <code><?= $c_path ?></code>.
            If you do not have the ability to modify this file, contact your
            administrator to have options changed. The configuration file should
            contain an array with the name <code>$config</code> containing the
            following offsets:</p>
        <?php
        $print_config_table();
    };
    $get_config(plugin());
    foreach (used_modules() as $module) {
        $get_config($module);
    }
    backend_collapsible('Configuration', $print_config_section);
}



function backend_format_size($size)
{
    if ($size > 1.0 * 1024 * 1024 * 1024) {
        return number_format($size / (1.0 * 1024 * 1024 * 1024), 1) . ' GB';
    }        
    if ($size > 1.0 * 1024 * 1024) {
        return number_format($size / (1.0 * 1024 * 1024), 1) . ' MB';
    }        
    if ($size > 1.0 * 1024) {
        return number_format($size / (1.0 * 1024), 1) . ' KB';
    }
    return number_format($size) . ' B';
}

function backend_format_time_ago($timestamp)
{
    if (!$timestamp) {
        return 'never';
    }
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return sprintf('%d seconds ago', $diff);
    }
    if ($diff < 60 * 60) {
        return sprintf('%d minutes ago', (int) $diff / 60);
    }
    if ($diff < 60 * 60 * 24) {
        return sprintf('%d hours ago', (int) ($diff / (60 * 60)));
    }
    if ($diff < 60 * 60 * 24 * 30) {
        return sprintf('%d days ago', (int) ($diff / (60 * 60 * 24)));
    }
    return 'on' . date('Y-m-d H:i', $timestamp);
}

function backend_code($content, $pre = true)
{
    $block = '<code>' . htmlspecialchars($content, ENT_NOQUOTES, null, false). '</code>';
    return $pre ? "<pre>{$block}</pre>" : $block;
}

function backend_codelink($url, $text = false)
{
    $link = "<a href='{$url}'>";
    $link .= $text ? $text : basename($url);
    $link .= '</a>';
    return $text ? $link : "<code>{$link}</code>";
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

function backend_main_menu()
{

    $links = scan_include_dirs('/framework');
    $links = array_filter($links, function ($link) {
        return substr($link, -4) === '.php'
            && $link !== 'index.php'
            && $link !== 'login.php';
    });
    ?><ul class="nav nav-pills">
        <li><a href="<?= router_base_url() ?>">Home</a></li>
        <?php if (fn_exists('auth_login_btn')) {
                echo '<li>', auth_login_btn(' '), '</li>';
        } ?>
        <li class="dropdown">
            <a class="dropdown-toggle btn-info" data-toggle="dropdown" href="#">
                <i class="fa fa-lg fa-gear"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-right"><?php
                foreach ($links as $link) {
                    printf(
                        '<li><a href="%s">%s</a></li>',
                        router_url($link),
                        ucwords(preg_replace('/[^a-z]/i', ' ', substr($link, 0, -4)))
                    );
                }
            ?></ul>
        </li>
    </ul><?php

}

/**
 * Print the asset links in the document head. Separated from backend_head() to
 * make that function more readable.
 *
 * @private
 * @param string $cdn the base URL to the CDN
 * @param string $bootswatch use a bootswatch theme, defaults to regular bootstrap
 *
 * @return void
 */
function backend_assets($bootswatch = false)
{
    $urls = array(
        'fontawesome' => 'font-awesome/4.4.0/css/font-awesome.css',
        'jquery'      => 'jquery-1.11.3.js',
        'bs_js'       => 'bootstrap/3.3.5/js/bootstrap.js',
        'html5shiv'   => 'html5shiv/3.7.0/html5shiv.js',
        'respond'     => 'respond/1.4.2/respond.min.js',
    );
    $urls['bs_css'] = $bootswatch
                    ? 'bootswatch/3.3.5/' . $bootswatch . '/bootstrap.css'
                    : 'bootstrap/3.3.5/css/bootstrap.css'
                    ;
    $urls = array_map(function ($asset) {
        return router_url('cdn/min/' . $asset);
    }, $urls);
    ?>
    <link href="<?= $urls['bs_css'] ?>" rel="stylesheet">
    <link href="<?= $urls['fontawesome'] ?>" rel="stylesheet">
    <script src="<?= $urls['jquery'] ?>"></script>
    <script src="<?= $urls['bs_js'] ?>"></script>
    <!--[if lt IE 9]>
        <script src="<?= $urls['html5shiv'] ?>"></script>
        <script src="<?= $urls['respond'] ?>"></script>
    <![endif]-->
    <?php
}

/**
 * Print the google analytics script. Separated from backend_head() to make that
 * function more readable.
 *
 * @param string $gaq_account the google analytics account ID, will not print if
 * empty.
 */
function backend_gaq($gaq_account)
{
    if (!$gaq_account) {
        return;
    }
    ?><script type="text/javascript">
    var _gaq = _gaq || [];
    _gaq.push(['_setAccount', '<?= $gaq_account ?>']);
    _gaq.push(['_trackPageview']);
    (function() {
        var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
    })();
    </script><?php
}

function backend_read_flashbag()
{
    $s = &superglobal('session');
    if (!empty($s['backend_flashbag'])) {
        foreach ($s['backend_flashbag'] as $msg) {
            echo sprintf('<p class="bg-%s" style="padding:10px 15px;">%s</p>', $msg['class'], $msg['content']);
        }
        echo '<hr>';
    }
    $s['backend_flashbag'] = array();
}

