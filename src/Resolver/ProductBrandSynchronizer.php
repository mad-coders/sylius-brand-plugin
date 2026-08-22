<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Resolver;

use Madcoders\SyliusBrandPlugin\Model\ProductInterface as BrandAwareProductInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @see ProductBrandSynchronizerInterface
 */
final readonly class ProductBrandSynchronizer implements ProductBrandSynchronizerInterface
{
    public function __construct(
        private ProductBrandResolverInterface $resolver,
    ) {
    }

    public function reset(): void
    {
        if ($this->resolver instanceof ResetInterface) {
            $this->resolver->reset();
        }
    }

    public function synchronize(ProductInterface $product): bool
    {
        // A host application that has not applied ProductTrait has nowhere to store the brand.
        // That is a valid intermediate state during installation, and it must not break saving a
        // product.
        if (!$product instanceof BrandAwareProductInterface) {
            return false;
        }

        $resolved = $this->resolver->resolve($product);
        $current = $product->getBrand();

        if ($resolved === $current) {
            return false;
        }

        // Comparing identity is not enough across unit-of-work boundaries: the resolver hands back
        // a freshly loaded brand, while the product may hold a proxy for the same row.
        if (null !== $resolved && null !== $current && $resolved->getCode() === $current->getCode()) {
            return false;
        }

        $product->setBrand($resolved);

        return true;
    }
}
