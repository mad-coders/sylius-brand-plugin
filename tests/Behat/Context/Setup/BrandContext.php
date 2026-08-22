<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ObjectManager;
use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Madcoders\SyliusBrandPlugin\Repository\BrandRepositoryInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;

/**
 * Creates the brands a scenario needs.
 *
 * The plugin's own example factory is reused rather than building brands by hand, so a scenario and
 * `sylius:fixtures:load` cannot drift apart in what a "brand" means.
 */
final class BrandContext implements Context
{
    /** @param ExampleFactoryInterface<BrandInterface> $brandExampleFactory */
    public function __construct(
        private readonly SharedStorageInterface $sharedStorage,
        private readonly ExampleFactoryInterface $brandExampleFactory,
        private readonly BrandRepositoryInterface $brandRepository,
        private readonly ObjectManager $brandManager,
    ) {
    }

    /**
     * @Given the store has a brand :name with code :code
     */
    public function theStoreHasABrandWithCode(string $name, string $code): void
    {
        $this->createBrand($code, $name);
    }

    /**
     * @Given the store has a disabled brand :name with code :code
     */
    public function theStoreHasADisabledBrandWithCode(string $name, string $code): void
    {
        $this->createBrand($code, $name, ['enabled' => false]);
    }

    /**
     * @Given the brand :name is disabled
     */
    public function theBrandIsDisabled(string $name): void
    {
        $this->updateBrand($name, static fn (BrandInterface $brand) => $brand->setEnabled(false));
    }

    /**
     * @Given the brand :name is not displayed on the brand overview
     */
    public function theBrandIsNotDisplayedOnTheBrandOverview(string $name): void
    {
        $this->updateBrand($name, static fn (BrandInterface $brand) => $brand->setDisplayOnBrandOverview(false));
    }

    /**
     * @Given the brand :name is displayed on the homepage
     */
    public function theBrandIsDisplayedOnTheHomepage(string $name): void
    {
        $this->updateBrand($name, static fn (BrandInterface $brand) => $brand->setDisplayOnHomepage(true));
    }

    /**
     * @Given the brand :name is displayed on product tiles
     */
    public function theBrandIsDisplayedOnProductTiles(string $name): void
    {
        $this->updateBrand($name, static fn (BrandInterface $brand) => $brand->setDisplayOnProductTile(true));
    }

    /** @param array<string, mixed> $options */
    private function createBrand(string $code, string $name, array $options = []): BrandInterface
    {
        $brand = $this->brandExampleFactory->create(array_merge(['code' => $code, 'name' => $name], $options));

        $this->brandManager->persist($brand);
        $this->brandManager->flush();

        $this->sharedStorage->set('brand', $brand);
        $this->sharedStorage->set(\sprintf('brand_%s', $code), $brand);

        return $brand;
    }

    /** @param callable(BrandInterface): void $mutate */
    private function updateBrand(string $name, callable $mutate): void
    {
        $brand = $this->findBrandByName($name);

        $mutate($brand);

        $this->brandManager->flush();
    }

    private function findBrandByName(string $name): BrandInterface
    {
        foreach ($this->brandRepository->findAll() as $brand) {
            if ($brand instanceof BrandInterface && $brand->getName() === $name) {
                return $brand;
            }
        }

        throw new \InvalidArgumentException(\sprintf('There is no brand named "%s".', $name));
    }
}
