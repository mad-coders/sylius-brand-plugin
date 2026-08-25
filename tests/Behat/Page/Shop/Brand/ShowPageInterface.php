<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Shop\Brand;

use Sylius\Behat\Page\Shop\PageInterface as ShopPageInterface;

interface ShowPageInterface extends ShopPageInterface
{
    public function getBrandName(): string;

    /** @return list<string> */
    public function getListedProductCodes(): array;

    /**
     * Brand codes rendered by the plugin's hookable on the product tiles of this page.
     *
     * @return list<string>
     */
    public function getTileBrandCodes(): array;
}
