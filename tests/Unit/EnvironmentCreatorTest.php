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

use CPSIT\Typo3ConfigLoader\EnvironmentCreator;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * EnvironmentCreatorTest.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 */
#[CoversTrait(EnvironmentCreator::class)]
final class EnvironmentCreatorTest extends UnitTestCase
{
    /**
     * @var EnvironmentCreator|object
     */
    private object $subject;

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
