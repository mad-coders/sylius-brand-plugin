<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\EventListener;

use Madcoders\SyliusBrandPlugin\Generator\BrandSlugGeneratorInterface;
use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Madcoders\SyliusBrandPlugin\Model\BrandTranslationInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Fills in blank slugs for writes that do **not** go through the admin form - an import, an API
 * call, a data migration.
 *
 * The form does its own generation, in a POST_SUBMIT listener, because the resource event fires
 * after validation: a slug generated here would arrive too late for the uniqueness constraint to
 * check it, and a collision would surface as a 500 at flush instead of a field error. Both paths
 * call the same generator, so they cannot disagree.
 *
 * @see \Madcoders\SyliusBrandPlugin\Form\Type\BrandTranslationType
 */
final readonly class GenerateBrandSlugListener
{
    public function __construct(
        private BrandSlugGeneratorInterface $slugGenerator,
    ) {
    }

    public function __invoke(GenericEvent $event): void
    {
        $brand = $event->getSubject();

        if (!$brand instanceof BrandInterface) {
            return;
        }

        foreach ($brand->getTranslations() as $translation) {
            if ($translation instanceof BrandTranslationInterface) {
                $this->slugGenerator->fillMissingSlug($translation);
            }
        }
    }
}
