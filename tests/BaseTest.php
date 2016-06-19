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
     * @param $resultFile
     *
     * @return bool|mixed|string
     */
    protected function getFileContent($resultFile)
    {
        return file_get_contents(realpath(static::RESULT_DIR . $resultFile));
    }

    /**
     * @param $resultFile
     * @param $content
     *
     * @return int
     */
    protected function putFileContent($resultFile, $content)
    {
        return file_put_contents(static::RESULT_DIR . $resultFile, $content);
    }
}
