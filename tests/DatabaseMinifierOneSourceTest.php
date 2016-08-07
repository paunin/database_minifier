<?php

namespace Paunin\DatabaseMinifier\Test;

use Paunin\DatabaseMinifier\DatabaseMinifier;

/**
 * Class DatabaseMinifierOneSourceTest
 *
 * @package Paunin\DatabaseMinifier\Test
 */
class DatabaseMinifierOneSourceTest extends BaseTest
{
    const RESULT_DIR = __DIR__ . '/_data/results/one_source/';


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

    /**
     * @return \Paunin\DatabaseMinifier\DatabaseMinifier
     */
    protected function getDm()
    {
        $dm = new DatabaseMinifier($this->getConfigOneConnection());

        return $dm;
    }

    /**
     *
     */
    public function testBuildJsonTree()
    {
        $dm = $this->getDm();

        $result = $dm->buildJsonTree();

        static::assertEquals($this->getFileContent('tree.json'), $result);
    }

    /**
     *  return array
     */
    public function copyRecordsByPksProvider()
    {
        return [
            [
                'source1:country',
                [[1, 'compose_pk'], [2, 'compose_pk']],
                'copy_country_1_2.json',
                'copy_country_1_2.sql'
            ],
            [
                'source1:country',
                [[1, 'compose_pk']],
                'copy_country_1.json',
                'copy_country_1.sql'
            ],
            [
                'source1:persone',
                [[1]],
                'copy_persone_1.json',
                'copy_persone_1.sql'
            ],
            [
                'source1:persone',
                [5],
                'copy_persone_5.json',
                'copy_persone_5.sql'
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
    public function testCopyRecordsByPks($tables, $pks, $expectedResultFile, $expectedSqlFile)
    {
        $result = $this->getDm()
                       ->setHandler('source1', fopen(self::RESULT_DIR . $expectedSqlFile . '.actual', 'w'))
                       ->copyRecordsByPks($tables, $pks);
        $res    = json_encode($result, JSON_PRETTY_PRINT);
        $this->putFileContent($expectedResultFile . '.actual', $res);
        static::assertEquals($this->getFileContent($expectedResultFile), $res);
        static::assertEquals(
            $this->getFileContent($expectedSqlFile),
            $this->getFileContent($expectedSqlFile . '.actual')
        );
    }

    public function copyRecordsByCriteriaProvider()
    {
        return [
            [
                'source1:country',
                ['id' => 1],
                'copy_country_id1.json',
                'copy_country_id1.sql'
            ],
            [
                'source1:country',
                ['id' => ['value' => 1, 'operator' => '<=']],
                'copy_country_id_le_1.json',
                'copy_country_id_le_1.sql'
            ],
            [
                'source1:country',
                ['id' => ['value' => 1, 'operator' => '>=']],
                'copy_country_id_ge_1.json',
                'copy_country_id_ge_1.sql'
            ],
            [
                'source1:country',
                ['id' => ['value' => '%1%', 'operator' => 'LIKE']],
                'copy_country_id_like_1.json',
                'copy_country_id_like_1.sql'
            ],
            [
                'source1:country',
                ['id' => ['value' => '%DONT%', 'operator' => 'LIKE']],
                'copy_country_id_like_dont.json',
                'copy_country_id_like_dont.sql'
            ],
            [
                'source1:country',
                'id LIKE "%DONT%"',
                'copy_country_str_id_like_dont.json',
                'copy_country_str_id_like_dont.sql'
            ],
            [
                'source1:country',
                'id LIKE "%1%"',
                'copy_country_str_id_like_1.json',
                'copy_country_str_id_like_1.sql'
            ],
            [
                'source1:country',
                ['id' => [1, 2]],
                'copy_country_id_in_1_2.json',
                'copy_country_id_in_1_2.sql'
            ],
        ];
    }

    /**
     * @param $tableName
     * @param $criteria
     * @param $expectedResultFile
     *
     * @dataProvider copyRecordsByCriteriaProvider
     */
    public function testCopyRecordsByCriteria($tableName, $criteria, $expectedResultFile, $expectedSqlFile)
    {
        $result = $this->getDm()
                       ->setHandler('source1', fopen(self::RESULT_DIR . $expectedSqlFile . '.actual', 'w'))
                       ->copyRecordsByCriteria($tableName, $criteria);
        $res    = json_encode($result, JSON_PRETTY_PRINT);
        $this->putFileContent($expectedResultFile . '.actual', $res);
        static::assertEquals($this->getFileContent($expectedResultFile), $res);
        static::assertEquals(
            $this->getFileContent($expectedSqlFile),
            $this->getFileContent($expectedSqlFile . '.actual')
        );
    }

    /**
     * @return array
     */
    public function copyRecordsCriteriaProvider()
    {
        return [
            ['source1:country'],
            ['source1:car']
        ];
    }

    /**
     * @param $table
     *
     * @dataProvider copyRecordsCriteriaProvider
     */
    public function testCopyRecordsCriteria($table)
    {
        $result = $this->getDm()
            ->setHandler('source1', fopen(($tempFile = tempnam(self::RESULT_DIR, 'tmp')), 'w'))
            ->copyRecordsByCriteria($table, [], true, true);
        unlink($tempFile);
        static::assertNotEquals('[]', $result);
    }

    /**
     * @return array
     */
    public function copyRecordsCriteriaLimitProvider()
    {
        return [
            ['source1:car']
        ];
    }

    /**
     * @param $table
     *
     * @dataProvider copyRecordsCriteriaLimitProvider
     */
    public function testCopyRecordsCriteriaLimit($table)
    {
        $fTmpHandler = fopen(($tempFile = tempnam(self::RESULT_DIR, 'tmp')), 'w');
        $this->getDm()
            ->setHandler('source1', $fTmpHandler)
            ->copyRecordsByCriteria($table, [], true, 1);
        // we have not all records in slave DB
        $result = $this->getDm()
            ->setHandler('source1', $fTmpHandler)
            ->copyRecordsByCriteria($table, [], true, true);
        unlink($tempFile);
        static::assertNotEquals('[]', json_encode($result, JSON_PRETTY_PRINT));
    }
}
