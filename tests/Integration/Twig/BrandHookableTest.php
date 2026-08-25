<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Integration\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Madcoders\SyliusBrandPlugin\Model\ProductInterface as BrandAwareProductInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Formatter\StringInflector;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Test\Services\DefaultChannelFactoryInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Sylius\TwigHooks\Bag\DataBag;
use Sylius\TwigHooks\Bag\ScalarDataBag;
use Sylius\TwigHooks\Hook\Metadata\HookMetadata;
use Sylius\TwigHooks\Hookable\Metadata\HookableMetadata;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * The plugin's shop brand hookable is public API: a host application attaches it to its own hooks
 * and drives it through `hookable_metadata.configuration`. That contract is only meaningful if the
 * configuration keys actually work, and the plugin's own hooks exercise just two of them - so the
 * rest are covered here, rendering the real template through the real Twig environment.
 */
final class BrandHookableTest extends KernelTestCase
{
    private const TEMPLATE = '@MadcodersSyliusBrandPlugin/shop/product/brand.html.twig';

    private Environment $twig;

    private EntityManagerInterface $entityManager;

    private string $localeCode = 'en_US';

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->twig = $container->get('twig');
        $this->entityManager = $container->get('doctrine.orm.entity_manager');

        // Everything this test writes is rolled back in tearDown, so it can safely create the
        // channel and locale it needs rather than borrowing whatever the database happens to hold.
        // Depending on ambient fixtures made this suite pass or fail purely on what had run before
        // it - Behat purges between scenarios, so a phpunit run after one saw an empty database.
        $this->entityManager->beginTransaction();

        $this->provideChannelAndLocale();
        $this->enableFeature();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();

