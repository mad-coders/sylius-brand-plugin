<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * @see ProductByBrandRepositoryInterface
 *
 * Queries `brand_id` directly rather than reaching back through the product attribute value. That
 * is the whole point of denormalising it - see
 * docs/adr-log/0004-brand-resolution-from-a-product-attribute.md.
 */
final readonly class ProductByBrandRepository implements ProductByBrandRepositoryInterface
{
    /**
     * @param class-string $productClass
     * @param class-string $productVariantClass
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $productClass,
        private string $productVariantClass,
    ) {
    }

    public function createShopListQueryBuilder(
        BrandInterface $brand,
        ChannelInterface $channel,
        string $localeCode,
    ): QueryBuilder {
        return $this->createBaseQueryBuilder($brand, $channel)
            ->addSelect('translation')
            // LEFT, unlike the brand listing: a product with no translation in this locale still
            // has a price and an image, and Sylius' own product card falls back gracefully. An
            // INNER join here would silently drop products from the brand's listing.
            ->leftJoin('o.translations', 'translation', 'WITH', 'translation.locale = :localeCode')
            ->setParameter('localeCode', $localeCode)
            ->addOrderBy('translation.name', 'ASC')
            ->addOrderBy('o.id', 'ASC')
        ;
    }

    public function countForShop(BrandInterface $brand, ChannelInterface $channel): int
    {
        /** @var int|string $count */
        $count = $this->createBaseQueryBuilder($brand, $channel)
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $count;
    }

    public function countAllPerBrand(): array
    {
        /** @var list<array{brandId: int|string|null, total: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(o.brand) AS brandId', 'COUNT(o.id) AS total')
            ->from($this->productClass, 'o')
            ->andWhere('o.brand IS NOT NULL')
            ->groupBy('o.brand')
            ->getQuery()
            ->getScalarResult()
        ;

        $counts = [];

        foreach ($rows as $row) {
            if (null !== $row['brandId']) {
                $counts[(int) $row['brandId']] = (int) $row['total'];
            }
        }

        return $counts;
    }

    private function createBaseQueryBuilder(BrandInterface $brand, ChannelInterface $channel): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('o')
            ->from($this->productClass, 'o')
            ->andWhere('o.brand = :brand')
            ->andWhere('o.enabled = :enabled')
            ->andWhere(':channel MEMBER OF o.channels')
            // Sylius' product card resolves a variant to price the product and throws
            // "Product has no variants" when it cannot - which would take the whole brand page
            // down over a single malformed product. A brand page is a landing page; it has to
            // survive a bad row in the catalogue, so variantless products are filtered out here
            // rather than blowing up in the template.
            //
            // EXISTS rather than a join: joining variants multiplies rows and would corrupt the
            // pager's count.
            ->andWhere(\sprintf(
                'EXISTS (SELECT 1 FROM %s variant WHERE variant.product = o AND variant.enabled = true)',
                $this->productVariantClass,
            ))
            ->setParameter('brand', $brand)
            ->setParameter('enabled', true)
            ->setParameter('channel', $channel)
        ;
    }
}
