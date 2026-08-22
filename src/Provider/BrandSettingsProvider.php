<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Provider;

use MonsieurBiz\SyliusSettingsPlugin\Exception\SettingsException;
use MonsieurBiz\SyliusSettingsPlugin\Provider\SettingsProviderInterface;
use MonsieurBiz\SyliusSettingsPlugin\Settings\RegistryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @see BrandSettingsProviderInterface
 *
 * Two things this class exists to absorb:
 *
 * 1. The Settings plugin returns `mixed` and throws when the alias is unknown. Every read here
 *    narrows the type and falls back to the documented default instead of propagating.
 * 2. Settings are channel-scoped, and the channel context does not resolve outside a request - the
 *    resync command runs on the CLI. When there is no current channel this falls back to the first
 *    enabled channel, so the command reads a real configuration rather than crashing.
 *
 * Two scopes, deliberately different:
 *
 *  - `enabled` is read **per channel**. Whether brands are shown is a display decision, and a shop
 *    may perfectly well want them on its retail channel and not on its B2B one.
 *  - `brand_attribute` and `brand_mapping` are read at the **global** scope only, bypassing the
 *    channel entirely. There is one `brand_id` per product, so a per-channel attribute has no
 *    single correct answer - and resolving it through "the current channel, or the first enabled
 *    one on the CLI" would make the same product resolve differently depending on whether it was
 *    saved in the admin or by the resync command. The settings form does not offer these two
 *    fields on channel tabs, and any value left there by an older install or by
 *    `monsieurbiz:settings:set --channel=...` is ignored rather than silently honoured.
 */
final class BrandSettingsProvider implements BrandSettingsProviderInterface, ResetInterface
{
    /**
     * The normalised mapping, memoised.
     *
     * The settings plugin caches the raw stored value, but normalising it - decoding, trimming,
     * lower-casing every key - runs on each call, and the resolver calls this once per candidate
     * per product. Over a catalogue-wide resync that is the same small array rebuilt tens of
     * thousands of times. A single slot, because the mapping is global.
     *
     * @var array<string, string>|null
     */
    private ?array $mappingCache = null;

    /**
     * The feature toggle, memoised per channel code.
     *
     * Measured, not assumed: every call cost two queries - one to resolve a channel when the
     * context has none, one for the setting itself - and the product tile hook calls it once per
     * tile. On a 12-product listing that was 24 queries to answer a question whose answer cannot
     * change within a request.
     *
     * @var array<string, bool>
     */
    private array $enabledCache = [];

    /**
     * The resolved channel. `false` records "there is no channel", so the fallback lookup is not
     * repeated on every miss.
     */
    private ChannelInterface|false|null $channelCache = null;

    /** @param ChannelRepositoryInterface<ChannelInterface> $channelRepository */
    public function __construct(
        private readonly SettingsProviderInterface $settingsProvider,
        private readonly RegistryInterface $settingsRegistry,
        private readonly ChannelContextInterface $channelContext,
        private readonly ChannelRepositoryInterface $channelRepository,
    ) {
    }

    public function isEnabled(): bool
    {
        $channel = $this->resolveChannel();
        $cacheKey = $channel?->getCode() ?? '';

        return $this->enabledCache[$cacheKey] ??= true === $this->getValue(self::PATH_ENABLED);
    }

    public function getBrandAttributeCode(): ?string
    {
        $value = $this->getGlobalValue(self::PATH_BRAND_ATTRIBUTE);

        if (!\is_string($value)) {
            return null;
        }

        $code = trim($value);

        return '' === $code ? null : $code;
    }

    public function getBrandMapping(): array
    {
        return $this->mappingCache ??= $this->normalizeMapping($this->getGlobalValue(self::PATH_BRAND_MAPPING));
    }

    /**
     * Drops the memoised mapping.
     *
     * Tagged kernel.reset, so a long-running worker picks up a settings change instead of serving
     * the map it read on its first message - the failure mode memoisation would otherwise
     * introduce.
     */
    public function reset(): void
    {
        $this->mappingCache = null;
        $this->enabledCache = [];
        $this->channelCache = null;
    }

    public function resolveBrandCode(string $attributeValue): ?string
    {
        $value = trim($attributeValue);

        if ('' === $value) {
            return null;
        }

        // 1:1 unless the mapping says otherwise - see
        // docs/adr-log/0004-brand-resolution-from-a-product-attribute.md.
        return $this->getBrandMapping()[mb_strtolower($value)] ?? $value;
    }

    /**
     * Turns whatever the admin saved into a normalised source-value => brand-code map.
     *
     * Three shapes reach this method, and all three are legitimate:
     *  - a list of `{source, brand}` rows, which is what the settings form produces;
     *  - an already-flat `{source: brand}` map, which is what `monsieurbiz:settings:set --type=json`
     *    produces when an operator scripts it;
     *  - a JSON string, when the stored value predates the json storage type.
     * Anything else - and any row missing either half - is dropped rather than guessed at.
     *
     * @return array<string, string>
     */
    private function normalizeMapping(mixed $value): array
    {
        if (\is_string($value)) {
            /** @var mixed $value */
            $value = json_decode($value, true);
        }

        if (!\is_array($value)) {
            return [];
        }

        $mapping = [];

        foreach ($value as $key => $entry) {
            if (\is_array($entry)) {
                $source = $entry['source'] ?? null;
                $brand = $entry['brand'] ?? null;
            } else {
                $source = $key;
                $brand = $entry;
            }

            if (!\is_string($source) || !\is_string($brand)) {
                continue;
            }

            $source = mb_strtolower(trim($source));
            $brand = trim($brand);

            if ('' === $source || '' === $brand) {
                continue;
            }

            $mapping[$source] = $brand;
        }

        return $mapping;
    }

    /**
     * Reads a path at the global scope - no channel, no locale.
     *
     * Goes through the settings registry rather than SettingsProviderInterface because the latter
     * requires a channel; `Settings::getCurrentValue()` accepts null and returns the "all channels"
     * value, falling back to the configured default. This is still the plugin's single reader of
     * the Settings plugin, as ADR 0003 requires.
     */
    private function getGlobalValue(string $path): mixed
    {
        $settings = $this->settingsRegistry->getByAlias(self::ALIAS);

        if (null === $settings) {
            // The host has the plugin but not its settings section. Treat as unconfigured.
            return null;
        }

        try {
            return $settings->getCurrentValue(null, null, $path);
        } catch (SettingsException) {
            return null;
        }
    }

    private function getValue(string $path): mixed
    {
        $channel = $this->resolveChannel();

        if (null === $channel) {
            return null;
        }

        try {
            return $this->settingsProvider->getSettingValueByChannelAndLocale(self::ALIAS, $path, $channel);
        } catch (SettingsException) {
            // The alias is unknown, which means the host has the plugin installed but the settings
            // section is not registered. Treat it as "not configured" rather than failing a page.
            return null;
        }
    }

    private function resolveChannel(): ?ChannelInterface
    {
        if (null !== $this->channelCache) {
            return false === $this->channelCache ? null : $this->channelCache;
        }

        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            // No channel in scope - the CLI. Falling back to a query every time would make this
            // the most expensive part of a catalogue-wide resync.
            /** @var ChannelInterface|null $channel */
            $channel = $this->channelRepository->findOneBy(['enabled' => true]);
        }

        $this->channelCache = $channel instanceof ChannelInterface ? $channel : false;

        return false === $this->channelCache ? null : $this->channelCache;
    }
}
