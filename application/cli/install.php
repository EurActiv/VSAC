<?php

namespace VSAC;


//----------------------------------------------------------------------------//
//-- collectors                                                             --//
//----------------------------------------------------------------------------//

// marginally better than globals
function &variables()
{
    static $variables = array();
    return $variables;
}


// list of directories to create
function create_dirs($add = null)
{
    static $dirs = array();
    if (!is_null($add)) {
        $dirs[] = $add;
        commands(sprintf('create_dir("%s");', $add));
    }
    return $dirs;
}

// keep a collection of the commands that need to be executed to install
function commands($add = null, $prepend = false)
{
    static $commands = '';
    if (!is_null($add)) {
        if (is_array($add)) {
            $add = implode("\n", $add);
        }
        if ($prepend) {
            $commands = $add . "\n" .$commands;
        } else {
            $commands .= "\n" . $add;
        }
    }
    return "<?php\n$commands";
}

// list of vendor directories
function vendor_dirs($add = null)
{
    static $dirs = array();
    if (!is_null($add)) {
        $dirs[] = $add;
    }
    return $dirs;
}


//----------------------------------------------------------------------------//
//-- ask questions that will have side effects                              --//
//----------------------------------------------------------------------------//


// ask if a directory should be created, log it in the commands list if so.
// does nothing if the directory already there
function ask_createdir($dir, $must_be_writable = false)
{
    $nope = function ($msg) use ($dir) {
        printf('--' . $msg . "\n", $dir);
        return false;
    };
    $yup = function () use ($dir) {
        create_dirs($dir);
        return true;
    };
    if (in_array($dir, create_dirs())) {
        return true;
    }
    if (file_exists($dir)) {
        if (!is_dir($dir)) return $nope('"%s" exists and is not a directory');
        if ($must_be_writable && !is_writable($dir)) return $nope('"%s" is not writable');
        return true;
    }
    if (!preg_match('#^/[/A-Za-z0-9\-_]+$#', $dir)) {
        return $nope('"%s" is not a valid directory name (abspath required)');
    }
    if (!ask_createdir(dirname($dir), true)) {
        return false;
    }
    $question = sprintf('Directory "%s" does not exist. Create it?', $dir);
    return cli_ask_bool($question, true) ? $yup() : false;
}

// ask for a directory to use, tries to create if it does not exist
function ask_dir($question, $default = null, $must_be_writable = false)
{
    return cli_ask(
        $question,
        $default,
        null,
        function ($answer) use ($must_be_writable) {
            if ($answer != '/' && substr($answer, -1) == '/') {
                $answer = substr($answer, 0, -1);
            }
            if (ask_createdir($answer, $must_be_writable)) {
                return $answer;
            }
        }
    );
}

// ask for a path to a file, tries to create the directory it's in
function ask_filepath($question, $base_dir, $default)
{
    $validate = function ($a) use ($base_dir) {
        if (strpos($a, '/') !== 0) $a = '/' . $a;
        $a = str_replace('/./', '/', $a);
        if (strpos($a, '/') === 0) $a = substr($a, 1);
        if (strpos($a, '/../') !== false) {
            echo " -- can't traverse directories ('/../')\n";
            return null;
        }
        $basename = basename($a);
        $dirname = dirname($a);
        if (!preg_match('/^[a-z\-]+\.php$/', $basename)) {
            echo " -- invalid filename $basename \n";
            return null;
        }
        if ($dirname && $dirname != '/' && $dirname != '.') {
            if (ask_createdir($base_dir . '/' . $dirname)) {
                return $dirname . '/' . $basename;
            }
            return null;
        }
        return $basename;
    };
    $question .= " (relative to $base_dir)";
    return cli_ask($question, $default, null, $validate);

}

function ask_user($label, $path)
{
    $find = function ($p) {
        $user = null;
        do {
            if (file_exists($p)) {
                $user = posix_getpwuid(fileowner($p))['name'];
            }
            $p = dirname($p);
        } while (!$user && $p && $p != '/');
        return $user;
    };
    $user = $find($path);
    return cli_ask($label, $user, null, function ($a) {
        if (posix_getpwnam($a) === false) {
            return cli_err("could not find that user");
        }
        return $a;
    });
}


//----------------------------------------------------------------------------//
//-- composed functions                                                     --//
//----------------------------------------------------------------------------//

// add a vendor include path, creating it if it does not exist
function add_vendor_directory()
{
    static $count = 0;
    $count += 1;

    $vendor_dir = dirname(variables()['docroot']) . '/vendor-' . $count;
    $vendor_dir = ask_dir("Vendor path", $vendor_dir, true);

    $create_subdir = function ($subdir) use ($vendor_dir) {
        if (is_dir($vendor_dir . '/' . $subdir)) {
            return true;
        }
        if (cli_ask_bool("Create subdir {$subdir}/", true)) {
            create_dirs($vendor_dir . '/' . $subdir);
            return true;
        }
        return false;
    };

    if ($create_subdir('config')) {
        if (cli_ask_bool('Copy default configs', true)) {
            $confdir = realpath(__DIR__ . '/../config');
            foreach (scandir($confdir) as $conf) {
                if (is_file($confdir . '/' . $conf)) {
                    commands(sprintf(
                        "cpy(\n  '%s',\n  '%s'\n);",
                        $confdir . '/' . $conf,
                        $vendor_dir . '/config/' . $conf
                    ));
                }
            }
        }
    }
    $create_subdir('plugins');
    $create_subdir('modules');
    return $vendor_dir;
}

function add_vendor_phar()
{
    return cli_ask('Path to phar', null, null, function ($a) {
        if (!is_file($a)) {
            return cli_err('path is not a regular file');
        }
        if (pathinfo($a, PATHINFO_EXTENSION) !== 'phar') {
            return cli_err('file is not a phar archive');
        }
        return 'phar://' . $a;
    });
}


