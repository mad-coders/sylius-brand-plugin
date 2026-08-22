<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Form\Type;

use Madcoders\SyliusBrandPlugin\Generator\BrandSlugGeneratorInterface;
use Madcoders\SyliusBrandPlugin\Model\BrandTranslationInterface;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class BrandTranslationType extends AbstractResourceType
{
    /**
     * @param string $dataClass FQCN
     * @param string[] $validationGroups
     */
    public function __construct(
        string $dataClass,
        array $validationGroups,
        private readonly BrandSlugGeneratorInterface $slugGenerator,
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // POST_SUBMIT, so the generated slug exists *before* validation runs and the uniqueness
        // constraint can actually see it. Generating it later - on the resource event, as the
        // non-form paths do - would mean a colliding slug slipped past validation and blew up as a
        // 500 at flush.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $translation = $event->getData();

            if ($translation instanceof BrandTranslationInterface) {
                $this->slugGenerator->fillMissingSlug($translation);
            }
        });

        $builder
            ->add('name', TextType::class, [
                'label' => 'sylius.ui.name',
            ])
            ->add('slug', TextType::class, [
                'label' => 'sylius.ui.slug',
                'help' => 'madcoders_sylius_brand.form.brand.slug_help',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'sylius.ui.description',
                'required' => false,
            ])
            ->add('metaKeywords', TextType::class, [
                'label' => 'sylius.ui.meta_keywords',
                'required' => false,
            ])
            ->add('metaDescription', TextType::class, [
                'label' => 'sylius.ui.meta_description',
                'required' => false,
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'madcoders_sylius_brand_brand_translation';
    }
}
