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

namespace CPSIT\Typo3ConfigLoader\Tests\Unit\Loader;

use CPSIT\Typo3ConfigLoader\Loader\System;
use CPSIT\Typo3ConfigLoader\Tests\Unit\VirtualConfigurationTrait;
use Helhum\ConfigLoader\Reader\ConfigReaderInterface;
use Helhum\ConfigLoader\Reader\EnvironmentReader;
use Helhum\ConfigLoader\Reader\PhpFileReader;
use Helhum\ConfigLoader\Reader\YamlFileReader;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * SystemTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 */
final class SystemTest extends UnitTestCase
{
    use VirtualConfigurationTrait;

    protected System $subject;

    protected function setUp(): void
    {
        // @todo Directly override property once support for typo3/testing-framework 6.x is dropped
        $this->backupEnvironment = true;

        parent::setUp();

        $this->initializeVirtualConfiguration();
        $this->mockEnvFileConfiguration();
        $this->mockContextConfiguration();

        $this->subject = new System();
    }

    /**
     * @test
     */
    public function constructorInitializesAllReaders(): void
    {
        $actual = $this->getReadersFromSubject($this->subject);

        self::assertCount(3, $actual);
        self::assertInstanceOf(PhpFileReader::class, $actual[0]);
        self::assertInstanceOf(YamlFileReader::class, $actual[1]);
        self::assertInstanceOf(EnvironmentReader::class, $actual[2]);
    }

    /**
     * @test
     */
    public function constructorSkipsEnvFileReaderIfNoEnvFilePathIsSet(): void
    {
        $this->unsetEnvFileConfiguration();

        $actual = $this->getReadersFromSubject(new System());

        self::assertCount(2, $actual);
        self::assertInstanceOf(PhpFileReader::class, $actual[0]);
        self::assertInstanceOf(EnvironmentReader::class, $actual[1]);
    }

    /**
     * @test
     */
    public function constructorDoesNotTriggerDeprecationNoticeForUsageOfUnsafeSeparatorIfRunningInProductionContext(): void
    {
        $errorMessage = null;

        // Switch to legacy (unsafe) key separator
        putenv('TYPO3_CONFIG_LOADER_USE_SAFE_SEPARATOR');

        // Simulate non-cli environment
        Environment::initialize(
            new ApplicationContext('Production'),
            Environment::isCli(),
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX'
        );

        \set_error_handler(
            static function (int $errno, string $errstr) use (&$errorMessage) {
                $errorMessage = $errstr;
                return true;
            },
            E_USER_DEPRECATED
        );

        new System();

        restore_error_handler();

        self::assertNull($errorMessage);
    }

    /**
     * @test
     */
    public function constructorDoesNotTriggerDeprecationNoticeForUsageOfUnsafeSeparatorIfNotRunningOnCli(): void
    {
        $errorMessage = null;

        // Switch to legacy (unsafe) key separator
        putenv('TYPO3_CONFIG_LOADER_USE_SAFE_SEPARATOR');

        // Simulate non-cli environment
        Environment::initialize(
            Environment::getContext(),
            false,
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX'
        );

        \set_error_handler(
            static function (int $errno, string $errstr) use (&$errorMessage) {
                $errorMessage = $errstr;
                return true;
            },
            E_USER_DEPRECATED
        );

        new System();

        restore_error_handler();

        self::assertNull($errorMessage);
    }

    /**
     * @test
     */
    public function constructorTriggersDeprecationNoticeForUsageOfUnsafeSeparatorIfRunningOnCliAndNotInProductionContext(): void
    {
        $errorMessage = null;

        // Switch to legacy (unsafe) key separator
        putenv('TYPO3_CONFIG_LOADER_USE_SAFE_SEPARATOR');

        // Simulate non-cli environment
        Environment::initialize(
            new ApplicationContext('Development'),
            true,
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX'
        );

        \set_error_handler(
            static function (int $errno, string $errstr) use (&$errorMessage) {
                $errorMessage = $errstr;
                return true;
            },
            E_USER_DEPRECATED
        );

        new System();

        restore_error_handler();

        self::assertSame(
            'Using an unsafe key separator for TYPO3_* environment variables is deprecated and will be ' .
            'removed with cpsit/typo3-config-loader v1.0. Please switch to `__` as key separator and specify the ' .
            'environment variable $TYPO3_CONFIG_LOADER_USE_SAFE_SEPARATOR=1 to enable the new behavior.',
            $errorMessage
        );
    }

