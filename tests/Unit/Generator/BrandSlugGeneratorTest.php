<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Unit\Generator;

use Madcoders\SyliusBrandPlugin\Generator\BrandSlugGenerator;
use Madcoders\SyliusBrandPlugin\Model\BrandTranslation;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;

final class BrandSlugGeneratorTest extends TestCase
{
    public function testItGeneratesASlugFromTheName(): void
    {
        $translation = new BrandTranslation();
        $translation->setName('Modern Wear');

        self::assertTrue($this->createGenerator()->fillMissingSlug($translation));
        self::assertSame('modern-wear', $translation->getSlug());
    }

    public function testItNeverOverwritesAnExistingSlug(): void
    {
        $translation = new BrandTranslation();
        $translation->setName('Modern Wear');
        $translation->setSlug('the-slug-we-have-been-linking-to');

        self::assertFalse($this->createGenerator()->fillMissingSlug($translation));
        self::assertSame('the-slug-we-have-been-linking-to', $translation->getSlug());
    }

    public function testItTreatsAWhitespaceOnlySlugAsMissing(): void
    {
        $translation = new BrandTranslation();
        $translation->setName('Modern Wear');
        $translation->setSlug('   ');

        self::assertTrue($this->createGenerator()->fillMissingSlug($translation));
        self::assertSame('modern-wear', $translation->getSlug());
    }

    public function testItLeavesAnUnnamedTranslationAlone(): void
    {
        $translation = new BrandTranslation();
        $translation->setName(null);

        self::assertFalse($this->createGenerator()->fillMissingSlug($translation));
        self::assertNull($translation->getSlug());
    }

    private function createGenerator(): BrandSlugGenerator
    {
        $inner = $this->createMock(SlugGeneratorInterface::class);
        $inner->method('generate')->willReturnCallback(
            static fn (string $name): string => trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-'),
        );

        return new BrandSlugGenerator($inner);
    }
}
