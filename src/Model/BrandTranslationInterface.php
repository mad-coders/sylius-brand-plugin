<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\SlugAwareInterface;
use Sylius\Resource\Model\TranslationInterface;

interface BrandTranslationInterface extends ResourceInterface, TranslationInterface, SlugAwareInterface
{
    public function getId(): ?int;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getDescription(): ?string;

    public function setDescription(?string $description): void;

    public function getMetaKeywords(): ?string;

    public function setMetaKeywords(?string $metaKeywords): void;

    public function getMetaDescription(): ?string;

    public function setMetaDescription(?string $metaDescription): void;
}
