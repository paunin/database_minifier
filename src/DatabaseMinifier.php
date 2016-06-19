<?php
namespace Paunin\DatabaseMinifier;

require_once 'Exception/DatabaseMinifierException.php';

use Paunin\DatabaseMinifier\Exception\DatabaseMinifierException;

/**
 * Class DatabaseMinifier
 *
 * @package Paunin\DatabaseMinifier
 */
class DatabaseMinifier
{
    const NAMESPACE_DELIMITER_TABLE = ':';
    const DRIVER_MYSQL              = 'mysql';
    const PHP_STDOUT                = 'php://stdout';

    /**
     * @var array
     */
    protected $copied = [];

    /**
     * @var array
     */
    protected $arrayTree;

    /**
     * @var \PDO[]
     */
    protected $connections = [];

    /**
     * @var resource[]
     */
    protected $outHandlers = [];

    /**
     * @var \PDO[]
     */
    protected $connectionsSchemas = [];

    /**
     * @var array
     */
    protected $connectionsConfigs = [];

    /**
     * @var array
     */
    protected $extraRelations = [];

    /**
     * DatabaseMinifier constructor.
     *
     * @param array $connectionsConfigs where each connection in format array('dbname' => {DBNAME}, 'username' => {USERNAME}, 'password'=> {PASSWORD}, 'host' => {HOST}, 'driver' => mysql|pgsql [,'out_file' => {FILENAME}] [, 'driver_options' => {options}])
     * @param array $extraRelations
     */
    public function __construct(array $connectionsConfigs, array $extraRelations = [])
    {
        $this->connectionsConfigs = $connectionsConfigs;

        foreach ($this->connectionsConfigs as $connectionName => $connectionInfo) {
            $this->connections[$connectionName] = $this->createPdo($connectionInfo);

            $outFile = array_key_exists('out_file', $connectionInfo) ? $connectionInfo['out_file'] : self::PHP_STDOUT;
            $this->setHandler($connectionName, fopen($outFile, 'w'));

            //TODO: fix for pgsql and add more options in connection configuration to define connection for schema db
            $connectionSchemaInfo = $connectionInfo;
            if ($connectionInfo['driver'] == self::DRIVER_MYSQL) {
                $connectionSchemaInfo['dbname'] = 'information_schema';
            }
            $this->connectionsSchemas[$connectionName] = $this->createPdo($connectionSchemaInfo);
        }

        $this->extraRelations = $extraRelations;
    }

