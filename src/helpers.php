<?php

use Al\Chaser\App;

if (!function_exists('app')) {
    function app($abstract = null)
    {
        if (is_null($abstract)) {
            return App::getInstance();
        }
        return App::getInstance()->get($abstract);
    }
}

if (!function_exists('resolve')) {
    function resolve($name, array $parameters = [])
    {
        return app($name);
    }
}

// create short lifetime object
if (!function_exists('make')) {
    function make($abstract, $parameters = [])
    {
        if (!empty($parameters)) {
            return app()->make($abstract, $parameters);
        }
        return app($abstract);
    }
}

if (!function_exists('array_get')) {
    function array_get()
    {

    }
}

// if (!function_exists('collect')) {
//     function collect($items)
//     {
//         return new Collection($items);
//     }
// }

function dd(...$vars)
{
    foreach ($vars as $var) {
        var_dump($var);
    }
    die();
}