<?php

namespace Lazada\DatabaseMinifier;

    /**
     * Class DatabaseMinifier
     *
     * @package Lazada\DatabaseMinifier
     */
    /**
     * Class DatabaseMinifier
     *
     * @package Lazada\DatabaseMinifier
     */
/**
 * Class DatabaseMinifier
 *
 * @package Lazada\DatabaseMinifier
 */
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
     * DatabaseMinifier constructor.
     *
     * @param array $masterDbConfig in format array('dbname' => {DBNAME}, 'username' => {USERNAME}, 'password'=> {PASSWORD}, 'host' => {HOST}[, 'driver_options' => {options}])
     * @param array $slaveDbConfig  in format array('dbname' => {DBNAME}, 'username' => {USERNAME}, 'password'=> {PASSWORD}, 'host' => {HOST}[, 'driver_options' => {options}])
     */
    public function __construct(array $masterDbConfig, array $slaveDbConfig = null)
    {
        $this->setMasterDbConfig($masterDbConfig);
        $this->setMasterPdo($this->createPdo($masterDbConfig));

        $schemaConfig           = $masterDbConfig;
        $schemaConfig['dbname'] = 'information_schema';
        $this->setSchemaPdo($this->createPdo($schemaConfig));

        if ($slaveDbConfig) {
            $this->setSlaveDbConfig($slaveDbConfig);
            $this->setSlavePdo($this->createPdo($slaveDbConfig));
        }
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
     * @return string json object in format
     *  {
     *      "%table%": {
     *          "primary_key": ["%PK1%", "%PK2%" /* , ... * /],
     *          "references": {
     *              "%table%": {
     *                  "%fk%": "%pk%" /* , ... * /
     *              } /* , ... * /
     *          },
     *          "referenced_by": {
     *              "%table%": {
     *                  "%fk%": "%pk%" /* , ... * /
     *              } /* , ... * /
     *          }
     *      } /* , ... * /
     *  }
     */
    public function buildJsonTree()
    {
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

        $sql = <<<SQL
            SELECT i.TABLE_NAME, GROUP_CONCAT(DISTINCT k.COLUMN_NAME SEPARATOR ',') AS 'COLUMN_NAME', k.REFERENCED_TABLE_NAME, GROUP_CONCAT(DISTINCT k.REFERENCED_COLUMN_NAME SEPARATOR ',') AS 'REFERENCED_COLUMN_NAME'
            FROM TABLE_CONSTRAINTS i
              INNER JOIN KEY_COLUMN_USAGE k ON i.CONSTRAINT_NAME = k.CONSTRAINT_NAME
            WHERE i.CONSTRAINT_TYPE = 'FOREIGN KEY' AND i.CONSTRAINT_SCHEMA =  '{$this->getMasterDbConfig()['dbname']}' AND k.REFERENCED_TABLE_NAME IS NOT NULL
              GROUP BY k.CONSTRAINT_NAME
SQL;

        $query = $this->getSchemaPdo()->query($sql);

        while ($row = $query->fetch()) {
            $result[$row['TABLE_NAME']]['references'][$row['REFERENCED_TABLE_NAME']] = array_combine(
                explode(',', $row['COLUMN_NAME']),
                explode(',', $row['REFERENCED_COLUMN_NAME'])
            );
        }
        $result = $this->createReferencedBy($result);

        return json_encode($result);
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

        return $pdo;
    }

    /**
     * @param $tables
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
     * @return array
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
     * @return array
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

}
