<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Unit\Provider;

use Madcoders\SyliusBrandPlugin\Provider\BrandSettingsProvider;
use Madcoders\SyliusBrandPlugin\Provider\BrandSettingsProviderInterface;
use MonsieurBiz\SyliusSettingsPlugin\Exception\SettingsException;
use MonsieurBiz\SyliusSettingsPlugin\Provider\SettingsProviderInterface;
use MonsieurBiz\SyliusSettingsPlugin\Settings\RegistryInterface;
use MonsieurBiz\SyliusSettingsPlugin\Settings\SettingsInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;

final class BrandSettingsProviderTest extends TestCase
{
    public function testItIsDisabledWhenNothingIsConfigured(): void
    {
        $provider = $this->createProvider([]);

        self::assertFalse($provider->isEnabled());
        self::assertNull($provider->getBrandAttributeCode());
        self::assertSame([], $provider->getBrandMapping());
    }

    public function testItIsEnabledOnlyForABooleanTrue(): void
    {
        self::assertTrue($this->createProvider([BrandSettingsProviderInterface::PATH_ENABLED => true])->isEnabled());

        // A truthy string is what a hand-written `monsieurbiz:settings:set ... --type=text` leaves
        // behind. It must not silently switch the feature on.
        self::assertFalse($this->createProvider([BrandSettingsProviderInterface::PATH_ENABLED => '1'])->isEnabled());
        self::assertFalse($this->createProvider([BrandSettingsProviderInterface::PATH_ENABLED => null])->isEnabled());
    }

    public function testItTrimsTheAttributeCodeAndTreatsBlankAsUnset(): void
    {
        self::assertSame(
            'brand',
            $this->createProvider([BrandSettingsProviderInterface::PATH_BRAND_ATTRIBUTE => '  brand '])->getBrandAttributeCode(),
        );

        self::assertNull(
            $this->createProvider([BrandSettingsProviderInterface::PATH_BRAND_ATTRIBUTE => '   '])->getBrandAttributeCode(),
        );
    }

    public function testItNormalisesMappingRowsFromTheForm(): void
    {
        $provider = $this->createProvider([BrandSettingsProviderInterface::PATH_BRAND_MAPPING => [
            ['source' => '  Nike Inc. ', 'brand' => ' nike '],
            ['source' => 'NIKE Sportswear', 'brand' => 'nike'],
        ]]);

        self::assertSame(
            ['nike inc.' => 'nike', 'nike sportswear' => 'nike'],
            $provider->getBrandMapping(),
        );
    }

    public function testItAcceptsAFlatMapAndAJsonString(): void
    {
        $flat = $this->createProvider([BrandSettingsProviderInterface::PATH_BRAND_MAPPING => ['Nike Inc.' => 'nike']]);
        self::assertSame(['nike inc.' => 'nike'], $flat->getBrandMapping());

        $json = $this->createProvider([BrandSettingsProviderInterface::PATH_BRAND_MAPPING => '{"Nike Inc.":"nike"}']);
        self::assertSame(['nike inc.' => 'nike'], $json->getBrandMapping());
    }

    public function testItDropsIncompleteOrUnusableMappingRows(): void
    {
        $provider = $this->createProvider([BrandSettingsProviderInterface::PATH_BRAND_MAPPING => [
            ['source' => 'ok', 'brand' => 'fine'],
            ['source' => '', 'brand' => 'nike'],
            ['source' => 'nike', 'brand' => '  '],
            ['source' => 'nike'],
            ['brand' => 'nike'],
            // A bare string in a list: the key is an integer, so there is no source value to key
            // the mapping by.
            'not-a-row-at-all',
        ]]);

        self::assertSame(['ok' => 'fine'], $provider->getBrandMapping());
    }

