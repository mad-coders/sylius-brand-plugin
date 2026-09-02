<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Compile-time configuration for the plugin.
 *
 * Almost everything a shop can configure lives in the Settings plugin instead, editable at runtime
 * from the admin - see docs/adr-log/0003-configuration-through-the-settings-plugin.md. What is here
 * is deliberately the exception: page sizes are a layout decision, made once by whoever builds the
 * theme, not something a shop operator changes per channel. Sylius treats its own paginate settings
 * the same way.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('madcoders_sylius_brand');

        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('products_per_page')
                    ->info('How many products a brand page lists before paginating.')
                    ->defaultValue(12)
                    ->min(1)
                ->end()
                ->integerNode('homepage_brands_limit')
                    ->info('How many brands the homepage strip shows at most.')
                    ->defaultValue(12)
                    ->min(1)
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
