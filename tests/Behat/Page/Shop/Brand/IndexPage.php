<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Shop\Brand;

use Sylius\Behat\Page\Shop\Page as ShopPage;

final class IndexPage extends ShopPage implements IndexPageInterface
{
    public function getRouteName(): string
    {
        return 'madcoders_sylius_brand_shop_brand_index';
    }

    public function getListedBrandCodes(): array
    {
        $codes = [];

        // Matched on the brand's code rather than its name: the code is the one thing on the tile
        // that is not translated, so the assertion does not move when the fixtures do.
        foreach ($this->getDocument()->findAll('css', '[data-test-madcoders-brand]') as $tile) {
            $code = $tile->getAttribute('data-test-madcoders-brand');

            if (null !== $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
