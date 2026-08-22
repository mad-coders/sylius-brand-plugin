<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One row of the value-to-brand mapping: the raw attribute value on the left, the brand code on the
 * right.
 *
 * The rows are stored as plain arrays (and so as JSON by the Settings plugin) rather than as an
 * entity, because the mapping is configuration, not data: it has no identity, no history, and it is
 * meaningless outside the setting it belongs to.
 */
final class BrandMappingEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('source', TextType::class, [
                'label' => 'madcoders_sylius_brand.form.mapping.source',
                'help' => 'madcoders_sylius_brand.form.mapping.source_help',
                'required' => false,
            ])
            ->add('brand', TextType::class, [
                'label' => 'madcoders_sylius_brand.form.mapping.brand',
                'help' => 'madcoders_sylius_brand.form.mapping.brand_help',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // No data_class: a row is an array, which is what the Settings plugin stores as JSON.
            'data_class' => null,
            'empty_data' => ['source' => '', 'brand' => ''],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'madcoders_sylius_brand_mapping_entry';
    }
}
