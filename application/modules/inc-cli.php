<?php

/**
 * This file contains some basic functions related to getting user input
 * on a command line and printing responses. Right now it's a
 * specifically a linux/bash thing, but it might be made driver friendly
 * if there's demand.
 */

namespace VSAC;

//----------------------------------------------------------------------------//
//-- Framework required functions                                           --//
//----------------------------------------------------------------------------//

/** @see example_module_dependencies() */
function cli_depends()
{
    return array();
}

/** @see example_module_config_items() */
function cli_config_items()
{
    return array();
}

/** @see example_module_sysconfig() */
function cli_sysconfig()
{
    if (strpos(strtolower(PHP_OS), 'win') === 0) {
        return 'OS must be a *nix';
    }

    if (!function_exists('readline')) {
        'Function readline() is not available';
    }

    return true;
}

/** @see example_module_test() */
function cli_test()
{
    return true;
}


//----------------------------------------------------------------------------//
//-- Public API                                                             --//
//----------------------------------------------------------------------------//

/**
 * print a message, wordwrapped
 *
 * @param string $msg the message to print
 * @param string $prefix prefix with this character, and indent subsequent
 * lines. Mostly for making bullet lists.
 *
 * @return void
 */
function cli_say($msg, $prefix = '')
{
    $msg = trim(preg_replace('/\s+/', ' ', $msg));
    $msg = wordwrap($msg, 75 - strlen($prefix));
    $msg = str_replace("\n", "\n" . str_repeat(' ', strlen($prefix)), $msg);
    echo $prefix, $msg, "\n";
    return null;
}

/**
 * Print a title
 * 
 * @param string $msg the title text
 * @param string $pad add a box around the title with this character
 *
 * @return void
 */
function cli_title($msg, $pad = '-')
{
    $msg = explode("\n", wordwrap($msg, 69));
    $msg = array_map(function ($line) use ($pad) {
        return str_pad($pad.$pad.' ' . $line, 73) . $pad.$pad;
    }, $msg);
    $msg = implode("\n", $msg);
    echo "\n";
    cli_space($pad);
    echo $msg, "\n";
    cli_space($pad);
}

/**
 * Print a blank line, or a line filled with a given character
 *
 * @pram string $char the character to fill the line with
 *
 * @return void
 */
function cli_space($char = ' ')
{
    echo str_repeat($char, 75), "\n";
}


/**
 * Standard format for saying something went wrong, without exiting.
 * Returns null for chaining with return in input validators in cli_ask
 *
 * @param string $msg the error message
 *
 * @return null
 */
function cli_err($msg)
{
    cli_say($msg, ' -- ');
    return null;
}


/**
 * Ask a question, get an answer
 *
 * @param string $question what to ask
 * @param string $default the default response
 * @param string $hint a hint to print at the end of the question,
 * will be the contents of $default if not set
 * @param callable $validate_cb a response validator. Return null to
 * indicate that the response is invalid and repeat the question, or the
 * validated/sanitized value if it passed.
 *
 * @return mixed
 */
function cli_ask($question, $default = null, $hint = null, callable $validate_cb = null)
{
    if (!$hint && $default) {
        $hint = $default;
    }
    if ($hint) {
        $question .= " [$hint]";
    }
    $question .= ': ';
    $question = wordwrap($question, 75);
    while (true) {
        $answer = readline($question);
        if (!$answer && !is_null($default)) $answer = $default;
        if ($validate_cb) {
            $answer = call_user_func($validate_cb, $answer);
            if (!is_null($answer)) return $answer;
        } else {
            return $answer;
        }
    }
}

/**
 * Ask a yes/no question, with the answer converted to a bool
 *
 * @param string $quetion
 * @param bool $default
 *
 * @return bool
 */
function cli_ask_bool($question, $default = null)
{
    if (is_null($default)) {
        $hint = 'y/n';
    } else {
        $hint = $default ? 'Y/n' : 'y/N';
    }
    return cli_ask($question, $default, $hint, function ($answer) {
        if (is_bool($answer)) return $answer;
        $answer = substr(strtolower($answer), 0, 1);
        if ($answer == 'y') return true;
        if ($answer == 'n') return false;
        return cli_err("Please answer 'yes' or 'no'.");
    });
}

/**
 * Print out a question section, with proper spacing and such
 *
 * @param string $title the section title
 * @param callable $content_callback the callback to generate the content;
 * will return the return value of this function
 *
 * @return mixed the result of $content_callback
 */
function cli_section($title, callable $content_callback)
{
    cli_space();
    cli_title($title, '-');
    $return = call_user_func($content_callback);
    cli_space();
    return $return;
}


/**
 * Ask a question, get one of an enumurated set of answers.
 *
 * @param string $question the question to ask
 * @param array $options an array of answers, which should be strings
 * @param string $default the default answer
 *
 * @return string
 */
function cli_ask_select($question, array $options, $default = null)
{
    readline_clear_history();
    $len = max(array_map('strlen', $options));
    $pos = 0;
    echo $question, ":\n";
    foreach($options as $option) {
        readline_add_history($option);
        if (($pos + $len + 2) > 75) {
            echo "\n";
            $pos = 0;
        }
        echo ' ', str_pad($option, $len), ' ';
        $pos += $len;
    }
    echo "\n";
    $question = 'Please select (type or use up/down arrows)';
    return cli_ask($question, $default, null, function ($a) use ($options) {
        return in_array($a, $options) ? $a : cli_err('Invalid option');
    });
}

/**
 * Select the cli file to run. Generally should be called by the cli
 * front controller.
 *
 * @return void;
 */
function cli_dispatch()
{
    global $argv;
    bootstrap_plugin('_framework');
    cli_title('VSAM CLI Console', '#');
    $options = scan_include_dirs('cli');
    $options = array_filter(array_map(function ($f) {
        if (strpos($f, '.') === 0) {
            return false;
        }
        if (pathinfo($f, PATHINFO_EXTENSION) !== 'php') {
            return false;
        }
        return pathinfo($f, PATHINFO_FILENAME);
    }, $options));

    if (!empty($argv[1]) && in_array($argv[1], $options)) {
        $command = $argv[1];
    } else {
        $command = cli_ask_select('Available commands', $options);
    }
    require_once 'cli/' . $command . '.php';
}
