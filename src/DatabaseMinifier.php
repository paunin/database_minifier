<?php

namespace Lazada\DatabaseMinifier;

use Lazada\DatabaseMinifier\Exception\DatabaseMinifierException;

/**
 * Class DatabaseMinifier
 *
 * @package Lazada\DatabaseMinifier
 */
class DatabaseMinifier
{

    /**
     * @var string mysql|pgsql
     */
    protected $driver = 'mysql';
    /**
     * @var array
     */
    protected $masterDbConfig = [];
    /**
     * @var array
     */
    protected $slaveDbConfig = [];
    /**
     * @var \PDO
     */
    protected $masterPdo;
    /**
     * @var \PDO
     */
    protected $slavePdo;
    /**
     * @var \PDO
     */
    protected $schemaPdo;
    /**
     * @var array
     */
    protected $arrayTree;
    /**
     * @var array
     */
    protected $builded = [];

    /**
     * @var array
     */
    protected $copied = [];

    /**
     * DatabaseMinifier constructor.
     *
     * @param array $masterDbConfig in format array('dbname' => {DBNAME}, 'username' => {USERNAME}, 'password'=> {PASSWORD}, 'host' => {HOST}[, 'driver_options' => {options}])
     * @param array $slaveDbConfig  in format array('dbname' => {DBNAME}, 'username' => {USERNAME}, 'password'=> {PASSWORD}, 'host' => {HOST}[, 'driver_options' => {options}])
     */
    public function __construct(array $masterDbConfig, array $slaveDbConfig = null)
    {
        $this->setDriver('mysql'); //TODO: support other RDBMS

        $this->setMasterDbConfig($masterDbConfig);
        $this->setMasterPdo($this->createPdo($masterDbConfig));

        $schemaConfig           = $masterDbConfig;
        $schemaConfig['dbname'] = 'information_schema';
        $this->setSchemaPdo($this->createPdo($schemaConfig));

        if ($slaveDbConfig) {
            $this->setSlaveDbConfig($slaveDbConfig);
            $this->setSlavePdo($this->createPdo($this->getSlaveDbConfig()));
        }
    }

    /**
     * @return array
     */
    public function getBuilded()
    {
        return $this->builded;
    }

    /**
     * @param array $builded
     *
     * @return self
     */
    public function setBuilded($builded)
    {
        $this->builded = $builded;

        return $this;
    }

    /**
     * @return string
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * @param string $driver
     *
     * @return self
     */
    public function setDriver($driver)
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * @return \PDO
     */
    public function getMasterPdo()
    {
        return $this->masterPdo;
    }

    /**
     * @param \PDO $masterPdo
     *
     * @return self
     */
    public function setMasterPdo($masterPdo)
    {
        $this->masterPdo = $masterPdo;

        return $this;
    }

    /**
     * @return \PDO
     */
    public function getSlavePdo()
    {
        return $this->slavePdo;
    }

    /**
     * @param \PDO $slavePdo
     *
     * @return self
     */
    public function setSlavePdo($slavePdo)
    {
        $this->slavePdo = $slavePdo;

        return $this;
    }

    /**
     * @return \PDO
     */
    public function getSchemaPdo()
    {
        return $this->schemaPdo;
    }

    /**
     * @param \PDO $schemaPdo
     *
     * @return self
     */
    public function setSchemaPdo($schemaPdo)
    {
        $this->schemaPdo = $schemaPdo;

        return $this;
    }

    /**
     * @return array
     */
    public function getMasterDbConfig()
    {
        return $this->masterDbConfig;
    }

    /**
     * @param array $masterDbConfig
     *
     * @return self
     */
    public function setMasterDbConfig($masterDbConfig)
    {
        $this->masterDbConfig = $masterDbConfig;

        return $this;
    }

    /**
     * @return array
     */
    public function getCopied()
    {
        return $this->copied;
    }

    /**
     * @param array $copied
     *
     * @return self
     */
    public function setCopied($copied)
    {
        $this->copied = $copied;

        return $this;
    }

    /**
     * @return array
     */
    public function getSlaveDbConfig()
    {
        return $this->slaveDbConfig;
    }

    /**
     * @param array $slaveDbConfig
     *
     * @return self
     */
    public function setSlaveDbConfig($slaveDbConfig)
    {
        $this->slaveDbConfig = $slaveDbConfig;

        return $this;
    }

