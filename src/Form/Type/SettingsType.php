<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Form\Type;

use MonsieurBiz\SyliusSettingsPlugin\Form\AbstractSettingsType;
use MonsieurBiz\SyliusSettingsPlugin\Form\SettingsTypeInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

/**
 * The plugin's settings screen, rendered by the Settings plugin at
 * Admin → Settings → Brands.
 *
 * `addWithDefaultCheckbox()` is the Settings plugin's mechanism for per-channel overrides: on a
 * channel tab each field gets a "use the default value" checkbox, and unchecking it stores a
 * channel-specific value. On the default tab there is nothing to fall back to, so the checkbox is
 * omitted - `isDefaultForm()` is how the parent tells us which tab we are on.
 *
 * @see docs/adr-log/0003-configuration-through-the-settings-plugin.md
 */
final class SettingsType extends AbstractSettingsType implements SettingsTypeInterface
{
    /**
     * Attribute types whose value can name a brand. A checkbox is a boolean and a date is a date -
     * neither can identify a brand, and offering them would only invite a mapping that never
     * matches. The resolver ignores them for the same reason.
     */
    private const array BRANDABLE_ATTRIBUTE_TYPES = ['text', 'textarea', 'select', 'integer', 'percent'];

    /** @param RepositoryInterface<ProductAttributeInterface> $productAttributeRepository */
    public function __construct(
        private readonly RepositoryInterface $productAttributeRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaultCheckbox(
            $builder,
            'enabled',
            CheckboxType::class,
            [
                'label' => 'madcoders_sylius_brand.form.settings.enabled',
                'help' => 'madcoders_sylius_brand.form.settings.enabled_help',
                'required' => false,
            ],
        );

        // Only on the "all channels" tab. There is one brand_id per product, so a per-channel
        // attribute or mapping has no single correct answer; offering the fields on a channel tab
        // would invite a setting that silently does nothing. BrandSettingsProvider reads both at
        // the global scope and ignores channel overrides for the same reason.
        if (!$this->isDefaultForm($builder)) {
            return;
        }

        $this->addWithDefaultCheckbox(
            $builder,
            'brand_attribute',
            ChoiceType::class,
            [
                'label' => 'madcoders_sylius_brand.form.settings.brand_attribute',
                'help' => 'madcoders_sylius_brand.form.settings.brand_attribute_help',
                'required' => false,
                'placeholder' => 'madcoders_sylius_brand.form.settings.brand_attribute_placeholder',
                'choices' => $this->getBrandAttributeChoices(),
            ],
        );

        $this->addWithDefaultCheckbox(
            $builder,
            'brand_mapping',
            LiveCollectionType::class,
            [
                'label' => 'madcoders_sylius_brand.form.settings.brand_mapping',
                'help' => 'madcoders_sylius_brand.form.settings.brand_mapping_help',
                'entry_type' => BrandMappingEntryType::class,
                'required' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'delete_empty' => true,
                'button_delete_options' => [
                    'attr' => ['class' => 'btn-outline-danger'],
                ],
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function getBrandAttributeChoices(): array
    {
        $choices = [];

        foreach ($this->productAttributeRepository->findAll() as $attribute) {
            $code = $attribute->getCode();

            if (null === $code || !\in_array($attribute->getType(), self::BRANDABLE_ATTRIBUTE_TYPES, true)) {
                continue;
            }

            // Keyed by label, valued by code - the code is what is stored, and it is shown in the
            // label too because two attributes can easily share a name across locales.
            $choices[\sprintf('%s (%s)', $attribute->getName() ?? $code, $code)] = $code;
        }

        ksort($choices);

        return $choices;
    }

    // getBlockPrefix() is deliberately not overridden: the Settings plugin renders the form through
    // its own Live Component and templates keyed on the parent's block prefix.
}
