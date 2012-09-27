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

        $this->app->addRoute('hello', $route);
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

        $this->app->addRoute('hello', $route);
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

        $this->app->addRoute('hello', $route);
        $request = Request::create('/hello/Roger/Moore');
        $response = $this->app->handle($request);

        $this->assertEquals('hello Roger Moore! - unset', $response->getContent());
    }

    /**
     * @depends testSimpleRouteWithParam
     */
    public function testCoreModulesDefinitions ()
    {
        $route = new Route('/hello/{name}');
        $route->setTargetModulePath('controller/hello');
        $route->setTargetModuleFunctionName('helloNameAction');

        $this->app->addRoute('hello', $route);
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
}