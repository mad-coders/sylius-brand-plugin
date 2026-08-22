<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Model;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Product;

/**
 * @see ProductInterface
 *
 * @mixin Product
 *
 * Unlike the plugin's own models - which are XML mapped superclasses - this trait carries Doctrine
 * attributes, because it is applied to the *host application's* Product entity and therefore has to
 * be picked up by that application's own mapping driver. See
 * docs/adr-log/0002-doctrine-xml-mapped-superclasses.md.
 *
 * `brand` is derived state. It is written only by ProductBrandSynchronizer, from the product
 * attribute value the plugin is configured to read, and is always recomputable with
 * `bin/console madcoders:brand:resync-products`.
 */
trait ProductTrait
{
    #[ORM\ManyToOne(targetEntity: BrandInterface::class)]
    #[ORM\JoinColumn(name: 'brand_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    protected ?BrandInterface $brand = null;

    public function getBrand(): ?BrandInterface
    {
        return $this->brand;
    }

    public function setBrand(?BrandInterface $brand): void
    {
        $this->brand = $brand;
    }
}
