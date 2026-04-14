<?php

declare(strict_types=1);

/*
 * This file is part of the Composer package "cpsit/typo3-config-loader".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace CPSIT\Typo3ConfigLoader\Loader;

use CPSIT\Typo3ConfigLoader\EnvironmentCreator;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\Exception\MissingArrayPathException;

/**
 * Solr configuration loader.
 *
 * Provides a configuration loader for EXT:solr. It transforms local Solr configuration
 * to referencable environment variables, making them globally available in the TYPO3
 * ecosystem.
 *
 * Example configuration (YAML):
 *
 * CMS:
 *   solr:
 *     scheme: http
 *     host: localhost
 *     port: 8983
 *     path_read: /solr/
 *     path:
 *       1:
 *         de: core_de
 *         en: core_en
 *
 * Resulting environment variables:
 *
 * PHP_SOLR_SCHEME_READ:    http
 * PHP_SOLR_HOST_READ:      localhost
 * PHP_SOLR_PORT_READ:      8983
 * PHP_SOLR_PATH_READ:      /solr/
 * PHP_SOLR_CORE_READ_1_DE: core_de
 * PHP_SOLR_CORE_READ_1_EN: core_en
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final readonly class SolrConfigurationLoader implements ConfigurationLoader
{
    use EnvironmentCreator;

    private const SOLR_CONFIG_PATH = 'CMS/solr';
    private const ENV_PREFIX = 'PHP_SOLR_';

    /**
     * Load Solr configuration and store them as global environment variables.
     *
     * Reads base configuration as well as site- and language-related configuration
     * of EXT:solr. All loaded configuration values are converted to environment
     * variables making them referencable in TYPO3 site configuration as well as
     * in TypoScript (for legacy systems).
     */
    public function load(): void
    {
        $globalConfig = $GLOBALS['TYPO3_CONF_VARS'] ?? null;

        // Early return if global configuration is invalid
        if (!\is_array($globalConfig)) {
            return;
        }

        try {
            $solrConfig = ArrayUtility::getValueByPath($globalConfig, self::SOLR_CONFIG_PATH);
        } catch (MissingArrayPathException) {
            // Early return if Solr configuration cannot be read
            return;
        }

        // Early return if Solr configuration is invalid
        if (!\is_array($solrConfig)) {
            return;
        }

        // Mapping table between local configuration <> environment key
        $mapping = [
            'scheme' => 'SCHEME_READ',
            'host' => 'HOST_READ',
            'port' => 'PORT_READ',
            'path_read' => 'PATH_READ',
        ];

        // Map local configuration to environment variables
        foreach ($mapping as $configKey => $envKey) {
            /** @var array<string, mixed> $solrConfig */
            $this->mapConfigToEnvironment($solrConfig, $configKey, $envKey);
        }

        $solrRootPaths = $solrConfig['path'] ?? null;

        // Early return if Solr root path configuration is invalid
        if (!\is_array($solrRootPaths)) {
            return;
        }

        // Map site- and language-specific configuration to environment variables
        foreach ($solrRootPaths as $rootPageId => $languages) {
            if (!\is_array($languages)) {
                continue;
            }

            foreach (array_keys($languages) as $language) {
                $envKey = sprintf('CORE_READ_%s_%s', $rootPageId, strtoupper((string)$language));
                $this->mapConfigToEnvironment($solrConfig, sprintf('path/%s/%s', $rootPageId, $language), $envKey);
            }
        }
    }

    protected function getEnvironmentPrefix(): string
    {
        return self::ENV_PREFIX;
    }
}