function functions($user)
{
    commands([
        sprintf('function create_dir($path, $user = "%s") {', $user),
        '  mkdir($path);',
        '  chown($path, $user);',
        '}',
        sprintf('function cpy($src, $dest, $user = "%s") {', $user),
        '  copy($src, $dest);',
        '  chown($dest, $user);',
        '}',
        sprintf('function put($dest, $content, $user = "%s") {', $user),
        '  file_put_contents($dest, $content);',
        '  chown($dest, $user);',
        '}',
        'function get($dest) {',
        '  return file_exists($dest) ? file_get_contents($dest) : "";',
        '}',
    ], true);
}

function create_front_controller($which, $data_directory, $vendor_dirs)
{
    
    $front_controller = file_get_contents(__DIR__ . '/../' . $which);
    $var_block = "\$include_path = %s;\n\$data_directory = %s;\n\$vendor_dirs = %s;";

    $app_path = dirname(get_included_filepath(__DIR__));
    $i = strpos($app_path, 'phar://') === 0
       ? sprintf('"%s/__application_phar__" . PATH_SEPARATOR . "%s"', $data_directory, $app_path)
       : sprintf('"%s"', $app_path)
       ;
    $d = sprintf('"%s"', $data_directory);
    $v = var_export($vendor_dirs, true);
    $var_block = sprintf($var_block, $i, $d, $v);
    $fc = preg_replace('/#[^#]+#/', '##', $front_controller);
    $fc = str_replace('##', $var_block, $fc);
    return addcslashes($fc, "'");
}

function create_htaccess($front_controller_path)
{
    $htaccess = file_get_contents(__DIR__ . '/../htaccess.txt');
    if ($front_controller_path == 'index.php') {
        return $htaccess;
    }
    $htaccess = str_replace('index.php', $front_controller_path, $htaccess);
    $front_controller_dir = dirname($front_controller_path);
    if ($front_controller_dir == '.') {
        return $htaccess;
    }
    $htaccess = str_replace('(.*)', $front_controller_dir . '/(.*)', $htaccess);
    return addcslashes($htaccess, "'");
}

//----------------------------------------------------------------------------//
//-- run the installer                                                      --//
//----------------------------------------------------------------------------//


commands([
    'if (posix_geteuid() !== 0) {',
    '  die("Please run this script as root");',
    '}'
]);

cli_title('Welcome to the VSAC CLI Installer', '#');

$write_to = getcwd() . '/install.php';

if (!is_writable(dirname($write_to))) {
    cli_say(sprintf('Cannot run, "%s" is not writable', dirname($write_to)));
    die();
}


if (!cli_ask_bool("Install script will be saved at {$write_to}. That OK?", true)) {
    die();
}
if (file_exists($write_to)) {
    if (!cli_ask_bool(sprintf('File "%s" already exists, delete it?', $write_to), false)) {
        die();
    }
    unlink($write_to);
}



cli_section('Web environment', function () {
    $vars = &variables();
    $vars['docroot'] = ask_dir("Webserver's document root", getcwd(), true);
    $user = ask_user('Web server user', $vars['docroot']);
    functions($user);
});



cli_section('Installing base application', function () {
    $vars = &variables();
    $dr = $vars['docroot'];
    $q = 'Location of web front controller';
    $vars['web_controller'] = ask_filepath($q, $dr, './index.php');
    $q = 'Install .htaccess for short URLs';
    $vars['htaccess'] = cli_ask_bool($q, true);
    $q = 'Location of cli front controller';
    $vars['cli_controller'] = ask_filepath($q, dirname($dr), './cli.php');
    $q = 'Directory for application data';
    $vars['data'] = ask_dir($q, dirname($dr) . '/data', true);
});

cli_section('Installing customizations', function () {
    $vars = &variables();
    while (cli_ask_bool('Add a vendor include directory', true)) {
        vendor_dirs(add_vendor_directory());
    }
    while (cli_ask_bool('Add a vendor include phar', true)) {
        vendor_dirs(add_vendor_phar());
    }
});

$vars = variables();

$cli_controller = create_front_controller('cli.php', $vars['data'], vendor_dirs());
commands(sprintf(
    "put(\n  '%s',\n  '%s'\n);",
    dirname($vars['docroot']) . '/' . $vars['cli_controller'],
    $cli_controller
));

$web_controller = create_front_controller('index.php', $vars['data'], vendor_dirs());
commands(sprintf(
    "put(\n  '%s',\n  '%s'\n);",
    $vars['docroot'] . '/' . $vars['web_controller'],
    $web_controller
));

$htaccess = create_htaccess($vars['web_controller']);
$htpath = $vars['docroot'] . '/.htaccess';
if ($vars['htaccess']) commands([
    sprintf('$htaccess = get("%s");', $htpath),
    sprintf('$htaccess .= "%s";', $htaccess),
    sprintf('put("%s", $htaccess);', $htpath)
]);


$visit = dirname($vars['web_controller']);
$visit = $visit == '.' ? 'syscheck.php' : $visit . '/syscheck.php';

commands("echo \"Install script ran.\\n\";");
commands("echo \"Go to http://<server_name>/$visit to see if it works.\\n\";");
$users = framework_config('users', array());
$user = array_keys($users)[0];
$passphrase = $users[$user];
commands("echo \"The default user is '$user'\\n\";");
commands("echo \"The default passphrase is '$passphrase'\\n\";");

file_put_contents($write_to, commands());
cli_say("The install script is located at {$write_to}. To run it, do:");
cli_say(" $ sudo php $write_to");



