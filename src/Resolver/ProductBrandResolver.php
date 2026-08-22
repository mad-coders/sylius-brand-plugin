<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Resolver;

use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Madcoders\SyliusBrandPlugin\Provider\BrandSettingsProviderInterface;
use Madcoders\SyliusBrandPlugin\Repository\BrandRepositoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @see ProductBrandResolverInterface
 */
final class ProductBrandResolver implements ProductBrandResolverInterface, ResetInterface
{
    /**
     * Brands already looked up, keyed by code, including the misses (as null).
     *
     * A resync walks the whole catalogue and every product triggers a lookup, even though a shop
     * has a handful of brands - so without this the command issues one query per product. The
     * misses are cached too: an attribute value that names no brand is usually shared by many
     * products, and re-asking about it is the same wasted query.
     *
     * Entities are held, not ids, so this **must** be dropped whenever the entity manager is
     * cleared - a detached brand assigned to a managed product is a Doctrine error waiting to
     * happen. That is what reset() is for, and the resync command calls it on every batch.
     *
     * @var array<string, BrandInterface|null>
     */
    private array $brandCache = [];

    public function __construct(
        private readonly BrandSettingsProviderInterface $settings,
        private readonly BrandRepositoryInterface $brandRepository,
    ) {
    }

    public function reset(): void
    {
        $this->brandCache = [];
    }

    public function resolve(ProductInterface $product): ?BrandInterface
    {
        // Deliberately not gated on the `enabled` setting. That toggle is per channel and controls
        // *display*; resolution writes one brand_id per product and must give the same answer
        // wherever it runs. Gating on it would make a product resolve differently in the admin
        // (current channel) than in the resync command (first enabled channel) - and would leave
        // brand_id stale for as long as the feature stayed off. See ADR 0004, rule 2.
        $attributeCode = $this->settings->getBrandAttributeCode();

        if (null === $attributeCode) {
            return null;
        }

        foreach ($this->findCandidates($product, $attributeCode) as $candidate) {
            $brandCode = $this->settings->resolveBrandCode($candidate);

            if (null === $brandCode) {
                continue;
            }

            $brand = $this->brandCache[$brandCode] ??= $this->brandRepository->findOneByCode($brandCode);

            if (null !== $brand) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * Every string the product's brand attribute could reasonably be identified by, in the order
     * they should be tried.
     *
     * A product carries one attribute value per locale, so the same brand attribute appears several
     * times; they are all candidates, because a shop may only have filled the attribute in one
     * locale.
     *
     * @return list<string>
     */
    private function findCandidates(ProductInterface $product, string $attributeCode): array
    {
        $candidates = [];

        foreach ($product->getAttributes() as $attributeValue) {
            if (!$attributeValue instanceof ProductAttributeValueInterface) {
                continue;
            }

            if ($attributeCode !== $attributeValue->getCode()) {
                continue;
            }

            foreach ($this->extractCandidates($attributeValue) as $candidate) {
                $candidates[] = $candidate;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return list<string>
     */
    private function extractCandidates(ProductAttributeValueInterface $attributeValue): array
    {
        $value = $attributeValue->getValue();

        // Text, textarea, integer and percent attributes name the brand directly. Booleans, dates
        // and anything else cannot, and are deliberately not coerced into a string - "1" is not a
        // brand code, and treating it as one would silently attach every checked product to
        // whichever brand happens to be called "1".
        if (\is_string($value) || \is_int($value) || \is_float($value)) {
            return [(string) $value];
        }

        if (!\is_array($value)) {
            return [];
        }

        // A select attribute stores choice *keys*; the human-readable label lives in the
        // attribute's configuration, per locale. Both are offered as candidates so a shop can
        // write its mapping against whichever it finds readable - the key is tried first because
        // it is stable, while a label can be edited.
        $choices = $this->extractChoices($attributeValue);
        $candidates = [];

        foreach ($value as $choiceKey) {
            if (!\is_string($choiceKey) && !\is_int($choiceKey)) {
                continue;
            }

            $choiceKey = (string) $choiceKey;
            $candidates[] = $choiceKey;

            foreach ($choices[$choiceKey] ?? [] as $label) {
                if (\is_string($label) && '' !== trim($label)) {
                    $candidates[] = $label;
                }
            }
        }

        return $candidates;
    }

    /**
     * @return array<string, array<array-key, mixed>>
     */
    private function extractChoices(ProductAttributeValueInterface $attributeValue): array
    {
        $configuration = $attributeValue->getAttribute()?->getConfiguration() ?? [];
        $choices = $configuration['choices'] ?? null;

        if (!\is_array($choices)) {
            return [];
        }

        $normalized = [];

        // An array key is always int|string, so only the labels need checking. The keys are cast
        // because a numeric choice key arrives as an int and is matched against string values.
        foreach ($choices as $key => $labels) {
            if (\is_array($labels)) {
                $normalized[(string) $key] = $labels;
            }
        }

        return $normalized;
    }
}
