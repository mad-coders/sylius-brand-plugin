<?php

declare(strict_types=1);

namespace Tests\Madcoders\SyliusBrandPlugin\Unit;

use Madcoders\SyliusBrandPlugin\DependencyInjection\MadcodersSyliusBrandExtension;
use Madcoders\SyliusBrandPlugin\MadcodersSyliusBrandPlugin;
use PHPUnit\Framework\TestCase;

final class MadcodersSyliusBrandPluginTest extends TestCase
{
    public function testItsPathIsTheRepositoryRootSoConfigAndTemplatesResolve(): void
    {
        $plugin = new MadcodersSyliusBrandPlugin();

        // Sylius 2.x plugins keep config/ and templates/ at the repository root rather than under
        // src/Resources/, which only works because the bundle reports the parent of src/.
        self::assertFileExists($plugin->getPath() . '/config/config.yaml');
        self::assertFileExists($plugin->getPath() . '/templates');
    }

    public function testItUsesTheResourceAwareExtension(): void
    {
        self::assertInstanceOf(MadcodersSyliusBrandExtension::class, (new MadcodersSyliusBrandPlugin())->getContainerExtension());
    }
}
