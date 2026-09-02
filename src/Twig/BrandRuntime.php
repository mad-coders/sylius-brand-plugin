<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Twig;

use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Madcoders\SyliusBrandPlugin\Model\ProductInterface as BrandAwareProductInterface;
use Madcoders\SyliusBrandPlugin\Provider\BrandSettingsProviderInterface;
use Madcoders\SyliusBrandPlugin\Repository\BrandRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * @see BrandExtension
 */
final class BrandRuntime implements RuntimeExtensionInterface, ResetInterface
{
    private const int DEFAULT_HOMEPAGE_LIMIT = 12;

    /**
     * Brands already resolved this request, per surface, indexed by id.
     *
     * A null value is a cached miss - the brand exists but is not displayable on that surface - and
     * is as much worth remembering as a hit.
     *
     * @var array<string, array<int, BrandInterface|null>>
     */
    private array $brandsBySurface = [];

    public function __construct(
        private readonly BrandSettingsProviderInterface $settings,
        private readonly BrandRepositoryInterface $brandRepository,
        private readonly LocaleContextInterface $localeContext,
        private readonly int $homepageLimit = self::DEFAULT_HOMEPAGE_LIMIT,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settings->isEnabled();
    }

    public function reset(): void
    {
        $this->brandsBySurface = [];
    }

    /**
     * @return array<array-key, BrandInterface>
     */
    public function getHomepageBrands(?int $limit = null): array
    {
        if (!$this->settings->isEnabled()) {
            return [];
        }

        // Null rather than a literal default: an explicit limit from the caller still wins, but a
        // template that asks for "the usual number" gets whatever the application configured in
        // `madcoders_sylius_brand.homepage_brands_limit`.
        return $this->brandRepository->findAllForHomepage(
            $this->localeContext->getLocaleCode(),
            $limit ?? $this->homepageLimit,
        );
    }

    /**
     * The brand to show for a product on a given surface, or null when there is nothing to show.
     *
     * The public entry point for host applications putting brands on their own listings - see the
     * README. It applies every rule the templates would otherwise have to repeat: the feature
     * toggle, the brand's own enabled flag, and the display toggle for that surface. Pass
     * `BrandInterface::SURFACE_ANY` to ignore the surface toggles.
     *
     * It also exists to kill an N+1. Reading `$product->getBrand()` in a template returns an
     * uninitialised Doctrine proxy, and touching anything on it beyond the identifier - the name,
     * the toggles - loads the brand *and* its translation, once per row. On a 12-product listing
     * that is up to 24 extra queries.
     *
     * Getting the **identifier** off a proxy is free: Doctrine already has it and does not
     * initialise for it. So the brand is loaded by id, with its translation and images joined, and
     * memoised for the rest of the request - a listing that repeats the same brand across rows
     * costs one query, not one per row.
     *
     * Loading by id rather than loading every displayable brand keeps the work proportional to the
     * page instead of to the brand table: a catalogue with several thousand brands would otherwise
     * hydrate all of them, with translations and images, to answer twelve tiles.
     *
     * @param string $surface one of BrandInterface::SURFACE_*
     */
    public function getBrandFor(mixed $product, string $surface = BrandInterface::SURFACE_PRODUCT_TILE): ?BrandInterface
    {
        if (!$product instanceof BrandAwareProductInterface || !$this->settings->isEnabled()) {
            return null;
        }

        $brand = $product->getBrand();

        if (null === $brand) {
            return null;
        }

        // Identifier only - deliberately nothing else, or the proxy initialises and the whole
        // point of this method is lost.
        $id = $brand->getId();

        if (null === $id) {
            return null;
        }

        // array_key_exists, not ??=: a brand that is not displayable on this surface caches as
        // null, and `??=` would re-query it on every row that references it.
        if (!\array_key_exists($id, $this->brandsBySurface[$surface] ?? [])) {
            $found = $this->brandRepository->findDisplayedOnByIds(
                $surface,
                $this->localeContext->getLocaleCode(),
                [$id],
            );

            $this->brandsBySurface[$surface][$id] = $found[$id] ?? null;
        }

        return $this->brandsBySurface[$surface][$id];
    }
}
