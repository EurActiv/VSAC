<?php

namespace VSAC;

use Phar;
use FilesystemIterator as FSI;

use_module('filesystem');

$directory = cli_ask(
    'Make a phar of:',
    realpath(__DIR__ . '/../'),
    null,
    function ($a) {
        if (!($a = realpath($a))) {
            return cli_err("Directory does not exist");
        }
        if (!is_dir($a)) {
            return cli_err("Path is not a directory");
        }
        return $a;
    }
);

$phar_name = basename($directory) . '.phar';
$phar_path = dirname($directory) . '/' . $phar_name;

if (!cli_ask_bool("The phar will be saved to '$phar_path'. That OK?", true)) {
    exit;
}
if (file_exists($phar_path)) {
    if (!cli_ask_bool("Phar archive '$phar_path' already exists. OK to delete it?", false)) {
        exit;
    }
    unlink($phar_path);
}

$phar = new Phar(
    $phar_path,
    FSI::CURRENT_AS_FILEINFO|FSI::KEY_AS_FILENAME,
    $phar_name
);

$phar->startBuffering();

$phar->buildFromDirectory($directory);

if ($phar_name == 'application.phar') {
    $phar->setStub($phar->createDefaultStub('cli.php', 'index.php'));
} elseif (file_exists($directory . '/cli/install.php')) {
    $phar->setStub($phar->createDefaultStub('cli/install.php'));
} else {
    cli_say("cli/install.php not found. Skipping stub generation");
}

$phar->stopBuffering();


cli_say('Phar saved.');

