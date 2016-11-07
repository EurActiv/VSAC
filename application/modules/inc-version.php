<?php

/**
 * Functions for adding version management to the backend. So you can have
 *
 *   plugin/v1/script.js
 *   plugin/v2/script.js // updated api
 *   plugin/v3/script.js // fixed bug, but has BC change
 *   plugin/v...
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function version_depends()
{
    return array('auth', 'docs', 'filesystem', 'form', 'request', 'router');
}

/** @see example_module_config_items() */
function version_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function version_sysconfig()
{
    return true;
}

/** @see example_module_test() */
function version_test()
{
    return true;
}

//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//

/**
 * Get all versions of the assets in the plugin, '-edge' is always first
 *
 * @return array for example array('-edge', '1', '2', ...)
 */
function version_get_all()
{
    $return = array('-edge');
    $dir = filesystem_plugin_path();
    foreach(scandir($dir) as $file) {
        if (strpos($file, '.') === 0) continue;
        if (!is_dir($dir.$file)) continue;
        if (!preg_match('/^v\d+$/', $file)) continue;
        $return[] = substr($file, 1);
    }
    return $return;

}

/**
 * Get the version of the assets in the plugin that are currently being viewed
 *
 * @param &$version the version will be stored here
 * @param &$minify whether to use the minified version will be stored here
 * @param &$debug whether to enable javascipt debug messages will be stored here
 *
 * @return bool the value of $debug
 */
function version_get(&$version=false, &$minify = false, &$debug = false)
{
    $versions = version_get_all();
    $default_version = end($versions);
    reset($versions);
    $version = request_query('version', $default_version);
    if (!in_array($version, $versions)) {
        $version = $default_version;
    }
    $minify = !((bool) request_query('no_minify'));
    $debug = (bool) request_query('debug');
    return $debug;
}

/**
 * Get the relative path to a versioned file, based on version_get. Example:
 *
 *     $path = version_file('script.js');
 *     // $path might be:
 *     //  - 'v1/script-min.js'
 *     //  - 'v-edge/script-min.js'
 *     //  - 'v-edgs/script.js'
 *     //  - ...
 *     $url = router_plugin_url($path);
 *     // $url is the URL to the versioned file
 *
 * @param string $file the file within the version directory, may or may not
 * actually exist.
 *
 * @return string the relative versioned path
 */
function version_file($file)
{
    version_get($version, $minify);
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $file = substr($file, 0, -1 * (strlen($ext) + 1));
    if ($minify && ($ext == 'js' || $ext == 'css')) {
        if (substr($file, -4) != '-min') {
            $file .= '-min';
        }
    } else {
        if (substr($file, -4) == '-min') {
            $file = substr($file, 0, -4);
        }
    }
    return 'v' . $version . '/' . $file . '.' . $ext;
}

/**
 * Get a URL to the current backend page with version query paramters attached
 *
 * @param mixed $version the version to go to, integer or "-edge"
 * @param bool $minify set the minify flag
 * @param bool $debug set the debug flag
 *
 * @return string
 */
function version_url($version, $minify = true, $debug = false)
{
    $query = array(
        'version'   => $version,
        'no_minify' => $minify ? '0' : '1',
        'debug'     => $debug ? '1' : '0',
    );
    return router_add_query(request_url(), $query);
}

/**
 * Output an additional header on the versioned asset screens that will allow
 * users to select the version to view. Logged in users will also get a form
 * that allows them to manage the versions (publish, revert).
 *
 * @return void
 */
function version_header()
{
    ?><div>
        <style scoped>
            .vc .well               { min-height: 145px; }
            .vc .notes              { font-size: 14px; min-height:75px; }
            .vc .notes .text-danger { font-size: 15px;}
            .vc .notes p:last-child { margin-bottom: 0; }
            .vc td                  { vertical-align: top; padding-right: 30px;}
            .vc .checkbox           { margin-top: 5px; }
        </style>
        <div class="row vc">
            <div class="col-md-6"><div class="well">
                <?php version_picker() ?>
            </div></div>
            <div class="col-md-6"><div class="well">
                <?php version_manager() ?>
            </div></div>
        </div>
        <hr>
    </div>
    <?php
}


/**
 * Works about the same as docs_examples, but looks in the current version
 * directory instead of the plugin root.
 *
 * @return void
 */
