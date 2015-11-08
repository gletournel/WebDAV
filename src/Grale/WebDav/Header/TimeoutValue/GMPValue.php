<?php
namespace Grale\WebDav\Header\TimeoutValue;

/**
 *
 * @author samizdam
 *        
 */
class GMPValue implements TimeoutValueInterface
{

    /**
     *
     * @var \GMP
     */
    private $value;

    /**
     *
     * @param unknown $value            
     */
    public function __construct($value)
    {
        $this->value = gmp_init((string) $value);
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
        return gmp_cmp($this->value, gmp_init(0)) < 0;
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
        return gmp_strval($this->value);
    }

    /**
     *
     * (non-PHPdoc)
     *
     * @see \Grale\WebDav\Header\TimeoutValue\TimeoutValueInterface::getValidity()
     *
     * @param unknown $time            
     * @return string
     */
    public function getValidity($time)
    {
        return gmp_strval(gmp_add($this->value, gmp_init($time)));
    }
}