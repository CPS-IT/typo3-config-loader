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

namespace CPSIT\Typo3ConfigLoader\Loader;

/**
 * CacheableConfigurationLoader.
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-3.0-or-later
 */
interface CacheableConfigurationLoader extends ConfigurationLoader
{
    /**
     * Load configuration from cache and apply it to the global configuration.
     *
     * Reads all configuration provided by this class and applies it to the
     * globally available configuration. The parsed data will be cached in
     * order to allow further loading from cache instead of parsing the real
     * data.
     */
    public function loadCached(): void;
}
