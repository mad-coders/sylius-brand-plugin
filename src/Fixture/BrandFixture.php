<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class BrandFixture extends AbstractResourceFixture
{
    public function getName(): string
    {
        return 'madcoders_brand';
    }

    #[\Override]
    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $resourceNode
            ->children()
                ->scalarNode('code')->cannotBeEmpty()->end()
                ->scalarNode('name')->cannotBeEmpty()->end()
                ->scalarNode('slug')->end()
                ->scalarNode('description')->end()
                ->integerNode('position')->end()
                ->booleanNode('enabled')->end()
                ->booleanNode('display_on_homepage')->end()
                ->booleanNode('display_on_product_page')->end()
                ->booleanNode('display_on_product_tile')->end()
                ->booleanNode('display_on_brand_overview')->end()
                ->scalarNode('image')->end()
        ;
    }
}
