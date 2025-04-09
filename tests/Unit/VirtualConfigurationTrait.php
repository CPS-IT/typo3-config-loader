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

namespace CPSIT\Typo3ConfigLoader\Tests\Unit;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
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
    protected vfsStreamDirectory $rootPath;
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
        $this->rootPath = vfsStream::setup('fr-typo3-config-loader-tests');
        $this->contextFilePath = 'app/config/environment/Testing/FrTypo3ConfigLoader.php';
        /* @phpstan-ignore assign.propertyType */
        $this->backedUpConfiguration = $GLOBALS['TYPO3_CONF_VARS'];
        $this->backedUpEnvironmentVariables = array_merge(getenv(), $_ENV);

        $GLOBALS['TYPO3_CONF_VARS'] = [];
    }

    protected function mockEnvFileConfiguration(): void
    {
        $filename = vfsStream::newFile('env.yml')
            ->withContent(file_get_contents(__DIR__ . '/Fixtures/env.yml'))
            ->at($this->rootPath)
            ->url();

        putenv('ENV_FILE_PATH=' . $filename);
    }

    protected function mockContextConfiguration(): void
    {
        $this->mockEnvironment();

        $structure = [];
        $lastComponent = &$structure;

        foreach (str_getcsv($this->contextFilePath, '/', '"', '\\') as $pathComponent) {
            $lastComponent[$pathComponent] = [];
            $lastComponent = &$lastComponent[$pathComponent];
        }

        $lastComponent = file_get_contents(__DIR__ . '/Fixtures/env.php');
        unset($lastComponent);

        vfsStream::create($structure);
    }

    protected function mockEnvironment(): void
    {
        $publicPath = vfsStream::newDirectory('public')->at($this->rootPath);
        $varPath = vfsStream::newDirectory('var')->at($this->rootPath);
        $configPath = vfsStream::newDirectory('config')->at($this->rootPath);

        Environment::initialize(
            new ApplicationContext('Testing/FrTypo3ConfigLoader'),
            Environment::isCli(),
            Environment::isComposerMode(),
            $this->rootPath->url(),
            $publicPath->url(),
            $varPath->url(),
            $configPath->url(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX'
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
        unlink($this->rootPath->getChild($this->contextFilePath)->url());
    }

    protected function restoreConfiguration(): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = $this->backedUpConfiguration;
    }

    protected function restoreEnvironmentVariables(): void
    {
        array_map('putenv', array_keys(array_diff_assoc(getenv(), $this->backedUpEnvironmentVariables)));
        $_ENV = $this->backedUpEnvironmentVariables;
    }
}
