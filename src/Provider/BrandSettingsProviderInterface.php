<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Provider;

/**
 * The plugin's single reader of the Settings plugin.
 *
 * Everything here has a defined fallback: a shop that has not configured the plugin, or has
 * configured it wrongly, must degrade to "feature off" rather than throw at request time. See
 * docs/adr-log/0003-configuration-through-the-settings-plugin.md.
 */
interface BrandSettingsProviderInterface
{
    /** The settings alias this plugin registers with the Settings plugin. */
    public const ALIAS = 'madcoders_brand.default';

    public const PATH_ENABLED = 'enabled';

    public const PATH_BRAND_ATTRIBUTE = 'brand_attribute';

    public const PATH_BRAND_MAPPING = 'brand_mapping';

    /** Feature toggle. False unless the shop has explicitly turned the feature on. */
    public function isEnabled(): bool;

    /** Code of the product attribute carrying the brand, or null when none is configured. */
    public function getBrandAttributeCode(): ?string;

    /**
     * The configured value-to-brand mapping, keyed by the **normalised** source value (trimmed and
     * lower-cased). An empty map means the 1:1 case: an attribute value is its own brand code.
     *
     * @return array<string, string>
     */
    public function getBrandMapping(): array;

    /**
     * The brand code an attribute value resolves to, or null when the value is blank.
     *
     * Applies the mapping if the value has an entry, and falls back to the value itself otherwise.
     * A returned code is not a promise that a brand with that code exists.
     */
    public function resolveBrandCode(string $attributeValue): ?string;
}
