<div align="center">

# TYPO3 config loader

[![Coverage](https://img.shields.io/coverallsCoverage/github/CPS-IT/typo3-config-loader?logo=coveralls)](https://coveralls.io/github/CPS-IT/typo3-config-loader)
[![Maintainability](https://qlty.sh/badges/825c9008-975f-4fcf-9039-a0c12ad07781/maintainability.svg)](https://qlty.sh/gh/CPS-IT/projects/typo3-config-loader)
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
* Compatible with TYPO3 12.4 LTS, 13.4 LTS and 14.0

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

## 🔍 System configuration readers

### 1. Context specific reader

The [`Helhum\ConfigLoader\Reader\PhpFileReader`][2] receives lowest priority when
loading system configuration. It resolves system configuration from a context-specific
file path within the `app/config/environment` directory. The default directory path can be
overwritten with an environment variable `CONTEXT_CONFIGURATION_PATH`. Each file must return an
array with additional system configuration values.

**Example:**

* TYPO3 context: `Development/Local`
* File path: `app/config/environment/Development/Local.php`

**File contents:**

```php
# /var/www/html/app/config/environment/Development/Local.php

return [
    'SYS' => [
        'debug' => 1,
    ],
];
```

### 2. Environment file reader

The next reader in the priority chain for system configuration is the
[`Helhum\ConfigLoader\Reader\YamlFileReader`][3]. It reads additional configuration
from a configured YAML file. The file path must be specified as environment variable
`ENV_FILE_PATH`. If the variable is not present, this reader is skipped.

**Example:**

* Environment variable: `ENV_FILE_PATH=/var/www/html/env.yml`
* File path: `/var/www/html/env.yml`

**File:**

```yaml
# /var/www/html/env.yml

SYS:
  debug: 1
```

### 3. Environment variables reader

The [`Helhum\ConfigLoader\Reader\EnvironmentReader`][4] receives highest priority.
It is used to map environment variables to configuration values. Environment variables
must be prefixed by `TYPO3` and each configuration key must be separated by `__`
(two underscore characters).

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

## 🚧 Migration

### 0.4.x/0.5.x → 1.x

* **Removal of environment variables reader compatibility layer**
  * Default key separator was changed from `_` (one underscore) to `__` (two underscores).
  * Support for feature flag environment variable `TYPO3_CONFIG_LOADER_USE_SAFE_SEPARATOR`
    was removed.
  * Make sure to convert existing environment variables to make use of the changed
    key separator, e.g. `TYPO3_SYS_debug` → `TYPO3__SYS__debug`.
* **Renaming of shipped configuration loader class names**
  * Both shipped configuration loaders were renamed from `<Type>` to
    `<Type>ConfigurationLoader`, e.g. [`System`][5] →
    [`SystemConfigurationLoader`](src/Loader/SystemConfigurationLoader.php).
  * Change references to the renamed classes and make sure to adapt the class names
    as described.
* **Hardening of configuration loader classes**
  * Both shipped configuration loaders are now marked as `final readonly`.
  * Custom configuration loaders may no longer extended default configuration loaders.
  * Change your custom implementations to a direct implementation of the
    [`ConfigurationLoader`](src/Loader/ConfigurationLoader.php) or
    [`CacheableConfigurationLoader`](src/Loader/CacheableConfigurationLoader.php) interfaces.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 3.0 (or later)](LICENSE).

[1]: https://github.com/helhum/config-loader
[2]: https://github.com/helhum/config-loader/blob/main/src/Reader/PhpFileReader.php
[3]: https://github.com/helhum/config-loader/blob/main/src/Reader/YamlFileReader.php
[4]: https://github.com/helhum/config-loader/blob/main/src/Reader/EnvironmentReader.php
[5]: https://github.com/CPS-IT/typo3-config-loader/blob/5e516082108bce67adcf4b5b20e344725a3764f5/src/Loader/System.php