    /**
     * Build 2-level tree for your database
     *
     * @return array in format
     *  [
     *      "%table%": [
     *          "primary_key": ["%PK1%", "%PK2%" /* , ... * /],
     *          "references": [
     *              "%table%": [
     *                  ["%fk%": "%pk%" /* , ... * /] /* , ... * /
     *              ] /* , ... * /
     *          ],
     *          "referenced_by": [
     *              "%table%": [
     *                  ["%fk%": "%pk%" /* , ... * /] /* , ... * /
     *              ] /* , ... * /
     *          ]
     *      ] /* , ... * /
     *  ]
     */
    public function buildArrayTree()
    {
        if (null !== $this->arrayTree) {
            return $this->arrayTree;
        }
        $result = [];

        $pks = $this->getPks();
        foreach ($this->getTables() as $table) {
            if (!array_key_exists($table, $result)) {
                $result[$table] = [
                    'primary_key'   => [],
                    'references'    => [],
                    'referenced_by' => []
                ];
            }

            $result[$table]['primary_key'] = array_key_exists($table, $pks) ? $pks[$table] : [];
        }

        $result = $this->createReferences($result);
        $result = $this->createReferencedBy($result);

        $this->arrayTree = $result;

        return $result;
    }

    /**
     * @see self::buildArrayTree
     * @return string json object
     */
    public function buildJsonTree()
    {
        $result = $this->buildArrayTree();

        return json_encode($result);
    }

    /**
     * Copy record with all needed dependencies (constraints)
     *
     * @param string $tableName
     * @param array  $criteria         ['%field%'=>'%value%' /* , ... * /]
     * @param bool   $copyReferencedBy if we need referenced records
     *
     * @return array
     */
    public function copyRecordsByCriteria($tableName, array $criteria = [], $copyReferencedBy = true)
    {
        $this->setCopied([]);

        return $this->copyRecordsByCriteriaInternal($tableName, $criteria, $copyReferencedBy);
    }

    /**
     * Copy record with all needed dependencies using PKs
     *
     * @see self::copyRecordsByCriteria
     * @see self::copyRecordsByPk
     *
     * @param string $tableName
     * @param array  $pks in format ['%pk1%', '%pk2%' /* , ... * /] OR [['%pk1_1%', '%pk1_2%' /* , ... * /], ['%pk2_1%', '%pk2_2%' /* , ... * /] /* , ... * /]
     * @param bool   $copyReferencedBy
     *
     * @return array
     */
    public function copyRecordsByPks($tableName, array $pks = [], $copyReferencedBy = true)
    {
        $result = [];
        foreach ($pks as $pk) {
            $key          = is_array($pk) ? implode(',', $pk) : $pk;
            $result[$key] = $this->copyRecordsByPk($tableName, $pk, $copyReferencedBy);
        }

        return $result;
    }

    /**
     * Copy record with all needed dependencies using PK
     *
     * @see self::copyRecordsByCriteria
     *
     * @param string $tableName
     * @param mixed  $pk in format '%pk%' OR ['%pk_1%', '%pk_2%' /* , ... * /]
     * @param bool   $copyReferencedBy
     *
     * @return array
     * @throws DatabaseMinifierException
     */
    public function copyRecordsByPk($tableName, $pk, $copyReferencedBy = true)
    {

        $table = $this->getTable($tableName);

        if (!is_array($pk)) {
            $pk = [$pk];
        }

        $criteria = array_combine($table['primary_key'], $pk);

        return $this->copyRecordsByCriteria($tableName, $criteria, $copyReferencedBy);
    }

    /**
     * Build text-based uml for DB
     *
     * @see      http://plantuml.com/
     *
     * @param array $tables
     * @param bool  $references
     * @param bool  $referenced
     *
     * @return string
     * @internal param array $tables
     */
    public function buildPlantuml($tables = [], $references = true, $referenced = true)
    {
        if (!is_array($tables)) {
            $tables = [$tables];
        }
        if (!count($tables)) {
            $tables = $this->getTables();
        }

        $result = $this->buildPlantumlForTables($tables, $references, $referenced);

        return "@startuml\n" . implode("\n", array_unique($result)) . "\n@enduml";
    }

    /**
     * @param $tableName
     *
     * @return array
     * @throws DatabaseMinifierException
     */
    public function getTable($tableName)
    {
        $tree = $this->buildArrayTree();

        if (!array_key_exists($tableName, $tree)) {
            throw new DatabaseMinifierException("Table $tableName not found in db");
        }

        return $tree[$tableName];
    }

