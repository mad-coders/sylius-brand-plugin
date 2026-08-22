<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Admin\Product;

use Sylius\Behat\Page\SyliusPage;

/**
 * Sylius' admin product show page, only far enough to read the plugin's diagnostics panel.
 */
final class ShowPage extends SyliusPage implements ShowPageInterface
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
