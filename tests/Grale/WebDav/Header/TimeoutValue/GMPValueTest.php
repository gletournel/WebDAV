<?php
namespace Grale\WebDav\Header\TimeoutValue;

/**
 *
 * @author samizdam
 *        
 */
class GMPValueTest extends \PHPUnit_Framework_TestCase
{

    public function testToString()
    {
        $value = new GMPValue(4100000000);
        $this->assertEquals("4100000000", $value);
    }
}