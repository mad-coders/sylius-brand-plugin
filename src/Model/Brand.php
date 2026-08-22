<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Resource\Model\TimestampableTrait;
use Sylius\Resource\Model\ToggleableTrait;
use Sylius\Resource\Model\TranslatableTrait;
use Sylius\Resource\Model\TranslationInterface;

/**
 * @see BrandInterface
 *
 * **Writing translated fields.** `getName()`, `setSlug()` and the rest go through
 * `getTranslation()`, which falls back to the fallback locale when the current locale has no
 * translation yet. That is fine for reads and a trap for writes: setting a name for a locale the
 * brand has no translation in will overwrite the *fallback* translation instead of creating a new
 * one. This is Sylius' TranslatableTrait behaviour, shared by Product, Taxon and every other
 * translatable model, so it is kept rather than special-cased here.
 *
 * To write per locale, do one of the two things the plugin itself does:
 *   - set the fallback locale to the locale you are writing (BrandExampleFactory), or
 *   - write to the translation objects directly (GenerateBrandSlugListener, and the admin form via
 *     ResourceTranslationsType).
 */
class Brand implements BrandInterface, \Stringable
{
    use TimestampableTrait;
    use ToggleableTrait;
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
    }

    protected ?int $id = null;

    protected ?string $code = null;

    protected int $position = 0;

    protected bool $displayOnHomepage = false;

    protected bool $displayOnProductPage = true;

    protected bool $displayOnProductTile = false;

    protected bool $displayOnBrandOverview = true;

    /** @var Collection<array-key, ImageInterface> */
    protected Collection $images;

    public function __construct()
    {
        $this->initializeTranslationsCollection();

        $this->images = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    /**
     * Falls back to the code, and never throws.
     *
     * `getName()` needs a current locale, and there are places - a console command, an exception
     * message, a var_dump in a test - where a brand is stringified before Sylius' locale assigner
     * has touched it. A __toString() that throws turns a diagnostic into a second bug.
     */
    public function __toString(): string
    {
        try {
            return (string) ($this->getName() ?? $this->code);
        } catch (\RuntimeException) {
            return (string) $this->code;
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isDisplayOnHomepage(): bool
    {
        return $this->displayOnHomepage;
    }

    public function setDisplayOnHomepage(bool $displayOnHomepage): void
    {
        $this->displayOnHomepage = $displayOnHomepage;
    }

    public function isDisplayOnProductPage(): bool
    {
        return $this->displayOnProductPage;
    }

    public function setDisplayOnProductPage(bool $displayOnProductPage): void
    {
        $this->displayOnProductPage = $displayOnProductPage;
    }

    public function isDisplayOnProductTile(): bool
    {
        return $this->displayOnProductTile;
    }

    public function setDisplayOnProductTile(bool $displayOnProductTile): void
    {
        $this->displayOnProductTile = $displayOnProductTile;
    }

    public function isDisplayOnBrandOverview(): bool
    {
        return $this->displayOnBrandOverview;
    }

    public function setDisplayOnBrandOverview(bool $displayOnBrandOverview): void
    {
        $this->displayOnBrandOverview = $displayOnBrandOverview;
    }

    public function getImages(): Collection
    {
        return $this->images;
    }

    public function getImagesByType(string $type): Collection
    {
        return $this->images->filter(static fn (ImageInterface $image): bool => $type === $image->getType());
    }

    public function hasImages(): bool
    {
        return !$this->images->isEmpty();
    }

    public function hasImage(ImageInterface $image): bool
    {
        return $this->images->contains($image);
    }

    public function addImage(ImageInterface $image): void
    {
        $image->setOwner($this);
        $this->images->add($image);
    }

    public function removeImage(ImageInterface $image): void
    {
        if ($this->hasImage($image)) {
            $image->setOwner(null);
            $this->images->removeElement($image);
        }
    }

    public function getLogo(): ?BrandImageInterface
    {
        $logo = $this->getImagesByType(BrandInterface::LOGO_IMAGE_TYPE)->first();

        return $logo instanceof BrandImageInterface ? $logo : null;
    }

    public function getName(): ?string
    {
        return $this->getBrandTranslation()->getName();
    }

    public function setName(?string $name): void
    {
        $this->getBrandTranslation()->setName($name);
    }

    public function getSlug(): ?string
    {
        return $this->getBrandTranslation()->getSlug();
    }

    public function setSlug(?string $slug): void
    {
        $this->getBrandTranslation()->setSlug($slug);
    }

    public function getDescription(): ?string
    {
        return $this->getBrandTranslation()->getDescription();
    }

    public function setDescription(?string $description): void
    {
        $this->getBrandTranslation()->setDescription($description);
    }

    public function getMetaKeywords(): ?string
    {
        return $this->getBrandTranslation()->getMetaKeywords();
    }

    public function setMetaKeywords(?string $metaKeywords): void
    {
        $this->getBrandTranslation()->setMetaKeywords($metaKeywords);
    }

    public function getMetaDescription(): ?string
    {
        return $this->getBrandTranslation()->getMetaDescription();
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->getBrandTranslation()->setMetaDescription($metaDescription);
    }

    /**
     * An application that supplies its own translation entity **must override this** - Doctrine
     * rejects a translation whose class is not the one the association is mapped to. Same contract
     * as Sylius' own translatable models; see docs/INSTALLATION.md.
     */
    protected function createTranslation(): TranslationInterface
    {
        return new BrandTranslation();
    }

    private function getBrandTranslation(): BrandTranslationInterface
    {
        /** @var BrandTranslationInterface $translation */
        $translation = $this->getTranslation();

        return $translation;
    }
}
