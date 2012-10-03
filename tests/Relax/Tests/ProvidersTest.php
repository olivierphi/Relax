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
use Relax\Exception\RelaxProviderException;
use CommonJS\CommonJSProvider;

class ProvidersTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Relax\Application
     */
    protected $app;

    static protected $counter = 0;

    public function setUp()
    {
        $commonJsNewEnvironment = CommonJSProvider::getInstance('relax_providers_unit_tests_' . ++self::$counter);
        $this->app = new Application($commonJsNewEnvironment);
        $this->app->addModulesPath(__DIR__.'/modules/');
    }

    public function tearDown()
    {
        $monologTestLogFilePath = __DIR__.'/monolog.log';
        if (file_exists($monologTestLogFilePath)) {
            unlink($monologTestLogFilePath);
        }
    }

    /**
     * @expectedException \Relax\Exception\RelaxProviderException
     */
    public function testMonologProviderExceptionIfNotEnabled ()
    {
        $this->app->requireModule('relax/providers/monolog');
    }

    /**
     * @expectedException \Relax\Exception\RelaxProviderException
     */
    public function testMonologProviderExceptionIfNoFilePathProvided ()
    {
        $this->app->params['monolog.enabled'] = true;
        $this->app->requireModule('relax/providers/monolog');
    }

    public function testMonologProviderWorksIfEnabled ()
    {
        $targetLogFilePath = __DIR__.'/monolog.log';

        $this->app->params['monolog.enabled'] = true;
        $this->app->params['monolog.filePath'] = $targetLogFilePath;
        $monolog = $this->app->requireModule('relax/providers/monolog');
        $this->assertInstanceOf('\Monolog\Logger', $monolog);

        $monolog->addDebug('test');
        $logContents = file_get_contents($targetLogFilePath);
        $this->assertRegExp('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] relax\.DEBUG: test \[\] \[\]$/', $logContents);
    }

    //TODO: add Twig Provider test
    //TODO: add URL Generator Provider test
    //TODO: add Session Provider test

}