    /**
     * @param      $tables
     * @param bool $references
     * @param bool $referenced
     *
     * @return array
     */
    protected function buildPlantumlForTables($tables, $references = true, $referenced = true)
    {
        $result = [];
        foreach ($tables as $table) {
            $tresult = $this->buildPlantumlForTable($table, $references, $referenced);
            $result  = array_unique(array_merge($result, $tresult));
        }

        return $result;
    }

    /**
     *
     * @param string $tableName
     * @param bool   $references
     * @param bool   $referenced
     *
     * @return array
     * @throws DatabaseMinifierException
     * @internal param string $table
     */
    protected function buildPlantumlForTable($tableName, $references = true, $referenced = true)
    {
        $builded = $this->getBuilded();
        if (array_key_exists($tableName, $builded)) {
            return [];
        }

        $table  = $this->getTable($tableName);
        $result = [];
        foreach ($table['references'] as $refTable => $links) {
            foreach ($links as $link) {

                $fk = implode(',', array_keys($link));
                $pk = implode(',', $link);

                $result[] = $tableName . " \"[$fk]" . '\n' . "∞\" ---* \"[$pk]" . '\n' . "1\"  $refTable";
            }
        }

        $builded[$tableName] = 1;
        $this->setBuilded($builded);
        $referencesResult = [];
        if ($references && count($table['references'])) {
            $referencesResult = $this->buildPlantumlForTables(array_keys($table['references']), true, false);
        }
        $referencedResult = [];
        if ($referenced && count($table['referenced_by'])) {
            $referencedResult = $this->buildPlantumlForTables(array_keys($table['referenced_by']), false, true);
        }

        return array_unique(array_merge($result, $referencesResult, $referencedResult));
    }

    /**
     * @param string $tableName
     * @param array  $row
     *
     * @return array
     * @throws DatabaseMinifierException
     */
    protected function copyReferences($tableName, array $row)
    {
        $result = [];
        $table  = $this->getTable($tableName);
        foreach ($table['references'] as $table => $refs) {
            foreach ($refs as $links) {
                $criteria = [];
                foreach ($links as $fk => $pk) {
                    $criteria[$pk] = $row[$fk];
                }
                $result[$table] = $this->copyRecordsByCriteriaInternal($table, $criteria, false);
            }
        }

        return $result;
    }

    /**
     * @param string $tableName
     * @param array  $row
     * @param bool   $copyReferencedBy
     *
     * @return array
     * @throws DatabaseMinifierException
     */
    protected function copyReferencedBy($tableName, array $row, $copyReferencedBy = true)
    {
        $result = [];
        $table  = $this->getTable($tableName);
        foreach ($table['referenced_by'] as $table => $refs) {
            foreach ($refs as $links) {
                $criteria = [];
                foreach ($links as $fk => $pk) {
                    $criteria[$fk] = $row[$pk];
                }
                $result[$table] = $this->copyRecordsByCriteriaInternal($table, $criteria, $copyReferencedBy);
            }
        }

        return $result;
    }

    /**
     * @param string $tableName
     * @param array  $row
     *
     * @return bool - true if copied | false if row has been pasted in this session
     */
    protected function pasteRow($tableName, $row)
    {
        static $rows = [];
        $table = $this->getTable($tableName);

        $inserts = [];
        foreach (array_keys($row) as $field) {
            $inserts["`$field`"] = ":$field";
        }

        $sql = "INSERT IGNORE INTO $tableName (" . implode(', ', array_keys($inserts)) . ")
                VALUES (" . implode(', ', $inserts) . "); ";

        $query = $this->getSlavePdo()->prepare($sql);

        $result = $query->execute($row);

        return $result;
    }

