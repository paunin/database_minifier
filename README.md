# Lazada DatabaseMinifier

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

## Install

Via Composer

Add information about new package in your `composer.json`

    "repositories": [
        ...
        {
          "type": "vcs",
          "url": "https://github.com/lazada-com/database_minifier",
          "name": "lazada-com/database_minifier"
        }
    ],
    
    "require-dev": {
        "lazada-com/database_minifier": ">=0.0.1"
    },

## Usage

Create new object:

    $dm = new \Lazada\DatabaseMinifier\DatabaseMinifier($masterConfig, $slaveConfig);

### DatabaseMinifier::buildJsonTree()

You can build your database tree and use it in your purposes

    {
        "%table%": {
          "primary_key": ["%PK1%", "%PK2%" /* , ... * /],
          "references": {
              "%table%": {
                  "%fk%": "%pk%" /* , ... * /
              } /* , ... * /
          },
          "referenced_by": {
              "%table%": {
                  "%fk%": "%pk%" /* , ... * /
              } /* , ... * /
          }
        } /* , ... * /
    }

## Testing

``` bash
$ phpunit
```

## Contributing

Please see [CONTRIBUTING](./CONTRIBUTING.md) for details.

## Credits

- [Dmitriy Paunin](https://github.com/paunin)
- [Ruben Ribeiro](https://github.com/rmribeiro)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
