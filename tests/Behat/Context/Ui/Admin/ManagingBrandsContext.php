<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Madcoders\SyliusBrandPlugin\Repository\BrandRepositoryInterface;
use Sylius\Behat\Page\Admin\Crud\IndexPageInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Formatter\StringInflector;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Admin\Brand\CreatePageInterface;
use Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Admin\Brand\UpdatePageInterface;
use Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Admin\Product\ShowPageInterface as ProductShowPageInterface;
use Webmozart\Assert\Assert;

final class ManagingBrandsContext implements Context
{
    private const DEFAULT_LOCALE = 'en_US';

    public function __construct(
        private readonly SharedStorageInterface $sharedStorage,
        private readonly IndexPageInterface $indexPage,
        private readonly CreatePageInterface $createPage,
        private readonly UpdatePageInterface $updatePage,
        private readonly IndexPageInterface $productIndexPage,
        private readonly ProductShowPageInterface $productShowPage,
        /** @var RepositoryInterface<ProductInterface> */
        private readonly RepositoryInterface $productRepository,
        private readonly BrandRepositoryInterface $brandRepository,
        private readonly ObjectManager $brandManager,
    ) {
    }

    /**
     * @Given I want to create a new brand
     */
    public function iWantToCreateANewBrand(): void
    {
        $this->createPage->open();
    }

    /**
     * @When I specify its code as :code
     */
    public function iSpecifyItsCodeAs(string $code): void
    {
        $this->createPage->specifyCode($code);
    }

    /**
     * @When I name it :name in :localeName
     */
    public function iNameItIn(string $name, string $localeName): void
    {
        $this->createPage->nameIt($name, self::DEFAULT_LOCALE);
    }

    /**
     * @When I leave its slug empty
     */
    public function iLeaveItsSlugEmpty(): void
    {
        $this->createPage->specifySlug('', self::DEFAULT_LOCALE);
    }

    /**
     * @When I add it
     */
    public function iAddIt(): void
    {
        $this->createPage->create();
    }

    /**
     * Same submit as "I add it", named differently so the scenario reads as an attempt that is
     * expected to be refused.
     *
     * @When I try to add it
     */
    public function iTryToAddIt(): void
    {
        $this->createPage->create();
    }

    /**
     * @When I browse brands
     */
    public function iBrowseBrands(): void
    {
        $this->indexPage->open();
    }

    /**
     * @When I want to modify this brand
     */
    public function iWantToModifyThisBrand(): void
    {
        $brand = $this->sharedStorage->get('brand');
        Assert::isInstanceOf($brand, BrandInterface::class);

        $this->updatePage->open(['id' => $brand->getId()]);
    }

    /**
     * @When I disable it
     */
    public function iDisableIt(): void
    {
        $this->updatePage->disable();
    }

    /**
     * @When I save my changes
     */
    public function iSaveMyChanges(): void
    {
        $this->updatePage->saveChanges();
    }

    /**
     * @When I browse products
     */
    public function iBrowseProducts(): void
    {
        $this->productIndexPage->open();
    }

    /**
     * @When I view the product :name
     */
    public function iViewTheProduct(string $name): void
    {
        $this->brandManager->clear();

        $product = $this->productRepository->findOneBy(['code' => StringInflector::nameToUppercaseCode($name)]);
        Assert::isInstanceOf($product, ProductInterface::class, \sprintf('There is no product named "%s".', $name));

        $this->productShowPage->open(['id' => $product->getId()]);
    }

    /**
     * @Then the product grid should show the brand :name
     */
    public function theProductGridShouldShowTheBrand(string $name): void
    {
        Assert::true($this->productIndexPage->isSingleResourceOnPage(['madcodersBrand' => $name]));
    }

    /**
     * @Then I should see that it resolved to the brand :name
     */
    public function iShouldSeeThatItResolvedToTheBrand(string $name): void
    {
        Assert::contains($this->productShowPage->getBrandDiagnostics(), $name);
    }

    /**
     * @Then I should see that it resolved to no brand
     */
    public function iShouldSeeThatItResolvedToNoBrand(string $name = ''): void
    {
        Assert::contains($this->productShowPage->getBrandDiagnostics(), 'not attached to any brand');
    }

    /**
     * @Then I should see :count brands in the list
     */
    public function iShouldSeeBrandsInTheList(int $count): void
    {
        Assert::same($this->indexPage->countItems(), $count);
    }

    /**
     * @Then I should see the brand :name in the list
     */
    public function iShouldSeeTheBrandInTheList(string $name): void
    {
        Assert::true($this->indexPage->isSingleResourceOnPage(['name' => $name]));
    }

    /**
     * @Then the brand :name should appear in the store
     */
    public function theBrandShouldAppearInTheStore(string $name): void
    {
        Assert::notNull($this->findBrandByName($name));
    }

    /**
     * @Then the brand :name should have the slug :slug
     */
    public function theBrandShouldHaveTheSlug(string $name, string $slug): void
    {
        $brand = $this->findBrandByName($name);
        Assert::notNull($brand);
        Assert::same($brand->getSlug(), $slug);
    }

    /**
     * @Then I should be told that a brand with this code already exists
     */
    public function iShouldBeToldThatABrandWithThisCodeAlreadyExists(): void
    {
        Assert::same(
            $this->createPage->getValidationMessage('code'),
            'There is already a brand with this code.',
        );
    }

    /**
     * @Then I should be told that a brand with this slug already exists
     */
    public function iShouldBeToldThatABrandWithThisSlugAlreadyExists(): void
    {
        Assert::same(
            $this->createPage->getValidationMessage('slug', ['%locale%' => self::DEFAULT_LOCALE]),
            'There is already a brand with this slug in this locale.',
        );
    }

    /**
     * @Then there should still be only :count brand(s) in the store
     */
    public function thereShouldStillBeOnlyBrandsInTheStore(int $count): void
    {
        $this->brandManager->clear();

        Assert::count($this->brandRepository->findAll(), $count);
    }

    /**
     * @Then the brand :name should be disabled
     */
    public function theBrandShouldBeDisabled(string $name): void
    {
        $brand = $this->findBrandByName($name);
        Assert::notNull($brand);
        Assert::false($brand->isEnabled());
    }

    private function findBrandByName(string $name): ?BrandInterface
    {
        // The assertions run against the database, not the identity map the previous step left
        // behind - a controller that never flushed would otherwise still look successful.
        $this->brandManager->clear();

        foreach ($this->brandRepository->findAll() as $brand) {
            if ($brand instanceof BrandInterface && $brand->getName() === $name) {
                return $brand;
            }
        }

        return null;
    }
}
