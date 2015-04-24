# lazada/database-minifier

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

Tool for copy records from one Mysql database to another with all dependencies.

Additional features:

* Build array/json 2-level tree for representation database

## Install

Via Composer

Add information about new package in your `composer.json`

    "repositories": [
        ...
        {
          "type": "vcs",
          "url": "git@gitlab.lzd.co:operations/database-minifier.git",
          "name": "lazada/database-minifier"
        }
    ],
    
    "require-dev": {
        "paunin/database_minifier": ">=0.0.1"
    },

## Main ideas and conventions

* Database minifier allows you to create dump files with `INSERT` queries for database with respect to references. That means you will be able to extract data from database and apply it on another one with the same structure.
* All records which are referenced by rows you want to move will be copied as well as DB should keep consistency.
* Optionally you can copy records which have references to target rows (`copyReferencedBy` option in all directives)
* Minifier supports multi-source extraction. You can describe links between 2 separate databases and the script will extract data like they have foreign keys across two databases
* For multi-source mode Minifier can dump `INSERT` queries into different files. By default all queries will be dumped in `stdout`

As Minifier has multi-source mode each function accepts table names with namespaces only (e.g. `connection_name:tableA`), where namespace is a connection name you have in configuration for minifier.

## Usage with config file

* Copy `minifier.json.dist` to `minifier.json` and configure it:
    * `connections` - sources you want to explore

            {
                "source1": {                    # use this name as namespace for tables in the connection
                    "dbname": "%dbname%",
                    "username": "%user%",
                    "password": "%pwd%",
                    "host": "%host%",
                    "driver": "mysql",            # only mysql is supported
                    "out_file": "php://stdout",   # any file for dumping SQL for this connection
                }/* , ... * /
            }

    * `relations` - links between different sources/databases or inside one.

            {
                "%table%": [
                    "references": [
                        "%table%": [
                            ["%fk%": "%pk%" /* , ... * /]
                            /* , ... * /
                        ] /* , ... * /
                    ],
                ] /* , ... * /
            }
* Add more `directives` in array format `["method": "%method%", "arguments": [%arg1%, %arg2%, ... ]]`
* Run command `php run-minifier.php [%config_json%]` where `config_json` file with configurations (`minifier.json` by default)


## Usage in PHP code

Create new object:

    $dm = new \Paunin\DatabaseMinifier\DatabaseMinifier($connections, );
    
Where `$connections` and `$relations` are options in format described for json config.

### DatabaseMinifier::buildArrayTree() (only for PHP)

You can build your database tree and use it in your purposes

    [
        "%table%": [
            "primary_key": ["%PK1%", "%PK2%" /* , ... * /],
            "references": [
                "%table%": [
                    ["%fk%": "%pk%" /* , ... * /] /* , ... * /
                ] /* , ... * /
            ],
            "referenced_by": [
                "%table%": [
                    ["%fk%": "%pk%" /* , ... * /] /* , ... * /
                ] /* , ... * /
            ]
        ] /* , ... * /
    ]

### buildJsonTree()

Returns Json object like `DatabaseMinifier::buildArrayTree()`

### copyRecordsByCriteria($tableName, array $criteria = [], $copyReferencedBy = true, $limit = 0)

Copy all records (with all dependencies) from master `db` to salve.

* `$tableName` - table name with namespace (e.g. `connection_name:table_name`)
* `$copyReferencedBy` - if is `true` it will also copy all records depend on found records.
* `$limit` - can be integer or string in format `{LIMIT}, {OFFSET}`

### copyRecordsByPk($tableName, $pk, $copyReferencedBy = true)

* `$tableName` - table name with namespace (e.g. `connection_name:table_name`)
* `$pk` - primary key value or array of value for complex primary keys
* `$copyReferencedBy` - if is `true` it will also copy all records depend on found.

### copyRecordsByPks($tableName, array $pks = [], $copyReferencedBy = true)

* `$tableName` - table name with namespace (e.g. `connection_name:table_name`)
* `$pks` - array of primary keys for function `copyRecordsByPk`
* `$copyReferencedBy` - if is `true` it will also copy all records depend on found records.

## Testing

* Start environment with docker-compose `docker-compose build`
* And run tests `docker-compose run application ./vendor/phpunit/phpunit/phpunit`
