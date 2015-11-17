<?php
namespace Grale\WebDav\Header\TimeoutValue;

use Grale\WebDav\Exception\RuntimeException;

/**
 *
 * @author samizdam
 *        
 */
class TimeoutValueFactory
{

    /**
     *
     * @param TimeoutValueInterface $timeout            
     */
    public static function createFromValue($timeout)
    {
        if (PHP_INT_MAX === pow(2, 32)) {
            return new NativeIntValue($timeout);
        } elseif (extension_loaded('gmp')) {
            return new GMPValue($timeout);
        } else {
            $msg = "Current platform PHP_MAX_INT value is : %d. For correct work with timeouts greater enable gmp extention. ";
            throw new RuntimeException(sprintf($msg, PHP_INT_MAX));
        }
    }
}