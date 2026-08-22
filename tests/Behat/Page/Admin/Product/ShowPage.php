<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Admin\Product;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPage;

/**
 * Sylius' admin product show page, only far enough to read the plugin's diagnostics panel.
 *
 * Extends the **page-object-extension** base rather than a Sylius one on purpose: Sylius has
 * renamed that class in every 2.x minor - absent in 2.0, `Sylius\Behat\Page\SymfonyPage` in 2.1,
 * `Sylius\Behat\Page\SyliusPage` in 2.2 - so a plugin spanning `^2.0` cannot name any of them. The
 * vendor class they all extend is stable, and the `sylius.behat.symfony_page` parent service passes
 * the same three arguments in every version.
 */
final class ShowPage extends SymfonyPage implements ShowPageInterface
{
    public function getRouteName(): string
    {
        return 'sylius_admin_product_show';
    }

    public function getBrandDiagnostics(): string
    {
        return trim($this->getElement('brand_diagnostics')->getText());
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'brand_diagnostics' => '[data-test-madcoders-product-brand-diagnostics]',
        ]);
    }
}
