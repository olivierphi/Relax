<?php
/*
 * This file is part of the Relax micro-framwork.
 *
 * (c) Olivier Philippon <https://github.com/DrBenton>
 * with inspiration from Fabien Potencier's Silex framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see http://symfony.com/doc/master/components/http_foundation/sessions.html
 * @see http://symfony.com/doc/master/components/http_foundation/session_configuration.html
 */

use Symfony\Component\HttpFoundation\Session\Session;
use Relax\Exception\RelaxProviderException;

$params = $require('relax/params');


// Params check
if (!isset($params['session.enabled']) || !$params['session.enabled']) {
    throw new RelaxProviderException('Enable Sessions with the parameter "session.enabled" set to "true" before using Session Provider !');
}


// Options setup
//TODO: add Session customization


// Go! Go! Go!
$session = new Session();
$session->start();


// Module exports
$module['exports'] = $session;
