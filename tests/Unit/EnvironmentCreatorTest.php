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

use CPSIT\Typo3ConfigLoader\EnvironmentCreator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * EnvironmentCreatorTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 */
final class EnvironmentCreatorTest extends UnitTestCase
{
    /**
     * @var EnvironmentCreator|object
     */
    protected object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new class () {
            use EnvironmentCreator;

            protected function getEnvironmentPrefix(): string
            {
                return 'FOO_';
            }
        };
    }

    /**
     * @test
     */
    public function mapConfigToEnvironmentReturnsEarlyIfConfigOfGivenPathDoesNotExist(): void
    {
        $reflectionMethod = $this->getAccessibleMethod('mapConfigToEnvironment');
        $expected = getenv();

        $reflectionMethod->invoke($this->subject, [], 'foo/baz', 'baz');

        self::assertSame($expected, getenv());
    }

    /**
     * @test
     */
    public function mapConfigToEnvironmentCreatesEnvironmentVariableForGivenConfig(): void
    {
        $reflectionMethod = $this->getAccessibleMethod('mapConfigToEnvironment');

        $reflectionMethod->invoke($this->subject, ['foo' => ['baz' => 'hello world!']], 'foo/baz', 'baz');

        self::assertSame('hello world!', getenv('FOO_BAZ'));
    }

    /**
     * @test
     */
    public function createEnvironmentVariableCreatesEnvironmentVariable(): void
    {
        $reflectionMethod = $this->getAccessibleMethod('createEnvironmentVariable');

        $reflectionMethod->invoke($this->subject, 'baz', 'hello world!');

        self::assertSame('hello world!', getenv('FOO_BAZ'));
    }

    private function getAccessibleMethod(string $methodName): \ReflectionMethod
    {
        $reflection = new \ReflectionObject($this->subject);
        $reflectionMethod = $reflection->getMethod($methodName);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod;
    }
}