        parent::tearDown();
    }

    public function testItRendersTheBrandForAProductUnderTheDefaultContextKey(): void
    {
        $product = $this->createProductWithBrand('nike-default', 'Nike');

        $output = $this->render(['product' => $product]);

        self::assertStringContainsString('nike-default', $output);
        self::assertStringContainsString('Nike', $output);
    }

    public function testItFindsTheProductUnderAConfiguredContextKey(): void
    {
        // The one option the plugin's own hooks never exercise: a hook whose context calls the
        // product something else.
        $product = $this->createProductWithBrand('nike-item', 'Nike');

        $output = $this->render(['item' => $product], ['context_key' => 'item']);

        self::assertStringContainsString('nike-item', $output);
    }

    public function testItRendersNothingWhenTheConfiguredContextKeyHoldsNoProduct(): void
    {
        $product = $this->createProductWithBrand('nike-missing', 'Nike');

        // The product is there, but not under the key the hook was told to look at.
        $output = $this->render(['product' => $product], ['context_key' => 'somewhere_else']);

        self::assertSame('', trim($output));
    }

    public function testItRespectsTheSurfaceToggle(): void
    {
        $product = $this->createProductWithBrand('nike-surface', 'Nike', displayOnProductTile: false);

        self::assertSame('', trim($this->render(['product' => $product], ['surface' => 'product_tile'])));

        // `any` ignores the per-surface toggles; the brand's own enabled flag still applies.
        self::assertStringContainsString(
            'nike-surface',
            $this->render(['product' => $product], ['surface' => 'any']),
        );
    }

    public function testItRendersNothingForADisabledBrandWhateverTheSurface(): void
    {
        $product = $this->createProductWithBrand('nike-disabled', 'Nike', enabled: false);

        self::assertSame('', trim($this->render(['product' => $product], ['surface' => 'any'])));
    }

    public function testItRendersNothingForAProductWithoutABrand(): void
    {
        $product = $this->createProduct('NO_BRAND');

        self::assertSame('', trim($this->render(['product' => $product])));
    }

    public function testTheLinkOptionSwitchesBetweenAnAnchorAndPlainText(): void
    {
        $product = $this->createProductWithBrand('nike-link', 'Nike');

        self::assertStringContainsString('<a ', $this->render(['product' => $product], ['link' => true]));
        self::assertStringNotContainsString('<a ', $this->render(['product' => $product], ['link' => false]));
    }

    public function testTheLabelOptionAddsTheTranslatedPrefix(): void
    {
        $product = $this->createProductWithBrand('nike-label', 'Nike');

        self::assertStringNotContainsString('Brand:', $this->render(['product' => $product]));
        self::assertStringContainsString('Brand:', $this->render(['product' => $product], ['label' => true]));
    }

    public function testTheClassOptionReachesTheWrapper(): void
    {
        $product = $this->createProductWithBrand('nike-class', 'Nike');

        self::assertStringContainsString(
            'my-own-wrapper',
            $this->render(['product' => $product], ['class' => 'my-own-wrapper']),
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $configuration
     */
    private function render(array $context, array $configuration = []): string
    {
        $metadata = new HookableMetadata(
            new HookMetadata('test.hook', new DataBag($context)),
            new DataBag($context),
            new ScalarDataBag($configuration),
        );

        return $this->twig->render(self::TEMPLATE, array_merge($context, ['hookable_metadata' => $metadata]));
    }

    private function createProductWithBrand(
        string $brandCode,
        string $brandName,
        bool $enabled = true,
        bool $displayOnProductTile = true,
    ): ProductInterface {
        /** @var ExampleFactoryInterface<BrandInterface> $brandFactory */
        $brandFactory = self::getContainer()->get('madcoders_sylius_brand.fixture.example_factory.brand');

        $brand = $brandFactory->create([
            'code' => $brandCode,
            'name' => $brandName,
            'enabled' => $enabled,
            'display_on_product_tile' => $displayOnProductTile,
        ]);

        // The example factory translates into every locale the store has; make sure the one the
        // template reads is among them even on a store that has only just been given a locale.
        $brand->setCurrentLocale($this->localeCode);
        $brand->setFallbackLocale($this->localeCode);
        $brand->setName($brandName);

        $this->entityManager->persist($brand);

        $product = $this->createProduct(StringInflector::nameToUppercaseCode($brandCode));
        self::assertInstanceOf(BrandAwareProductInterface::class, $product);
        $product->setBrand($brand);

        $this->entityManager->flush();

        return $product;
    }

    private function createProduct(string $code): ProductInterface
    {
        /** @var FactoryInterface<ProductInterface> $factory */
        $factory = self::getContainer()->get('sylius.factory.product');

        $product = $factory->createNew();
        $product->setCode($code);
        $product->setCurrentLocale($this->localeCode);
        $product->setFallbackLocale($this->localeCode);
        $product->setName($code);
        $product->setSlug(StringInflector::nameToSlug($code));
        $product->setEnabled(true);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    /**
     * Ensures there is a channel and a locale, because the plugin reads both: the feature toggle is
     * per channel, and the displayable-brand query is per locale.
     */
    private function provideChannelAndLocale(): void
    {
        $container = self::getContainer();

        /** @var LocaleInterface|null $locale */
        $locale = $container->get('sylius.repository.locale')->findOneBy([]);

        /** @var ChannelInterface|null $channel */
        $channel = $container->get('sylius.repository.channel')->findOneBy([]);

        if (null !== $locale && null !== $channel) {
            $this->localeCode = (string) $locale->getCode();

            return;
        }

        // Sylius' own minimal channel setup - the same one its Behat suite uses - rather than a
        // hand-rolled one that would drift from whatever Channel requires next.
        /** @var DefaultChannelFactoryInterface $factory */
        $factory = $container->get('sylius.behat.factory.default_channel');
        $created = $factory->create();

        $this->entityManager->flush();

        /** @var LocaleInterface $createdLocale */
        $createdLocale = $created['locale'];
        $this->localeCode = (string) $createdLocale->getCode();
    }

    private function enableFeature(): void
    {
        $container = self::getContainer();
        $registry = $container->get('monsieurbiz.settings.registry');
        $settings = $registry->getByAlias('madcoders_brand.default');
        self::assertNotNull($settings);

        ['vendor' => $vendor, 'plugin' => $plugin] = $settings->getAliasAsArray();

        $provider = $container->get('MonsieurBiz\SyliusSettingsPlugin\Provider\SettingProvider');
        $setting = $provider->getSettingOrCreateNew($vendor, $plugin, 'enabled', null, null);
        $provider->resetExistingValue($setting);
        $setting->setStorageType('boolean');
        $setting->setValue(true);

        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        $container->get('monsieurbiz_settings.cache')->clear();
        $container->get('madcoders_sylius_brand.provider.settings')->reset();
        $container->get('madcoders_sylius_brand.twig.runtime.brand')->reset();
    }
}