    /**
     * @param array $config in format array('dbname' => {DBNAME}, 'username' => {USERNAME}, 'password'=> {PASSWORD}, 'host' => {HOST}[, 'driver_options' => {options}])
     *
     * @return \PDO
     */
    protected function createPdo(array $config)
    {
        $dsn = sprintf('%s:dbname=%s;host=%s', $config['driver'], $config['dbname'], $config['host']);

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
     * Set handler for specific connection
     *
     * @param string   $connectionName
     * @param resource $handler
     *
     * @return DatabaseMinifier
     */
    public function setHandler($connectionName, $handler)
    {
        $this->outHandlers[$connectionName] = $handler;

        return $this;
    }

    /**
     * @see self::buildArrayTree
     * @return bool
     */
    public function buildJsonTree()
    {
        $result = $this->buildArrayTree();

        return json_encode($result);
    }

    /**
     * Build 2-level tree for your database
     *
     * @param bool $noCache
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
    public function buildArrayTree($noCache = false)
    {
        if (null !== $this->arrayTree && true !== $noCache) {
            return $this->arrayTree;
        }

        $results = [];
        foreach (array_keys($this->connections) as $connectionName) {
            $result = [];
            $pks    = $this->getPks($connectionName);

            foreach ($this->getTables($connectionName) as $tableName) {

                $tableNameNs = $this->addNamespaceToTable($connectionName, $tableName);
                if (!array_key_exists($tableNameNs, $result)) {
                    $result[$tableNameNs] = [
                        'primary_key'   => [],
                        'references'    => [],
                        'referenced_by' => [],
                    ];
                }

                $result[$tableNameNs]['primary_key'] = array_key_exists($tableName, $pks) ? $pks[$tableName] : [];
            }

            $result  = $this->addReferences($result, $connectionName);
            $results = array_merge($results, $result);
        }

        $results = $this->addExtraRelations($results);

        $this->arrayTree = $results;

        return $results;
    }

    /**
     * Gett all PKs in the given schema per table
     *
     * @param $connectionName
     *
     * @return array in format
     *  [
     *      '%table%' => [
     *          '%pk1%', '%pk2%' /* , ... * /
     *      ] /* , ... * /
     *  ]
     */
    protected function getPks($connectionName)
    {
        $sql   = sprintf(
            'SELECT TABLE_NAME as \'table\',  GROUP_CONCAT(DISTINCT COLUMN_NAME SEPARATOR \',\') AS  \'pk\'
             FROM COLUMNS
             WHERE (TABLE_SCHEMA = \'%s\') AND (COLUMN_KEY = \'PRI\')
             GROUP BY TABLE_NAME
             ORDER BY TABLE_NAME ',
            $this->connectionsConfigs[$connectionName]['dbname']
        );
        $query = $this->getSchemaConnectionForConnection($connectionName)
                      ->query($sql);

        $pks = [];
        while ($row = $query->fetch()) {
            if (!array_key_exists($row['table'], $pks)) {
                $pks[$row['table']] = [];
            }

            $pks[$row['table']] = explode(',', $row['pk']);
        }

        return $pks;
    }

    /**
     * @param string $connectionName Table name with e.g. `source1`
     *
     * @return \PDO
     * @throws DatabaseMinifierException
     */
    protected function getSchemaConnectionForConnection($connectionName)
    {
        if (!array_key_exists($connectionName, $this->connectionsSchemas)) {
            throw new DatabaseMinifierException('Wrong connection name = '.$connectionName);
        }

        return $this->connectionsSchemas[$connectionName];
    }

    /**
     * @param $connectionName
     *
     * @return array of tables' names (without namespaces) for all specific connection
     */
    protected function getTables($connectionName)
    {
        $sql = sprintf(
            'SELECT TABLE_NAME as \'table\' FROM TABLES t WHERE t.TABLE_SCHEMA = \'%s\' ORDER BY TABLE_NAME',
            $this->connectionsConfigs[$connectionName]['dbname']
        );

        $query  = $this->getSchemaConnectionForConnection($connectionName)
                       ->query($sql);
        $tables = [];
        while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
            $tables[] = $row['table'];
        }

        return $tables;
    }

    /**
     * @param string $connectionName
     * @param string $tableName
     *
     * @return string
     */
    protected function addNamespaceToTable($connectionName, $tableName)
    {
        return $connectionName.self::NAMESPACE_DELIMITER_TABLE.$tableName;
    }

    /**
     * Fulfill tree of tables with references
     *
     * @param array  $tables
     * @param string $connectionName
     *
     * @return mixed
     */
    protected function addReferences($tables, $connectionName)
    {
        $sql = sprintf(
            'SELECT i.TABLE_NAME, GROUP_CONCAT(DISTINCT k.COLUMN_NAME SEPARATOR \',\') AS \'COLUMN_NAME\', k.REFERENCED_TABLE_NAME, GROUP_CONCAT(DISTINCT k.REFERENCED_COLUMN_NAME SEPARATOR \',\') AS \'REFERENCED_COLUMN_NAME\'
            FROM TABLE_CONSTRAINTS i
              INNER JOIN KEY_COLUMN_USAGE k ON i.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND k.CONSTRAINT_SCHEMA = i.CONSTRAINT_SCHEMA
            WHERE i.CONSTRAINT_TYPE = \'FOREIGN KEY\' AND i.CONSTRAINT_SCHEMA =  \'%s\' AND k.REFERENCED_TABLE_NAME IS NOT NULL
              GROUP BY k.CONSTRAINT_NAME
            ORDER BY i.TABLE_NAME, k.REFERENCED_COLUMN_NAME',
            $this->connectionsConfigs[$connectionName]['dbname']
        );

        $query = $this->getSchemaConnectionForConnection($connectionName)
                      ->query($sql);

        while ($row = $query->fetch()) {
            $tableName           = $this->addNamespaceToTable($connectionName, $row['TABLE_NAME']);
            $referencedTableName = $this->addNamespaceToTable($connectionName, $row['REFERENCED_TABLE_NAME']);

            if (!array_key_exists($referencedTableName, $tables[$tableName]['references'])) {
                $tables[$tableName]['references'][$referencedTableName] = [];
            }

            if (!array_key_exists($tableName, $tables[$referencedTableName]['references'])) {
                $tables[$referencedTableName]['referenced_by'][$tableName] = [];
            }

            $link = array_combine(
                explode(',', $row['COLUMN_NAME']),
                explode(',', $row['REFERENCED_COLUMN_NAME'])
            );
            ksort($link);

            $tables[$tableName]['references'][$referencedTableName][]    = $link;
            $tables[$referencedTableName]['referenced_by'][$tableName][] = $link;
        }

        return $tables;
    }

    /**
     * @param $tablesTree
     *
     * @return array
     * @throws DatabaseMinifierException
     * @internal param array $tables
     */
    protected function addExtraRelations($tablesTree)
    {
        foreach ($this->extraRelations as $targetTable => $relations) {
            if (!array_key_exists($targetTable, $tablesTree)) {
                throw new DatabaseMinifierException(
                    'Wrong target table name in extra relations option ('.$targetTable.') which does not exist in any schema'
                );
            }

            foreach ($relations as $tableName => $links) {
                if (!array_key_exists($tableName, $tablesTree)) {
                    throw new DatabaseMinifierException(
                        'Wrong table name for reference in extra relations option ('.$tableName.') which does not exist in any schema'
                    );
                }

                if (!array_key_exists($tableName, $tablesTree[$targetTable]['references'])) {
                    $tablesTree[$targetTable]['references'][$tableName] = [];
                }
                if (!array_key_exists($targetTable, $tablesTree[$tableName]['referenced_by'])) {
                    $tablesTree[$tableName]['referenced_by'][$targetTable] = [];
                }

                $tablesTree[$targetTable]['references'][$tableName] = array_merge(
                    $tablesTree[$targetTable]['references'][$tableName],
                    $links
                );
                $tablesTree[$tableName]['referenced_by'][$targetTable] = array_merge(
                    $tablesTree[$tableName]['referenced_by'][$targetTable],
                    $links
                );
            }
        }

        return $tablesTree;
    }

    /**
     * Copy record with all needed dependencies using PKs
     *
     * @see self::copyRecordsByCriteria
     * @see self::copyRecordsByPk
     *
     * @param string $tableName with namespace
     * @param array  $pks       in format ['%pk1%', '%pk2%' /* , ... * /] OR [['%pk1_1%', '%pk1_2%' /* , ... * /], ['%pk2_1%', '%pk2_2%' /* , ... * /] /* , ... * /]
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
     * @param string $tableName with namespace
     * @param mixed  $pk        in format '%pk%' OR ['%pk_1%', '%pk_2%' /* , ... * /]
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
     * @param string $tableName with namespace
     *
     * @return array
     * @throws DatabaseMinifierException
     */
    protected function getTable($tableName)
    {
        $tree = $this->buildArrayTree();

        if (!array_key_exists($tableName, $tree)) {
            throw new DatabaseMinifierException("Table $tableName not found in db");
        }

        return $tree[$tableName];
    }

    /**
     * Copy record with all needed dependencies (constraints)
     *
     * @param string       $tableName        with namespace
     * @param array|string $criteria         '{SQL EXPRESSION FOR `WHERE`}' OR
     *                                       ['%field%'=>'%value%' /* , ... * /] OR
     *                                       ['%field%' => ['value' =>'%value%', 'operator' => '%operator%' ] /* , ... * /]
     *                                       Where operator any SQL operator ( e.g. `>`, `<=`, `LIKE` )
     * @param bool         $copyReferencedBy if we need referenced records
     * @param int          $limit
     *
     * @return array
     * @throws DatabaseMinifierException
     */
    public function copyRecordsByCriteria(
        $tableName,
        $criteria = [],
        $copyReferencedBy = true,
        $limit = 0
    ) {
        $this->copied = [];

        return $this->copyRecordsByCriteriaInternal($tableName, $criteria, $copyReferencedBy, $limit);
    }

    /**
     * @param string       $tableName with namespace
     * @param array|string $criteria
     * @param bool|true    $copyReferencedBy
     * @param int          $limit
     *
     * @return array
     * @throws DatabaseMinifierException
     */
    protected function copyRecordsByCriteriaInternal(
        $tableName,
        $criteria = [],
        $copyReferencedBy = true,
        $limit = 0
    ) {
        $results    = [];
        $conditions = [];
        $params     = [];

        if (is_array($criteria)) {
            foreach ($criteria as $key => $value) {
                if (is_scalar($value)) {
                    $conditions[] = "$key = :$key";
                    $params[$key] = $value;
                } elseif (is_array($value) && array_key_exists('value', $value) && array_key_exists(
                        'operator',
                        $value
                    )
                ) {
                    $conditions[] = "$key {$value['operator']} :$key";
                    $params[$key] = $value['value'];
                } else {
                    throw new DatabaseMinifierException(
                        'Unsupported type of criteria parameter. Should be array (with `value` and `operator` element) or scalar.'.
                        "\nCriteria was: \n".print_r($criteria, true)
                    );
                }
            }
            $where = (count($conditions) ? (' WHERE '.implode(' AND ', $conditions)) : '');
        } elseif (is_string($criteria)) {
            $where = ' WHERE '.$criteria.' ';
        } else {
            throw new DatabaseMinifierException('Unsupported criteria. Should be array or string.');
        }

        $sql = sprintf(
            'SELECT * FROM %s %s %s',
            $this->cleanTableName($tableName),
            $where,
            $limit ? ' LIMIT '.$limit : ''
        );

        $query = $this->getConnectionForTable($tableName)
                      ->prepare($sql);
        $query->execute($params);

        while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {

            if ($this->checkIfRowCopied($tableName, $row)) {
                continue;
            }

            $result = [
                'record'        => [],
                'references'    => [],
                'referenced_by' => [],
            ];

            //TODO: what if we have not existed references
            $result['references'] = $this->copyReferences($tableName, $row);

            if (!$this->checkIfRowCopied($tableName, $row)) {
                $this->pasteRow($tableName, $row);
            }

            if ($copyReferencedBy) {
                $result['referenced_by'] = $this->copyReferencedBy($tableName, $row, $copyReferencedBy);
            }
            $result['record'] = $row;
            $results[]        = $result;
        }

        return $results;
    }

    /**
     * @param string $table Table name with namespace e.g. `source1:tableName`
     *
     * @return string
     */
    protected function cleanTableName($table)
    {
        list(, $tableName) = explode(self::NAMESPACE_DELIMITER_TABLE, $table);

        return $tableName;
    }

    /**
     * @param string $table Table name with namespace e.g. `source1:tableName`
     *
     * @return \PDO
     * @throws DatabaseMinifierException
     */
    protected function getConnectionForTable($table)
    {
        $connectionName = $this->getConnectionNameForTable($table);
        if (!array_key_exists($connectionName, $this->connections)) {
            throw new DatabaseMinifierException('Table name has wrong namespace = '.$connectionName);
        }

        return $this->connections[$connectionName];
    }

    /**
     * @param string $table Table name with namespace e.g. `source1:tableName`
     *
     * @return string
     * @throws DatabaseMinifierException
     */
    protected function getConnectionNameForTable($table)
    {
        list($connectionName) = explode(self::NAMESPACE_DELIMITER_TABLE, $table);

        return $connectionName;
    }

    /**
     * @param string $tableName
     * @param array  $row
     *
     * @return bool
     */
    protected function checkIfRowCopied($tableName, $row)
    {
        return array_key_exists($this->makeRowHash($tableName, $row), $this->copied);
    }

    /**
     * @param string $tableName
     * @param array  $row
     *
     * @return string
     */
    protected function makeRowHash($tableName, $row)
    {
        return md5($tableName.implode('::', $row));
    }

    /**
     * Copy records which required to be in db because $row has references on them
     *
     * @param string $tableName with namespace
     * @param array  $row
     *
     * @return array
     * @throws DatabaseMinifierException
     */
    protected function copyReferences(
        $tableName,
        array $row
    ) {
        $result = [];
        $table  = $this->getTable($tableName);
        foreach ($table['references'] as $table => $refs) {
            foreach ($refs as $links) {
                $criteria = [];
                foreach ($links as $fk => $pk) {
                    if ($row[$fk]) {
                        $criteria[$pk] = $row[$fk];
                    }
                }
                if (count($criteria)) {
                    $result[$table] = $this->copyRecordsByCriteriaInternal($table, $criteria, false);
                }
            }
        }

        return $result;
    }

    /**
     * Output row in SQL::Insert format
     *
     * @param string $tableName wit namespace
     * @param array  $row
     *
     * @return bool - true if copied | false if row has been pasted in this session
     */
    protected function pasteRow($tableName, $row)
    {
        foreach ($row as $field => $value) {
            $quotedValue               = $this->quote($value, $tableName);
            $insertDataValues[]        = $quotedValue;
            $updateExpressionsValues[] = "`$field` = ".$quotedValue;
            $fields[]                  = "`$field`";
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s;',
            $this->cleanTableName($tableName),
            implode(', ', $fields),
            implode(', ', $insertDataValues),
            implode(',', $updateExpressionsValues)
        );

        $result = (bool)fwrite($this->outHandlers[$this->getConnectionNameForTable($tableName)], $sql.PHP_EOL);
        $this->markRowAsCopied($tableName, $row);

        return $result;
    }

    /**
     * Quote value for SQL statements
     *
     * @param $value
     * @param $tableName string Table name with namespace, we can have different quoting strategies for different
     *
     * @return mixed
     */
    protected function quote($value, $tableName)
    {
        if (is_null($value)) {
            return 'NULL';
        } elseif (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        } elseif (is_int($value) || is_float($value)) {
            return $value;
        } else {
            return $this->getConnectionForTable($tableName)
                        ->quote($value);
        }
    }

    /**
     * @param string $tableName
     * @param array  $row
     *
     * @return mixed
     */
    protected function markRowAsCopied($tableName, $row)
    {
        return $this->copied[$this->makeRowHash($tableName, $row)] = 1;
    }

    /**
     * Copy records which has references on $row
     *
     * @param string $tableName
     * @param array  $row
     * @param bool   $copyReferencedBy
     *
     * @return array
     * @throws DatabaseMinifierException
     */
    protected function copyReferencedBy(
        $tableName,
        array $row,
        $copyReferencedBy = true
    ) {
        $result = [];
        $table  = $this->getTable($tableName);
        foreach ($table['referenced_by'] as $table => $refs) {
            foreach ($refs as $links) {
                $criteria = [];
                foreach ($links as $fk => $pk) {
                    $criteria[$fk] = $row[$pk];
                }
                $result[$table] = $this->copyRecordsByCriteriaInternal(
                    $table,
                    $criteria,
                    $copyReferencedBy
                );
            }
        }

        return $result;
    }
}
