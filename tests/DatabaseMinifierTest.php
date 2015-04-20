<?php

namespace Lazada\DatabaseMinifier\Test;

use Lazada\DatabaseMinifier\DatabaseMinifier;

/**
 * Class DatabaseMinifierTest
 *
 * @package Lazada\DatabaseMinifier\Test
 */
class DatabaseMinifierTest extends BaseTest
{

    /**
     * @return \Lazada\DatabaseMinifier\DatabaseMinifier
     */
    protected function getDm()
    {
        $dm = new DatabaseMinifier($this->getConfigMaster(), $this->getConfigSlave());

        return $dm;
    }

    /**
     *  return array
     */
    public function copyRecordsByPksProvider()
    {
        return [
            [
                'country',
                [[1, 'compose_pk'], [2, 'compose_pk']],
                'copy_country_1_2.json'
            ],
            [
                'country',
                [[1, 'compose_pk']],
                'copy_country_1.json'
            ],
            [
                'persone',
                [[1]],
                'copy_persone_1.json'
            ],
            [
                'persone',
                [5],
                'copy_persone_5.json'
            ]
        ];
    }


    /**
     * @param $tables
     * @param $pks
     * @param $expectedResultFile
     *
     * @dataProvider copyRecordsByPksProvider
     */
    public function testCopyRecordsByPks($tables, $pks, $expectedResultFile)
    {
        $result = $this->getDm()->copyRecordsByPks($tables, $pks);
        static::assertEquals($this->getFileContent($expectedResultFile), json_encode($result, JSON_PRETTY_PRINT));
    }

    /**
     * @return array
     */
    public function buildPlantumlProvider()
    {
        return [
            [[], 'null.puml'],
            ['country', 'country.puml'],
            ['persone', 'persone.puml']
        ];
    }

    /**
     * @param $table
     * @param $resultFile
     *
     * @dataProvider buildPlantumlProvider
     */
    public function testBuildPlantuml($table, $resultFile)
    {
        $result   = $this->getDm()->buildPlantuml($table);
        $expected = $this->getFileContent($resultFile);

        static::assertEquals($expected, $result);
    }

    /**
     * @expectedException \Lazada\DatabaseMinifier\Exception\DatabaseMinifierException
     */
    public function testBuildPlantumlException()
    {
        $this->getDm()->buildPlantuml('WHATEVER' . rand(1, 1000));
    }

    /**
     *
     */
    public function testBuildJsonTree()
    {
        $json = $this->getDm()->buildJsonTree();
        static::assertEquals($this->getFileContent('tree.json'), $json);
    }


    /**
     * @param $resultFile
     *
     * @return bool|mixed|string
     */
    protected function getFileContent($resultFile)
    {
        return file_get_contents(realpath(__DIR__ . '/_data/results/' . $resultFile));
    }
}
