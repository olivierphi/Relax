<?php
/*
 * This file is part of the Relax micro-framwork.
 *
 * (c) Olivier Philippon <https://github.com/DrBenton>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Relax\Tests;

use Relax\Application;
use Relax\Route;
use CommonJS\CommonJSProvider;
use Symfony\Component\HttpFoundation\Request;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Relax\Application
     */
    protected $app;

    static protected $counter = 0;

    public function setUp()
    {
        $commonJsNewEnvironment = CommonJSProvider::getInstance('relax_unit_tests_' . ++self::$counter);
        $this->app = new Application($commonJsNewEnvironment);
        $this->app->setModulesPath(__DIR__.'/modules/');
    }


    public function testSimpleRouteMatching ()
    {
        $route = new Route('/hello');
        $route->setTargetModulePath('controller/hello');
        $route->setTargetModuleFunctionName('simpleHelloAction');

        $this->app->addRoute($route);
        $request = Request::create('/hello');
        $response = $this->app->handle($request);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        $this->assertEquals('hello!', $response->getContent());
    }

    public function testSimpleRouteWithParam ()
    {
        $route = new Route('/hello/{name}');
        $route->setTargetModulePath('controller/hello');
        $route->setTargetModuleFunctionName('helloNameAction');

        $this->app->addRoute($route);
        $request = Request::create('/hello/Roger');
        $response = $this->app->handle($request);

        $this->assertEquals('hello Roger!', $response->getContent());
    }

    /**
     * @depends testSimpleRouteWithParam
     */
    public function testSimpleRouteWithParams ()
    {
        $route = new Route('/hello/{firstName}/{lastName}');
        $route->setTargetModulePath('controller/hello');
        $route->setTargetModuleFunctionName('helloMultipleParamsAction');

        $this->app->addRoute($route);
        $request = Request::create('/hello/Roger/Moore');
        $response = $this->app->handle($request);

        $this->assertEquals('hello Roger Moore! - unset', $response->getContent());
    }

    public function testSymfonySimpleResponse ()
    {
        $route = new Route('/hello');
        $route->setTargetModulePath('controller/hello');
        $route->setTargetModuleFunctionName('helloSymfonyResponseAction');

        $this->app->addRoute($route);
        $request = Request::create('/hello');
        $response = $this->app->handle($request);

        $this->assertEquals('hello', $response->getContent());
    }

    public function testSymfonyRedirectResponses ()
    {
        $route = new Route('/hello/redirect');
        $route->setTargetModulePath('controller/hello');
        $route->setTargetModuleFunctionName('helloSymfonyRedirectionAction');

        $this->app->addRoute($route);
        $request = Request::create('/hello/redirect');
        $response = $this->app->handle($request);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $response);
        $this->assertEquals('http://github.com', $response->getTargetUrl());
        $this->assertEquals(302, $response->getStatusCode());
    }

    /**
     * @depends testSimpleRouteWithParam
     */
    public function testCoreModulesDefinitions ()
    {
        $route = new Route('/hello/{name}');
        $route->setTargetModulePath('controller/hello');
        $route->setTargetModuleFunctionName('helloNameAction');

        $this->app->addRoute($route, 'hello');
        $request = Request::create('/hello/Roger');
        $this->app->handle($request);

        $this->assertInstanceOf('Symfony\Component\Routing\RouteCollection', $this->app->requireModule('relax/routes'));
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Request', $this->app->requireModule('relax/request'));
        $this->assertInstanceOf('Symfony\Component\Routing\RequestContext', $this->app->requireModule('relax/request/context'));
        $this->assertEquals(array(
            'name' => 'Roger',
            '_modulePath' => 'controller/hello',
            '_moduleFunctionName' => 'helloNameAction',
            '_route' => 'hello',
        ), $this->app->requireModule('relax/raw-route-data'));
        $this->assertEquals('hello', $this->app->requireModule('relax/route-name'));

        $params = $this->app->requireModule('relax/params');
        $this->assertInternalType('array', $params);
        $this->assertEquals(false, $params['relax.debug']);
    }

    public function testSimpleRouteFromYaml ()
    {
        $this->app->addYamlConfig(__DIR__.'/fixtures/hello-world.yml');

        $request = Request::create('/hello');
        $response = $this->app->handle($request);

        $this->assertEquals('hello!', $response->getContent());
    }

    /**
     * @depends testSimpleRouteFromYaml
     */
    public function testMultipleSimpleRoutesFromYaml ()
    {
        $this->app->addYamlConfig(__DIR__.'/fixtures/multiple-simple-routes.yml');

        $routesCollection = $this->app->requireModule('relax/routes');
        $this->assertEquals(3, count($routesCollection));
        $routesIterator = $routesCollection->getIterator();
        //print_r($routesIterator);
        $this->assertEquals('/hello', $routesIterator['namedRoute']->getPattern());
        $this->assertEquals('/hello/{name}', $routesIterator['_hello_name']->getPattern());
        $this->assertEquals('controller/hello', $routesIterator['_hello_name']->getDefault('_modulePath'));
        $this->assertEquals('helloNameAction', $routesIterator['_hello_name']->getDefault('_moduleFunctionName'));
        $this->assertEquals('/hello/{firstName}/{lastName}', $routesIterator['_hello_firstName_lastName']->getPattern());
    }

    /**
     * @depends testSimpleRouteFromYaml
     */
    public function testParametersFromYaml ()
    {
        $this->app->addYamlConfig(__DIR__.'/fixtures/params.yml');

        $routesCollection = $this->app->requireModule('relax/routes');
        $this->assertEquals(1, count($routesCollection));

        $params = $this->app->requireModule('relax/params');
        $this->assertEquals(true, $params['relax.debug']);
        $this->assertEquals('hello', $params['app.simple.param']);
        $this->assertEquals(array(1, 2), $params['app.array.param']);
        $this->assertEquals(array('key1' => 1, 'key2' => 2), $params['app.hash.param']);
    }
}