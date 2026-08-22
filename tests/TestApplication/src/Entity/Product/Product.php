<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusBrandPlugin\Model\ProductInterface as BrandAwareProductInterface;
use Madcoders\SyliusBrandPlugin\Model\ProductTrait as BrandAwareProductTrait;
use Sylius\Component\Core\Model\Product as BaseProduct;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product')]
class Product extends BaseProduct implements BrandAwareProductInterface
{
    use BrandAwareProductTrait;
}
