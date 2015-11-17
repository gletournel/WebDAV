<?php
namespace Grale\WebDav\Header\TimeoutValue;

/**
 *
 * @author samizdam
 *        
 */
interface TimeoutValueInterface
{

    const INFINITE = - 1;

    /**
     *
     * @return boolean
     */
    public function isInfinite();

    /**
     *
     * @return string
     */
    public function __toString();

    /**
     *
     * @param unknown $time            
     */
    public function getValidity($time);
}