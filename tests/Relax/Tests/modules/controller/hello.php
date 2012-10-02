<?php

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

$exports['simpleHelloAction'] = function () {
    return 'hello!';
};

$exports['helloNameAction'] = function ($name) {
    return 'hello '.$name.'!';
};

$exports['helloMultipleParamsAction'] = function ($lastName, $firstName, $dummy = 'unset') {
    return 'hello '.$firstName.' '.$lastName.'! - '.$dummy;
};

$exports['helloSymfonyResponseAction'] = function () {
    return new Response('hello');
};

$exports['helloSymfonyRedirectionAction'] = function () {
    return new RedirectResponse('http://github.com');
};