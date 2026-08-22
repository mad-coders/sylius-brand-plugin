<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Unit\Resolver;

use Doctrine\Common\Collections\ArrayCollection;
use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Madcoders\SyliusBrandPlugin\Provider\BrandSettingsProviderInterface;
use Madcoders\SyliusBrandPlugin\Repository\BrandRepositoryInterface;
use Madcoders\SyliusBrandPlugin\Resolver\ProductBrandResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;

final class ProductBrandResolverTest extends TestCase
{
    private const ATTRIBUTE_CODE = 'brand';

    public function testItResolvesATextAttributeValueOneToOne(): void
    {
        $brand = $this->createBrand('adidas');
        $resolver = $this->createResolver(['adidas' => $brand]);

        $product = $this->createProduct([$this->createAttributeValue(self::ATTRIBUTE_CODE, 'adidas')]);

        self::assertSame($brand, $resolver->resolve($product));
    }

    public function testItRoutesAValueThroughTheMapping(): void
    {
        $brand = $this->createBrand('nike');
        $resolver = $this->createResolver(['nike' => $brand], mapping: ['nike inc.' => 'nike']);

        $product = $this->createProduct([$this->createAttributeValue(self::ATTRIBUTE_CODE, 'Nike Inc.')]);

        self::assertSame($brand, $resolver->resolve($product));
    }

    public function testItStillResolvesWhenTheDisplayToggleIsOff(): void
    {
        // `enabled` is a per-channel *display* toggle. Resolution writes one brand_id per product
        // and must not depend on it: gating here would make the same product resolve differently
        // in the admin (current channel) than in the resync command (first enabled channel), and
        // would leave brand_id stale for as long as the feature was off. The shop hides brands via
        // the toggle; the column stays correct underneath.
        $brand = $this->createBrand('adidas');
        $resolver = $this->createResolver(['adidas' => $brand], enabled: false);

        $product = $this->createProduct([$this->createAttributeValue(self::ATTRIBUTE_CODE, 'adidas')]);

        self::assertSame($brand, $resolver->resolve($product));
    }

    public function testItReturnsNullWhenNoAttributeIsConfigured(): void
    {
        $resolver = $this->createResolver(['adidas' => $this->createBrand('adidas')], attributeCode: null);

        $product = $this->createProduct([$this->createAttributeValue(self::ATTRIBUTE_CODE, 'adidas')]);

        self::assertNull($resolver->resolve($product));
    }

    public function testItIgnoresOtherAttributes(): void
    {
        $resolver = $this->createResolver(['adidas' => $this->createBrand('adidas')]);

        $product = $this->createProduct([$this->createAttributeValue('material', 'adidas')]);

        self::assertNull($resolver->resolve($product));
    }

    public function testItReturnsNullForAnUnknownBrandCodeRatherThanGuessing(): void
    {
        $resolver = $this->createResolver(['adidas' => $this->createBrand('adidas')]);

        $product = $this->createProduct([$this->createAttributeValue(self::ATTRIBUTE_CODE, 'a-brand-nobody-created')]);

        self::assertNull($resolver->resolve($product));
    }

    public function testItIgnoresValueTypesThatCannotNameABrand(): void
    {
        $resolver = $this->createResolver(['1' => $this->createBrand('1')]);

        // A checkbox attribute. Coercing `true` to "1" would attach every checked product to
        // whichever brand happens to be coded "1".
        $product = $this->createProduct([$this->createAttributeValue(self::ATTRIBUTE_CODE, true)]);

        self::assertNull($resolver->resolve($product));
    }

    public function testItResolvesASelectAttributeByChoiceKeyOrLabel(): void
    {
        $brand = $this->createBrand('nike');

        $byKey = $this->createAttributeValue(
            self::ATTRIBUTE_CODE,
            ['choice-uuid'],
            ['choices' => ['choice-uuid' => ['en_US' => 'Nike Sportswear']]],
        );
        self::assertSame(
            $brand,
            $this->createResolver(['nike' => $brand], mapping: ['choice-uuid' => 'nike'])
                ->resolve($this->createProduct([$byKey])),
        );

        $byLabel = $this->createAttributeValue(
            self::ATTRIBUTE_CODE,
            ['choice-uuid'],
            ['choices' => ['choice-uuid' => ['en_US' => 'Nike Sportswear']]],
        );
        self::assertSame(
            $brand,
            $this->createResolver(['nike' => $brand], mapping: ['nike sportswear' => 'nike'])
                ->resolve($this->createProduct([$byLabel])),
        );
    }

    public function testItFallsBackToAnotherLocalesAttributeValue(): void
    {
        $brand = $this->createBrand('adidas');
        $resolver = $this->createResolver(['adidas' => $brand]);

        // The English value is blank; only the Polish one was ever filled in.
        $product = $this->createProduct([
            $this->createAttributeValue(self::ATTRIBUTE_CODE, ''),
            $this->createAttributeValue(self::ATTRIBUTE_CODE, 'adidas'),
        ]);

        self::assertSame($brand, $resolver->resolve($product));
    }

    /**
     * @param array<string, BrandInterface> $brandsByCode
     * @param array<string, string> $mapping
     */
    private function createResolver(
        array $brandsByCode,
        array $mapping = [],
        bool $enabled = true,
        ?string $attributeCode = self::ATTRIBUTE_CODE,
    ): ProductBrandResolver {
        $settings = $this->createMock(BrandSettingsProviderInterface::class);
        $settings->method('isEnabled')->willReturn($enabled);
        $settings->method('getBrandAttributeCode')->willReturn($attributeCode);
        $settings->method('getBrandMapping')->willReturn($mapping);
        $settings->method('resolveBrandCode')->willReturnCallback(
            static function (string $value) use ($mapping): ?string {
                $value = trim($value);

                return '' === $value ? null : ($mapping[mb_strtolower($value)] ?? $value);
            },
        );

        $repository = $this->createMock(BrandRepositoryInterface::class);
        $repository->method('findOneByCode')->willReturnCallback(
            static fn (string $code): ?BrandInterface => $brandsByCode[$code] ?? null,
        );

        return new ProductBrandResolver($settings, $repository);
    }

    private function createBrand(string $code): BrandInterface
    {
        $brand = $this->createMock(BrandInterface::class);
        $brand->method('getCode')->willReturn($code);

        return $brand;
    }

    /** @param array<array-key, ProductAttributeValueInterface> $attributeValues */
    private function createProduct(array $attributeValues): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getAttributes')->willReturn(new ArrayCollection($attributeValues));

        return $product;
    }

    /** @param array<string, mixed>|null $configuration */
    private function createAttributeValue(string $code, mixed $value, ?array $configuration = null): ProductAttributeValueInterface
    {
        $attribute = $this->createMock(ProductAttributeInterface::class);
        $attribute->method('getConfiguration')->willReturn($configuration ?? []);

        $attributeValue = $this->createMock(ProductAttributeValueInterface::class);
        $attributeValue->method('getCode')->willReturn($code);
        $attributeValue->method('getValue')->willReturn($value);
        $attributeValue->method('getAttribute')->willReturn($attribute);

        return $attributeValue;
    }
}
