<?php


// Module API

function execute_sync($commands, &$environ, $quiet) {
    foreach($commands as $command) {

        # Log process
        if (!$command->variable() && !$quiet) {
            print("[run] Launched '{$command->code()}'\n");
        }

        # Create process
        $descriptorspec = [];
        if ($command->variable()) {
            $descriptorspec = [
               1 => array("pipe", "w"),
               2 => array("pipe", "w"),
            ];
        }
        $process = proc_open($command->code(), $descriptorspec, $pipes, null, $environ);

        # Wait process
        if ($pipes) {
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
        }
        $return_value = proc_close($process);
        if ($command->variable()) {
            $environ[$command->variable()] = trim($output);
        }

        # Failed process
        if ($return_value !== 0) {
            $message = "[run] Command '{$command->code()}' has failed";
            print_message('general', ['message' => $message]);
            exit(1);
        }

    }
}


function execute_async($commands, &$environ, $multiplex=false, $quiet=false, $faketty=false) {

    // Launch processes
    $processes = [];
    $color_iterator = iter_colors();
    foreach($commands as $command) {

        # Log process
        if (!$quiet) {
            print("[run] Launched '{$command->code()}'\n");
        }

        # Create process
        $color = $color_iterator->next();
        $descriptorspec = [1 => array("pipe", "w"), 2 => array("pipe", "w")];
        $process = proc_open($command->code(), $descriptorspec, $pipes, null, $environ);
        array_push($processes, [$command, $process, $pipes, $color]);

    }

    // Wait processes
    while ($processes) {
        foreach($processes as $index => $item) {
            [$command, $process, $pipes, $color] = $item;

            # Process output
            if ($multiplex || $index === 0) {
                $read = [$pipes[1]];
                $write  = null;
                $except = null;
                $ready = stream_select($read, $write, $except, 0.01);
                if ($ready) {
                    $line = stream_get_contents($pipes[1]);
                    _print_line($line, $command->name(), $color, $multiplex, $quiet);
                }
            }

            # Process finish
            $state = proc_get_status($process);
            if (!$state['running']) {
                if ($state['exitcode'] !== 0) {
                    $output = stream_get_contents($pipes[1]);
                    fclose($pipes[1]);
                    _print_line($line, $command->name(), $color, $multiplex, $quiet);
                    $message = "[run] Command '{$command->code()}' has failed";
                    print_message('general', ['message' => $message]);
                    exit(1);
                }
                if ($index === 0) {
                    array_splice($processes, $index, 1);
                    break;
                }
            }

        }
    }

}


# Internal

function _print_line($line, $name, $color, $multiplex, $quiet) {
    if ($multiplex && !$quiet) {
        print("{$name} | ");
    }
    print($line);
}
