<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Resolver;

use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The **only** writer of the denormalised brand on a product.
 *
 * See docs/adr-log/0004-brand-resolution-from-a-product-attribute.md - if a new write path for
 * products appears, it either calls this or is covered by `madcoders:brand:resync-products`.
 *
 * Extends ResetInterface because resolution memoises brands, and a caller that clears the entity
 * manager - as any batch job must - has to be able to say so. Calling reset() is never wrong; not
 * calling it after a clear() is.
 */
interface ProductBrandSynchronizerInterface extends ResetInterface
{
    /**
     * Resolves the product's brand and writes it if it differs.
     *
     * Does not flush - the caller owns the unit of work.
     *
     * @return bool whether the product's brand actually changed
     */
    public function synchronize(ProductInterface $product): bool;
}
