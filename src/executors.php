<?php


// Module API

function execute_sync($commands, $environ, $quiet) {
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
            $environ[$command->variable()] = $output;
        }

        # Failed process
        if ($return_value !== 0) {
            $message = "[run] Command '{$command->code()}' has failed";
            print_message('general', ['message' => $message]);
            exit(1);
        }

    }
}


function execute_async($commands, $environ, $multiplex, $quiet, $faketty) {

}


# Internal

function _print_line($line, $name, $color, $multiplex, $quiet) {

}
