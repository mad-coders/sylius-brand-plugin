<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The plugin has no compile-time configuration of its own: everything a shop can configure lives in
 * the Settings plugin, editable at runtime from the admin. See
 * docs/adr-log/0003-configuration-through-the-settings-plugin.md.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        return new TreeBuilder('madcoders_sylius_brand');
    }
}
