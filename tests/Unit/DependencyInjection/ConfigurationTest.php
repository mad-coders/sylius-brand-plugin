<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Unit\DependencyInjection;

use Madcoders\SyliusBrandPlugin\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * The configuration tree is the plugin's compile-time contract with a host application: once 1.0 is
 * tagged, an option cannot be renamed or given a different default without breaking installs. That
 * makes the defaults themselves worth asserting, not just the fact that the tree parses.
 */
final class ConfigurationTest extends TestCase
{
    public function testItDefaultsToTwelveProductsPerPage(): void
    {
        self::assertSame(12, $this->process([])['products_per_page']);
    }

    public function testItDefaultsToTwelveHomepageBrands(): void
    {
        self::assertSame(12, $this->process([])['homepage_brands_limit']);
    }

    public function testAHostCanOverrideEitherValue(): void
    {
        $config = $this->process([['products_per_page' => 24, 'homepage_brands_limit' => 6]]);

        self::assertSame(24, $config['products_per_page']);
        self::assertSame(6, $config['homepage_brands_limit']);
    }

    /**
     * A page size below one is not a smaller page, it is a Pagerfanta exception on the first
     * request. Rejecting it at container build time turns a production 500 into a boot failure with
     * a message naming the option.
     */
    public function testItRejectsAPageSizeBelowOne(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['products_per_page' => 0]]);
    }

    public function testItRejectsAHomepageLimitBelowOne(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['homepage_brands_limit' => 0]]);
    }

    public function testItRejectsAnUnknownOption(): void
    {
        // A typo in a host's configuration has to fail loudly; silently ignoring it would leave the
        // host believing they had changed something.
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['prodcuts_per_page' => 24]]);
    }

    /**
     * @param list<array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }
}
