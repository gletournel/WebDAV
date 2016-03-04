<?php
namespace Grale\WebDav\Header\TimeoutValue;

/**
 *
 * @author samizdam
 *        
 */
class NativeIntValue implements TimeoutValueInterface
{

    /**
     *
     * @var int
     */
    private $value;

    public function __construct($value)
    {
        $this->value = (int) $value;
    }

    /**
     *
     * (non-PHPdoc)
     *
     * @see \Grale\WebDav\Header\TimeoutValue\TimeoutValueInterface::__toString()
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->value;
    }

    /**
     *
     * (non-PHPdoc)
     *
     * @see \Grale\WebDav\Header\TimeoutValue\TimeoutValueInterface::isInfinite()
     *
     * @return boolean
     */
    public function isInfinite()
    {
        return $this->value < 0;
    }

    /**
     *
     * (non-PHPdoc)
     *
     * @see \Grale\WebDav\Header\TimeoutValue\TimeoutValueInterface::getValidity()
     *
     * @param unknown $time            
     * @return number
     */
    public function getValidity($time)
    {
        return $this->value + $time;
    }
}