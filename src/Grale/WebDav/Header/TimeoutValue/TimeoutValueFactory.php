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

    const WIN32_MAX_INT = 2147483647;

    /**
     *
     * @param TimeoutValueInterface $timeout            
     */
    public static function createFromValue($timeout)
    {
        if (PHP_INT_MAX === self::WIN32_MAX_INT) {
            if (extension_loaded('gmp')) {
                return new GMPValue($timeout);
            } else {
                $msg = "Current platform PHP_MAX_INT value is : %d. For correct work with timeouts greater enable gmp extention. ";
                throw new RuntimeException(sprintf($msg, PHP_INT_MAX));
            }
        } else {
            return new NativeIntValue($timeout);
        }
    }
}