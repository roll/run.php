<?php
require_once(dirname(__FILE__) . '/plan.php');
require_once(dirname(__FILE__) . '/command.php');


// Module API

class Task {

    // Public

    function __construct($descriptor, $options, $parent=null, $parent_type=null, $quiet=false) {
        $this->_parent = $parent;

        // Prepare
        $desc = $parent ? '' : 'General run description';
        foreach(array_slice($descriptor, 0, 1) as $key => $value) {
            $name = $key;
            $code = $value;
        }
        if (is_array($code) && array_key_exists('code', $code)) {
            $desc = $code['desc'];
            $code = $code['code'];
        }

        // Optional
        $optional = false;
        if (substr($name, 0, 1) === '/') {
            $name = substr($name, 1);
            $optional = true;
        }

        // Quiet
        if (strpos($name, '!') !== false) {
            $name = str_replace($name, '!', '');
            $quiet = true;
        }

        // Directive type
        $type = 'directive';

        // Variable type
        if ($name && $name === strtoupper($name)) {
            $type = 'variable';
            $desc = 'Prints the variable';
        }

        // Sequence type
        $childs = [];
        if (is_array($code)) {
            $type = 'sequence';

            // Parent type
            if (in_array($parent_type, ['parallel', 'multiplex'])) {
                $type = $parent_type;
            }

            // Parallel type
            if (substr($name, 0, 1) === '(' && substr($name, -1, 1) === ')') {
                if (count($this->parents()) >= 2) {
                    $message = 'Subtask descriptions and execution control not supported';
                    print_message('general', ['message' => message]);
                    exit(1);
                }
                $name = substr($name, 1, -1);
                $type = 'parallel';
            }

            // Multiplex type
            if (substr($name, 0, 1) === '(' && substr($name, -1, 1) === ')') {
                $name = substr($name, 1, -1);
                $type = 'multiplex';
            }

            // Create childs
            foreach ($code as $descriptor) {
                if (!is_array($descriptor)) $descriptor = ['' => $descriptor];
                array_push($childs, new Task($descriptor, $options, $this, $type, $quiet));
            }

            // Reset code
            $code = null;

        }

        // Set attributes
        $this->_name = $name;
        $this->_code = $code;
        $this->_type = $type;
        $this->_desc = $desc;
        $this->_quiet = $quiet;
        $this->_childs = $childs;
        $this->_options = $options;
        $this->_optional = $optional;

    }

    function name() {
        return $this->_name;
    }

    function code() {
        return $this->_code;
    }

    function type() {
        return $this->_type;
    }

    function desc() {
        return $this->_desc;
    }

    function parent() {
        return $this->_parent;
    }

    function quiet() {
        return $this->_quiet;
    }

    function childs() {
        return $this->_childs;
    }

    function options() {
        return $this->_options;
    }

    function optional() {
        return $this->_optional;
    }

    function composite() {
        return !!count($this->_childs);
    }

    function isRoot() {
        return !$this->_parent;
    }

    function parents() {
        $parents = [];
        $task = $this;
        while (true) {
            if (!$task->parent()) break;
            array_push($parents, $task->parent());
            $task = $task->parent();
        }
        return array_reverse($parents);
    }

    function qualifiedName() {
        $names = [];
        foreach (array_merge($this->parents(), [$this]) as $parent) {
            if ($parent->name()) array_push($names, $parent->name());
        }
        return join(' ', $names);
    }

    function flattenSetupTasks() {
        $tasks = [];
        foreach ($this->parents() as $parent) {
            foreach ($parent->childs() as $task) {
                if ($task === $this) break;
                if (in_array($task, $this->parents())) break;
                if ($task->type() === 'variable') {
                    array_push($tasks, $task);
                }
            }
        }
        return $tasks;
    }

    function flattenGeneralTasks() {
        $tasks = [];
        foreach ($this->composite() ? $this->childs() : [$this] as $task) {
            if ($task->composite()) {
                $tasks = array_merge($tasks, $task->flattenGeneralTasks());
                continue;
            }
            array_push($tasks, $task);
        }
        return $tasks;
    }

    function flattenChildsWithComposite() {
        $tasks = [];
        foreach ($this->childs() as $task) {
            array_push($tasks, $task);
            if ($task->composite()) {
                $tasks = array_merge($tasks, $task->flattenChildsWithComposite());
            }
        }
        return $tasks;
    }

    function findChildTasksByName($name) {
        $tasks = [];
        foreach ($this->flattenGeneralTasks() as $task) {
            if ($task->name() === $name) {
                array_push($tasks, $task);
            }
        }
        return $tasks;
    }

    function findChildTasksByAbbrevation($abbrevation) {
        $letter = $abbrevation[0];
        $abbrev = substr($abbrevation, 1);
        foreach ($this->childs() as $task) {
            if (substr($task->name(), 0, 1) === $letter) {
                if ($abbrev) {
                    return $task->findChildTasksByAbbrevation($abbrev);
                }
                return $task;
            }
        }
        return null;
    }

