<?php
use Colors\Color;
use Symfony\Component\Yaml\Yaml;


// Module API

function read_config($path='run.yml') {

    // Bad file
    if (!is_file($path)) {
        $message = "No '{$path}' found";
        print_message('general', ['message' => $message]);
        exit(1);
    }

    // Read documents
    $documents = [null, null];
    $contents = explode("---\n", file_get_contents($path));
    // PHP Yaml doesn't support set type like {value}
    $contents[0] = preg_replace('/{([\w$]+)}/', '{$1: null}', $contents[0]);
    $documents[0] = Yaml::parse($contents[0]);
    $documents[1] = (count($contents) > 1) ? Yaml::parse($contents[1]) : null;

    // Get config
    $comments = [];
    $config = ['run' => []];
    $rawConfig = $documents[0];
    foreach (explode("\n", $contents[0]) as $line) {

        // Comment begin
        if (substr($line, 0, 2) === '# ') {
            array_push($comments, str_replace('# ', '', $line));
            continue;
        }

        // Add config item
        foreach ($rawConfig as $key => $value) {
            if (substr($line, 0, strlen($key)) === $key) {
                array_push(
                    $config['run'],
                    [$key => ['code' => $value, 'desc' => join('\n', $comments)]]
                );
            }
        }

        // Commend end
        if (substr($line, 0, 2) !== '# ') {
            $comments = [];
        }
    }

    // Get options
    $options = [];
    if (count($documents) > 1) {
        $options = $documents[1] || [];
    }

    return [$config, $options];
}


function print_message($type, $data) {
    $colorize = new Color();
    $text = $colorize($data['message'])->bold();
    print("${text}\n");
}


function iter_colors() {
    $COLORS = [
        'cyan',
        'yellow',
        'green',
        'magenta',
        'red',
        'blue',
        'intense_cyan',
        'intense_yellow',
        'intense_green',
        'intense_magenta',
        'intense_red',
        'intense_blue',
    ];
    while (true) {
        foreach ($COLORS as $color) {
            yield $color;
        }
    }
}
