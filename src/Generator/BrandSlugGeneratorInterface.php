<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Generator;

use Madcoders\SyliusBrandPlugin\Model\BrandTranslationInterface;

/**
 * Fills in a blank slug from the translation's name.
 *
 * Extracted into a service because it has to happen in two places, and they must not disagree:
 * inside the admin form *before validation runs* (so the slug uniqueness constraint sees the
 * generated value), and on the resource event for every write that does not go through the form.
 */
interface BrandSlugGeneratorInterface
{
    /**
     * Generates a slug for the translation when it has none, leaving an existing one untouched.
     *
     * @return bool whether a slug was generated
     */
    public function fillMissingSlug(BrandTranslationInterface $translation): bool;
}
