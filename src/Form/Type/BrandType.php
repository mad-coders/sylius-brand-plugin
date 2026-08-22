<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class BrandType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // No inline constraints anywhere in this form: they live on the model, in
            // config/validation/, so fixtures and imports are covered by the same rules.
            ->add('code', TextType::class, [
                'label' => 'sylius.ui.code',
                'help' => 'madcoders_sylius_brand.form.brand.code_help',
            ])
            ->add('translations', ResourceTranslationsType::class, [
                'label' => 'sylius.ui.translations',
                'entry_type' => BrandTranslationType::class,
            ])
            ->add('images', CollectionType::class, [
                'label' => 'madcoders_sylius_brand.form.brand.logo',
                'entry_type' => BrandImageType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
            ])
            ->add('position', IntegerType::class, [
                'label' => 'sylius.ui.position',
                'help' => 'madcoders_sylius_brand.form.brand.position_help',
                'required' => false,
                'empty_data' => '0',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius.ui.enabled',
                'required' => false,
            ])
            ->add('displayOnHomepage', CheckboxType::class, [
                'label' => 'madcoders_sylius_brand.ui.display_on_homepage',
                'required' => false,
            ])
            ->add('displayOnProductPage', CheckboxType::class, [
                'label' => 'madcoders_sylius_brand.ui.display_on_product_page',
                'required' => false,
            ])
            ->add('displayOnProductTile', CheckboxType::class, [
                'label' => 'madcoders_sylius_brand.ui.display_on_product_tile',
                'required' => false,
            ])
            ->add('displayOnBrandOverview', CheckboxType::class, [
                'label' => 'madcoders_sylius_brand.ui.display_on_brand_overview',
                // The one toggle that does more than hide a widget: it also gates the brand's own
                // listing page. Documented here because the form is where an admin meets it.
                'help' => 'madcoders_sylius_brand.form.brand.display_on_brand_overview_help',
                'required' => false,
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'madcoders_sylius_brand_brand';
    }
}
