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

use Symfony\Component\Routing\Generator\UrlGenerator;
use Relax\Exception\RelaxProviderException;

$params = $require('relax/params');
$routesCollection = $require('relax/routes');
$requestContext = $require('relax/request/context');


// Params check
if (!$params['url-generator.enabled']) {
    throw new RelaxProviderException('Enable URL Generator with the parameter "url-generator.enabled" set to "true" before using URL Generator Provider !');
}


// Go! Go! Go!
$urlGenerator = new UrlGenerator($routesCollection, $requestContext);


// Module exports
$module['exports'] = $urlGenerator;
