<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Model;

use Sylius\Component\Core\Model\ImagesAwareInterface;
use Sylius\Resource\Model\CodeAwareInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;
use Sylius\Resource\Model\ToggleableInterface;
use Sylius\Resource\Model\TranslatableInterface;

/**
 * A brand: the manufacturer or label a product belongs to.
 *
 * The brand is the *target* of resolution, never the source - a product's brand comes from a
 * product attribute value routed through the configured mapping. See
 * docs/adr-log/0004-brand-resolution-from-a-product-attribute.md.
 */
interface BrandInterface extends
    ResourceInterface,
    CodeAwareInterface,
    ToggleableInterface,
    TimestampableInterface,
    TranslatableInterface,
    ImagesAwareInterface
{
    /** The image type carried by a brand's logo. */
    public const LOGO_IMAGE_TYPE = 'logo';

    public function getId(): ?int;

    /** Ordering on the brand overview page; lower comes first. */
    public function getPosition(): int;

    public function setPosition(int $position): void;

    public function isDisplayOnHomepage(): bool;

    public function setDisplayOnHomepage(bool $displayOnHomepage): void;

    public function isDisplayOnProductPage(): bool;

    public function setDisplayOnProductPage(bool $displayOnProductPage): void;

    public function isDisplayOnProductTile(): bool;

    public function setDisplayOnProductTile(bool $displayOnProductTile): void;

    /**
     * Whether the brand is listed on the overview page. This toggle also gates the brand's own
     * product listing page, which 404s when it is off - see
     * docs/adr-log/0008-display-toggles-per-brand.md.
     */
    public function isDisplayOnBrandOverview(): bool;

    public function setDisplayOnBrandOverview(bool $displayOnBrandOverview): void;

    /** The logo, or null when the brand has no image of type {@see self::LOGO_IMAGE_TYPE}. */
    public function getLogo(): ?BrandImageInterface;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;

    public function getDescription(): ?string;

    public function setDescription(?string $description): void;

    public function getMetaKeywords(): ?string;

    public function setMetaKeywords(?string $metaKeywords): void;

    public function getMetaDescription(): ?string;

    public function setMetaDescription(?string $metaDescription): void;
}
