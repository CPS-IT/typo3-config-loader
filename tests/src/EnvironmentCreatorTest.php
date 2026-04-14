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

namespace CPSIT\Typo3ConfigLoader\Tests;

use CPSIT\Typo3ConfigLoader\EnvironmentCreator;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * EnvironmentCreatorTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
#[CoversTrait(EnvironmentCreator::class)]
final class EnvironmentCreatorTest extends TestCase
{
    /**
     * @var EnvironmentCreator|object
     */
    private object $subject;

    protected function setUp(): void
    {
        $this->subject = new class () {
            use EnvironmentCreator;

            protected function getEnvironmentPrefix(): string
            {
                return 'FOO_';
            }
        };
    }

    #[Test]
    public function mapConfigToEnvironmentReturnsEarlyIfConfigOfGivenPathDoesNotExist(): void
    {
        $expected = getenv();

        $reflectionMethod = $this->getReflectionMethod('mapConfigToEnvironment');
        $reflectionMethod->invoke($this->subject, [], 'foo/baz', 'baz');

        self::assertSame($expected, getenv());
    }

    #[Test]
    public function mapConfigToEnvironmentReturnsEarlyIfValueOfGivenPathIsNotScalar(): void
    {
        $expected = getenv();

        $reflectionMethod = $this->getReflectionMethod('mapConfigToEnvironment');
        $reflectionMethod->invoke($this->subject, ['foo' => ['baz' => new \stdClass()]], 'foo/baz', 'baz');

        self::assertSame($expected, getenv());
    }

    #[Test]
    public function mapConfigToEnvironmentCreatesEnvironmentVariableForGivenConfig(): void
    {
        $reflectionMethod = $this->getReflectionMethod('mapConfigToEnvironment');
        $reflectionMethod->invoke($this->subject, ['foo' => ['baz' => 'hello world!']], 'foo/baz', 'baz');

        self::assertSame('hello world!', getenv('FOO_BAZ'));
    }

    #[Test]
    public function createEnvironmentVariableCreatesEnvironmentVariable(): void
    {
        $reflectionMethod = $this->getReflectionMethod('createEnvironmentVariable');
        $reflectionMethod->invoke($this->subject, 'baz', 'hello world!');

        self::assertSame('hello world!', getenv('FOO_BAZ'));
    }

    private function getReflectionMethod(string $methodName): \ReflectionMethod
    {
        $reflection = new \ReflectionObject($this->subject);

        return $reflection->getMethod($methodName);
    }
}
