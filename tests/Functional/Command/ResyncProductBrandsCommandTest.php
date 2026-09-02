<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Functional\Command;

use Doctrine\ORM\EntityManagerInterface;
use Madcoders\SyliusBrandPlugin\Command\ResyncProductBrandsCommand;
use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Madcoders\SyliusBrandPlugin\Model\ProductInterface as BrandAwareProductInterface;
use Madcoders\SyliusBrandPlugin\Provider\BrandSettingsProviderInterface;
use Madcoders\SyliusBrandPlugin\Resolver\ProductBrandSynchronizerInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Formatter\StringInflector;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Test\Services\DefaultChannelFactoryInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * `madcoders:brand:resync-products` is the only supported way to backfill brands on a catalogue
 * that was written outside the resource layer - a CSV import, a PIM sync, a mapping change. It runs
 * against production data, so the two things worth pinning down are that `--dry-run` really writes
 * nothing and that a second run cannot start while the first is still going.
 *
 * The command is built by hand rather than pulled from the container so the lock factory can be
 * swapped for an in-memory one; everything else is the real service.
 */
final class ResyncProductBrandsCommandTest extends KernelTestCase
{
    private const ATTRIBUTE_CODE = 'brand';

    private const COMMAND_NAME = 'madcoders:brand:resync-products';

    private EntityManagerInterface $entityManager;

    private string $localeCode = 'en_US';

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        // Everything written here is rolled back in tearDown, so the test provisions the channel,
        // locale and attribute it needs instead of depending on whatever fixtures last ran.
        $this->entityManager->beginTransaction();

        $this->provideChannelAndLocale();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();

