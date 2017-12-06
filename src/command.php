<?php

// Module API

class Command {

    // Public

    function __construct($name, $code, $variable=null) {
        $this->_name = $name;
        $this->_code = $code;
        $this->_variable = $variable;
    }

    function name() {
        return $this->_name;
    }

    function code($value=null) {
        if ($value) $this->_code = value;
        return $this->_code;
    }

    function variable() {
        return $this->_variable;
    }

}
