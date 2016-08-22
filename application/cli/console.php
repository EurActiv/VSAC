<?php

namespace VSAC;


cli_title('CLI Console');
cli_say('Type raw php into the prompt, using <enter> for new lines. Meta commands:');
cli_say(' -- "\\exec"  Execute the contents of the buffer');
cli_say(' -- "\\show"  Display the contents of the buffer');
cli_say(' -- "\\clear" Clear the buffer');
cli_say(' -- "\\quit"  Exit the console');
cli_space();

$buffer = array();
while(true) {
    $line = readline('> ');
    readline_add_history($line);
    switch($line) {
        case '\exec':
            $php = implode("\n", $buffer);
            $buffer = array();
            eval($php);
            echo "\n";
            break;
        case '\show':
            foreach($buffer as $l => $c) {
                echo str_pad($l, 3, '0', STR_PAD_LEFT), '. ', $c, "\n";
            }
            break;
        case '\clear':
            $buffer = array();
            break;
        case '\quit':
            die("\nGoodbye\n");
            break;
        default:
            $buffer[] = $line;
    }
}
