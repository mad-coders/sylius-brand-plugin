<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Form\Type;

use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * A brand has exactly one kind of image - its logo - so the type field is hidden and fixed rather
 * than free text as in Sylius' generic image form. Sylius' own ImageType is not extended for that
 * reason: overriding an inherited visible field with a hidden one is more surprising than declaring
 * the two fields we actually want.
 */
final class BrandImageType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', HiddenType::class, [
                'empty_data' => BrandInterface::LOGO_IMAGE_TYPE,
            ])
            ->add('file', FileType::class, [
                'label' => 'madcoders_sylius_brand.form.brand.logo',
                'required' => false,
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'madcoders_sylius_brand_brand_image';
    }
}
