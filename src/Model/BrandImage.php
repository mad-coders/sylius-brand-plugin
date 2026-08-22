<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Model;

use Sylius\Component\Core\Model\Image;

/**
 * @see BrandImageInterface
 */
class BrandImage extends Image implements BrandImageInterface
{
    public function __construct()
    {
        $this->type = BrandInterface::LOGO_IMAGE_TYPE;
    }
}
