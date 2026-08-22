<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Admin\Product;

use Sylius\Behat\Page\SyliusPageInterface;

interface ShowPageInterface extends SyliusPageInterface
{
    public function getBrandDiagnostics(): string;
}
