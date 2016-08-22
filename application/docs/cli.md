#Writing CLI Commands

**Read First:** [Application Overview](./overview.md)

**See Also:** [Examples Extension](https://github.com/EurActiv/VSAC-Examples)

**Important:** All PHP files should be in the `VSAC` namespace.

VSAC includes a **very rudimentary** command line interface to allow admins to automate some tasks.

Command line commands belong in the `cli` directory. The file name will become the name of the command. For example, your vendor-specific directory might have the structure:

    /path/to/vendor/stuff/
    `-- cli/
        `-- my-command.php      # The file containing your command logic

In the file `my-command.php` you might have something like this:

    <?php
    namespace VSAC;
    cli_title('Hello World!');


Then, when you run the command line controller, you'll see your command in the menu:

    user@machine:/path/to/application $ php cli.php

    ###########################################################################
    ## VSAM CLI Console                                                      ##
    ###########################################################################
    Available commands:
     console      create-phar  install      my-command
    Please select (type or use up/down arrows): my-command

    ---------------------------------------------------------------------------
    -- Hello World!                                                          --
    ---------------------------------------------------------------------------

    user@machine:/path/to/application $

For a complete list of functions available in the CLI interface, see the [cli module](../modules/inc-cli.php).


