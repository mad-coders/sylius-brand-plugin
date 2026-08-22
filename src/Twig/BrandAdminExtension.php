<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Admin-only Twig functions, kept separate from BrandExtension so the shop never pays for them.
 */
final class BrandAdminExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('madcoders_brand_product_count', [BrandAdminRuntime::class, 'getProductCount']),
            new TwigFunction('madcoders_brand_diagnostics', [BrandAdminRuntime::class, 'getDiagnostics']),
        ];
    }
}
