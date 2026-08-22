<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Entity\Brand;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusBrandPlugin\Model\BrandTranslation as BaseBrandTranslation;

#[ORM\Entity]
#[ORM\Table(name: 'madcoders_brand__brand_translation')]
class BrandTranslation extends BaseBrandTranslation
{
}
