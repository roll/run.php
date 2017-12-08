<?php


// Module API

function executeSync($commands, $environ, $quiet) {
    foreach($commands as $command) {

        # Log process
        if (!$command->variable() && !$quiet) {
            print("[run] Launched '{$command->code()}'\n");
        }

        # Create process
        $descriptorspec = [];
        if ($command->variables()) {
            $descriptorspec = [
               1 => array("pipe", "w"),
               2 => array("pipe", "w"),
            ];
        }
        $process = proc_open($command->code(), $descriptorspec, $pipes, null, $environ);

        # Wait process
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $return_value = proc_close($process);
        if ($command->variables()) {
            $environ[$command->variable()] = $ouput;
        }

        # Failed process
        if ($return_value !== 0) {
            $message = "[run] Command '{$command->code()}' has failed";
            print_message('general', ['message' => $message]);
            exit(1);
        }

    }
}


function executeAsync($commands, $environ, $multiplex, $quiet, $faketty) {

}


# Internal

function _printLine($line, $name, $color, $multiplex, $quiet) {

}
