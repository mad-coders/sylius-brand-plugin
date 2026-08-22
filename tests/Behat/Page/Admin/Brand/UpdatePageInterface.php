<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Admin\Brand;

use Sylius\Behat\Page\Admin\Crud\UpdatePageInterface as BaseUpdatePageInterface;

interface UpdatePageInterface extends BaseUpdatePageInterface
{
    public function disable(): void;

    public function enable(): void;
}
