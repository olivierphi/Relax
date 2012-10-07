<?php
/*
 * This file is part of the Relax micro-framework.
 *
 * (c) Olivier Philippon <https://github.com/DrBenton>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Relax;

use Symfony\Component\Routing\Route as SymfonyRoute;

class Route extends SymfonyRoute
{

    /**
     * @var string
     */
    protected $targetModulePath;
    /**
     * @var string
     */
    protected $targetModuleFunctionName;

    /**
     * @param string $targetModulePath
     */
    public function setTargetModulePath($targetModulePath)
    {
        $this->addDefaults(array('_modulePath' => $targetModulePath));
    }

    /**
     * @param string $targetModuleFunctionName
     */
    public function setTargetModuleFunctionName($targetModuleFunctionName)
    {
        $this->addDefaults(array('_moduleFunctionName' => $targetModuleFunctionName));
    }

    /**
     * @param string $prefix
     * @return string
     *
     * This method comes from Silex's Controller
     * @see https://github.com/fabpot/Silex/blob/master/src/Silex/Controller.php
     * @copyright (c) Fabien Potencier <fabien@symfony.com>
     * @license MIT
     */
    public function generateRouteName($prefix = '')
    {
        $requirements = $this->getRequirements();
        $method = isset($requirements['_method']) ? $requirements['_method'] : '';

        $routeName = $prefix.$method.$this->getPattern();
        $routeName = str_replace(array('/', ':', '|', '-'), '_', $routeName);
        $routeName = preg_replace('/[^a-z0-9A-Z_.]+/', '', $routeName);

        return $routeName;
    }

}
