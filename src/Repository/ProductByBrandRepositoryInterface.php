<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Reads the products of a brand.
 *
 * This is deliberately *not* a method on the Sylius product repository: decorating it would force
 * every host application that already replaces `sylius.repository.product` to reconcile with the
 * plugin. A separate read model has no such collision.
 */
interface ProductByBrandRepositoryInterface
{
    /**
     * Enabled, in-channel products of the brand, with their translation for the locale joined and
     * ordered by name. Returned as a query builder so the caller can paginate it.
     */
    public function createShopListQueryBuilder(
        BrandInterface $brand,
        ChannelInterface $channel,
        string $localeCode,
    ): QueryBuilder;

    /** How many enabled, in-channel products the brand has. */
    public function countForShop(BrandInterface $brand, ChannelInterface $channel): int;

    /**
     * How many products point at each brand, indexed by brand id, across every channel and
     * regardless of the enabled flag.
     *
     * One query for the whole set rather than a count per brand: the admin grid asks for this on
     * every row, and the delete confirmation asks for it again. Brands with no products are absent
     * from the result.
     *
     * @return array<int, int>
     */
    public function countAllPerBrand(): array;
}
