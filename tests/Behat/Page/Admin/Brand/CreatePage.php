<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Behat\Page\Admin\Brand;

use Sylius\Behat\Page\Admin\Crud\CreatePage as BaseCreatePage;

final class CreatePage extends BaseCreatePage implements CreatePageInterface
{
    public function specifyCode(string $code): void
    {
        $this->getDocument()->fillField('Code', $code);
    }

    public function nameIt(string $name, string $localeCode): void
    {
        $this->getElement('name', ['%locale%' => $localeCode])->setValue($name);
    }

    public function specifySlug(string $slug, string $localeCode): void
    {
        $this->getElement('slug', ['%locale%' => $localeCode])->setValue($slug);
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'code' => '#madcoders_sylius_brand_brand_code',
            // The translations collection is keyed by locale code, which is what makes a
            // per-locale field addressable at all.
            'name' => '#madcoders_sylius_brand_brand_translations_%locale%_name',
            'slug' => '#madcoders_sylius_brand_brand_translations_%locale%_slug',
        ]);
    }
}
