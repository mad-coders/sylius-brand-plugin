<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Shop\Brand;

use Sylius\Behat\Page\Shop\Page as ShopPage;

final class ShowPage extends ShopPage implements ShowPageInterface
{
    public function getRouteName(): string
    {
        return 'madcoders_sylius_brand_shop_brand_show';
    }

    public function getBrandName(): string
    {
        return trim($this->getElement('brand_name')->getText());
    }

    public function getListedProductCodes(): array
    {
        $codes = [];

        // Sylius' own product card carries data-test-product="<code>", so the brand page is
        // asserted against the same marker as every other product listing in the suite.
        foreach ($this->getDocument()->findAll('css', '[data-test-product]') as $card) {
            $code = $card->getAttribute('data-test-product');

            if (null !== $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'brand_name' => '[data-test-madcoders-brand-show] h1',
        ]);
    }
}
