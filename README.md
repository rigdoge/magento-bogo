# Magento 2 Buy One Get One Free Module

This module adds Buy One Get One Free (BOGO) functionality to your Magento 2.4.7 store.

## Features

- Enable/disable BOGO offers per product
- Configure eligible customer groups
- Set maximum number of free items per product
- Schedule BOGO offers with start/end dates
- Automatic stock checking
- Custom BOGO labels on products
- Compatible with Magento 2.4.7
- Supports PHP 8.2 and 8.3

## Installation

### Via Composer

```bash
composer require bogo/module-buyonegetone
bin/magento module:enable Bogo_BuyOneGetOne
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

## Configuration

1. Go to Admin > Stores > Configuration > Papa BOGO Extensions > Buy One Get One Free
2. Enable the module and configure:
   - Customer groups
   - Maximum free items
   - Active date range
   - Display settings

## Product Configuration

1. Edit a product in Admin > Catalog > Products
2. Find "Enable Buy One Get One Free" under the Promotions tab
3. Set to "Yes" to enable BOGO for the product

## Requirements

- Magento 2.4.7
- PHP ~8.2.0 || ~8.3.0

## Support

For support, please email support@tschenfeng.com

## License

This project is licensed under the MIT License - see the LICENSE file for details.
