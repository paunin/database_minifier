<?php

namespace Paunin\DatabaseMinifier\Test;

use Paunin\DatabaseMinifier\DatabaseMinifier;

/**
 * Class DatabaseMinifierMultiSourceTest
 *
 * @package Paunin\DatabaseMinifier\Test
 */
class DatabaseMinifierMultiSourceTest extends BaseTest
{
    const RESULT_DIR = __DIR__.'/_data/results/multi_source/';

    /**
     * @return array
     */
    public function buildJsonTreeProvider()
    {
        return [
            [[], 'tree.json'],
            [
                [
                    'source1:persone' =>
                        [
                            'source2:persone' => [
                                [
                                    'idpersone'  => 'idpersone',
                                    'country_id' => 'country_id',
                                ],
                            ],
                        ],
                ],
                'tree_persone_linked.json',
            ],
            [
                [
                    'source2:persone' =>
                        [
                            'source1:country' => [
                                [
                                    'country_id' => 'id',
                                    'country_id2' => 'id2',
                                ],
                            ],
                        ],
                ],
                'tree_persone_country_linked.json',
            ],
        ];
    }

    /**
     * @dataProvider buildJsonTreeProvider
     *
     * @param $relations
     * @param $expectedFile
     */
    public function testBuildJsonTree($relations, $expectedFile)
    {

        $dm = $this->getDm($relations);

        $result = $dm->buildJsonTree();

        $this->putFileContent($expectedFile.'.actual', $result);
        static::assertEquals($this->getFileContent($expectedFile), $result);
    }

    /**
     * @param $relations
     *
     * @return DatabaseMinifier
     */
    protected function getDm($relations)
    {
        $dm = new DatabaseMinifier($this->getConfigConnections(), $relations);

        return $dm;
    }

    /**
     * @return array
     */
    protected function getConfigConnections()
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
                'source2' => [
                    'dbname'         => 'minifierin2',
                    'username'       => 'minifier2',
                    'password'       => 'minifier2',
                    'host'           => 'mysqlin2',
                    'driver'         => 'mysql',
                    'driver_options' => null,
                ],
            ];
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
                'copy_country_1_2.sql',
            ],
            [
                'source1:country',
                [[1, 'compose_pk']],
                'copy_country_1.json',
                'copy_country_1.sql',
            ],
            [
                'source1:persone',
                [[1]],
                'copy_persone_1.json',
                'copy_persone_1.sql',
            ],
            [
                'source1:persone',
                [5],
                'copy_persone_5.json',
                'copy_persone_5.sql',
            ],
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
                       ->setHandler('source1', fopen(self::RESULT_DIR.$expectedSqlFile.'.actual', 'w'))
                       ->copyRecordsByPks($tables, $pks);
        $res    = json_encode($result, JSON_PRETTY_PRINT);
        $this->putFileContent($expectedResultFile.'.actual', $res);
        static::assertEquals($this->getFileContent($expectedResultFile), $res);
        static::assertEquals(
            $this->getFileContent($expectedSqlFile),
            $this->getFileContent($expectedSqlFile.'.actual')
        );
    }

    public function copyRecordsByCriteriaProvider()
    {
        return [
            [
                'source1:country',
                ['id' => 1],
                'copy_country_id1.json',
                'copy_country_id1.sql',
            ],
            [
                'source1:country',
                ['id' => ['value' => 1, 'operator' => '<=']],
                'copy_country_id_le_1.json',
                'copy_country_id_le_1.sql',
            ],
            [
                'source1:country',
                ['id' => ['value' => 1, 'operator' => '>=']],
                'copy_country_id_ge_1.json',
                'copy_country_id_ge_1.sql',
            ],
            [
                'source1:country',
                ['id' => ['value' => '%1%', 'operator' => 'LIKE']],
                'copy_country_id_like_1.json',
                'copy_country_id_like_1.sql',
            ],
            [
                'source1:country',
                ['id' => ['value' => '%DONT%', 'operator' => 'LIKE']],
                'copy_country_id_like_dont.json',
                'copy_country_id_like_dont.sql',
            ],
            [
                'source1:country',
                'id LIKE "%DONT%"',
                'copy_country_str_id_like_dont.json',
                'copy_country_str_id_like_dont.sql',
            ],
            [
                'source1:country',
                'id LIKE "%1%"',
                'copy_country_str_id_like_1.json',
                'copy_country_str_id_like_1.sql',
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
                       ->setHandler('source1', fopen(self::RESULT_DIR.$expectedSqlFile.'.actual', 'w'))
                       ->copyRecordsByCriteria($tableName, $criteria);
        $res    = json_encode($result, JSON_PRETTY_PRINT);
        $this->putFileContent($expectedResultFile.'.actual', $res);
        static::assertEquals($this->getFileContent($expectedResultFile), $res);
        static::assertEquals(
            $this->getFileContent($expectedSqlFile),
            $this->getFileContent($expectedSqlFile.'.actual')
        );
    }

}
