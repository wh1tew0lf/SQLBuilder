<?php
namespace phpUnitTutorial\Test;

use \SQLBuilder\BaseSQLBuilder;

class SQLTest extends \PHPUnit_Framework_TestCase {
    
    public function testSELECT() {
        $this->assertEquals(
            BaseSQLBuilder::start()->select('a')->genSelect(),
            BaseSQLBuilder::start()->select(['a'])->genSelect());

        $this->assertEquals(BaseSQLBuilder::start()->genSelect(), '*');

        $this->assertEquals(
            BaseSQLBuilder::start()->select('a')->addSelect('b')->genSelect(),
            BaseSQLBuilder::start()->select(['a', 'b'])->genSelect());
    }

}