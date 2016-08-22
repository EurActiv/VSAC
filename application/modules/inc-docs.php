<?php

/**
 * Functions for printing documentation examples
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_config_items() */
function docs_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function docs_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function docs_test()
{
    return true;
}

//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//

function docs_example_enumerator($basedir = null)
{
    $basedir = docs_basedir($basedir);

    $examples = filesystem_ls($basedir, function ($f) use ($basedir) {
        if (is_dir($basedir . '/' .$f)) {
            return is_file($basedir . '/' .$f . '/title.txt');
        }
        $f = pathinfo($f, PATHINFO_FILENAME);
        return substr($f, -6) == '-title';
    });

    $examples = array_map(function ($f) use ($basedir) {
        if (is_dir($basedir . '/' .$f)) {
            return $f;
        }
        $f = pathinfo($f, PATHINFO_FILENAME);
        return substr($f, 0, -6);
    }, $examples);

    return $examples;
}

function docs_example_locator($example_name, $item_name, $basedir = null)
{
    $basedir = docs_basedir($basedir);
    $paths = array();
    $exts = array('php', 'txt', 'html', 'htm', 'js', 'css');
    foreach ($exts as $ext) {
        $paths[] = $basedir . '/' . $example_name . '-' . $item_name . '.' . $ext;
        $paths[] = $basedir . '/' . $example_name . '/' . $item_name . '.' . $ext;
    }
    foreach ($paths as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return false;
}

function docs_examples(callable $enumerator = null, callable $locator = null)
{
    static $count = 0;

    if (is_null($locator)) {
        $locator = __NAMESPACE__ . '\\docs_example_locator';
    }

    if (is_null($enumerator)) {
        $enumerator = __NAMESPACE__ . '\\docs_example_enumerator';
    }

    $examples = call_user_func($enumerator);

    $examples = array_map(function ($name) use ($locator) {
        return array(
            'name' => $name,
            'title' => call_user_func($locator, $name, 'title'),
            'about' => call_user_func($locator, $name, 'about'),
            'source' => call_user_func($locator, $name, 'source'),
        );
    }, $examples);

    $examples = array_map(function ($example) {
        $example['title'] = docs_load_file($example['title'], $example['name']);
        $example['about'] = docs_load_file($example['about'], null);
        $example['source'] = docs_load_file($example['source'], null);
        return $example;
    }, $examples);

    $accordion = array();
    foreach ($examples as $example) {
        $accordion[$example['title']] = docs_format_example($example);
    }

    echo '<h3>Documentation and Examples</h3>';
    echo backend_accordion($accordion);

    echo '<script>$(".panel-heading a").on("click", function () {
              setTimeout(function () { $(window).trigger("resize"); }, 500);
          });</script>';

}

function docs_dependencies(array $dependencies)
{
    backend_collapsible('Dependencies', function () use ($dependencies) {
        echo '<div class="table-responsive"><table class="table table-bordered">';
        echo '<tr><th>Library</th><th>Version</th><th>Notes</th></tr>';
        array_walk($dependencies, function ($dep) {
            if (count($dep) < 3) {
                err('Input dependencies are mal-formatted.');
            }
            printf(
                '<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars($dep[2]),
                htmlspecialchars($dep[0]),
                htmlspecialchars($dep[1]),
                empty($dep[3]) ? '' : htmlspecialchars($dep[3])
            );
        });
        echo '</table></div>';
    });
}

//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//

function docs_load_file($path, $default)
{
    if (empty($path) || !is_file($path)) {
        return $default;
    } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
        ob_start();
        require $path;
        return ob_get_clean();
    } else {
        return file_get_contents($path);
    }
}


function docs_format_example($example)
{
    extract($example, EXTR_SKIP);
    if (empty($source)) {
        return $about;
    }
    return backend_tabs(array(
        'About' => $about,
        'Example' => $source,
        'Example Source' => sprintf('<pre><code>%s</code></pre>', htmlspecialchars($source)),
    ));
}

function docs_basedir($basedir = null)
{
    if (!$basedir) {
        $basedir = filesystem_plugin_path() . 'examples';
    }
    if (substr($basedir, -1) == '/') {
        $basedir = substr($basedir, 0, -1);
    }
    return $basedir;
}