    public function testItFallsBackToAOneToOneMappingWhenTheValueIsNotMapped(): void
    {
        $provider = $this->createProvider([BrandSettingsProviderInterface::PATH_BRAND_MAPPING => [
            ['source' => 'Nike Inc.', 'brand' => 'nike'],
        ]]);

        self::assertSame('nike', $provider->resolveBrandCode('Nike Inc.'));
        self::assertSame('nike', $provider->resolveBrandCode('  NIKE INC.  '));
        self::assertSame('adidas', $provider->resolveBrandCode('adidas'));
        self::assertNull($provider->resolveBrandCode('   '));
    }

    public function testItTreatsAnUnknownSettingsAliasAsUnconfigured(): void
    {
        $settingsProvider = $this->createMock(SettingsProviderInterface::class);
        $settingsProvider
            ->method('getSettingValueByChannelAndLocale')
            ->willThrowException(new SettingsException('unknown alias'))
        ;

        $registry = $this->createMock(RegistryInterface::class);
        $registry->method('getByAlias')->willReturn(null);

        $provider = new BrandSettingsProvider(
            $settingsProvider,
            $registry,
            $this->createChannelContext($this->createMock(ChannelInterface::class)),
            $this->createMock(ChannelRepositoryInterface::class),
        );

        self::assertFalse($provider->isEnabled());
        self::assertNull($provider->getBrandAttributeCode());
        self::assertSame([], $provider->getBrandMapping());
    }

    public function testItFallsBackToAnEnabledChannelOutsideARequest(): void
    {
        $channel = $this->createMock(ChannelInterface::class);

        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willThrowException(new ChannelNotFoundException());

        $channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $channelRepository->method('findOneBy')->with(['enabled' => true])->willReturn($channel);

        $settingsProvider = $this->createMock(SettingsProviderInterface::class);
        $settingsProvider
            ->expects(self::once())
            ->method('getSettingValueByChannelAndLocale')
            ->with(BrandSettingsProviderInterface::ALIAS, BrandSettingsProviderInterface::PATH_ENABLED, $channel)
            ->willReturn(true)
        ;

        $provider = new BrandSettingsProvider($settingsProvider, $this->createRegistry([]), $channelContext, $channelRepository);

        self::assertTrue($provider->isEnabled());
    }

    public function testItIsInertWhenThereIsNoChannelAtAll(): void
    {
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willThrowException(new ChannelNotFoundException());

        $channelRepository = $this->createMock(ChannelRepositoryInterface::class);
        $channelRepository->method('findOneBy')->willReturn(null);

        $settingsProvider = $this->createMock(SettingsProviderInterface::class);
        $settingsProvider->expects(self::never())->method('getSettingValueByChannelAndLocale');

        $provider = new BrandSettingsProvider($settingsProvider, $this->createRegistry([]), $channelContext, $channelRepository);

        self::assertFalse($provider->isEnabled());
    }

    /** @param array<string, mixed> $values */
    private function createProvider(array $values): BrandSettingsProvider
    {
        $channel = $this->createMock(ChannelInterface::class);

        $settingsProvider = $this->createMock(SettingsProviderInterface::class);
        $settingsProvider
            ->method('getSettingValueByChannelAndLocale')
            ->willReturnCallback(static fn (string $alias, string $path): mixed => $values[$path] ?? null)
        ;

        return new BrandSettingsProvider(
            $settingsProvider,
            $this->createRegistry($values),
            $this->createChannelContext($channel),
            $this->createMock(ChannelRepositoryInterface::class),
        );
    }

    /**
     * The registry is how the global-scope paths are read.
     *
     * @param array<string, mixed> $values
     */
    private function createRegistry(array $values): RegistryInterface
    {
        $settings = $this->createMock(SettingsInterface::class);
        $settings
            ->method('getCurrentValue')
            ->willReturnCallback(static fn (mixed $channel, ?string $locale, string $path): mixed => $values[$path] ?? null)
        ;

        $registry = $this->createMock(RegistryInterface::class);
        $registry->method('getByAlias')->willReturn($settings);

        return $registry;
    }

    private function createChannelContext(ChannelInterface $channel): ChannelContextInterface
    {
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        return $channelContext;
    }
}
