<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\EventListener;

use Madcoders\SyliusBrandPlugin\Resolver\ProductBrandSynchronizerInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Keeps `sylius_product.brand_id` in step with the product's brand attribute whenever a product is
 * saved through the resource layer - which is every admin edit.
 *
 * Deliberately a resource event listener rather than a Doctrine lifecycle listener: the brand comes
 * from the product's *attribute values*, which are separate entities cascaded from the product.
 * During `prePersist`/`preUpdate` on the product those are not reliably in their final state, and
 * changing an association from inside a flush is exactly the kind of write Doctrine warns about.
 * The resource event fires before the flush, with the whole aggregate assembled.
 *
 * Imports and other non-resource writes are covered by `madcoders:brand:resync-products` - see
 * docs/adr-log/0004-brand-resolution-from-a-product-attribute.md.
 */
final readonly class SynchronizeProductBrandListener
{
    public function __construct(
        private ProductBrandSynchronizerInterface $synchronizer,
    ) {
    }

    public function __invoke(GenericEvent $event): void
    {
        $product = $event->getSubject();

        if (!$product instanceof ProductInterface) {
            return;
        }

        $this->synchronizer->synchronize($product);
    }
}
