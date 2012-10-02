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

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use CommonJS\CommonJSProvider;
use Relax\RelaxException;

class Application implements HttpKernelInterface
{

    /**
     * @var array the Applications parameters array. Available as "relax/params" Module.
     */
    public $params;

    /**
     * @var \Symfony\Component\Routing\RouteCollection an array of Relax Routes
     */
    protected $routes;

    /**
     * @var array the CommonJS environment (an array with "define", "require", "config" keys)
     */
    protected $commonJS;

    /**
     * If you don't pass an existing CommonJS environment, "CommonJSProvider::getInstance()" will be
     * used to get one.
     *
     * @param array|null $commonJsEnvironment a CommonJS environment (an array with "define", "require" and "config" keys)
     */
    public function __construct(array $commonJsEnvironment = null)
    {
        if (null === $commonJsEnvironment) {
            $commonJsEnvironment = CommonJSProvider::getInstance();
        }
        $this->commonJS = $commonJsEnvironment;

        $this->routes = new RouteCollection();
        $this->addModulesDefinitions(array(
           'relax/app' => $this,
           'relax/routes' => $this->routes,
        ));

        $this->initParams();
    }

    /**
     * @param string|array $modulesRootPath
     */
    public function setModulesPath ($modulesRootPath)
    {
        $this->commonJS['config']['basePath'] = $modulesRootPath;
    }

    /**
     * @param string|array $modulesRootPath
     */
    public function addToModulesPath ($modulesRootPath)
    {
        $basePath = is_array($this->commonJS['config']['basePath']) ? $this->commonJS['config']['basePath'] :
            array($this->commonJS['config']['basePath']) ;

        if (is_string($modulesRootPath)) {
            $basePath[] = $modulesRootPath;
        } elseif (is_array($modulesRootPath)) {
            $basePath = array_merge($basePath, $modulesRootPath);
        }

        $this->commonJS['config']['basePath'] = $basePath;
    }

    /**
     * @param \Relax\Route $relaxRoute
     * @param string|null $routeName
     * @return void
     */
    public function addRoute (Route $relaxRoute, $routeName = null)
    {
        if (!$routeName) {
            $routeName = $relaxRoute->generateRouteName();
        }
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

        if (isset($rawConfigData['parameters'])) {
            foreach ($rawConfigData['parameters'] as $currentParamName => $currentParamValue) {
                $this->params[$currentParamName] = $currentParamValue;
            }
        }
    }

    /**
     * Handles a HTTP request and delivers a HTTP response.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request Request to process
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
                'relax/route-name' => $matchingRouteData['_route'],
            ));
            $targetModuleResponse = $this->triggerRequestTargetModuleFunction($targetModulePath, $targetModuleFunctionName);
            //print_r($targetModuleResponse);

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

    /**
     * This method should only be used for test purposes, as your
     * routes mapped Modules will be CommonJS Modules : they
     * will have an automatic access to their local "$require" function.
     *
     * @param string $modulePath
     * @param callable $callable
     */
    public function defineModule ($modulePath, $callable)
    {
        call_user_func($this->commonJS['define'], $modulePath, $callable);
    }

    /**
     * Redirects the user to another URL.
     *
     * @param string  $url    The URL to redirect to
     * @param integer $status The status code (302 by default)
     *
     * @see RedirectResponse
     * @copyright Fabien Potencier's Silex framework
     */
    public function redirect($url, $status = 302)
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * Creates a streaming response.
     *
     * @param mixed   $callback A valid PHP callback
     * @param integer $status   The response status code
     * @param array   $headers  An array of response headers
     *
     * @see StreamedResponse
     * @copyright Fabien Potencier's Silex framework
     */
    public function stream($callback = null, $status = 200, $headers = array())
    {
        return new StreamedResponse($callback, $status, $headers);
    }

    /**
     * Escapes a text for HTML.
     *
     * @param string  $text         The input text to be escaped
     * @param integer $flags        The flags (@see htmlspecialchars)
     * @param string  $charset      The charset
     * @param Boolean $doubleEncode Whether to try to avoid double escaping or not
     *
     * @return string Escaped text
     * @copyright Fabien Potencier's Silex framework
     */
    public function escape($text, $flags = ENT_COMPAT, $charset = null, $doubleEncode = true)
    {
        return htmlspecialchars($text, $flags, $charset ?: $this['charset'], $doubleEncode);
    }

    /**
     * Convert some data into a JSON response.
     *
     * @param mixed   $data    The response data
     * @param integer $status  The response status code
     * @param array   $headers An array of response headers
     *
     * @see JsonResponse
     * @copyright Fabien Potencier's Silex framework
     */
    public function json($data = array(), $status = 200, $headers = array())
    {
        return new JsonResponse($data, $status, $headers);
    }

    protected function initParams ()
    {
        $this->params = array(
            'relax.debug' => false,
            'relax.logger' => null,
        );
        $self = $this;
        $this->defineModule('relax/params', function () use ($self) {
            return $self->params;
        });
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

        $routeName = (isset($routeRawData['name'])) ? $routeRawData['name']: null ;

        $this->addRoute($route, $routeName);
    }

}
