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

namespace CPSIT\Typo3ConfigLoader\Tests\Unit\Loader;

use CPSIT\Typo3ConfigLoader\Loader\System;
use CPSIT\Typo3ConfigLoader\Tests\Unit\VirtualConfigurationTrait;
use Helhum\ConfigLoader\Reader\ConfigReaderInterface;
use Helhum\ConfigLoader\Reader\EnvironmentReader;
use Helhum\ConfigLoader\Reader\PhpFileReader;
use Helhum\ConfigLoader\Reader\YamlFileReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * SystemTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 */
#[CoversClass(System::class)]
final class SystemTest extends UnitTestCase
{
    use VirtualConfigurationTrait;

    protected bool $backupEnvironment = true;

    private System $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initializeVirtualConfiguration();
        $this->mockEnvFileConfiguration();
        $this->mockContextConfiguration();

        $this->subject = new System();
    }

    #[Test]
    public function constructorInitializesAllReaders(): void
    {
        $actual = $this->getReadersFromSubject($this->subject);

        self::assertCount(3, $actual);
        self::assertInstanceOf(PhpFileReader::class, $actual[0]);
        self::assertInstanceOf(YamlFileReader::class, $actual[1]);
        self::assertInstanceOf(EnvironmentReader::class, $actual[2]);
    }

    #[Test]
    public function constructorSkipsEnvFileReaderIfNoEnvFilePathIsSet(): void
    {
        $this->unsetEnvFileConfiguration();

        $actual = $this->getReadersFromSubject(new System());

        self::assertCount(2, $actual);
        self::assertInstanceOf(PhpFileReader::class, $actual[0]);
        self::assertInstanceOf(EnvironmentReader::class, $actual[1]);
    }

    #[Test]
    public function loadDoesNothingIfGlobalConfigIsInvalid(): void
    {
        $this->unsetEnvFileConfiguration();

        $globalConfig = $GLOBALS['TYPO3_CONF_VARS'] ?? [];

        unset($GLOBALS['TYPO3_CONF_VARS']);

        $this->subject->load();

        self::assertSame($this->backedUpEnvironmentVariables, getenv());
        self::assertArrayNotHasKey('TYPO3_CONF_VARS', $GLOBALS);

        $GLOBALS['TYPO3_CONF_VARS'] = $globalConfig;

        $this->unsetEnvironmentVariablesConfiguration();
    }

    #[Test]
    public function loadReadsConfigurationFromAllReaders(): void
    {
        $this->unsetEnvironmentVariablesConfiguration();

        $_ENV['TYPO3__FOO'] = 'baz';

        $this->subject->load();

        $expected = [
            'foo' => 'baz',
            'CMS' => [
                'base' => [
                    'baz' => 'foo',
                    'foo' => 'baz',
                    'another' => [
                        'foo' => 'baz',
                    ],
                ],
            ],
            'FOO' => 'baz',
        ];

        self::assertSame($expected, $GLOBALS['TYPO3_CONF_VARS']);
        self::assertSame('foo', getenv('PHP_CMS_BASE_BAZ'));
        self::assertSame('baz', getenv('PHP_CMS_BASE_FOO'));
        self::assertSame('baz', getenv('PHP_CMS_BASE_ANOTHER_FOO'));
    }

    #[Test]
    public function loadSkipsEnvironmentVariableCreationIfConfigPathIsNotAvailable(): void
    {
        $this->unsetContextConfiguration();
        $this->unsetEnvFileConfiguration();

        (new System())->load();

        $expected = $this->backedUpEnvironmentVariables;
        unset($expected['ENV_FILE_PATH']);

        self::assertSame($expected, getenv());
    }

    #[Test]
    public function loadSkipsEnvironmentVariableCreationIfConfigPathIsNotIterable(): void
    {
        $this->unsetContextConfiguration();
        $this->unsetEnvFileConfiguration();

        /* @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible */
        $GLOBALS['TYPO3_CONF_VARS']['CMS']['base'] = false;

        (new System())->load();

        $expected = $this->backedUpEnvironmentVariables;
        unset($expected['ENV_FILE_PATH']);

        self::assertSame($expected, getenv());
    }

    #[Test]
    public function loadCachedData(): void
    {
        $cacheDir = Environment::getVarPath() . '/cache/data/typo3_config';

        self::assertDirectoryDoesNotExist($cacheDir);

        (new System())->loadCached();

        $cacheDir .= '/' . (Environment::getContext()->isProduction() ? 'prod' : 'dev');

        self::assertDirectoryExists($cacheDir);

        $filesInDirectory = scandir($cacheDir);

        self::assertIsArray($filesInDirectory);
        self::assertCount(3, $filesInDirectory);
        self::assertSame(
            [
                'base' => [
                    'baz' => 'foo',
                    'foo' => 'baz',
                    'another' => [
                        'foo' => 'baz',
                    ],
                ],
            ],
            /* @phpstan-ignore offsetAccess.nonOffsetAccessible */
            $GLOBALS['TYPO3_CONF_VARS']['CMS'] ?? null,
        );
    }

    #[Test]
    public function loadCachedDataWithoutFileReader(): void
    {
        $cacheDir = Environment::getVarPath() . '/cache/data/typo3_config';

        self::assertDirectoryDoesNotExist($cacheDir);

        putenv('TYPO3__CMS__TEST=test');
        $_ENV['TYPO3__CMS__TEST'] = 'test';

        $this->unsetContextConfiguration();
        $this->unsetEnvFileConfiguration();

        (new System())->loadCached();

        $cacheFile = sprintf(
            '%s/%s/cached-config-%s.php',
            $cacheDir,
            Environment::getContext()->isProduction() ? 'prod' : 'dev',
            md5(''),
        );

        self::assertFileExists($cacheFile);
        /* @phpstan-ignore offsetAccess.nonOffsetAccessible */
        self::assertSame(['TEST' => 'test'], $GLOBALS['TYPO3_CONF_VARS']['CMS'] ?? null);

        $this->restoreEnvironmentVariables();
        $this->unsetEnvironmentVariablesConfiguration();
    }

    /**
     * @return ConfigReaderInterface[]
     */
    private function getReadersFromSubject(?System $subject = null): array
    {
        $subject ??= $this->subject;

        $reflection = new \ReflectionObject($subject);
        $property = $reflection->getProperty('readers');
        $readers = $property->getValue($subject);

        self::assertIsArray($readers);
        self::assertContainsOnlyInstancesOf(ConfigReaderInterface::class, $readers);

        return $readers;
    }

    protected function tearDown(): void
    {
        $this->restoreConfiguration();
        $this->restoreEnvironmentVariables();

        parent::tearDown();
    }
}
