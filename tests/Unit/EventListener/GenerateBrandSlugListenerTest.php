<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Unit\EventListener;

use Madcoders\SyliusBrandPlugin\EventListener\GenerateBrandSlugListener;
use Madcoders\SyliusBrandPlugin\Generator\BrandSlugGenerator;
use Madcoders\SyliusBrandPlugin\Model\Brand;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Covers the listener's own job - reaching every translation of the brand. The generation rules
 * themselves are covered by BrandSlugGeneratorTest.
 */
final class GenerateBrandSlugListenerTest extends TestCase
{
    public function testItGeneratesAMissingSlugForEveryLocale(): void
    {
        $brand = new Brand();

        // Fallback follows the locale being written, or the second setName() would land on the
        // first translation - see BrandTest for the full explanation.
        foreach (['en_US' => 'Modern Wear', 'pl_PL' => 'Nowoczesna Odzież'] as $locale => $name) {
            $brand->setCurrentLocale($locale);
            $brand->setFallbackLocale($locale);
            $brand->setName($name);
        }

        $this->createListener()(new GenericEvent($brand));

        $brand->setCurrentLocale('en_US');
        self::assertSame('modern-wear', $brand->getSlug());

        $brand->setCurrentLocale('pl_PL');
        self::assertSame('nowoczesna-odziez', $brand->getSlug());
    }

    public function testItIgnoresASubjectThatIsNotABrand(): void
    {
        $this->expectNotToPerformAssertions();

        $this->createListener()(new GenericEvent(new \stdClass()));
    }

    private function createListener(): GenerateBrandSlugListener
    {
        $generator = $this->createMock(SlugGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(
            static function (string $name): string {
                $ascii = str_replace(
                    ['ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż'],
                    ['a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z'],
                    mb_strtolower($name),
                );

                return trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
            },
        );

        return new GenerateBrandSlugListener(new BrandSlugGenerator($generator));
    }
}
