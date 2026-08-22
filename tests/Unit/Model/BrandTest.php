<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Unit\Model;

use Madcoders\SyliusBrandPlugin\Model\Brand;
use Madcoders\SyliusBrandPlugin\Model\BrandImage;
use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ImageInterface;

final class BrandTest extends TestCase
{
    public function testItStartsEnabledAndVisibleWhereABrandUsuallyIs(): void
    {
        $brand = new Brand();

        self::assertTrue($brand->isEnabled());
        self::assertTrue($brand->isDisplayOnProductPage());
        self::assertTrue($brand->isDisplayOnBrandOverview());

        // The two surfaces that add clutter to an existing shop are opt-in.
        self::assertFalse($brand->isDisplayOnHomepage());
        self::assertFalse($brand->isDisplayOnProductTile());
    }

    public function testItReadsAndWritesTranslatedFieldsThroughTheCurrentLocale(): void
    {
        $brand = new Brand();
        $brand->setCurrentLocale('en_US');
        $brand->setFallbackLocale('en_US');

        $brand->setName('Nike');
        $brand->setSlug('nike');
        $brand->setDescription('Just do it.');
        $brand->setMetaKeywords('nike, sportswear');
        $brand->setMetaDescription('Nike products.');

        self::assertSame('Nike', $brand->getName());
        self::assertSame('nike', $brand->getSlug());
        self::assertSame('Just do it.', $brand->getDescription());
        self::assertSame('nike, sportswear', $brand->getMetaKeywords());
        self::assertSame('Nike products.', $brand->getMetaDescription());
    }

    public function testItKeepsTranslationsPerLocale(): void
    {
        $brand = new Brand();

        // Pointing the fallback at the locale being written is what makes this create a new
        // translation rather than reuse the fallback one - see
        // testWritingWithADifferentFallbackLocaleOverwritesTheFallbackTranslation().
        foreach (['en_US' => 'Winter Collection', 'pl_PL' => 'Kolekcja zimowa'] as $locale => $name) {
            $brand->setCurrentLocale($locale);
            $brand->setFallbackLocale($locale);
            $brand->setName($name);
        }

        $brand->setCurrentLocale('en_US');
        self::assertSame('Winter Collection', $brand->getName());

        $brand->setCurrentLocale('pl_PL');
        self::assertSame('Kolekcja zimowa', $brand->getName());

        self::assertCount(2, $brand->getTranslations());
    }

    public function testItFallsBackWhenReadingALocaleItHasNoTranslationFor(): void
    {
        $brand = new Brand();
        $brand->setCurrentLocale('en_US');
        $brand->setFallbackLocale('en_US');
        $brand->setName('Winter Collection');

        $brand->setCurrentLocale('pl_PL');

        self::assertSame('Winter Collection', $brand->getName());
    }

    public function testWritingWithADifferentFallbackLocaleOverwritesTheFallbackTranslation(): void
    {
        // Pinning Sylius' TranslatableTrait semantics rather than endorsing them. getTranslation()
        // falls back for reads, and the translated setters go through it, so a write against a
        // locale that has no translation yet lands on the *fallback* translation. Every Sylius
        // translatable model behaves this way; the plugin's own write paths avoid it by setting the
        // fallback to the locale being written (fixtures) or by writing to the translation objects
        // directly (the slug listener and the admin form). Host code has to do one of those two.
        $brand = new Brand();
        $brand->setCurrentLocale('en_US');
        $brand->setFallbackLocale('en_US');
        $brand->setName('Winter Collection');

        $brand->setCurrentLocale('pl_PL');
        $brand->setName('Kolekcja zimowa');

        self::assertCount(1, $brand->getTranslations());

        $brand->setCurrentLocale('en_US');
        self::assertSame('Kolekcja zimowa', $brand->getName());
    }

    public function testItExposesTheFirstLogoImageAndNothingElse(): void
    {
        $brand = new Brand();
        self::assertNull($brand->getLogo());

        $logo = new BrandImage();
        $brand->addImage($logo);

        self::assertSame($logo, $brand->getLogo());
        self::assertTrue($brand->hasImages());
        self::assertSame($brand, $logo->getOwner());

        $brand->removeImage($logo);

        self::assertNull($brand->getLogo());
        self::assertFalse($brand->hasImages());
        self::assertNull($logo->getOwner());
    }

    public function testAnImageOfAnotherTypeIsNotALogo(): void
    {
        $brand = new Brand();

        $banner = new BrandImage();
        $banner->setType('banner');
        $brand->addImage($banner);

        self::assertNull($brand->getLogo());
        self::assertCount(1, $brand->getImagesByType('banner'));
        self::assertCount(0, $brand->getImagesByType(BrandInterface::LOGO_IMAGE_TYPE));
    }

    public function testANewImageIsALogoByDefault(): void
    {
        // A brand has exactly one kind of image, so an image created without a type is a logo -
        // otherwise every upload would need the admin to remember to set it.
        self::assertSame(BrandInterface::LOGO_IMAGE_TYPE, (new BrandImage())->getType());
    }

    public function testItPrintsItsNameAndFallsBackToItsCode(): void
    {
        $brand = new Brand();
        $brand->setCode('nike');
        $brand->setCurrentLocale('en_US');
        $brand->setFallbackLocale('en_US');

        self::assertSame('nike', (string) $brand);

        $brand->setName('Nike');

        self::assertSame('Nike', (string) $brand);
    }

    public function testImagesAreExposedAsSyliusImages(): void
    {
        $brand = new Brand();
        $brand->addImage(new BrandImage());

        foreach ($brand->getImages() as $image) {
            self::assertInstanceOf(ImageInterface::class, $image);
        }
    }
}