    /**
     * @param array $config in format array('dbname' => {DBNAME}, 'username' => {USERNAME}, 'password'=> {PASSWORD}, 'host' => {HOST}[, 'driver_options' => {options}])
     *
     * @return \PDO
     */
    protected function createPdo(array $config)
    {
        $dsn = sprintf('%s:dbname=%s;host=%s', $this->getDriver(), $config['dbname'], $config['host']);

        $pdo = new \PDO(
            $dsn,
            $config['username'],
            $config['password'],
            array_key_exists('driver_options', $config) ? $config['driver_options'] : null
        );

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    /**
     * @return array of tables' names
     */
    protected function getTables()
    {
        $sql    = <<<SQL
            SELECT TABLE_NAME as 'table'
            FROM TABLES t
            WHERE t.TABLE_SCHEMA = '{$this->getMasterDbConfig()['dbname']}';
SQL;
        $query  = $this->getSchemaPdo()->query($sql);
        $tables = [];
        while ($row = $query->fetch()) {
            $tables[] = $row['table'];
        }

        return $tables;
    }

    /**
     * @param array $result
     *
     * @return mixed
     */
    protected function createReferences($result)
    {
        $sql = <<<SQL
            SELECT i.TABLE_NAME, GROUP_CONCAT(DISTINCT k.COLUMN_NAME SEPARATOR ',') AS 'COLUMN_NAME', k.REFERENCED_TABLE_NAME, GROUP_CONCAT(DISTINCT k.REFERENCED_COLUMN_NAME SEPARATOR ',') AS 'REFERENCED_COLUMN_NAME'
            FROM TABLE_CONSTRAINTS i
              INNER JOIN KEY_COLUMN_USAGE k ON i.CONSTRAINT_NAME = k.CONSTRAINT_NAME
            WHERE i.CONSTRAINT_TYPE = 'FOREIGN KEY' AND i.CONSTRAINT_SCHEMA =  '{$this->getMasterDbConfig()['dbname']}' AND k.REFERENCED_TABLE_NAME IS NOT NULL
              GROUP BY k.CONSTRAINT_NAME
            ORDER BY k.REFERENCED_COLUMN_NAME
SQL;

        $query = $this->getSchemaPdo()->query($sql);

        while ($row = $query->fetch()) {
            if (!array_key_exists($row['REFERENCED_TABLE_NAME'], $result[$row['TABLE_NAME']]['references'])) {
                $result[$row['TABLE_NAME']]['references'][$row['REFERENCED_TABLE_NAME']] = [];
            }

            $link = array_combine(
                explode(',', $row['COLUMN_NAME']),
                explode(',', $row['REFERENCED_COLUMN_NAME'])
            );
            ksort($link);

            $result[$row['TABLE_NAME']]['references'][$row['REFERENCED_TABLE_NAME']][] = $link;
        }

        return $result;
    }

    /**
     * @param array[] $tables
     *
     * @return mixed
     */
    protected function createReferencedBy($tables)
    {
        $result = $tables;
        foreach ($tables as $table => $info) {
            foreach ($info['references'] as $refTable => $relations) {
                $result[$refTable]['referenced_by'][$table] = $relations;
            }
        }

        return $result;
    }

    /**
     * @return array in format
     *  [
     *      '%table%' => [
     *          '%pk1%', '%pk2%' /* , ... * /
     *      ] /* , ... * /
     *  ]
     */
    protected function getPks()
    {
        $sql   = <<<SQL
            SELECT
              TABLE_NAME as 'table',  GROUP_CONCAT(DISTINCT COLUMN_NAME SEPARATOR ',') AS  'pk'
            FROM COLUMNS
            WHERE (TABLE_SCHEMA = '{$this->getMasterDbConfig()['dbname']}') AND (COLUMN_KEY = 'PRI')
            GROUP BY TABLE_NAME;
SQL;
        $query = $this->getSchemaPdo()->query($sql);
        $pks   = [];
        while ($row = $query->fetch()) {
            if (!array_key_exists($row['table'], $pks)) {
                $pks[$row['table']] = [];
            }

            $pks[$row['table']] = explode(',', $row['pk']);
        }

        return $pks;
    }

    /**
     * @param       $tableName
     * @param array $criteria
     * @param       $copyReferencedBy
     *
     * @return array
     */
    protected function copyRecordsByCriteriaInternal($tableName, array $criteria, $copyReferencedBy)
    {
        $results    = [];
        $conditions = [];
        foreach (array_keys($criteria) as $key) {
            $conditions[] = "$key = :$key";
        }

        $sql   = "SELECT * FROM {$tableName} WHERE " . (implode(' AND ', $conditions));
        $query = $this->getMasterPdo()->prepare($sql);

        $query->execute($criteria);

        while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {

            // WE need to prevent situation when we will copy one row more than once. What for?
            $copied  = $this->getCopied();
            $rowHash = md5($tableName . json_encode($row));
            if (array_key_exists($rowHash, $copied)) {
                continue;
            }

            $result = [
                'record'        => [],
                'references'    => [],
                'referenced_by' => []
            ];

            //TODO: what if we have not existed references
            $result['references'] = $this->copyReferences($tableName, $row);

            $this->pasteRow($tableName, $row);

            $copied[$rowHash] = 1;
            $this->setCopied($copied);

            if ($copyReferencedBy) {
                $result['referenced_by'] = $this->copyReferencedBy($tableName, $row, $copyReferencedBy);
            }
            $result['record'] = $row;
            $results[]        = $result;
        }

        return $results;
    }

}
