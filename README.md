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

## Version History

### v1.1.0 (Latest)
- Major refactor: improved cart handling
- Replaced observer with plugin for better performance
- Fixed free item quantity synchronization
- Improved handling of multiple cart updates

### v1.0.2
- Fixed free item quantity calculation
- Added maximum items limit enforcement

### v1.0.1
- Fixed cart item addition issues
- Improved error handling

### v1.0.0
- Initial stable release

## Installation

### Via Composer

#### Install Latest Stable Version (Recommended)
```bash
composer require bogo/module-buyonegetone:^1.1.0
```

#### Install Specific Version
```bash
composer require bogo/module-buyonegetone:1.1.0
```

#### Install Development Version
```bash
composer require bogo/module-buyonegetone:dev-develop
```

#### Post-Installation Steps
```bash
bin/magento module:enable Bogo_BuyOneGetOne
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

## Upgrade

### Regular Upgrade
```bash
composer update bogo/module-buyonegetone
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

### Upgrade to Latest Version
```bash
composer require bogo/module-buyonegetone:^1.1.0
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

### Switch to Development Version
```bash
composer require bogo/module-buyonegetone:dev-develop
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

## Uninstallation

### Method 1: Remove via Composer
```bash
# Disable the module
bin/magento module:disable Bogo_BuyOneGetOne

# Remove the module
composer remove bogo/module-buyonegetone

# Clean up
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

### Method 2: Manual Cleanup
```bash
# Disable the module
bin/magento module:disable Bogo_BuyOneGetOne

# Remove module files
rm -rf app/code/Bogo/BuyOneGetOne

# Remove module from config
rm -f app/etc/config.php
bin/magento module:enable --all

# Clean up
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

## Troubleshooting

### After Installation/Upgrade
- Clear cache and generated files:
  ```bash
  rm -rf var/cache/* var/page_cache/* generated/*
  bin/magento cache:clean
  bin/magento cache:flush
  ```
- If you see 404 errors in admin:
  ```bash
  bin/magento setup:static-content:deploy
  ```

### After Uninstallation
- If you experience any issues after uninstallation, clear all caches:
  ```bash
  rm -rf var/cache/* var/page_cache/* generated/*
  bin/magento cache:clean
  bin/magento cache:flush
  ```

## Requirements

- Magento 2.4.7
- PHP ~8.2.0 || ~8.3.0

## Support

For support, please email support@tschenfeng.com

## License

This project is licensed under the MIT License - see the LICENSE file for details.
