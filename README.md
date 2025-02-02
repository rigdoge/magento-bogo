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




rm -rf vendor/bogo
composer clearcache
composer remove bogo/module-buyonegetone
composer require bogo/module-buyonegetone:1.4.0

rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* var/generation/* generated/*
php bin/magento cache:clean
php bin/magento cache:flush
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:clean
php bin/magento cache:flush

### 1. Using Composer (Recommended)

在你的 Magento 项目的根目录下运行：

```bash
# 方法 1：直接安装（推荐）
composer require bogo/module-buyonegetone

# 方法 2：如果需要指定版本
composer require bogo/module-buyonegetone:1.4.0

# 方法 3：如果遇到冲突，可以先添加依赖再更新
composer require bogo/module-buyonegetone --no-update
composer update bogo/module-buyonegetone --with-dependencies
```

注意：
- 确保你的 `composer.json` 中已经配置了 `https://repo.magento.com/`
- 如果安装特定版本，建议使用最新的稳定版本（当前是 1.4.0）

### 2. Enable the Module

After installation, enable the module by running:

```bash
bin/magento module:enable Bogo_BuyOneGetOne
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

### Version Compatibility

| Magento Version | Module Version | PHP Version |
|-----------------|----------------|-------------|
| 2.4.7          | 1.1.x          | 8.2, 8.3   |

### Troubleshooting

If you encounter any issues during installation:

1. Clear the cache:
   ```bash
   bin/magento cache:flush
   ```

2. If you see composer conflicts, try:
   ```bash
   # 只更新 BOGO 模块和其依赖
   composer update bogo/module-buyonegetone --with-dependencies
   
   # 或者清理 composer 缓存后重试
   composer clearcache
   composer require bogo/module-buyonegetone
   ```

3. For permission issues:
   ```bash
   chmod -R 777 var/ generated/ pub/static/
   ```

## Version History

### v1.1.2 (Latest)
- Fixed BOGO items not being added correctly for subsequent cart additions
- Improved handling of new item detection
- Optimized cart update logic

### v1.1.1
- Fixed issue with BOGO items not being added on subsequent cart updates
- Optimized cart item handling logic
- Removed redundant code and improved maintainability

### v1.1.0
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

#### Version Constraints Explained
- `^1.1.0`: Installs the latest stable version >= 1.1.0 and < 2.0.0 (Recommended)
- `~1.1.0`: Installs the latest stable version >= 1.1.0 and < 1.2.0
- `1.1.0`: Installs exactly version 1.1.0
- `*`: Installs the latest version (including breaking changes)
- `dev-develop`: Installs the latest development code

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

### Understanding Upgrade Commands

#### composer update
```bash
composer update bogo/module-buyonegetone
```
This command updates the package based on the version constraint in your composer.json:
- If you have `^1.0.0`: Updates to latest 1.x.x version
- If you have `~1.0.0`: Updates to latest 1.0.x version
- If you have `*`: Updates to the latest version
- If you have `dev-develop`: Updates to latest development code

#### composer require
```bash
composer require bogo/module-buyonegetone:^1.1.0
```
This command explicitly updates to the specified version constraint, regardless of what's in your composer.json

### Regular Upgrade (Based on composer.json)
```bash
composer update bogo/module-buyonegetone
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

### Upgrade to Latest Version (Recommended)
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
