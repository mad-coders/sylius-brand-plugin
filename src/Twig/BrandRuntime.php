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
     * Every tile-displayable brand, indexed by id, loaded at most once per request.
     *
     * @var array<int, BrandInterface>|null
     */
    private ?array $tileBrands = null;

    public function __construct(
        private readonly BrandSettingsProviderInterface $settings,
        private readonly BrandRepositoryInterface $brandRepository,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->settings->isEnabled();
    }

    public function reset(): void
    {
        $this->tileBrands = null;
    }

    /**
     * @return array<array-key, BrandInterface>
     */
    public function getHomepageBrands(int $limit = self::DEFAULT_HOMEPAGE_LIMIT): array
    {
        if (!$this->settings->isEnabled()) {
            return [];
        }

        return $this->brandRepository->findAllForHomepage($this->localeContext->getLocaleCode(), $limit);
    }

    /**
     * The brand to show on a product tile, or null when there is nothing to show.
     *
     * This exists to kill an N+1. Reading `$product->getBrand()` in the template returns an
     * uninitialised Doctrine proxy, and touching anything on it beyond the identifier - the name,
     * the toggles - loads the brand, plus its translation, once per tile. On a 12-product listing
     * that is up to 24 extra queries.
     *
     * Getting the **identifier** off a proxy is free: Doctrine already has it, and does not
     * initialise for it. So the whole displayable set is loaded once (with translations and images
     * joined) and each tile is answered from that map. One query per page, regardless of how many
     * tiles there are, and brands that are disabled or not flagged for tiles simply are not in the
     * map - which is also the filtering the template would otherwise do by hand.
     */
    public function getTileBrand(mixed $product): ?BrandInterface
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

        $this->tileBrands ??= $this->brandRepository->findAllDisplayedOnProductTiles(
            $this->localeContext->getLocaleCode(),
        );

        return $this->tileBrands[$id] ?? null;
    }
}
