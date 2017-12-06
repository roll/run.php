<?php

// Module API

class Task {

    // Public

    function __construct($descriptor, $options, $parent, $parent_type, $quiet) {
        $this->_parent = parent;

        // Prepare
        $desc = parent ? '' : 'General run description';
        foreach(array_slice($descriptor, 0, 1) as $key => $value) {
            $name = $key;
            $code = $value;
        }
        if (is_array($code)) {
            $desc = $code['desc'];
            $code = $code['code'];
        }

        // Optional
        $optional = false;
        if (substr($name, 0, 1) === '/') {
            $name = array_slice($name, 1);
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
        if ($name === strtoupper($name)) {
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
                $name = array_slice($name, 1, -1);
                $type = 'parallel';
            }

            // Multiplex type
            if (substr($name, 0, 1) === '(' && substr($name, -1, 1) === ')') {
                $name = array_slice($name, 1, -1);
                $type = 'multiplex';
            }

            // Create childs
            foreach ($code as $descriptor) {
                if (!is_array($descriptor)) $descriptor = ['' => $descriptor];
                array_push($childs, new Task($descriptor, $options, self, $type, $quiet));
            }

            // Reset code
            $code = null;

        }

        // Set attributes
        $this->_name = name;
        $this->_code = code;
        $this->_type = type;
        $this->_desc = desc;
        $this->_quiet = quiet;
        $this->_childs = childs;
        $this->_options = options;
        $this->_optional = optional;

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

    function is_root() {
        return !$this->_parent;
    }

    function parents() {
        $parents = [];
        $task = this;
        while (true) {
            if (!task.parent) break;
            array_push($parents, $task->parent());
            $task = $task->parent();
        }
        return array_reverse($parents);
    }

    function qualified_name() {
        $names = [];
        foreach (array_merge($this->parents(), [self]) as $parent) {
            if (parent.name) array_push($names, $parent->name());
        }
        return join(' ', $names);
    }

    function flatten_setup_tasks() {
        $tasks = [];
        foreach ($this->parents() as $parent) {
            foreach ($parent->childs as $task) {
                if ($task === $this) break;
                if (in_array($task, $this->parents())) break;
                if ($task->type() === 'variable') {
                    array_push($tasks, $task);
                }
            }
        }
        return tasks;
    }

    function flatten_general_tasks() {
        $tasks = [];
        foreach ($this.composite ? $this.childs : [$this] as $task) {
            if ($task.composite) {
                $tasks = array_merge($tasks, $task->flatten_general_tasks());
                continue;
            }
            array_push($tasks, $task);
        }
        return $tasks;
    }

    function flatten_childs_with_composite() {
        $tasks = [];
        foreach ($this.childs() as $task) {
            array_push($tasks, $task);
            if ($task->composite()) {
                $tasks = array_merge($tasks, $task->flatten_childs_with_composite());
            }
        }
        return tasks;
    }

    function find_child_tasks_by_name($name) {
        $tasks = [];
        foreach ($this->flatten_general_tasks() as $task) {
            if ($task->name() === $name) {
                array_push($tasks, $task);
            }
        }
        return tasks;
    }

    function find_child_task_by_abbrevation($abbrevation) {
        $letter = $abbrevation[0];
        $abbrev = array_slice($abbrevation, 1);
        foreach ($this->childs() as $task) {
            if (substr($task->name(), 0, 1) === $letter) {
                if ($abbrev) {
                    return $task->find_child_task_by_abbrevation($abbrev);
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
            foreach ($this->childs() as $child) {
                if ($task->name() === $argv[0]) {
                    return $task->complete(array_slice($argv, 1));
                }
            }
        }

        // Delegate by abbrevation
        if ($argv) {
            if ($this->is_root()) {
                $task = $this->find_child_task_by_abbrevation($argv[0]);
                if ($task) {
                    return $task->run(array_slice($argv, 1));
                }
            }
        }

        // Root task
        if ($this->is_root()) {
            if ($argv && $argv != ['?']) {
                $message = `Task "${argv[0]}" not found`;
                print_message('general', ['message' => $message]);
                exit(1);
            }
            print_help($this, $this);
            return true;
        }

        // Prepare filters
        $argvCopy = $argv;
        $filters = ['pick' => [], 'enable' => [], 'disable' => []];
        $iterator = [['pick', '='], ['enable', '+'], ['disable', '-']];
        foreach ($iterator as [$name, $prefix]) {
            foreach ($argvCopy as $arg) {
                if (substr($name, 0, 1) === $prefix) {
                    $childs = $this->find_child_tasks_by_name(str_slice($name, 1));
                    if ($childs) {
                        $filters[name] = array_merge($filters[name], $childs]);
                        $argv = array_diff($argv, [$arg]);
                    }
                }
            }
        }

        // Detect help
        $help = false;
        if ($argv === ['?'])) {
            array_pop($argv);
            $help = true;
        }

        // Collect setup commands
        foreach ($this->flatten_setup_tasks() as $task) {
            $command = new Command($task->qualified_name(), $task->code(), $task->name());
            array_push($commands, $command);
        }

        // Collect general commands
        foreach ($this->flatten_general_tasks() as $task) {
            if ($task !== $this && !in_array($filters['pick'], $task)) {
                if ($task->optional() && !in_array($filters['enable'], $task)) continue;
                if (in_array($filters['disable'], $task)) continue;
                if (!filters['pick']) continue;
            }
            $variable = $task->type() === 'variable' ? $task->name() : null;
            $command = new Command($task->qualified_name(), $task->code(), $variable);
            array_push($commands, $command);
        }

        // Normalize arguments
        $argumentsIndex = null
            foreach ($commands as $index => $command){
                if (strpos($command->code(), '$RUNARGS') === false) {
                    if (!$command->variable()) {
                        $argumentsIndex = $index;
                        continue;
                    }
                }
                if ($argumentsIndex !== null) {
                    $command->code(str_replace($command->code, '$RUNARGS', ''));
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
            $task = count($this->parents) < 2 ? $this : $this->parents()[1];
            print_help($task, $this, $plan, $filters);
            exit();
        }

        // Execute commands
        $plan->execute($argv, $this->quiet(), $this->options()['faketty']);

        return true;
    }

    function complete($argv) {

        // Delegate by name
        if ($argv) {
            foreach ($this->childs() as $child) {
                if ($task->name() === $argv[0]) {
                    return $task->complete(array_slice($argv, 1));
                }
            }
        }

        // Autocomplete
        foreach ($this->childs() as $child) {
            if ($child->name()) {
                print($child->name());
            }
        }

        return true;
    }

}


// Internal

function print_help($task, $selected_task, $plan, $filters) {

    // General
    print_message('general', ['message' => task.qualified_name]);
    print_message('general', ['message' => '\n---']);
    if (!$task->desc()) {
        print_message('general', ['message' => '\nDescription\n']);
        print($task->desc());
    }

    // Vars
    $header = false;
    for (array_merge([$task], $task->flatten_childs_with_composite()) as $child) {
        if ($child.type === 'variable') {
            if (!$header) {
                print_message('general', ['message': '\nVars\n']);
                header = true;
            }
            print($child->qualified_name());
        }
    }

    // Tasks
    $header = false;
    for (array_merge([$task], $task->flatten_childs_with_composite()) as $child) {
        if (!$child->name()) continue;
        if ($child->type() === 'variable') continue;
        if (!$header) {
            print_message('general', ['message' => '\nTasks\n']);
            $header = true;
        }
        $message = $child->qualified_name();
        if ($child->optional()) {
            $message += ' (optional)';
        }
        if ($filters) {
            if (in_array($filters['pick'], $child)) {
                $message += ' (picked)';
            }
            if (in_array($filters['enable'], $child)) {
                $message += ' (enabled)';
            }
            if (in_array($filters['disable'], $child)) {
                $message += ' (disabled)';
            }
        }
        if ($child === $selected_task) {
            $message += ' (selected)';
            print_message('general', ['message' => $message])
        } else {
            print($message);
        }
    }

    // Execution plan
    if ($plan) {
        print_message('general', ['message' => '\nExecution Plan\n']);
        print($plan->explain());
    }

}
