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

use CPSIT\Typo3ConfigLoader\Loader\Solr;
use CPSIT\Typo3ConfigLoader\Tests\Unit\VirtualConfigurationTrait;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * SolrTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 */
final class SolrTest extends UnitTestCase
{
    use VirtualConfigurationTrait;

    protected Solr $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initializeVirtualConfiguration();

        $this->subject = new Solr();
    }

    /**
     * @test
     */
    public function loadSkipsEnvironmentVariableCreationIfConfigPathIsNotAvailable(): void
    {
        $this->subject->load();

        self::assertSame($this->backedUpEnvironmentVariables, getenv());
    }

    /**
     * @test
     */
    public function loadCreatesEnvironmentVariablesFromConfiguration(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['CMS']['solr'] = [
            'scheme' => 'https',
            'host' => 'localhost',
            'port' => '8983',
            'path_read' => '/solr',
            'path' => [
                '1' => [
                    'de' => '/solr/core_de',
                    'en' => '/solr/core_en',
                ],
            ],
        ];

        $this->subject->load();

        self::assertNotSame($this->backedUpEnvironmentVariables, getenv());
        self::assertSame('https', getenv('PHP_SOLR_SCHEME_READ'));
        self::assertSame('localhost', getenv('PHP_SOLR_HOST_READ'));
        self::assertSame('8983', getenv('PHP_SOLR_PORT_READ'));
        self::assertSame('/solr', getenv('PHP_SOLR_PATH_READ'));
        self::assertSame('/solr/core_de', getenv('PHP_SOLR_CORE_READ_1_DE'));
        self::assertSame('/solr/core_en', getenv('PHP_SOLR_CORE_READ_1_EN'));
    }

    protected function tearDown(): void
    {
        $this->restoreConfiguration();
        $this->restoreEnvironmentVariables();

        parent::tearDown();
    }
}
