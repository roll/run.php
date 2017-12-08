<?php


// Module API

function applyFaketty($code, $faketty=false) {
    return $faketty ? "script -qefc {$code}" : $code;
}
