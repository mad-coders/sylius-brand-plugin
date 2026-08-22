<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The shop-side templates' entry point into the plugin.
 *
 * Split into an extension and a runtime so that registering the extension does not instantiate the
 * repository and the settings provider on every request - most pages never call either.
 */
final class BrandExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('madcoders_brands_enabled', [BrandRuntime::class, 'isEnabled']),
            new TwigFunction('madcoders_homepage_brands', [BrandRuntime::class, 'getHomepageBrands']),
            new TwigFunction('madcoders_tile_brand', [BrandRuntime::class, 'getTileBrand']),
        ];
    }
}
