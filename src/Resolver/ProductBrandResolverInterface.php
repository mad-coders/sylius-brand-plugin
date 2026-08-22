<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Resolver;

use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * Turns a product into the brand it belongs to, by reading the configured product attribute and
 * routing its value through the configured mapping.
 *
 * Total and deterministic: it never throws for a catalogue it does not understand, and it returns
 * null rather than guessing. See docs/adr-log/0004-brand-resolution-from-a-product-attribute.md.
 */
interface ProductBrandResolverInterface
{
    public function resolve(ProductInterface $product): ?BrandInterface;
}