    function run($argv) {
        $commands = [];

        // Delegate by name
        if ($argv) {
            foreach ($this->childs() as $task) {
                if ($task->name() === $argv[0]) {
                    return $task->run(array_slice($argv, 1));
                }
            }
        }

        // Delegate by abbrevation
        if ($argv) {
            if ($this->isRoot()) {
                $task = $this->findChildTasksByAbbrevation($argv[0]);
                if ($task) {
                    return $task->run(array_slice($argv, 1));
                }
            }
        }

        // Root task
        if ($this->isRoot()) {
            if ($argv && $argv != ['?']) {
                $message = `Task "${argv[0]}" not found`;
                print_message('general', ['message' => $message]);
                exit(1);
            }
            _print_help($this, $this);
            return true;
        }

        // Prepare filters
        $argvCopy = $argv;
        $filters = ['pick' => [], 'enable' => [], 'disable' => []];
        $iterator = [['pick', '='], ['enable', '+'], ['disable', '-']];
        foreach ($iterator as [$name, $prefix]) {
            foreach ($argvCopy as $arg) {
                if (substr($arg, 0, 1) === $prefix) {
                    $childs = $this->findChildTasksByName(substr($arg, 1));
                    if ($childs) {
                        $filters[$name] = array_merge($filters[$name], $childs);
                        $argv = array_diff($argv, [$arg]);
                    }
                }
            }
        }

        // Detect help
        $help = false;
        if ($argv === ['?']) {
            array_pop($argv);
            $help = true;
        }

        // Collect setup commands
        foreach ($this->flattenSetupTasks() as $task) {
            $command = new Command($task->qualifiedName(), $task->code(), $task->name());
            array_push($commands, $command);
        }

        // Collect general commands
        foreach ($this->flattenGeneralTasks() as $task) {
            if ($task !== $this && !in_array($task, $filters['pick'])) {
                if ($task->optional() && !in_array($task, $filters['enable'])) continue;
                if (in_array($task, $filters['disable'])) continue;
                if (!empty($filters['pick'])) continue;
            }
            $variable = $task->type() === 'variable' ? $task->name() : null;
            $command = new Command($task->qualifiedName(), $task->code(), $variable);
            array_push($commands, $command);
        }

        // Normalize arguments
        $argumentsIndex = null;
        foreach ($commands as $index => $command){
            if (strpos($command->code(), '$RUNARGS') === false) {
                if (!$command->variable()) {
                    $argumentsIndex = $index;
                    continue;
                }
            }
            if ($argumentsIndex !== null) {
                $command->code(str_replace($command->code(), '$RUNARGS', ''));
            }
        }

        // Provide arguments
        if ($argumentsIndex === null) {
            foreach ($commands as $index => $command) {
                if (!$command->variable()) {
                    $command->code("{$command->code()} $RUNARGS");
                    break;
                }
            }
        }

        // Create plan
        $plan = new Plan($commands, $this->type());

        // Show help
        if ($help) {
            $task = count($this->parents()) < 2 ? $this : $this->parents()[1];
            _print_help($task, $this, $plan, $filters);
            exit();
        }

        // Execute commands
        $plan->execute($argv, $this->quiet(), $this->options()['faketty']);

        return true;
    }

    function complete($argv) {

        // Delegate by name
        if ($argv) {
            foreach ($this->childs() as $task) {
                if ($task->name() === $argv[0]) {
                    return $task->complete(array_slice($argv, 1));
                }
            }
        }

        // Autocomplete
        foreach ($this->childs() as $child) {
            if ($child->name()) {
                print($child->name() . PHP_EOL);
            }
        }

        return true;
    }

}


// Internal

function _print_help($task, $selected_task, $plan=null, $filters=null) {

    // General
    print_message('general', ['message' => $task->qualifiedName()]);
    print_message('general', ['message' => "\n---"]);
    if (!$task->desc()) {
        print_message('general', ['message' => "\nDescription\n"]);
        print($task->desc());
    }

    // Vars
    $header = false;
    foreach (array_merge([$task], $task->flattenChildsWithComposite()) as $child) {
        if ($child->type() === 'variable') {
            if (!$header) {
                print_message('general', ['message' => "\nVars\n"]);
                $header = true;
            }
            print($child->qualifiedName() . PHP_EOL);
        }
    }

    // Tasks
    $header = false;
    foreach (array_merge([$task], $task->flattenChildsWithComposite()) as $child) {
        if (!$child->name()) continue;
        if ($child->type() === 'variable') continue;
        if (!$header) {
            print_message('general', ['message' => "\nTasks\n"]);
            $header = true;
        }
        $message = $child->qualifiedName();
        if ($child->optional()) {
            $message .= ' (optional)';
        }
        if ($filters) {
            if (in_array($child, $filters['pick'])) {
                $message .= ' (picked)';
            }
            if (in_array($child, $filters['enable'])) {
                $message .= ' (enabled)';
            }
            if (in_array($child, $filters['disable'])) {
                $message .= ' (disabled)';
            }
        }
        if ($child === $selected_task) {
            $message .= ' (selected)';
            print_message('general', ['message' => $message]);
        } else {
            print($message . PHP_EOL);
        }
    }

    // Execution plan
    if ($plan) {
        print_message('general', ['message' => "\nExecution Plan\n"]);
        print($plan->explain() . PHP_EOL);
    }

}
