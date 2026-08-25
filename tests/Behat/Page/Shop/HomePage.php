<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Shop;

use FriendsOfBehat\PageObjectExtension\Page\SymfonyPage;

/**
 * The shop homepage, only far enough to read the plugin's brand strip.
 *
 * Extends the page-object-extension base rather than Sylius' own, for the reason recorded on the
 * admin product show page: Sylius renamed its base page class in every 2.x minor.
 */
final class HomePage extends SymfonyPage implements HomePageInterface
{
    public function getRouteName(): string
    {
        return 'sylius_shop_homepage';
    }

    public function getStripBrandCodes(): array
    {
        $strip = $this->getDocument()->find('css', '[data-test-madcoders-brands]');

        if (null === $strip) {
            return [];
        }

        $codes = [];

        foreach ($strip->findAll('css', '[data-test-madcoders-brand]') as $element) {
            $code = $element->getAttribute('data-test-madcoders-brand');

            if (null !== $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
