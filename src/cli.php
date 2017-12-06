<?php
require_once('vendor/autoload.php');

// Main program

# Arguments
$argv = array_slice($argv);

# Path argument
$path = 'run.yml';
if (in_array('--run-path', $argv)) {
    $index = array_search('--run-path', $argv);
    $path = $argv[$index + 1];
    array_splice($argv, $index, 2);
}

# Complete argument
$complete = False;
if (in_array('--run-complete', $argv)) {
    $argv = array_diff($argv, ['--run-complete']);
    $complete = True;
}

# Prepare
[$config, $options] = read_config($path);
$task = new Task($config, $options);

# Complete
if ($complete) {
    $task->complete(argv);
    exit();
}

# Run
task->run(argv);
