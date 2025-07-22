<?php

declare(strict_types=1);

/*
 * This file is part of the Composer package "cpsit/typo3-config-loader".
 *
 * Copyright (C) 2021 Elias Häußler <e.haeussler@familie-redlich.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace CPSIT\Typo3ConfigLoader\Loader;

use CPSIT\Typo3ConfigLoader\EnvironmentCreator;
use Helhum\ConfigLoader\CachedConfigurationLoader;
use Helhum\ConfigLoader\ConfigurationLoader;
use Helhum\ConfigLoader\ConfigurationLoaderInterface;
use Helhum\ConfigLoader\Reader\ConfigReaderInterface;
use Helhum\ConfigLoader\Reader\EnvironmentReader;
use Helhum\ConfigLoader\Reader\PhpFileReader;
use Helhum\ConfigLoader\Reader\YamlFileReader;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\Exception\MissingArrayPathException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * System configuration loader.
 *
 * Provides a configuration loader for global TYPO3 configuration. The loader reads
 * all configuration provided by various configuration loaders and applies them to
 * the globally available `$GLOBALS['TYPO3_CONF_VARS']` array making it available
 * in the TYPO3 ecosystem.
 *
 * By default, three loaders are available:
 *
 * == 1. Context specific loader:
 * Loads configuration based on the current TYPO3 application context. The
 * configuration is located in PHP files within the main context configuration
 * path ({@see self::CONTEXT_CONFIGURATION_PATH}). In order to ease naming,
 * the configuration files follow the application context, e.g. when application
 * context `Production/Staging` is active, a file `Staging.php` in sub-directory
 * `Production` is located.
 *
 * == 2. Env file loader:
 * Loads configuration provided by a global env.yml file. This file is defined
 * using an environment variable `ENV_FILE_PATH`. In case the variable is not set,
 * this loader is ignored.
 *
 * == 3. Environment variables loader:
 * Loads configuration provided by environment variables. The variables need to
 * be prefixed by `TYPO3_` in order to be respected by this loader.
 *
 * All loaders provide configuration for `$GLOBALS['TYPO3_CONF_VARS']`. Additionally,
 * all configuration within {@see self::BASE_CONFIG_PATH} is transformed to
 * environment variables, prefixed by {@see self::ENV_PREFIX}.
 *
 * Example configuration (YAML):
 *
 * CMS:
 *   base:
 *     foo: baz
 *
 * Resulting environment variables:
 *
 * PHP_CMS_BASE_FOO: baz
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 */
final readonly class SystemConfigurationLoader implements CacheableConfigurationLoader
{
    use EnvironmentCreator;

    private const VAR_STORAGE_LOCATION_CACHED = 'cache/data/typo3_config';
    private const CONTEXT_CONFIGURATION_PATH = 'app/config/environment';
    private const BASE_CONFIG_PATH = 'CMS/base';
    private const ENV_PREFIX = 'PHP_';
    private const CONFIG_DELIMITER = '/';
    private const ENV_DELIMITER = '_';

    /**
     * @var ConfigReaderInterface[]
     */
    private array $readers;

    public function __construct()
    {
        $this->readers = $this->initializeReaders();
    }

    /**
     * Load TYPO3 system configuration and apply it to the global configuration.
     *
     * Reads all available TYPO3 system configuration provided by various loaders and
     * applies them to the globally available `$GLOBALS['TYPO3_CONF_VARS']` array. Due
     * to the usage of {@see array_replace_recursive()}, all previously applied
     * configuration is applied in case the exact same configuration path exists in
     * the new configuration set.
     */
    public function load(): void
    {
        $this->loadWithConfigLoader(new ConfigurationLoader($this->readers));
    }

    /**
     * Load cached TYPO3 system configuration and apply it to the global configuration.
     *
     * This is a cached version of {@link self::load()}.
     *
     * @see self::load()
     */
    public function loadCached(): void
    {
        $this->loadWithConfigLoader(
            new CachedConfigurationLoader(
                $this->getCachePath(),
                $this->getCachedFileIdentifier(),
                fn() => new ConfigurationLoader($this->readers),
            ),
        );
    }

    /**
     * Load data with given configuration loader.
     *
     * @param ConfigurationLoaderInterface $loader The configuration loader that is used to load configuration
     */
    private function loadWithConfigLoader(ConfigurationLoaderInterface $loader): void
    {
        $this->processLoadedData($loader->load());
    }

    /**
     * Post Process the Loaded Config Data.
     *
     * @param array<mixed> $data
     */
    private function processLoadedData(array $data): void
    {
        $globalConfig = $GLOBALS['TYPO3_CONF_VARS'] ?? null;

        // Early return if global config is invalid
        if (!\is_array($globalConfig)) {
            return;
        }

        $GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive($globalConfig, $data);

        // Create CMS specific environment variables
        $this->createEnvironmentVariables(self::BASE_CONFIG_PATH);
    }

    /**
     * Get currently used cache path.
     *
     * @return string Currently used cache path
     */
    private function getCachePath(): string
    {
        $environment = Environment::getContext()->isProduction() ? 'prod' : 'dev';
        $cacheDir = sprintf('%s/%s/%s', Environment::getVarPath(), self::VAR_STORAGE_LOCATION_CACHED, $environment);

        if (!file_exists($cacheDir)) {
            GeneralUtility::mkdir_deep($cacheDir);
        }

        return $cacheDir;
    }

    /**
     * Get currently used cache identifier.
     *
     * Returns the currently used cache identifier for cached load. The
     * identifier will change if the raw data changes.
     *
     * @return string Currently used cache identifier
     */
    private function getCachedFileIdentifier(): string
    {
        $name = '';
        $filePaths = [
            $this->getEnvFilePath(),
            $this->getContextFilePath(),
        ];

        foreach ($filePaths as $filePath) {
            if ($filePath !== null && file_exists($filePath)) {
                $name .= md5_file($filePath);
            }
        }

        return md5($name);
    }

    /**
     * Recursively create environment variables from given config path.
     *
     * Creates environment variables for all configuration available within the given
     * config path. All path components of the resulting config path are used as
     * components of the resulting environment variable, e.g. `CMS/base/foo/baz` is
     * resulting in `CMS_BASE_FOO_BAZ`.
     *
     * @param string $configPath Path to the configuration to be transformed to environment variables
     */
    private function createEnvironmentVariables(string $configPath): void
    {
        $globalConfig = $GLOBALS['TYPO3_CONF_VARS'] ?? null;

        // Early return if global config is invalid
        if (!\is_array($globalConfig)) {
            return;
        }

        try {
            $config = ArrayUtility::getValueByPath($globalConfig, $configPath);
        } catch (MissingArrayPathException) {
            // Early return if configuration cannot be read
            return;
        }

        // $config is expected to be iterable, otherwise it cannot be traversed
        if (!is_iterable($config)) {
            return;
        }

        // Resolve all available configuration recursively and transform it to environment variables
        foreach ($config as $key => $value) {
            if (!\is_scalar($key)) {
                continue;
            }

            if (is_array($value)) {
                $this->createEnvironmentVariables($configPath . self::CONFIG_DELIMITER . $key);
            } elseif (\is_scalar($value)) {
                $envKey = strtoupper(str_replace(self::CONFIG_DELIMITER, self::ENV_DELIMITER, $configPath) . self::ENV_DELIMITER . $key);
                $this->createEnvironmentVariable($envKey, (string)$value);
            }
        }
    }

    /**
     * Get all available configuration readers.
     *
     * Initializes and returns all currently available configuration readers.
     * By default, context-specific reader, env file reader and environment
     * variable reader are available.
     *
     * Note: The array order determines the loading order of the individual
     * configurations. The later a loader is mapped in the array, the more
     * likely it is that the associated configuration will be loaded and not
     * overwritten.
     *
     * @return ConfigReaderInterface[] All available configuration readers
     */
    private function initializeReaders(): array
    {
        $readers = [
            $this->getContextSpecificReader(),
            $this->getEnvironmentFileReader(),
            $this->getEnvironmentVariablesReader(),
        ];

        return array_values(array_filter($readers));
    }

    /**
     * Get reader for context-specific configuration.
     *
     * Initializes and returns a configuration reader providing context-specific
     * configuration. This configuration is provided by a PHP file located in
     * the application which follows a specific naming convention. The filename
     * consists of the currently used TYPO3 application context.
     *
     * @return ConfigReaderInterface Context-specific configuration reader
     */
    private function getContextSpecificReader(): ConfigReaderInterface
    {
        $contextFile = $this->getContextFilePath();

        return new PhpFileReader($contextFile);
    }

    /**
     * Get reader for configuration from env file.
     *
     * Initializes and returns a configuration reader providing configuration
     * from a global env file provided by {@link self::getEnvFilePath()}. In
     * case the env file is not configured, this method returns NULL.
     *
     * @return ConfigReaderInterface|null Reader for configuration provided by env file, if available, NULL otherwise
     */
    private function getEnvironmentFileReader(): ?ConfigReaderInterface
    {
        $envFilePath = $this->getEnvFilePath();

        if ($envFilePath !== null) {
            return new YamlFileReader($envFilePath);
        }

        return null;
    }

    /**
     * Get reader for configuration from environment variables.
     *
     * Initializes and returns a configuration reader providing configuration
     * from environment variables, prefixed by `TYPO3__`.
     *
     * @return ConfigReaderInterface Reader for configuration from environment variables
     */
    private function getEnvironmentVariablesReader(): ConfigReaderInterface
    {
        return new EnvironmentReader('TYPO3', '__');
    }

    /**
     * Get path to global context-specific file.
     *
     * Returns the path to the globally available context-specific PHP file
     * holding all context-specific system configuration. This method does
     * not check whether the file actually exists!
     *
     * @return string Path to context-specific file
     */
    private function getContextFilePath(): string
    {
        $rootPath = Environment::getProjectPath();
        $context = Environment::getContext();

        return sprintf('%s/%s/%s.php', $rootPath, $this->getContextConfigurationPath(), (string)$context);
    }

    /**
     * Get path to context configuration.
     *
     * Returns the path to the context configuration directory. This is used
     * to load context-specific configuration files.
     *
     * @return string Path to context configuration directory
     */
    private function getContextConfigurationPath(): string
    {
        if (is_string(getenv('CONTEXT_CONFIGURATION_PATH'))) {
            return (string)getenv('CONTEXT_CONFIGURATION_PATH');
        }

        return self::CONTEXT_CONFIGURATION_PATH;
    }

    /**
     * Get path to global env.yml file.
     *
     * Returns the path to the globally available env.yml file holding all
     * environment-specific system configuration.
     *
     * @return string|null Path to env.yml file, if available, NULL otherwise
     */
    private function getEnvFilePath(): ?string
    {
        $envFilePath = getenv('ENV_FILE_PATH');

        if (is_string($envFilePath)) {
            return $envFilePath;
        }

        return null;
    }

    protected function getEnvironmentPrefix(): string
    {
        return self::ENV_PREFIX;
    }
}
