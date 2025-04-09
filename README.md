<div align="center">

# TYPO3 config loader

[![Coverage](https://img.shields.io/coverallsCoverage/github/CPS-IT/typo3-config-loader?logo=coveralls)](https://coveralls.io/github/CPS-IT/typo3-config-loader)
[![Maintainability](https://img.shields.io/codeclimate/maintainability/CPS-IT/typo3-config-loader?logo=codeclimate)](https://codeclimate.com/github/CPS-IT/typo3-config-loader/maintainability)
[![CGL](https://img.shields.io/github/actions/workflow/status/CPS-IT/typo3-config-loader/cgl.yaml?label=cgl&logo=github)](https://github.com/CPS-IT/typo3-config-loader/actions/workflows/cgl.yaml)
[![Tests](https://img.shields.io/github/actions/workflow/status/CPS-IT/typo3-config-loader/tests.yaml?label=tests&logo=github)](https://github.com/CPS-IT/typo3-config-loader/actions/workflows/tests.yaml)
[![TYPO3 support](https://img.shields.io/badge/TYPO3-12_%26_13-orange?logo=typo3)](https://get.typo3.org/)

📦&nbsp;[Packagist](https://packagist.org/packages/cpsit/typo3-config-loader) |
💾&nbsp;[Repository](https://github.com/CPS-IT/typo3-config-loader) |
🐛&nbsp;[Issue tracker](https://github.com/CPS-IT/typo3-config-loader/issues)

</div>

A loader for various TYPO3-related configuration, including system configuration and
configuration for third-party extensions. Based on the [`helhum/config-loader`][1]
library.

## 🚀 Features

* Config loader for [system configuration](src/Loader/SystemConfigurationLoader.php)
* Config loader for [EXT:solr](src/Loader/SolrConfigurationLoader.php)
* Compatible with TYPO3 12.4 LTS and 13.4 LTS

## 🔥 Installation

[![Packagist](https://img.shields.io/packagist/v/cpsit/typo3-config-loader?label=version&logo=packagist)](https://packagist.org/packages/cpsit/typo3-config-loader)
[![Packagist Downloads](https://img.shields.io/packagist/dt/cpsit/typo3-config-loader?color=brightgreen)](https://packagist.org/packages/cpsit/typo3-config-loader)

```bash
composer require cpsit/typo3-config-loader
```

## ⚡ Usage

> [!TIP]
> Read more about loader-specific configuration in the appropriate class documentation.

### Basic (non-cached)

Add the following code snippet to your project's `config/system/additional.php` file:

```php
$systemConfigLoader = new CPSIT\Typo3ConfigLoader\Loader\SystemConfigurationLoader();
$systemConfigLoader->load();
```

In case your project uses EXT:solr, you can load its configuration as well:

```php
$solrConfigLoader = new CPSIT\Typo3ConfigLoader\Loader\SolrConfigurationLoader();
$solrConfigLoader->load();
```

### Cached

You can also use a cached version of the appropriate loaders:

```php
$systemConfigLoader = new CPSIT\Typo3ConfigLoader\Loader\SystemConfigurationLoader();
$systemConfigLoader->loadCached();
```

## 🔍 Readers

### Environment variables reader

The `Helhum\ConfigLoader\Reader\EnvironmentReader` is used within the `SystemConfigurationLoader`
class to map environment variables to configuration values. Environment variables must be
prefixed by `TYPO3` and each configuration key must be separated by `__` (two underscore
characters).

**Example:**

* Environment variable: `TYPO3__MAIL__transport_smtp_server`
* Configuration path: `$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_smtp_server']`

## 💡 Custom loaders

In case this library does not fulfill all your requirements, you are free to extend
it by your needs. A [`ConfigurationLoader`](src/Loader/ConfigurationLoader.php)
interface exists which can be used to provide additional loaders. Additionally, you
are free to make use of the [`EnvironmentCreator`](src/EnvironmentCreator.php) trait
which allows the transformation of loaded configuration to environment variables.

Next to the `ConfigurationLoader` there exists an extended interface that is able to
cache and load cached data. Use
[`CacheableConfigurationLoader`](src/Loader/CacheableConfigurationLoader.php) and
implement the additional method `loadCached` to make use of cached configuration.

Consider contributing to this project if you feel like some functionality is missing
or not yet fully covered.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 3.0 (or later)](LICENSE).

[1]: https://github.com/helhum/config-loader
