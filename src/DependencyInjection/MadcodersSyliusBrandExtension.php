<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Yaml\Yaml;

final class MadcodersSyliusBrandExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.xml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMappings($container);
        $this->prependSettings($container);
        $this->prependImagineFilterSets($container);
        $this->prependDoctrineMigrations($container);
    }

    /**
     * Registers config/doctrine explicitly rather than letting DoctrineBundle auto-detect it.
     *
     * Auto-detection assumes a bundle's mapped classes live under `<BundleNamespace>\Entity` and
     * derives the class name from the file name. The plugin's mapped superclasses live under
     * `\Model` (they are models, not entities - the host application supplies the entities), so the
     * prefix has to be stated.
     */
    private function prependDoctrineMappings(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'MadcodersSyliusBrandPlugin' => [
                        'is_bundle' => false,
                        'type' => 'xml',
                        'dir' => \dirname(__DIR__, 2) . '/config/doctrine',
                        'prefix' => 'Madcoders\SyliusBrandPlugin\Model',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Declares the plugin's settings section with the Settings plugin.
     *
     * Prepending it here rather than asking the host application to copy a YAML block means
     * `composer require` plus the bundle registration is enough to get a working settings screen.
     * The guard turns a host that has not registered MonsieurBizSyliusSettingsPlugin into an
     * installation problem rather than an unresolvable container: prepending configuration for an
     * unregistered extension is a hard failure with an error that names the wrong culprit.
     *
     * @see docs/adr-log/0003-configuration-through-the-settings-plugin.md
     */
    private function prependSettings(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('monsieurbiz_sylius_settings')) {
            return;
        }

        /** @var array{monsieurbiz_sylius_settings?: array<string, mixed>} $config */
        $config = Yaml::parseFile(\dirname(__DIR__, 2) . '/config/settings.yaml');

        if (isset($config['monsieurbiz_sylius_settings'])) {
            $container->prependExtensionConfig('monsieurbiz_sylius_settings', $config['monsieurbiz_sylius_settings']);
        }
    }

    /**
     * Registers the brand logo filter sets, so a freshly installed plugin renders logos instead of
     * throwing "Could not find configuration for a filter". Guarded because LiipImagineBundle is a
     * Sylius dependency rather than a plugin one, and a host could in principle run without it.
     */
    private function prependImagineFilterSets(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('liip_imagine')) {
            return;
        }

        /** @var array{liip_imagine?: array<string, mixed>} $config */
        $config = Yaml::parseFile(\dirname(__DIR__, 2) . '/config/liip_imagine.yaml');

        if (isset($config['liip_imagine'])) {
            $container->prependExtensionConfig('liip_imagine', $config['liip_imagine']);
        }
    }

    /**
     * Vendor-namespaced on purpose. `doctrine_migrations.migrations_paths` is a map keyed by
     * namespace, and `DoctrineMigrations` is what the stock Symfony Flex recipe uses for the
     * application's own migrations - sharing that key means one of the two paths silently wins and
     * the other's migrations never run.
     */
    protected function getMigrationsNamespace(): string
    {
        return 'Madcoders\SyliusBrandPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@MadcodersSyliusBrandPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
