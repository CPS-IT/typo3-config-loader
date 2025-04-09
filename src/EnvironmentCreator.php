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

namespace CPSIT\Typo3ConfigLoader;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\Exception\MissingArrayPathException;

/**
 * Trait to create consistently named environment variables.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 */
trait EnvironmentCreator
{
    /**
     * Load config by given path and store it as environment variable.
     *
     * Loads the given config path by the given config array and creates a new environment
     * variable out of it. The given environment key is additionally prefixed by the global
     * environment prefix (see {@link EnvironmentCreator::getEnvironmentPrefix()}).
     *
     * @param array<string, mixed> $config     Full configuration set
     * @param string               $configPath Path to a specific configuration to be transformed to an environment variable
     * @param string               $envKey     Resulting environment variable name, will be prefixed with the global environment prefix
     */
    protected function mapConfigToEnvironment(array $config, string $configPath, string $envKey): void
    {
        try {
            $value = ArrayUtility::getValueByPath($config, $configPath);
        } catch (MissingArrayPathException) {
            // Early return if configuration is not available
            return;
        }

        // Only scalar values can be used as environment variables
        if (!is_scalar($value)) {
            return;
        }

        $this->createEnvironmentVariable($envKey, (string)$value);
    }

    /**
     * Create new environment variable for the given key.
     *
     * Creates an environment variable for the given key, prefixed with the current
     * environment prefix. The environment prefix is provided by calling
     * {@link EnvironmentCreator::getEnvironmentPrefix()}. Note that the final key
     * is converted to its uppercase string representation.
     */
    protected function createEnvironmentVariable(string $key, string $value): void
    {
        $key = strtoupper($this->getEnvironmentPrefix() . $key);
        putenv(sprintf('%s=%s', $key, $value));
    }

    /**
     * Return current environment prefix.
     *
     * Returns the currently used prefix of all created environment variables. The
     * prefix is used to ensure all environment variables are named consistently. It
     * should end with an appropriate character to separate real names from prefixes,
     * e.g. by using an underscore.
     */
    abstract protected function getEnvironmentPrefix(): string;
}
