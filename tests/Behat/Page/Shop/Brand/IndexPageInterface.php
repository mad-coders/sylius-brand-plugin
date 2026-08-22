<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Shop\Brand;

use Sylius\Behat\Page\Shop\PageInterface as ShopPageInterface;

interface IndexPageInterface extends ShopPageInterface
{
    /** @return list<string> */
    public function getListedBrandCodes(): array;
}
