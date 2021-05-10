<?php

/**
 * @package Pandora
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Pandora;

interface Provider
{
    /**
     * @phpstan-return array<class-string>
     */
    public static function getProvidedServices(): array;

    public function registerServices(Container $container): void;
}
