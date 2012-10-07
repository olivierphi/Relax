<?php
/*
 * This file is part of the Relax micro-framework.
 *
 * (c) Olivier Philippon <https://github.com/DrBenton>
 * with inspiration from Fabien Potencier's Silex framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Relax\Exception\RelaxProviderException;

$app = $require('relax/app');
$params = $require('relax/params');


// Params check
if (!$params['twig.enabled']) {
    throw new RelaxProviderException('Enable Twig with the parameter "twig.enabled" set to "true" before using Twig Provider !');
}
if (!isset($params['twig.path'])) {
    throw new RelaxProviderException('Set Twig templates path with the parameter "twig.path" before using Twig Provider !');
}


// Options setup
$twigOptions = isset($params['twig.options']) ? $params['twig.options'] : array() ;
$twigOptions = array_merge(array(
    'debug'            => $params['debug'],
    'strict_variables' => $params['debug'],
), $twigOptions);


// Go! Go! Go!
$twig = new \Twig_Environment(new Twig_Loader_Filesystem($params['twig.path']), $twigOptions);
$twig->addGlobal('app', $app);
$twig->addGlobal('request', $require('relax/request'));

// Did we add twig extensions to load in our Relax params ?
if (isset($params['twig.extensions'])) {
    foreach ($params['twig.extensions'] as $extension) {
        $twig->addExtension(new $extension['class']());
    }
}
// Twig_Extension_Debug is added if our app is in "debug" mode
if ($params['debug']) {
    $twig->addExtension(new \Twig_Extension_Debug());
}
// Symfony RoutingExtension is added if our app is in "debug" mode
if (isset($params['url-generator.enabled']) && true === $params['url-generator.enabled'] && class_exists('\Symfony\Bridge\Twig\Extension\RoutingExtension')) {
    $twig->addExtension(new \Symfony\Bridge\Twig\Extension\RoutingExtension($require('relax/providers/url-generator')));
}


// Module exports
$module['exports'] = $twig;
