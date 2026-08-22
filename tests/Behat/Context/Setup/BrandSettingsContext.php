<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use Madcoders\SyliusBrandPlugin\Provider\BrandSettingsProviderInterface;
use MonsieurBiz\SyliusSettingsPlugin\Entity\Setting\SettingInterface;
use MonsieurBiz\SyliusSettingsPlugin\Provider\SettingProviderInterface;
use MonsieurBiz\SyliusSettingsPlugin\Settings\RegistryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Webmozart\Assert\Assert;

/**
 * Writes the plugin's settings the way the admin screen does - real `Setting` rows through the
 * Settings plugin's own provider.
 *
 * Stubbing BrandSettingsProvider instead would be easier and would prove nothing: the part most
 * likely to break is the wiring between the two plugins, and that is exactly what this context
 * exercises. The cache is cleared after every write because settings are read through a tag-aware
 * pool that would otherwise hand a scenario the value from its previous step.
 */
final class BrandSettingsContext implements Context
{
    public function __construct(
        private readonly RegistryInterface $settingsRegistry,
        private readonly SettingProviderInterface $settingProvider,
        private readonly EntityManagerInterface $settingManager,
        // Typed as the PSR-6 pool rather than TagAwareCacheInterface: the tag-aware contract only
        // offers invalidateTags(), and a scenario wants the whole pool gone, not one tag.
        private readonly CacheItemPoolInterface $settingsCache,
    ) {
    }

    /**
     * @Given brands are enabled in the settings
     */
    public function brandsAreEnabledInTheSettings(): void
    {
        $this->write(BrandSettingsProviderInterface::PATH_ENABLED, SettingInterface::STORAGE_TYPE_BOOLEAN, true);
    }

    /**
     * @Given brands are disabled in the settings
     */
    public function brandsAreDisabledInTheSettings(): void
    {
        $this->write(BrandSettingsProviderInterface::PATH_ENABLED, SettingInterface::STORAGE_TYPE_BOOLEAN, false);
    }

    /**
     * @Given the brand attribute in the settings is :attributeCode
     */
    public function theBrandAttributeInTheSettingsIs(string $attributeCode): void
    {
        $this->write(BrandSettingsProviderInterface::PATH_BRAND_ATTRIBUTE, SettingInterface::STORAGE_TYPE_TEXT, $attributeCode);
    }

    /**
     * @Given no brand attribute is configured in the settings
     */
    public function noBrandAttributeIsConfiguredInTheSettings(): void
    {
        $this->write(BrandSettingsProviderInterface::PATH_BRAND_ATTRIBUTE, SettingInterface::STORAGE_TYPE_TEXT, '');
    }

    /**
     * @Given the brand mapping maps :source to :brandCode
     */
    public function theBrandMappingMapsTo(string $source, string $brandCode): void
    {
        $mapping = $this->readMapping();
        $mapping[] = ['source' => $source, 'brand' => $brandCode];

        $this->write(BrandSettingsProviderInterface::PATH_BRAND_MAPPING, SettingInterface::STORAGE_TYPE_JSON, $mapping);
    }

    /**
     * @Given there is no brand mapping
     */
    public function thereIsNoBrandMapping(): void
    {
        $this->write(BrandSettingsProviderInterface::PATH_BRAND_MAPPING, SettingInterface::STORAGE_TYPE_JSON, []);
    }

    /**
     * @return list<array{source: string, brand: string}>
     */
    private function readMapping(): array
    {
        $settings = $this->settingsRegistry->getByAlias(BrandSettingsProviderInterface::ALIAS);
        Assert::notNull($settings, 'The brand settings section is not registered.');

        /** @var mixed $value */
        $value = $settings->getCurrentValue(null, null, BrandSettingsProviderInterface::PATH_BRAND_MAPPING);

        if (!\is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $source = $entry['source'] ?? null;
            $brand = $entry['brand'] ?? null;

            if (\is_string($source) && \is_string($brand)) {
                $rows[] = ['source' => $source, 'brand' => $brand];
            }
        }

        return $rows;
    }

    private function write(string $path, string $type, mixed $value): void
    {
        $settings = $this->settingsRegistry->getByAlias(BrandSettingsProviderInterface::ALIAS);
        Assert::notNull($settings, 'The brand settings section is not registered.');

        $aliases = $settings->getAliasAsArray();
        $vendor = $aliases['vendor'] ?? null;
        $plugin = $aliases['plugin'] ?? null;
        Assert::string($vendor);
        Assert::string($plugin);

        // Written at the "all channels, all locales" scope, which is where the plugin's settings
        // are meant to live - the resolved brand is stored once per product, so a per-channel
        // attribute would have no single right answer.
        $setting = $this->settingProvider->getSettingOrCreateNew($vendor, $plugin, $path, null, null);
        $this->settingProvider->resetExistingValue($setting);
        $setting->setStorageType($type);
        $setting->setValue($value);

        $this->settingManager->persist($setting);
        $this->settingManager->flush();

        $this->settingsCache->clear();
    }
}
