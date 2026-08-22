<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Entity\Brand;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusBrandPlugin\Model\Brand as BaseBrand;
use Sylius\Resource\Model\TranslationInterface;

#[ORM\Entity]
#[ORM\Table(name: 'madcoders_brand__brand')]
class Brand extends BaseBrand
{
    /**
     * Required whenever an application supplies its own translation entity.
     *
     * `getTranslation()` calls this to build a translation for a locale that has none yet, and the
     * plugin's own implementation can only return the plugin's model class. Doctrine then rejects
     * it, because the association is mapped to *this* application's translation entity. Sylius'
     * models have the same contract - see docs/INSTALLATION.md.
     */
    protected function createTranslation(): TranslationInterface
    {
        return new BrandTranslation();
    }
}