function version_examples()
{
    version_get($version);
    $basedir = filesystem_plugin_path() . 'v' . $version . '/examples';
    $enumerator = function () use ($basedir) {
        return docs_example_enumerator($basedir);
    };
    $locator = function ($example_name, $item_name) use ($basedir) {
        return docs_example_locator($example_name, $item_name, $basedir);
    };
    return docs_examples($enumerator, $locator);
}


//----------------------------------------------------------------------------//
//-- Private functions                                                      --//
//----------------------------------------------------------------------------//


/**
 * Pick the version to use, callback for version_header()
 *
 * @private
 *
 * @return void
 */
function version_picker()
{
    version_get($current_version, $minify, $debug);
    ?><p><label>Select Version</label></p><?php

    $versions = array();
    foreach (version_get_all() as $version) {
        $versions[$version] = 'V' . $version;
    }
    if (!auth_is_authenticated()) {
        unset($versions['-edge']);
    }
    form_form(
        array('id' => 'version-picker'),
        function () use ($versions, $current_version, $minify, $debug) {
            if (is_numeric($current_version)) {
                $current_version = (int) $current_version;
            }
            echo '<table><tr><td>';
            form_selectbox($versions, $current_version, '', 'version', 'version');
            echo '</td><td>';
            form_checkbox(!$minify, 'Do not minify', 'no_minify', 'no_minify');
            echo '</td><td>';
            form_checkbox($debug, 'Enable Debug Messages', 'debug', 'debug');
            echo '</td></tr></table>';
        }
    );
    ?><script>$(function () {
        'use strict';
        var vp = $('#version-picker');
        vp.find('input, select').on('change', function () {
            vp.submit();
        });
    });</script><?php

    if($current_version == '-edge') { ?>
        <div class="notes">
        <p class="text-danger"><b>Warning:</b> You are currently viewing
            documentation for the <code>-edge</code> version.  This version
            is used for development and is inherently unstable. Don't use it
            in production.</p>
        </div>
    <?php }

}





/**
 * Manage publishing and reverting versions. Callback for version_header()
 *
 * @private
 *
 * @return void
 */
function version_manager()
{
    if (!auth_is_authenticated()) {
        ?><p class="notes">Log in to deploy new versions or manage existing
            ones.</p><?php
        return;
    }
    version_get($version);
    if ($version !== '-edge') {
        version_reverter();
    } else {
        version_publisher();
    }
}


/**
 * Revert versions, callback for version_manager()
 *
 * @private
 *
 * @return void
 */
function version_reverter()
{
    version_get($version);
    form_form(
        array('id' => 'version-reverter', 'method' => 'post'),
        function () use ($version) {
            form_hidden($version, 'rev-version', 'rev-version');
            form_hidden('', 'rev-confirm', 'rev-confirm');
            ?><div class="notes">
                <p>Switch to version <code>-edge</code> to deploy new versions.
                    If this version has a bug or requires a non-breaking change,
                    you can revert it to <code>-edge</code> to continue working
                    on it.</p>
                <?php form_submit(false, 'Revert'); ?>
            </div><?php

        },
        function () {
            $revert_from = (int) request_post('rev-version');
            $confirm = (string) request_post('rev-confirm');
            $expected = 'revert version ' . $revert_from;
            if ($confirm !== $expected) {
                $msg = "Confirmation error. Expected '{$expected}', got '{$confirm}'";
                return form_flashbag($msg, 'danger');
            }
            if ($error = version_move($revert_from, '-edge')) {
                return form_flashbag($error, 'danger');
            }
            $msg = sprintf(
                'Version reverted to -edge. <a href="%s">Click here</a> to edit.',
                version_url('-edge', false, true)
            );
            return form_flashbag($msg, 'success');
        }
    );

    ?><script>$(function () {
        'use strict';
        var vr = $('#version-reverter'),
            vrc = vr.find('input#rev-confirm'),
            v = vr.find('input#rev-version').val(),
            btn = vr.find('button');
        btn.on('click', function (e) {
            var msg, actual;
            e.preventDefault();
            msg = 'WARNING! Any changes made to v-edge will be lost! Please '
                + 'confirm you want to do this by typing "revert version ' + v
                + '" in the box below.';
            actual = prompt(msg);
            if (actual) {
                vrc.val(actual);
                vr.submit();
            }
        });
    });</script><?php
}



