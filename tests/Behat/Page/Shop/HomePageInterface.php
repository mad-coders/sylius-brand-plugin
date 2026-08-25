<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Shop;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPageInterface;

interface HomePageInterface extends SymfonyPageInterface
{
    /**
     * Brand codes rendered in the plugin's homepage strip.
     *
     * @return list<string>
     */
    public function getStripBrandCodes(): array;
}
