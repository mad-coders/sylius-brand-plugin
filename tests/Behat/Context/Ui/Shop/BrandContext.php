<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use FriendsOfBehat\PageObjectExtension\Page\UnexpectedPageException;
use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Madcoders\SyliusBrandPlugin\Repository\BrandRepositoryInterface;
use Sylius\Component\Core\Formatter\StringInflector;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Shop\Brand\IndexPageInterface;
use Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Shop\Brand\ShowPageInterface;
use Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Shop\HomePageInterface;
use Webmozart\Assert\Assert;

final class BrandContext implements Context
{
    private bool $pageWasNotFound = false;

    public function __construct(
        private readonly IndexPageInterface $indexPage,
        private readonly ShowPageInterface $showPage,
        private readonly HomePageInterface $homePage,
        private readonly BrandRepositoryInterface $brandRepository,
    ) {
    }

    /**
     * @When I browse the brand overview page
     */
    public function iBrowseTheBrandOverviewPage(): void
    {
        $this->indexPage->open();
    }

    /**
     * @When I try to browse the brand overview page
     */
    public function iTryToBrowseTheBrandOverviewPage(): void
    {
        $this->pageWasNotFound = !$this->tryToOpen(fn () => $this->indexPage->open());
    }

    /**
     * @When I open the page of the brand :name
     */
    public function iOpenThePageOfTheBrand(string $name): void
    {
        $this->showPage->open(['slug' => $this->getSlug($name)]);
    }

    /**
     * @When I try to open the page of the brand :name
     */
    public function iTryToOpenThePageOfTheBrand(string $name): void
    {
        $slug = $this->getSlug($name);

        $this->pageWasNotFound = !$this->tryToOpen(fn () => $this->showPage->open(['slug' => $slug]));
    }

    /**
     * @Then I should see the brand :name
     */
    public function iShouldSeeTheBrand(string $name): void
    {
        Assert::inArray($this->getBrandCode($name), $this->indexPage->getListedBrandCodes());
    }

    /**
     * @Then I should not see the brand :name
     */
    public function iShouldNotSeeTheBrand(string $name): void
    {
        $code = $this->getBrandCode($name);
        Assert::false(
            \in_array($code, $this->indexPage->getListedBrandCodes(), true),
            \sprintf('The brand "%s" is listed on the overview page but should not be.', $name),
        );
    }

    /**
     * @Then I should see the product :name
     */
    public function iShouldSeeTheProduct(string $name): void
    {
        Assert::inArray(StringInflector::nameToUppercaseCode($name), $this->showPage->getListedProductCodes());
    }

    /**
     * @Then I should not see the product :name
     */
    public function iShouldNotSeeTheProduct(string $name): void
    {
        Assert::false(
            \in_array(StringInflector::nameToUppercaseCode($name), $this->showPage->getListedProductCodes(), true),
            \sprintf('The product "%s" is listed on the brand page but should not be.', $name),
        );
    }

    /**
     * @When I browse the homepage
     */
    public function iBrowseTheHomepage(): void
    {
        $this->homePage->open();
    }

    /**
     * @Then the homepage should show the brand :name
     */
    public function theHomepageShouldShowTheBrand(string $name): void
    {
        Assert::inArray($this->getBrandCode($name), $this->homePage->getStripBrandCodes());
    }

    /**
     * @Then the homepage should not show the brand :name
     */
    public function theHomepageShouldNotShowTheBrand(string $name): void
    {
        Assert::false(
            \in_array($this->getBrandCode($name), $this->homePage->getStripBrandCodes(), true),
            \sprintf('The brand "%s" is in the homepage strip but should not be.', $name),
        );
    }

    /**
     * @Then the product tiles should show the brand :name
     */
    public function theProductTilesShouldShowTheBrand(string $name): void
    {
        Assert::inArray($this->getBrandCode($name), $this->showPage->getTileBrandCodes());
    }

    /**
     * @Then the product tiles should not show the brand :name
     */
    public function theProductTilesShouldNotShowTheBrand(string $name): void
    {
        Assert::false(
            \in_array($this->getBrandCode($name), $this->showPage->getTileBrandCodes(), true),
            \sprintf('The brand "%s" is shown on a product tile but should not be.', $name),
        );
    }

    /**
     * @Then I should be told that the page does not exist
     */
    public function iShouldBeToldThatThePageDoesNotExist(): void
    {
        Assert::true($this->pageWasNotFound, 'Expected the page to be missing, but it opened.');
    }

    /**
     * "The page is gone" is observed through the page object, not the kernel.
     *
     * The Symfony Mink driver returns the 404 *response* rather than letting the
     * NotFoundHttpException escape, and the page object then refuses to consider the page open,
     * throwing UnexpectedPageException with the status code in its message. Catching
     * NotFoundHttpException alone never fires; both are caught so the step keeps working if the
     * driver is swapped for one that does rethrow.
     *
     * @param callable(): void $open
     */
    private function tryToOpen(callable $open): bool
    {
        try {
            $open();
        } catch (NotFoundHttpException | UnexpectedPageException) {
            return false;
        }

        return true;
    }

    private function getSlug(string $name): string
    {
        return (string) $this->findBrand($name)->getSlug();
    }

    private function getBrandCode(string $name): string
    {
        return (string) $this->findBrand($name)->getCode();
    }

    private function findBrand(string $name): BrandInterface
    {
        foreach ($this->brandRepository->findAll() as $brand) {
            if ($brand instanceof BrandInterface && $brand->getName() === $name) {
                return $brand;
            }
        }

        throw new \InvalidArgumentException(\sprintf('There is no brand named "%s".', $name));
    }
}
