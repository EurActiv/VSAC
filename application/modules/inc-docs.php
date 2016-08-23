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

/**
 * Read the documenation directory and extract the examples. Acceptable
 * structures:
 *
 * Flat:
 *
 *     basedir/
 *     |-- {$name_one}-title.txt     # the example title
 *     |-- {$name_one}-about.{$ext}  # the example documentation
 *     |-- {$name_one}-source.{$ext} # (optional) a code example
 *     |-- {$name_two}-title.txt
 *     |-- {$name_two}-about.{$ext}
 *     `-- {$name_two}-source.{$ext}
 *
 * Sub directories:
 *
 *     basedir/
 *     |-- {$name_one}
 *     |   |-- title.txt     # the example title
 *     |   |-- about.{$ext}  # the example documentation
 *     |   `-- source.{$ext} # (optional) a code example
 *     `-- {$name_two}
 *         |-- title.txt
 *         |-- about.{$ext}
 *         `-- source.{$ext}
 *
 * @param string $basedir @see docs_basedir()
 *
 * @return array the names of found examples [eg, array($name_one, $name_two)]
 */
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

/**
 * Locate the files in a given example.
 *
 * @param string $example_name the example name, as found in docs_example_enumerator()
 * @param string $item_name one of 'title', 'about', or 'source'
 * @param basedir @see docs_basedir
 *
 * @return string the absolute path to the example file
 */
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

/**
 * Print the documentation items for a plugin in an accordion
 *
 * @param callable $enumerator a function that returns the names of the examples
 * for the plugin, see docs_example_enumerator
 * @param callable $locator a function that locates the example files from the
 * example name, see docs_example_locator
 *
 * @return void
 */
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

/**
 * Print a series of dependencies
 *
 * @param array $dependencies an array of arrays where the sub arrays have the
 * offsets: 0: library name; 1: required version; 2: url to library; 3: optional
 * notes
 *
 * @return void
 */
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

/**
 * Get the base directory for examples, defaults to
 * "plugins/{$current_plugin}/examples"
 *
 * @param string $base_dir the override base directory
 *
 * @return string
 */
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

/**
 * Load the contents of a document file. If it's a PHP file, it will be
 * evaluated and the echo'd content captured in an output buffer and returned.
 *
 * @param string $path the path to include
 * @param string $default the content to return if the file does not exist
 *
 * @return string
 */
function docs_load_file($path, $default)
{
    if (empty($path) || !is_file($path)) {
        return $default;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'php':
            ob_start();
            require $path;
            return ob_get_clean();
        case 'js':
            return '<script>' . file_get_contents($path) . '</script>';
        case 'css':
            return '<style>' . file_get_contents($path) . '</style>';
        default:
            return file_get_contents($path);
    }
}

/**
 * Format an example as tabs and return the content.
 *
 * @param string $example the example as extracted by docs_examples
 *
 * @return string the tabs html markup
 */
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


