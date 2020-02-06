<?php
/**
 * Test
 * 
 * @package colby-groups
 * @since   1.0.0
 */

use PHPUnit\Framework\TestCase;

/**
 * Test class.
 *
 * @since 1.0.0
 */
class Test extends TestCase
{

    /**
     * Test array push and pop
     *
     * @since 1.0.0
     */
    public function testPushAndPop()
    {
        $stack = array();
        $this->assertSame(0, count($stack));

        array_push($stack, 'foo');
        $this->assertSame('foo', $stack[ count($stack) - 1 ]);
        $this->assertSame(1, count($stack));

        $this->assertSame('foo', array_pop($stack));
        $this->assertSame(0, count($stack));
    }
}