        parent::tearDown();
    }

    public function testItFailsWhenNoBrandAttributeIsConfigured(): void
    {
        $this->writeSetting(BrandSettingsProviderInterface::PATH_BRAND_ATTRIBUTE, 'text', '');

        $tester = $this->execute();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No brand attribute is configured', $tester->getDisplay());
    }

    public function testADryRunReportsTheChangeWithoutWritingIt(): void
    {
        $this->configureBrandAttribute();
        $this->createBrand('resync-dry-run');
        $product = $this->createProductWithBrandAttribute('DRY_RUN_PRODUCT', 'resync-dry-run');

        $tester = $this->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('would change', $tester->getDisplay());

        // The point of the whole option: the row is untouched. Read it back from the database
        // rather than from the identity map, which the command clears as it goes.
        self::assertNull($this->readBrandCodeFromDatabase($product));
    }

    public function testARealRunAssignsTheBrand(): void
    {
        $this->configureBrandAttribute();
        $this->createBrand('resync-real-run');
        $product = $this->createProductWithBrandAttribute('REAL_RUN_PRODUCT', 'resync-real-run');

        $tester = $this->execute();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('changed', $tester->getDisplay());
        self::assertSame('resync-real-run', $this->readBrandCodeFromDatabase($product));
    }

    public function testItDoesNothingWhileAnotherRunHoldsTheLock(): void
    {
        $this->configureBrandAttribute();
        $this->createBrand('resync-locked');
        $product = $this->createProductWithBrandAttribute('LOCKED_PRODUCT', 'resync-locked');

        // One shared factory stands in for two processes reaching the same store. Without an
        // injected factory this could not be tested at all: LockableTrait would build its own over
        // a host-local semaphore, which is the bug the injection point exists to fix.
        $lockFactory = new LockFactory(new InMemoryStore());

        $heldElsewhere = $lockFactory->createLock(self::COMMAND_NAME);
        self::assertTrue($heldElsewhere->acquire());

        $tester = $this->execute(lockFactory: $lockFactory);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Another resync is already running', $tester->getDisplay());

        // The second run bailed out before touching anything.
        self::assertNull($this->readBrandCodeFromDatabase($product));

        $heldElsewhere->release();
    }

    public function testAnInvalidBatchSizeFallsBackToAUsableOne(): void
    {
        $this->configureBrandAttribute();
        $this->createBrand('resync-batch');
        $product = $this->createProductWithBrandAttribute('BATCH_PRODUCT', 'resync-batch');

        // A zero or negative batch size would be a division by zero in the flush interval, or an
        // endless loop over an empty page. Both have to degrade to a working run.
        $tester = $this->execute(['--batch-size' => '0']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('resync-batch', $this->readBrandCodeFromDatabase($product));
    }

    /** @param array<string, mixed> $input */
    private function execute(array $input = [], ?LockFactory $lockFactory = null): CommandTester
    {
        $container = self::getContainer();

        /** @var ProductBrandSynchronizerInterface $synchronizer */
        $synchronizer = $container->get('madcoders_sylius_brand.resolver.product_brand_synchronizer');

        /** @var BrandSettingsProviderInterface $settings */
        $settings = $container->get('madcoders_sylius_brand.provider.settings');

        /** @var class-string $productClass */
        $productClass = $container->getParameter('sylius.model.product.class');

        $command = new ResyncProductBrandsCommand(
            $this->entityManager,
            $synchronizer,
            $settings,
            $productClass,
            $lockFactory ?? new LockFactory(new InMemoryStore()),
        );
        $command->setName(self::COMMAND_NAME);

        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    private function readBrandCodeFromDatabase(ProductInterface $product): ?string
    {
        $code = $this->entityManager->getConnection()->fetchOne(
            'SELECT b.code FROM sylius_product p LEFT JOIN madcoders_brand__brand b ON b.id = p.brand_id WHERE p.id = :id',
            ['id' => $product->getId()],
        );

        return false === $code || null === $code ? null : (string) $code;
    }

    /**
     * Reuses the attribute if it is already there. Behat commits its data, so a phpunit run after
     * one finds whatever the last scenario left behind; creating it unconditionally would collide
     * on the unique code.
     */
    private function configureBrandAttribute(): void
    {
        $container = self::getContainer();

        $existing = $container->get('sylius.repository.product_attribute')
            ->findOneBy(['code' => self::ATTRIBUTE_CODE]);

        if (null === $existing) {
            /** @var FactoryInterface<ProductAttributeInterface> $factory */
            $factory = $container->get('sylius.factory.product_attribute');

            $attribute = $factory->createNew();
            $attribute->setCode(self::ATTRIBUTE_CODE);
            $attribute->setType('text');
            $attribute->setStorageType('text');
            $attribute->setCurrentLocale($this->localeCode);
            $attribute->setFallbackLocale($this->localeCode);
            $attribute->setName('Brand');

            $this->entityManager->persist($attribute);
            $this->entityManager->flush();
        }

        $this->writeSetting(BrandSettingsProviderInterface::PATH_BRAND_ATTRIBUTE, 'text', self::ATTRIBUTE_CODE);
    }

    private function createBrand(string $code): BrandInterface
    {
        /** @var ExampleFactoryInterface<BrandInterface> $factory */
        $factory = self::getContainer()->get('madcoders_sylius_brand.fixture.example_factory.brand');

        $brand = $factory->create(['code' => $code, 'name' => ucfirst($code), 'enabled' => true]);
        $brand->setCurrentLocale($this->localeCode);
        $brand->setFallbackLocale($this->localeCode);
        $brand->setName(ucfirst($code));

        $this->entityManager->persist($brand);
        $this->entityManager->flush();

        return $brand;
    }

    private function createProductWithBrandAttribute(string $code, string $attributeValue): ProductInterface
    {
        $container = self::getContainer();

        /** @var FactoryInterface<ProductInterface> $productFactory */
        $productFactory = $container->get('sylius.factory.product');

        $product = $productFactory->createNew();
        $product->setCode($code);
        $product->setCurrentLocale($this->localeCode);
        $product->setFallbackLocale($this->localeCode);
        $product->setName($code);
        $product->setSlug(StringInflector::nameToSlug($code));
        $product->setEnabled(true);

        /** @var FactoryInterface<ProductAttributeValueInterface> $valueFactory */
        $valueFactory = $container->get('sylius.factory.product_attribute_value');

        /** @var ProductAttributeInterface $attribute */
        $attribute = $container->get('sylius.repository.product_attribute')
            ->findOneBy(['code' => self::ATTRIBUTE_CODE]);

        $value = $valueFactory->createNew();
        $value->setAttribute($attribute);
        $value->setLocaleCode($this->localeCode);
        $value->setValue($attributeValue);

        $product->addAttribute($value);

        // Persisted without resolving the brand, which is exactly the state the command exists to
        // repair: a product written outside the resource layer, carrying the attribute but no
        // brand_id.
        self::assertInstanceOf(BrandAwareProductInterface::class, $product);
        self::assertNull($product->getBrand());

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    private function writeSetting(string $path, string $storageType, mixed $value): void
    {
        $container = self::getContainer();

        $settings = $container->get('monsieurbiz.settings.registry')->getByAlias(BrandSettingsProviderInterface::ALIAS);
        self::assertNotNull($settings);

        ['vendor' => $vendor, 'plugin' => $plugin] = $settings->getAliasAsArray();

        $provider = $container->get('MonsieurBiz\SyliusSettingsPlugin\Provider\SettingProvider');
        $setting = $provider->getSettingOrCreateNew($vendor, $plugin, $path, null, null);
        $provider->resetExistingValue($setting);
        $setting->setStorageType($storageType);
        $setting->setValue($value);

        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        // Settings are read through a tag-aware pool, and the memoising services in front of it
        // would otherwise answer with the value from a previous test.
        $container->get('monsieurbiz_settings.cache')->clear();
        $container->get('madcoders_sylius_brand.provider.settings')->reset();
    }

    /**
     * Ensures there is a channel and a locale; the settings provider reads the channel and the
     * resolver reads the locale off the attribute values.
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

        /** @var DefaultChannelFactoryInterface $factory */
        $factory = $container->get('sylius.behat.factory.default_channel');
        $created = $factory->create();

        $this->entityManager->flush();

        /** @var LocaleInterface $createdLocale */
        $createdLocale = $created['locale'];
        $this->localeCode = (string) $createdLocale->getCode();
    }
}
