# Relax

Yet another PHP 5.3+ microframework... Yeah, but this one is not a nth Sinatra clone ! ;-)

[![build status](https://secure.travis-ci.org/DrBenton/Relax.png)](http://travis-ci.org/DrBenton/Relax)

It is based on Symfony [HttpFoundation](http://symfony.com/doc/master/components/http_foundation/introduction.html)
& [Routing](http://symfony.com/doc/master/components/routing.html) Components and
[CommonJS For PHP](https://github.com/DrBenton/CommonJSForPHP) Module pattern, and aims to be as lightweight as possible.

It is designed to be used with a simple YAML config file, which contains all the application routes and configuration parameters.
Create a new instance of ```Relax\Application```, give it the YAML file path, and... relax, it's ready!

It is bundled with several common services Providers:
* Symfony Session Provider
* Symfony URL Generator Provider
* Twig Provider
* Monolog Provider

Althought it doesn't use Symfony's HttpKernel, the ```Relax\Application``` class implements
the ```Symfony\Component\HttpKernel\HttpKernelInterface```, and can be used with the pure PHP
[Symfony Reverse Proxy](http://symfony.com/doc/2.0/book/http_cache.html#symfony-gateway-cache).

Documentation will come soon. You can look at Unit Tests for more information.

