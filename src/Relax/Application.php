<?php
/*
 * This file is part of the Relax micro-framwork.
 *
 * (c) Olivier Philippon <https://github.com/DrBenton>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Relax;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Relax\RelaxException;

class Application extends EventDispatcher implements HttpKernelInterface
{

    /**
     * @var \Symfony\Component\Routing\RouteCollection an array of Relax Routes
     */
    protected $routes;

    /**
     * @var string
     */
    protected $commonJsLibPath;

    /**
     * @var array the CommonJS environment (an array with "define", "require", "config" keys)
     */
    protected $commonJS;

    public function __construct($commonJsLibPath = null)
    {
        $this->commonJsLibPath = $commonJsLibPath;

        $this->initCommonJSEnvironment();

        $this->routes = new RouteCollection();
        $this->addModulesDefinitions(array(
           'relax/routes' => $this->routes
        ));
    }

    /**
     * @param string $modulesRootPath
     */
    public function setModulesPath ($modulesRootPath)
    {
        $this->commonJS['config']['basePath'] = $modulesRootPath;
    }

    /**
     * @param string $routeName
     * @param \Relax\Route $relaxRoute
     * @return void
     */
    public function addRoute ($routeName, Route $relaxRoute)
    {
        $this->routes->add($routeName, $relaxRoute);
    }

    /**
     * @param string $yamlConfigFilePath
     */
    public function addYamlConfig ($yamlConfigFilePath)
    {
        $rawConfigData = Yaml::parse($yamlConfigFilePath);

        if (isset($rawConfigData['routes'])) {
            foreach ($rawConfigData['routes'] as $currentRouteData) {
                $this->addRouteFromRawData($currentRouteData);
            }
        }

        if (isset($rawConfigData['params'])) {
            foreach ($rawConfigData['params'] as $currentParam) {
                // TODO: handle params
            }
        }

        if (isset($rawConfigData['services'])) {
            foreach ($rawConfigData['services'] as $currentServiceData) {
                // TODO: handle services
            }
        }
    }

    /**
     * @param string $routesYamlDefinitionFilePath
    public function addRoutesYaml ($routesYamlDefinitionFilePath)
    {

    }
     */

    /**
     * Handles a request and delivers a response.
     *
     * @param Request $request Request to process
     */
    public function run (Request $request = null)
    {
        if (null === $request) {
            $request = Request::createFromGlobals();
        }

        $response = $this->handle($request);
        $response->send();
    }

    /**
     * {@inheritdoc}
     *
     * Allows an easy use of ESI in pure PHP with Symfony's HttpCache.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @see \Symfony\Component\HttpKernel\HttpCache\HttpCache
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {

        $context = new RequestContext();
        $context->fromRequest($request);
        $matcher = new UrlMatcher($this->routes, $context);

        try {

            $matchingRouteData = $matcher->match($request->getPathInfo());
            $request->attributes->add($matchingRouteData);
            //print_r($matchingRouteData);
            //print_r($request);

            $targetModulePath  = $matchingRouteData['_modulePath'];
            $targetModuleFunctionName  = $matchingRouteData['_moduleFunctionName'];

            $this->addModulesDefinitions(array(
                'relax/request' => $request,
                'relax/request/context' => $context,
                'relax/raw-route-data' => $matchingRouteData,
            ));
            $targetModuleResponse = $this->triggerRequestTargetModuleFunction($targetModulePath, $targetModuleFunctionName);

            if ($targetModuleResponse instanceof Response) {
                $response = $targetModuleResponse;
            } elseif (is_string($targetModuleResponse)) {
                $response = new Response($targetModuleResponse);
            } else {
                throw new RelaxException('Targer module must return a string or a Symfony Http Response, but got "'.$targetModuleResponse.'" instead!');
            }

        } catch (ResourceNotFoundException $e) {
            $response = new Response('Not Found', 404);
        } catch (\Exception $e) {
            $response = new Response('An error occurred', 500);
        }

        return $response;
    }

    /**
     * This method should only be used for test purposes, as your
     * routes mapped Modules will be CommonJS Modules : they
     * will have an automatic access to their local "$require" function.
     *
     * @param string $modulePath
     * @return mixed
     */
    public function requireModule ($modulePath)
    {
        return call_user_func($this->commonJS['require'], $modulePath);
    }


    protected function initCommonJSEnvironment ()
    {
        if (null === $this->commonJsLibPath) {
            // let's assume that we are in "vendor/relax/relax" dir...
            $this->commonJsLibPath = __DIR__ . '/../../dr-benton/commonjs/commonjs.php';
        }

        if (!file_exists($this->commonJsLibPath)) {
            throw new RelaxException('Unable to find CommonJS lib path "'.$this->commonJsLibPath.'"!');
        }

        $this->commonJS = include $this->commonJsLibPath;
    }

    protected function addModulesDefinitions (array $appModulesToDefine)
    {
        $define = $this->commonJS['define'];
        foreach ($appModulesToDefine as $modulePath => $moduleReturnedValue) {
            $define($modulePath, function () use ($moduleReturnedValue) {
               return $moduleReturnedValue;
            });
        }
    }

    /**
     * @param string $targetModulePath
     * @param string $targetModuleFunctionName
     * @return string
     */
    protected function triggerRequestTargetModuleFunction ($targetModulePath, $targetModuleFunctionName)
    {
        $require = $this->commonJS['require'];
        $targetModule = $require($targetModulePath);
        $targetFunction = $targetModule[$targetModuleFunctionName];

        $controllerResolver = new ControllerResolver();
        $targetFunctionArgs = $controllerResolver->getArguments($require('relax/request'), $targetFunction);

        return call_user_func_array($targetFunction, $targetFunctionArgs);
    }

    /**
     * @param array $routeRawData
     */
    protected function addRouteFromRawData (array $routeRawData)
    {
        $mandatoryKeys = array('pattern', 'module', 'moduleFunction');
        foreach ($mandatoryKeys as $key) {
            if (!isset($routeRawData[$key])) {
                throw new RelaxException('Missing route "'.$key.'" key!');
            }
        }

        $route = new Route($routeRawData['pattern']);
        $route->setTargetModulePath($routeRawData['module']);
        $route->setTargetModuleFunctionName($routeRawData['moduleFunction']);

        $routeName = (isset($routeRawData['name'])) ? $routeRawData['name']: $route->generateRouteName() ;

        $this->addRoute($routeName, $route);
    }

}
