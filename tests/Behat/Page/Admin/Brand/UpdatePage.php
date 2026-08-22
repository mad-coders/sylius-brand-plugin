<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Admin\Brand;

use Sylius\Behat\Page\Admin\Crud\UpdatePage as BaseUpdatePage;

/**
 * Sylius' CRUD update page deliberately keeps `getDocument()` protected, so form interaction lives
 * in a page object rather than in the context.
 */
final class UpdatePage extends BaseUpdatePage implements UpdatePageInterface
{
    public function disable(): void
    {
        $this->getDocument()->uncheckField('Enabled');
    }

    public function enable(): void
    {
        $this->getDocument()->checkField('Enabled');
    }
}
