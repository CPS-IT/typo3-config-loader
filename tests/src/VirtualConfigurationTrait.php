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

namespace CPSIT\Typo3ConfigLoader\Tests;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;

/**
 * VirtualConfigurationTrait.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 */
trait VirtualConfigurationTrait
{
    protected Filesystem $filesystem;
    protected string $rootPath;
    protected string $contextFilePath;

    /**
     * @var array<mixed>
     */
    protected array $backedUpConfiguration;

    /**
     * @var array<mixed>
     */
    protected array $backedUpEnvironmentVariables;

    protected function initializeVirtualConfiguration(): void
    {
        $this->rootPath = Path::join(
            sys_get_temp_dir(),
            uniqid('fr-typo3-config-loader-tests-'),
        );
        $this->contextFilePath = 'app/config/environment/Testing/FrTypo3ConfigLoader.php';
        /* @phpstan-ignore assign.propertyType */
        $this->backedUpConfiguration = $GLOBALS['TYPO3_CONF_VARS'] ?? [];
        $this->backedUpEnvironmentVariables = array_merge(getenv(), $_ENV);

        $GLOBALS['TYPO3_CONF_VARS'] = [
            'SYS' => [
                // Required in order to make TYPO3 properly create directories
                'folderCreateMask' => '2775',
            ],
        ];
    }

    protected function mockEnvFileConfiguration(): void
    {
        $filename = Path::join($this->rootPath, 'env.yml');
        $fileContents = file_get_contents(__DIR__ . '/Fixtures/env.yml');

        self::assertIsString($fileContents);

        $this->filesystem->dumpFile($filename, $fileContents);

        putenv('ENV_FILE_PATH=' . $filename);
    }

    protected function mockContextConfiguration(): void
    {
        $this->mockEnvironment();

        $filename = Path::join($this->rootPath, $this->contextFilePath);
        $fileContents = file_get_contents(__DIR__ . '/Fixtures/env.php');

        self::assertIsString($fileContents);

        $this->filesystem->dumpFile($filename, $fileContents);
    }

    protected function mockEnvironment(): void
    {
        Environment::initialize(
            new ApplicationContext('Testing/FrTypo3ConfigLoader'),
            true,
            false,
            $this->rootPath,
            Path::join($this->rootPath, 'public'),
            Path::join($this->rootPath, 'var'),
            Path::join($this->rootPath, 'config'),
            'index.php',
            'UNIX',
        );
    }

    protected function restoreEnvironment(): void
    {
        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            false,
            'http://typo3-testing.local/',
            (string)\getcwd(),
            \getcwd() . '/var',
            \getcwd() . '/config',
            'index.php',
            'UNIX',
        );
    }

    protected function unsetEnvFileConfiguration(): void
    {
        putenv('ENV_FILE_PATH');
    }

    protected function unsetEnvironmentVariablesConfiguration(): void
    {
        foreach (array_keys(getenv()) as $environmentVariable) {
            if (str_starts_with($environmentVariable, 'TYPO3_')) {
                unset($_ENV[$environmentVariable]);
            }
        }
    }

    protected function unsetContextConfiguration(): void
    {
        $this->filesystem->remove(
            Path::join($this->rootPath, $this->contextFilePath),
        );
    }

    protected function restoreConfiguration(): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = $this->backedUpConfiguration;
    }

    protected function restoreEnvironmentVariables(): void
    {
        // Unset additional environment variables
        array_map(putenv(...), array_keys(array_diff_assoc(getenv(), $this->backedUpEnvironmentVariables)));

        // Restore values of backend up environment variables
        foreach ($this->backedUpEnvironmentVariables as $name => $value) {
            if (\is_scalar($value)) {
                putenv($name . '=' . $value);
            }
        }

        $_ENV = $this->backedUpEnvironmentVariables;
    }
}
