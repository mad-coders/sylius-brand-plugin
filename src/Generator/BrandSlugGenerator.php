<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Generator;

use Madcoders\SyliusBrandPlugin\Model\BrandTranslationInterface;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;

/**
 * @see BrandSlugGeneratorInterface
 */
final readonly class BrandSlugGenerator implements BrandSlugGeneratorInterface
{
    public function __construct(
        private SlugGeneratorInterface $slugGenerator,
    ) {
    }

    public function fillMissingSlug(BrandTranslationInterface $translation): bool
    {
        $slug = $translation->getSlug();

        // An existing slug is never overwritten. Changing a live URL is an editorial decision, and
        // doing it silently on every save would break links whenever somebody fixes a typo in the
        // name.
        if (null !== $slug && '' !== trim($slug)) {
            return false;
        }

        $name = $translation->getName();

        if (null === $name || '' === trim($name)) {
            return false;
        }

        $translation->setSlug($this->slugGenerator->generate($name));

        return true;
    }
}
