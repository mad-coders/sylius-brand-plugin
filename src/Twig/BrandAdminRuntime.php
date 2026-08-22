<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Twig;

use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Madcoders\SyliusBrandPlugin\Model\ProductInterface as BrandAwareProductInterface;
use Madcoders\SyliusBrandPlugin\Provider\BrandSettingsProviderInterface;
use Madcoders\SyliusBrandPlugin\Repository\ProductByBrandRepositoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Admin-side answers about brands.
 *
 * @see BrandAdminExtension
 */
final class BrandAdminRuntime implements RuntimeExtensionInterface, ResetInterface
{
    /** @var array<int, int>|null */
    private ?array $productCounts = null;

    public function __construct(
        private readonly BrandSettingsProviderInterface $settings,
        private readonly ProductByBrandRepositoryInterface $productByBrandRepository,
    ) {
    }

    public function reset(): void
    {
        $this->productCounts = null;
    }

    /**
     * How many products currently point at this brand.
     *
     * Loaded for every brand at once and memoised, because the grid calls this per row and the
     * delete confirmation calls it again for the same brand.
     */
    public function getProductCount(BrandInterface $brand): int
    {
        $this->productCounts ??= $this->productByBrandRepository->countAllPerBrand();

        $id = $brand->getId();

        return null === $id ? 0 : ($this->productCounts[$id] ?? 0);
    }

    /**
     * Why this product has the brand it has - or why it has none.
     *
     * Without this, diagnosing a mapping means opening a SQL client: the brand on a product is
     * derived, and nothing in the admin shows the attribute value it was derived from. Returns the
     * whole chain so the template can show each link: which attribute is configured, what the
     * product actually holds for it, what that maps to, and whether a brand with that code exists.
     *
     * @return array{
     *     configured: bool,
     *     attributeCode: string|null,
     *     values: list<string>,
     *     resolvedCodes: list<string>,
     *     brand: BrandInterface|null,
     * }
     */
    public function getDiagnostics(mixed $product): array
    {
        $attributeCode = $this->settings->getBrandAttributeCode();

        $diagnostics = [
            'configured' => null !== $attributeCode,
            'attributeCode' => $attributeCode,
            'values' => [],
            'resolvedCodes' => [],
            'brand' => null,
        ];

        if (!$product instanceof ProductInterface) {
            return $diagnostics;
        }

        if ($product instanceof BrandAwareProductInterface) {
            $diagnostics['brand'] = $product->getBrand();
        }

        if (null === $attributeCode) {
            return $diagnostics;
        }

        foreach ($product->getAttributes() as $attributeValue) {
            if (!$attributeValue instanceof ProductAttributeValueInterface || $attributeCode !== $attributeValue->getCode()) {
                continue;
            }

            $value = $attributeValue->getValue();

            foreach (\is_array($value) ? $value : [$value] as $single) {
                if (!\is_string($single) && !\is_int($single) && !\is_float($single)) {
                    continue;
                }

                $single = (string) $single;

                if ('' === trim($single) || \in_array($single, $diagnostics['values'], true)) {
                    continue;
                }

                $diagnostics['values'][] = $single;

                $resolved = $this->settings->resolveBrandCode($single);

                if (null !== $resolved && !\in_array($resolved, $diagnostics['resolvedCodes'], true)) {
                    $diagnostics['resolvedCodes'][] = $resolved;
                }
            }
        }

        return $diagnostics;
    }
}
