<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Model;

use Sylius\Component\Core\Model\ProductInterface as BaseProductInterface;

/**
 * Applied by the host application to its own Product entity, together with {@see ProductTrait}.
 *
 * The brand carried here is **derived state**: it is resolved from a product attribute value and
 * written by ProductBrandSynchronizer, never edited directly. See
 * docs/adr-log/0004-brand-resolution-from-a-product-attribute.md.
 */
interface ProductInterface extends BaseProductInterface
{
    public function getBrand(): ?BrandInterface;

    public function setBrand(?BrandInterface $brand): void;
}