    /**
     * @test
     */
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

    /**
     * @test
     */
    public function loadAllowsEnvironmentVariablesWithUnsafeKeySeparator(): void
    {
        $this->unsetEnvironmentVariablesConfiguration();

        // Switch to legacy (unsafe) key separator
        putenv('TYPO3_CONFIG_LOADER_USE_SAFE_SEPARATOR');

        $_ENV['TYPO3_FOO'] = 'baz';

        $this->runWithSuppressedDeprecations(static function () {
            $subject = new System();
            $subject->load();
        });

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

    /**
     * @test
     */
    public function loadSkipsEnvironmentVariableCreationIfConfigPathIsNotAvailable(): void
    {
        $this->unsetContextConfiguration();
        $this->unsetEnvFileConfiguration();

        (new System())->load();
        $expected = $this->backedUpEnvironmentVariables;
        unset($expected['ENV_FILE_PATH']);

        self::assertSame($expected, getenv());
    }

    /**
     * @test
     */
    public function loadSkipsEnvironmentVariableCreationIfConfigPathIsNotIterable(): void
    {
        $this->unsetContextConfiguration();
        $this->unsetEnvFileConfiguration();

        $GLOBALS['TYPO3_CONF_VARS']['CMS']['base'] = false;

        (new System())->load();
        $expected = $this->backedUpEnvironmentVariables;
        unset($expected['ENV_FILE_PATH']);

        self::assertSame($expected, getenv());
    }

    /**
     * @test
     */
    public function loadCachedData(): void
    {
        // Make sure proper configuration is set (TYPO3 10.4 compatibility)
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask'] = '0755';

        $cacheDir = Environment::getVarPath() . '/cache/data/typo3_config';
        self::assertFalse(file_exists($cacheDir));

        (new System())->loadCached();

        $cacheDir .= ('/' . (Environment::getContext()->isProduction() ? 'prod' : 'dev'));
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
            $GLOBALS['TYPO3_CONF_VARS']['CMS']
        );

        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask']);
    }

    /**
     * @test
     */
    public function loadCachedDataWithoutFileReader(): void
    {
        // Make sure proper configuration is set (TYPO3 10.4 compatibility)
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask'] = '0755';

        $cacheDir = Environment::getVarPath() . '/cache/data/typo3_config';
        self::assertFalse(file_exists($cacheDir));

        putenv('TYPO3__CMS__TEST=test');
        $_ENV['TYPO3__CMS__TEST'] = 'test';
        $this->unsetContextConfiguration();
        $this->unsetEnvFileConfiguration();
        (new System())->loadCached();

        $cacheDir .= '/' . (Environment::getContext()->isProduction() ? 'prod' : 'dev');
        self::assertFileExists($cacheDir . '/cached-config-' . md5('') . '.php');
        self::assertDirectoryExists($cacheDir);
        self::assertSame(['TEST' => 'test'], $GLOBALS['TYPO3_CONF_VARS']['CMS']);
        $this->restoreEnvironmentVariables();
        $this->unsetEnvironmentVariablesConfiguration();

        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask']);
    }

    /**
     * @return ConfigReaderInterface[]
     */
    private function getReadersFromSubject(?System $subject = null): array
    {
        $subject ??= $this->subject;

        $reflection = new \ReflectionObject($subject);
        $property = $reflection->getProperty('readers');
        $property->setAccessible(true);
        $readers = $property->getValue($subject);

        self::assertIsArray($readers);
        self::assertContainsOnlyInstancesOf(ConfigReaderInterface::class, $readers);

        return $readers;
    }

    private function runWithSuppressedDeprecations(callable $test): void
    {
        \set_error_handler(null, E_USER_DEPRECATED);

        $test();

        restore_error_handler();
    }

    protected function tearDown(): void
    {
        $this->restoreConfiguration();
        $this->restoreEnvironmentVariables();

        parent::tearDown();
    }
}
