<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use Madcoders\SyliusBrandPlugin\Model\ProductInterface as BrandAwareProductInterface;
use Madcoders\SyliusBrandPlugin\Repository\BrandRepositoryInterface;
use Madcoders\SyliusBrandPlugin\Resolver\ProductBrandSynchronizerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Formatter\StringInflector;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

/**
 * Products that carry a brand, either through the attribute (the real path) or directly (when a
 * scenario is about the shop rather than about resolution).
 */
final class BrandProductContext implements Context
{
    /**
     * @param FactoryInterface<ProductInterface> $productFactory
     * @param FactoryInterface<ProductVariantInterface> $productVariantFactory
     * @param FactoryInterface<ChannelPricingInterface> $channelPricingFactory
     * @param FactoryInterface<ProductAttributeInterface> $productAttributeFactory
     * @param FactoryInterface<ProductAttributeValueInterface> $productAttributeValueFactory
     * @param RepositoryInterface<ProductAttributeInterface> $productAttributeRepository
     */
    public function __construct(
        private readonly SharedStorageInterface $sharedStorage,
        private readonly FactoryInterface $productFactory,
        private readonly FactoryInterface $productVariantFactory,
        private readonly FactoryInterface $channelPricingFactory,
        private readonly FactoryInterface $productAttributeFactory,
        private readonly FactoryInterface $productAttributeValueFactory,
        private readonly RepositoryInterface $productAttributeRepository,
        private readonly BrandRepositoryInterface $brandRepository,
        private readonly ProductBrandSynchronizerInterface $synchronizer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given the store has a product attribute :name with code :code
     */
    public function theStoreHasAProductAttributeWithCode(string $name, string $code): void
    {
        $attribute = $this->productAttributeFactory->createNew();
        $attribute->setCode($code);
        $attribute->setType('text');
        $attribute->setStorageType('text');
        $attribute->setCurrentLocale($this->getLocaleCode());
        $attribute->setFallbackLocale($this->getLocaleCode());
        $attribute->setName($name);

        $this->entityManager->persist($attribute);
        $this->entityManager->flush();
    }

    /**
     * @Given the store has a product :name with the brand attribute :value
     */
    public function theStoreHasAProductWithTheBrandAttribute(string $name, string $value): void
    {
        $product = $this->createProduct($name);

        $attribute = $this->productAttributeRepository->findOneBy(['code' => 'brand']);
        Assert::isInstanceOf($attribute, ProductAttributeInterface::class, 'The "brand" product attribute has to exist first.');

        $attributeValue = $this->productAttributeValueFactory->createNew();
        $attributeValue->setAttribute($attribute);
        $attributeValue->setLocaleCode($this->getLocaleCode());
        $attributeValue->setValue($value);

        $product->addAttribute($attributeValue);

        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    /**
     * Attaches the brand directly, without going through the attribute.
     *
     * This is the right shortcut for a shop scenario: it is about what a brand page shows, not about
     * how the brand got there, and resolution has its own feature file.
     *
     * @Given the store has a product :name of the brand :brandCode
     */
    public function theStoreHasAProductOfTheBrand(string $name, string $brandCode): void
    {
        $product = $this->createProduct($name);
        Assert::isInstanceOf($product, BrandAwareProductInterface::class);

        $product->setBrand($this->brandRepository->findOneByCode($brandCode));

        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    /**
     * @When the product brands are resynced
     */
    public function theProductBrandsAreResynced(): void
    {
        /** @var array<array-key, ProductInterface> $products */
        $products = $this->entityManager->getRepository($this->getProductClass())->findAll();

        foreach ($products as $product) {
            $this->synchronizer->synchronize($product);
        }

        $this->entityManager->flush();
    }

    /**
     * @Then the product :name should belong to the brand :brandCode
     */
    public function theProductShouldBelongToTheBrand(string $name, string $brandCode): void
    {
        $brand = $this->findProduct($name)->getBrand();

        Assert::notNull($brand, \sprintf('The product "%s" has no brand.', $name));
        Assert::same($brand->getCode(), $brandCode);
    }

    /**
     * @Then the product :name should belong to no brand
     */
    public function theProductShouldBelongToNoBrand(string $name): void
    {
        Assert::null($this->findProduct($name)->getBrand());
    }

    /**
     * A product complete enough for Sylius' shop product card to render: an enabled variant with a
     * price in the channel. A product without those is not a lighter fixture, it is a broken one -
     * the card component throws "Product has no variants" and takes the page down.
     */
    private function createProduct(string $name): ProductInterface
    {
        $code = StringInflector::nameToUppercaseCode($name);

        $product = $this->productFactory->createNew();
        $product->setCode($code);
        $product->setCurrentLocale($this->getLocaleCode());
        $product->setFallbackLocale($this->getLocaleCode());
        $product->setName($name);
        $product->setSlug(StringInflector::nameToSlug($name));
        $product->setEnabled(true);

        $channel = $this->sharedStorage->get('channel');

        $variant = $this->productVariantFactory->createNew();
        $variant->setCode($code . '_VARIANT');
        $variant->setCurrentLocale($this->getLocaleCode());
        $variant->setFallbackLocale($this->getLocaleCode());
        $variant->setName($name);
        $variant->setEnabled(true);

        if ($channel instanceof ChannelInterface) {
            $product->addChannel($channel);

            $channelPricing = $this->channelPricingFactory->createNew();
            $channelPricing->setChannelCode($channel->getCode());
            $channelPricing->setPrice(1000);
            $variant->addChannelPricing($channelPricing);
        }

        $product->addVariant($variant);

        return $product;
    }

    private function findProduct(string $name): BrandAwareProductInterface
    {
        // Cleared so the assertion reads the database rather than the identity map - a resync that
        // forgot to flush would otherwise still look like it worked.
        $this->entityManager->clear();

        $product = $this->entityManager->getRepository($this->getProductClass())
            ->findOneBy(['code' => StringInflector::nameToUppercaseCode($name)])
        ;

        Assert::isInstanceOf($product, BrandAwareProductInterface::class, \sprintf('There is no product named "%s".', $name));

        return $product;
    }

    /** @return class-string<ProductInterface> */
    private function getProductClass(): string
    {
        return $this->productFactory->createNew()::class;
    }

    private function getLocaleCode(): string
    {
        return 'en_US';
    }
}
