<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Unit\Resolver;

use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Madcoders\SyliusBrandPlugin\Model\ProductInterface as BrandAwareProductInterface;
use Madcoders\SyliusBrandPlugin\Resolver\ProductBrandResolverInterface;
use Madcoders\SyliusBrandPlugin\Resolver\ProductBrandSynchronizer;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductInterface;

final class ProductBrandSynchronizerTest extends TestCase
{
    public function testItWritesTheResolvedBrandAndReportsTheChange(): void
    {
        $brand = $this->createBrand('nike');
        $product = $this->createMock(BrandAwareProductInterface::class);
        $product->method('getBrand')->willReturn(null);
        $product->expects(self::once())->method('setBrand')->with($brand);

        self::assertTrue($this->createSynchronizer($brand)->synchronize($product));
    }

    public function testItClearsTheBrandWhenNothingResolves(): void
    {
        $product = $this->createMock(BrandAwareProductInterface::class);
        $product->method('getBrand')->willReturn($this->createBrand('nike'));
        $product->expects(self::once())->method('setBrand')->with(null);

        self::assertTrue($this->createSynchronizer(null)->synchronize($product));
    }

    public function testItDoesNothingWhenTheBrandIsUnchanged(): void
    {
        $brand = $this->createBrand('nike');

        $product = $this->createMock(BrandAwareProductInterface::class);
        $product->method('getBrand')->willReturn($brand);
        $product->expects(self::never())->method('setBrand');

        self::assertFalse($this->createSynchronizer($brand)->synchronize($product));
    }

    public function testItComparesByCodeSoAProxyIsNotMistakenForAChange(): void
    {
        // Two distinct objects for the same row - what a freshly loaded brand and a Doctrine proxy
        // look like on either side of a unit of work.
        $current = $this->createBrand('nike');
        $resolved = $this->createBrand('nike');

        $product = $this->createMock(BrandAwareProductInterface::class);
        $product->method('getBrand')->willReturn($current);
        $product->expects(self::never())->method('setBrand');

        self::assertFalse($this->createSynchronizer($resolved)->synchronize($product));
    }

    public function testItLeavesAProductWithoutTheTraitAlone(): void
    {
        $product = $this->createMock(ProductInterface::class);

        $resolver = $this->createMock(ProductBrandResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        self::assertFalse((new ProductBrandSynchronizer($resolver))->synchronize($product));
    }

    private function createSynchronizer(?BrandInterface $resolved): ProductBrandSynchronizer
    {
        $resolver = $this->createMock(ProductBrandResolverInterface::class);
        $resolver->method('resolve')->willReturn($resolved);

        return new ProductBrandSynchronizer($resolver);
    }

    private function createBrand(string $code): BrandInterface
    {
        $brand = $this->createMock(BrandInterface::class);
        $brand->method('getCode')->willReturn($code);

        return $brand;
    }
}