/**
 * Publish versions, callback for version_manager()
 *
 * @private
 *
 * @return void
 */
function version_publisher()
{
    ?><p><label>Publish to Version:</label></p><?php
    form_form(
        array('id' => 'version-deployer', 'method' => 'post'),
        function () {
            $versions = version_get_all();
            array_unshift($versions, 'no-action');
            $versions = array_combine($versions, $versions);
            $versions['-edge'] = (count($versions) - 1) . ' (new version)';
            $versions['no-action'] = ' - select - ';
            echo '<table><tr><td>';
            form_selectbox($versions, 'no-action', '', 'pub-version', 'pub-version');
            echo '</td><td>';
            form_hidden('', 'pub-confirm', 'pub-confirm');
            echo '</td></tr></table>';
        },
        function () {
            $deploy_to = (int) request_post('pub-version');
            $confirm = (string) request_post('pub-confirm');

            if ($deploy_to > 0) {
                $expected = 'overwrite version ' . $deploy_to;
            } else {
                $expected = 'create new version';
                $deploy_to = max(array_map('intval', version_get_all())) + 1;
            }
            if ($confirm !== $expected) {
                $msg = "Confirmation error. Expected '{$expected}', got '{$confirm}'";
                return form_flashbag($msg, 'danger');
            }
            if ($error = version_move('-edge', $deploy_to)) {
                return form_flashbag($error, 'danger');
            }

            $msg = sprintf(
                'Version %d Created. <a href="%s">Click here</a> to view',
                $deploy_to,
                version_url($deploy_to)
            );
            return form_flashbag($msg, 'success');
        }
    );
    ?><script>$(function () {
        'use strict';
        var vd = $('#version-deployer'),
            vdv = vd.find('select#pub-version'),
            vdc = vd.find('input#pub-confirm'),
            cancel = function (msg) {
                if (msg) {
                    alert(msg);
                }
                vdc.val('');
                vdv.val('no-action');
            };
        vdv.on('change', function () {
            var msg, expected, actual, v = vdv.val();
            if (v === '-edge') {
                v = 0;
            } else if (!(/^\d+$/).test(v)) {
                return cancel();
            } else {
                v = parseInt(v, 10);
            }
            expected = (v > 0) ? 'overwrite version ' + v : 'create new version';
            msg = 'The action you are requesting requires confirmation. '
                + 'Please type exactly this phrase in the box: '
                + '"' + expected + '"';
            actual = prompt(msg);
            if (!actual) {
                return cancel();
            }
            vdc.val(actual);
            vd.submit();
        });
    });</script><?php
    ?><div class="notes">
        <p>The development version is located at:</p>
        <p><code><?= filesystem_plugin_path() ?>v-edge/</code></p>
    </div><?php
}



//-- Utilities ---------------------------------------------------------------//

/**
 * Move a version from one directory to another. Very dangerous, do not call
 * it directly under any circumstances.
 *
 * @private
 *
 * @param string|int $from the version to move from (eg "-edge" or 4)
 * @param string|int $from the version to move to, anything already there will be deleted
 *
 * @return string an error message if there was one, empty string if not
 */
function version_move($from, $to)
{
    // checks should be redundant, but justincase.
    auth_check_csrf_token();
    if (!auth_is_authenticated()) {
        return;
    }

    // make some variables
    $base_path = filesystem_plugin_path();
    $from_dir = $base_path . 'v' . $from . '/';
    $to_dir = $base_path . 'v' . $to . '/';

    // make sure the move can happen
    if (!is_dir($from_dir)) {
        return "Source directory '{$dir}' does not exist";
    }

    if (is_dir($to_dir)) {
        filesystem_plugin_rmdir($to_dir, true);
    }
    if (is_dir($to_dir)) {
        return "Directory already exists and could not be removed: {$to_dir}";
    }

    // do the move
    filesystem_cpdir($from_dir, $to_dir);
    if (!is_dir($to_dir)) {
        return "Could not create {$to_dir}";
    }

    // repair references
    $search = plugin().'/v' . $from;
    $replace = plugin().'/v' . $to;
    $extensions = array('css', 'js', 'html', 'txt');
    foreach(filesystem_rglob($to_dir . '*') as $file) {
        if (!is_file($file)) continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions)) continue;

        $content = file_get_contents($file);
        $content = str_replace($search, $replace, $content);
        file_put_contents($file, $content);
    }
    return '';
}

