<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Fixture\Factory;

use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

/**
 * @implements ExampleFactoryInterface<BrandInterface>
 */
class BrandExampleFactory extends AbstractExampleFactory implements ExampleFactoryInterface
{
    private readonly OptionsResolver $optionsResolver;

    /**
     * @param FactoryInterface<BrandInterface> $brandFactory
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(
        private readonly FactoryInterface $brandFactory,
        private readonly RepositoryInterface $localeRepository,
        private readonly SlugGeneratorInterface $slugGenerator,
    ) {
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): BrandInterface
    {
        $options = $this->optionsResolver->resolve($options);

        $code = $options['code'];
        Assert::stringNotEmpty($code);

        $name = $options['name'];
        Assert::stringNotEmpty($name);

        // Asserted rather than assumed: OptionsResolver::resolve() is typed as a plain array, so
        // every value out of it is `mixed` however tightly configureOptions() constrained it.
        $position = $options['position'];
        Assert::integer($position);

        $enabled = $options['enabled'];
        Assert::boolean($enabled);

        $displayOnHomepage = $options['display_on_homepage'];
        Assert::boolean($displayOnHomepage);

        $displayOnProductPage = $options['display_on_product_page'];
        Assert::boolean($displayOnProductPage);

        $displayOnProductTile = $options['display_on_product_tile'];
        Assert::boolean($displayOnProductTile);

        $displayOnBrandOverview = $options['display_on_brand_overview'];
        Assert::boolean($displayOnBrandOverview);

        $slug = $options['slug'];
        Assert::nullOrString($slug);

        $description = $options['description'];
        Assert::nullOrString($description);

        $brand = $this->brandFactory->createNew();
        $brand->setCode($code);
        $brand->setPosition($position);
        $brand->setEnabled($enabled);
        $brand->setDisplayOnHomepage($displayOnHomepage);
        $brand->setDisplayOnProductPage($displayOnProductPage);
        $brand->setDisplayOnProductTile($displayOnProductTile);
        $brand->setDisplayOnBrandOverview($displayOnBrandOverview);

        // The same name and slug in every locale: a brand name is a proper noun, and inventing
        // per-locale variants for a fixture would only make the fixtures harder to write scenarios
        // against.
        foreach ($this->getLocaleCodes() as $localeCode) {
            $brand->setCurrentLocale($localeCode);
            $brand->setFallbackLocale($localeCode);

            $brand->setName($name);
            $brand->setSlug($slug ?? $this->slugGenerator->generate($name));
            $brand->setDescription($description);
        }

        return $brand;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('code')
            ->setAllowedTypes('code', 'string')

            ->setRequired('name')
            ->setAllowedTypes('name', 'string')

            ->setDefault('slug', null)
            ->setAllowedTypes('slug', ['null', 'string'])

            ->setDefault('description', null)
            ->setAllowedTypes('description', ['null', 'string'])

            ->setDefault('position', 0)
            ->setAllowedTypes('position', 'int')

            ->setDefault('enabled', true)
            ->setAllowedTypes('enabled', 'bool')

            ->setDefault('display_on_homepage', false)
            ->setAllowedTypes('display_on_homepage', 'bool')

            ->setDefault('display_on_product_page', true)
            ->setAllowedTypes('display_on_product_page', 'bool')

            ->setDefault('display_on_product_tile', false)
            ->setAllowedTypes('display_on_product_tile', 'bool')

            ->setDefault('display_on_brand_overview', true)
            ->setAllowedTypes('display_on_brand_overview', 'bool')
        ;
    }

    /**
     * @return list<string>
     */
    private function getLocaleCodes(): array
    {
        $codes = [];

        foreach ($this->localeRepository->findAll() as $locale) {
            $code = $locale->getCode();

            if (null !== $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
