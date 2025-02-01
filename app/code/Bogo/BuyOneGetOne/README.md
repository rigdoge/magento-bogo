# Magento 2 Buy One Get One Free Module

适用于 Magento 2.4.7 的买一送一促销模块。

## 功能特点
- 支持全站买一送一促销
- 后台可配置开启/关闭
- 自动添加免费商品到购物车

## 安装方法

### 通过 Composer 安装

1. 在 Magento 2 项目根目录运行:
```bash
composer require bogo/module-buyonegetone
composer require bogo/module-buyonegetone:dev-main
```


2. 启用模块:
```bash
php bin/magento module:enable Bogo_BuyOneGetOne
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:clean
php bin/magento cache:flush
```

### 升级
```bash
composer clearcache
rm -rf generated/*
composer update bogo/module-buyonegetone
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:clean
php bin/magento cache:flush
```

### 卸载方法

1. 在 Magento 2 项目根目录运行:
```bash
rm -rf vendor/bogo
composer remove bogo/module-buyonegetone
rm -rf generated/*
php bin/magento cache:clean
php bin/magento cache:flush
```

2. 清理系统:
```bash
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:clean
php bin/magento cache:flush
```

## 配置方法

1. 登录 Magento 后台
2. 进入 Stores > Configuration > Bogo Extensions > Buy One Get One Free
3. 启用/禁用该功能

## 技术支持

如有问题，请提交 Issue 或联系技术支持。 