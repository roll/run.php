<?php


// Module API

class Plan {

    // Public

    function __construct($commands, $mode) {
        $this->_commands = $commands;
        $this->_mode = $mode;
    }

    function explain() {

        // Explain
        $lines = [];
        $plain = true;
        foreach ($this->_commands as $command) {
            if (in_array($this->_mode, ['sequence', 'parallel', 'multiplex'])) {
                if (!$command->variable()) {
                    $mode = strtoupper($this->_mode);
                    if ($plain) $lines.push("[{$mode}]");
                    $plain = false;
                }
            }
            $code = $command->code();
            if ($command->variable()) $code = "{$command->variable()}='{$command->code()}'";
            $indent = str_repeat(' ', $plain ? 0 : 4);
            array_push($lines, "{$indent} {$code}");
        }

        return join("\n", $lines);

    }

    function execute($argv, $quiet, $faketty) {
        $commands = $this->_commands;

        // Variables
        $varnames = [];
        $variables = [];
        $commands_copy = $commands;
        foreach ($commands_copy as $command) {
            if ($command->variable()) {
                array_push($variables, $command);
                array_push($varnames, $command->variable());
                $commands = array_diff($commands, [$command]);
            }
            executeSync($variables, $_ENV, $quiet);
            if (!count($commands)) {
                print($_ENV[$varnames[count($varnames) - 1]]);
                return;
            }
        }

        // Update environ
        $_ENV['RUNARGS'] = join(' ', $argv);
        $runvars = $_ENV['RUNVARS'];
        if ($runvars) {
            $dotenv = new Dotenv\Dotenv('.', $runvars);
            $dotenv->load();
        }

        // Log prepared
        $start = microtime(true);
        if (!quiet) {
            $items = [];
            foreach (array_merge($varnames, ['RUNARGS']) as $name) {
                array_push($items, "{$name}={$_ENV[$name]}");
            }
            $items = join('; ', $items);
            print("[run] Prepared '{$items}'");
        }

        // Directive
        if ($this->_mode === 'directive') {
            executeSync($commands, $_ENV, $quiet);

            // Sequence
        } else if ($this->_mode === 'sequence') {
            executeSync($commands, $_ENV, $quiet);

            // Parallel
        } else if ($this->_mode === 'parallel') {
            executeAsync($commands, $_ENV, $quiet, $faketty);

            // Multiplex
        } else if ($this->_mode === 'multiplex') {
            executeAsync($commands, $_ENV, true, $quiet, $faketty);
        }

        // Log finished
        $stop = microtime(true);
        if (!quiet) {
            $time = $start - $stop;
            print("[run] Finished in {$time} seconds");
        }

    }
}
