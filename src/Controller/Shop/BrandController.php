<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Controller\Shop;

use Madcoders\SyliusBrandPlugin\Provider\BrandSettingsProviderInterface;
use Madcoders\SyliusBrandPlugin\Repository\BrandRepositoryInterface;
use Madcoders\SyliusBrandPlugin\Repository\ProductByBrandRepositoryInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * The brand overview and the per-brand product listing.
 *
 * A plain controller rather than a Sylius resource controller: both actions need the channel, the
 * locale and a product pager, none of which the CRUD controller has a concept of. See
 * docs/adr-log/0001-sylius-plugin-resource-model.md, rule 2.
 */
final readonly class BrandController
{
    private const int PRODUCTS_PER_PAGE = 12;

    public function __construct(
        private Environment $twig,
        private BrandRepositoryInterface $brandRepository,
        private ProductByBrandRepositoryInterface $productByBrandRepository,
        private BrandSettingsProviderInterface $settings,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
    ) {
    }

    public function indexAction(): Response
    {
        $this->assertFeatureIsEnabled();

        return new Response($this->twig->render('@MadcodersSyliusBrandPlugin/shop/brand/index.html.twig', [
            'brands' => $this->brandRepository->findAllForOverview($this->localeContext->getLocaleCode()),
        ]));
    }

    public function showAction(Request $request, string $slug): Response
    {
        $this->assertFeatureIsEnabled();

        $localeCode = $this->localeContext->getLocaleCode();
        $brand = $this->brandRepository->findOneEnabledBySlug($slug, $localeCode);

        // The overview toggle gates the brand's own page too: a brand deliberately kept off the
        // index must not be reachable by guessing its slug. See
        // docs/adr-log/0008-display-toggles-per-brand.md.
        if (null === $brand || !$brand->isDisplayOnBrandOverview()) {
            throw new NotFoundHttpException(\sprintf('No brand found for slug "%s".', $slug));
        }

        $products = new Pagerfanta(new QueryAdapter(
            $this->productByBrandRepository->createShopListQueryBuilder($brand, $this->getChannel(), $localeCode),
        ));
        $products->setMaxPerPage(self::PRODUCTS_PER_PAGE);
        $products->setCurrentPage(max(1, $request->query->getInt('page', 1)));

        return new Response($this->twig->render('@MadcodersSyliusBrandPlugin/shop/brand/show.html.twig', [
            'brand' => $brand,
            'products' => $products,
        ]));
    }

    /**
     * A disabled feature must look like a feature that was never installed, so the routes 404
     * rather than rendering an empty page - an empty "Brands" page in a sitemap is worse than no
     * page at all.
     */
    private function assertFeatureIsEnabled(): void
    {
        if (!$this->settings->isEnabled()) {
            throw new NotFoundHttpException('The brand feature is disabled.');
        }
    }

    private function getChannel(): ChannelInterface
    {
        $channel = $this->channelContext->getChannel();

        if (!$channel instanceof ChannelInterface) {
            throw new NotFoundHttpException('No Sylius channel could be resolved for this request.');
        }

        return $channel;
    }
}
