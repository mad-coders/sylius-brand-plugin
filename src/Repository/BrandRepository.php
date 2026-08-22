<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class BrandRepository extends EntityRepository implements BrandRepositoryInterface
{
    public function findOneByCode(string $code): ?BrandInterface
    {
        /** @var BrandInterface|null $brand */
        $brand = $this->createQueryBuilder('o')
            ->andWhere('o.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $brand;
    }

    /**
     * The admin grid's query builder.
     *
     * A translatable grid cannot use the plain doctrine/orm driver: sorting and filtering on
     * `translation.name` need the translation joined under exactly that alias. This is the same
     * shape as Sylius' own `ProductOptionRepository::createListQueryBuilder()`, and
     * config/grids/admin/brand.yaml points at it.
     *
     * LEFT, not INNER: a brand that has just been created in one locale must still appear in the
     * grid when the admin switches to another.
     */
    public function createListQueryBuilder(string $localeCode): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->addSelect('translation')
            ->leftJoin('o.translations', 'translation', 'WITH', 'translation.locale = :localeCode')
            ->setParameter('localeCode', $localeCode)
        ;
    }

    public function findOneEnabledBySlug(string $slug, string $localeCode): ?BrandInterface
    {
        /** @var BrandInterface|null $brand */
        $brand = $this->createQueryBuilder('o')
            ->addSelect('translation')
            ->addSelect('image')
            ->innerJoin('o.translations', 'translation', 'WITH', 'translation.locale = :localeCode')
            ->leftJoin('o.images', 'image')
            ->andWhere('translation.slug = :slug')
            ->andWhere('o.enabled = true')
            ->setParameter('slug', $slug)
            ->setParameter('localeCode', $localeCode)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $brand;
    }

    public function findAllForOverview(string $localeCode): array
    {
        /** @var array<array-key, BrandInterface> $brands */
        $brands = $this->createEnabledListQueryBuilder($localeCode)
            ->andWhere('o.displayOnBrandOverview = true')
            ->getQuery()
            ->getResult()
        ;

        return $brands;
    }

    public function findAllForHomepage(string $localeCode, ?int $limit = null): array
    {
        $queryBuilder = $this->createEnabledListQueryBuilder($localeCode)
            ->andWhere('o.displayOnHomepage = true')
        ;

        if (null === $limit) {
            /** @var array<array-key, BrandInterface> $brands */
            $brands = $queryBuilder->getQuery()->getResult();

            return $brands;
        }

        // setMaxResults cannot be combined with the image fetch-join: LIMIT applies to *rows*, and
        // a brand with two images would eat two of them. So the limit is applied to ids first, and
        // the fetch-join query then runs against that fixed set. Two cheap queries beat one wrong
        // one - and still beat the N+1 that lazy-loading the logos would cause.
        $ids = array_column(
            $this->createEnabledListQueryBuilder($localeCode, withImages: false)
                ->andWhere('o.displayOnHomepage = true')
                ->select('o.id')
                ->setMaxResults($limit)
                ->getQuery()
                ->getScalarResult(),
            'id',
        );

        if ([] === $ids) {
            return [];
        }

        /** @var array<array-key, BrandInterface> $brands */
        $brands = $this->createEnabledListQueryBuilder($localeCode)
            ->andWhere('o.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult()
        ;

        return $brands;
    }

    public function findAllDisplayedOnProductTiles(string $localeCode): array
    {
        /** @var array<array-key, BrandInterface> $brands */
        $brands = $this->createEnabledListQueryBuilder($localeCode)
            ->andWhere('o.displayOnProductTile = true')
            ->getQuery()
            ->getResult()
        ;

        $indexed = [];

        foreach ($brands as $brand) {
            $id = $brand->getId();

            if (null !== $id) {
                $indexed[$id] = $brand;
            }
        }

        return $indexed;
    }

    /**
     * Enabled brands with their translation for the given locale, and their images, eagerly joined.
     *
     * The translation join is an INNER join on purpose: a brand with no translation in the
     * shopper's locale has no name and no slug, so it has nothing to render and nowhere to link to.
     * Listing it would produce a blank tile pointing at a 404.
     *
     * The images are fetch-joined because every listing renders the logo. Without it, `getLogo()`
     * lazy-loads once per brand - one extra query per tile on the overview page.
     */
    private function createEnabledListQueryBuilder(string $localeCode, bool $withImages = true): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('o')
            ->addSelect('translation')
            ->innerJoin('o.translations', 'translation', 'WITH', 'translation.locale = :localeCode')
            ->andWhere('o.enabled = true')
            ->setParameter('localeCode', $localeCode)
            ->addOrderBy('o.position', 'ASC')
            ->addOrderBy('translation.name', 'ASC')
        ;

        if ($withImages) {
            $queryBuilder
                ->addSelect('image')
                ->leftJoin('o.images', 'image')
            ;
        }

        return $queryBuilder;
    }
}
