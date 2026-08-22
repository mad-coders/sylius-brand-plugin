<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Entity\Brand;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusBrandPlugin\Model\BrandImage as BaseBrandImage;

#[ORM\Entity]
#[ORM\Table(name: 'madcoders_brand__brand_image')]
class BrandImage extends BaseBrandImage
{
}
