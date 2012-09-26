<?php

$exports['simpleHelloAction'] = function () {
    return 'hello!';
};

$exports['helloNameAction'] = function ($name) {
    return 'hello '.$name.'!';
};

$exports['helloMultipleParamsAction'] = function ($lastName, $firstName, $dummy = 'unset') {
    return 'hello '.$firstName.' '.$lastName.'! - '.$dummy;
};