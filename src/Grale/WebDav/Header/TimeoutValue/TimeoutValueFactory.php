<?php
namespace Grale\WebDav\Header\TimeoutValue;

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
        } else {
            return new GMPValue($timeout);
        }
    }
}