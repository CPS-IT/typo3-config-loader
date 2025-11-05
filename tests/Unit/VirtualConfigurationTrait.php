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
     * @var array<string, mixed>
     */
    protected array $backedUpConfiguration;

    /**
     * @var array<string, mixed>
     */
    protected array $backedUpEnvironmentVariables;

    protected function initializeVirtualConfiguration(): void
    {
        $this->rootPath = vfsStream::setup('fr-typo3-config-loader-tests');
        $this->contextFilePath = 'app/config/environment/Testing/FrTypo3ConfigLoader.php';
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
            if ($environmentVariable === 'TYPO3_CONFIG_LOADER_USE_SAFE_SEPARATOR') {
                continue;
            }

            if (substr($environmentVariable, 0, 6) === 'TYPO3_') {
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
        // Unset additional environment variables
        array_map('putenv', array_keys(array_diff_assoc(getenv(), $this->backedUpEnvironmentVariables)));

        // Restore values of backend up environment variables
        foreach ($this->backedUpEnvironmentVariables as $name => $value) {
            putenv($name . '=' . $value);
        }

        $_ENV = $this->backedUpEnvironmentVariables;
    }
}
