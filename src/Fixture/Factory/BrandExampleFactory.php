<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Fixture\Factory;

use Madcoders\SyliusBrandPlugin\Model\BrandImageInterface;
use Madcoders\SyliusBrandPlugin\Model\BrandInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Generator\SlugGeneratorInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
     * @param FactoryInterface<BrandImageInterface> $brandImageFactory
     */
    public function __construct(
        private readonly FactoryInterface $brandFactory,
        private readonly RepositoryInterface $localeRepository,
        private readonly SlugGeneratorInterface $slugGenerator,
        private readonly FactoryInterface $brandImageFactory,
        private readonly ImageUploaderInterface $imageUploader,
        private readonly FileLocatorInterface $fileLocator,
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

        $image = $options['image'];
        Assert::nullOrString($image);

        if (null !== $image) {
            $this->attachLogo($brand, $image);
        }

        return $brand;
    }

    /**
     * Uploads the file and attaches it as the brand's logo.
     *
     * The upload is explicit because a fixture persists through the object manager, not through the
     * resource layer, so the plugin's `ImagesUploadListener` never fires for it. Without this a
     * fixture brand would carry an image row pointing at a file that was never written.
     */
    private function attachLogo(BrandInterface $brand, string $path): void
    {
        // Resolved through the file locator so a fixture can name the file the way the rest of
        // Sylius' fixtures do - `@MadcodersSyliusBrandPlugin/src/Resources/...` - instead of
        // hard-coding a path that only works in one checkout.
        $located = $this->fileLocator->locate($path);

        Assert::fileExists($located, \sprintf('Brand logo "%s" does not exist.', $path));

        $logo = $this->brandImageFactory->createNew();
        $logo->setFile(new UploadedFile($located, basename($located), null, null, true));

        $this->imageUploader->upload($logo);

        // The uploader keeps the handle open on the temporary copy; clearing it keeps the entity
        // serialisable once the file has been written to its final location.
        $logo->setFile(null);

        $brand->addImage($logo);
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

            // Path to a logo file, absolute or in `@Bundle/...` notation. Left null by default so a
            // host writing its own brand fixtures is not forced to supply one; a brand without a
            // logo renders as its name alone.
            ->setDefault('image', null)
            ->setAllowedTypes('image', ['null', 'string'])
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
