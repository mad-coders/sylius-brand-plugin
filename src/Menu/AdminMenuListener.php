<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

/**
 * Adds the brand entry to the admin main menu, under Catalog - a brand describes what is for sale,
 * so it sits next to products and taxons rather than under Configuration.
 */
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $catalog = $menu->getChild('catalog');

        if (null === $catalog) {
            // A host application that has rebuilt its menu without a catalog section should not get
            // a crash for it.
            return;
        }

        $catalog
            ->addChild('madcoders_brands', [
                'route' => 'madcoders_sylius_brand_admin_brand_index',
                'extras' => ['routes' => [
                    ['route' => 'madcoders_sylius_brand_admin_brand_create'],
                    ['route' => 'madcoders_sylius_brand_admin_brand_update'],
                ]],
            ])
            ->setLabel('madcoders_sylius_brand.menu.admin.brands')
            ->setLabelAttribute('icon', 'tabler:tag')
        ;
    }
}
