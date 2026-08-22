<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

interface BrandRepositoryInterface extends RepositoryInterface
{
    /**
     * The admin grid's query builder, with the translation for the given locale joined as
     * `translation` so the grid can sort and filter on it.
     */
    public function createListQueryBuilder(string $localeCode): QueryBuilder;

    public function findOneByCode(string $code): ?BrandInterface;

    /**
     * Enabled brand with this slug in this locale, or null. Does **not** apply the overview
     * toggle - the caller decides whether an unlisted brand is reachable, because the admin
     * preview and the shop page answer that differently.
     */
    public function findOneEnabledBySlug(string $slug, string $localeCode): ?BrandInterface;

    /**
     * Enabled brands listed on the overview page, ordered by position then name.
     *
     * @return array<array-key, BrandInterface>
     */
    public function findAllForOverview(string $localeCode): array;

    /**
     * Enabled brands shown in the homepage strip, ordered by position then name.
     *
     * @param int|null $limit null means no limit
     *
     * @return array<array-key, BrandInterface>
     */
    public function findAllForHomepage(string $localeCode, ?int $limit = null): array;

    /**
     * Every enabled brand that may appear on a product tile, indexed by id.
     *
     * Indexed by id because that is what a product carries: reading `$product->getBrand()->getId()`
     * on an uninitialised Doctrine proxy costs nothing, so a tile can find its brand in this map
     * without ever loading the brand itself. That is what turns one query per tile into one query
     * per page - see BrandRuntime::getTileBrand().
     *
     * @return array<int, BrandInterface>
     */
    public function findAllDisplayedOnProductTiles(string $localeCode): array;
}
