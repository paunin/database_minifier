<?php

namespace Paunin\DatabaseMinifier\Test;

/**
 * Class BaseTest
 *
 * @package Paunin\DatabaseMinifier\Test
 */
class BaseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * It should be here to prevent phpunit notice
     */
    public function testTrueIsTrue()
    {
        static::assertTrue(true);
    }

    /**
     * @return array
     */
    protected function getConfigOneConnection()
    {
        return
            [
                'source1' => [
                    'dbname'         => 'minifierin',
                    'username'       => 'minifier',
                    'password'       => 'minifier',
                    'host'           => 'mysqlin',
                    'driver'         => 'mysql',
                    'driver_options' => null,
                ],
            ];
    }
}
