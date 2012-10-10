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

class Params implements \ArrayAccess
{

    public $paramsInjectionRegExp = '/%([a-z0-9._]+)%/i';

    /**
     * @var array
     */
    protected $rawParams;

    /**
     * @var array
     */
    protected $initializedParams;

    public function __construct (array $initialParams = array())
    {
        $this->rawParams = $initialParams;
        $this->initializedParams = array();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return isset($this->rawParams[$offset]);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        if (!isset($this->rawParams[$offset])) {

            return null;
        }

        if (!isset($this->initializedParams[$offset])) {
            $this->initParam($offset);
        }

        return $this->initializedParams[$offset];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->rawParams[$offset] = $value;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset)
    {
        if (isset($this->rawParams[$offset])) {
            unset($this->rawParams[$offset]);
        }
    }

    /**
     * @param string $offset
     */
    protected function initParam ($offset)
    {
        $paramRawValue = $this->rawParams[$offset];

        $this->initializedParams[$offset] = $this->getInterpolatedParamValue($paramRawValue);
    }

    /**
     * @param string $rawValue
     * @return string
     */
    protected function getInterpolatedParamValue ($rawValue)
    {
        if (is_string($rawValue)) {
            // Let's handle vars interpolation!
            $value = preg_replace_callback($this->paramsInjectionRegExp, array($this, 'onParamInjectionFound'), $rawValue);
        } elseif (is_array($rawValue)) {
            // We offer vars interpolation in string values of first-level arrays
            $interpolatedArray = $rawValue;//array copy
            foreach ($rawValue as $arrayParamKey => $arrayParamValue) {
                if (is_string($arrayParamValue)) {
                    $interpolatedArray[$arrayParamKey] = preg_replace_callback($this->paramsInjectionRegExp, array($this, 'onParamInjectionFound'), $arrayParamValue);
                } else {
                    $interpolatedArray[$arrayParamKey] = $arrayParamValue;
                }
            }
            $value = $interpolatedArray;
        } else {
            $value = $rawValue;
        }

        return $value;
    }

    /**
     * @param array $matches
     * @return string
     */
    protected function onParamInjectionFound (array $matches)
    {
        $paramToInjectName = $matches[1];
        $paramToInjectValue = (isset($this->initializedParams[$paramToInjectName])) ?  $this->initializedParams[$paramToInjectName]:
            $this->rawParams[$paramToInjectName];

        return $this->getInterpolatedParamValue($paramToInjectValue);
    }
}