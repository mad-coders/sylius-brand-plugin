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
     * Displayable brands per surface, indexed by id, each surface loaded at most once per request.
     *
     * @var array<string, array<int, BrandInterface>>
     */
    private array $brandsBySurface = [];

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
        $this->brandsBySurface = [];
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
     * initialise for it. So the displayable set for the surface is loaded once, with translations
     * and images joined, and every row is answered from that map. One query per surface per
     * request, however many rows there are.
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

        $this->brandsBySurface[$surface] ??= $this->brandRepository->findAllDisplayedOn(
            $surface,
            $this->localeContext->getLocaleCode(),
        );

        return $this->brandsBySurface[$surface][$id] ?? null;
    }
}
