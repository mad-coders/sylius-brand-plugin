# 0001 - Built on the Sylius resource model

**Status:** accepted

## Context

The plugin introduces a persistent entity (the brand, with its translations and its logo image)
that needs admin CRUD, a grid, forms, a repository and a factory. Sylius already provides all of
this through `sylius/resource-bundle`: register a resource, get a controller, factory, repository,
form handling and grid integration for free, all overridable by the host application.

The alternative - hand-written controllers and services per entity - would duplicate that machinery
and break the extension points host applications expect from a Sylius plugin.

## Decision

Every persistent entity is registered as a **Sylius resource** in `config/resources.yaml`, using the
stock `ResourceController`, `Factory` and `EntityRepository` unless there is a concrete reason to
specialise. Custom repositories extend
`Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository`.

Resource names are prefixed `madcoders_sylius_brand.*`; service ids follow the same prefix. The
brand is registered as a **translatable** resource, so `BrandTranslation` is declared under the
resource's `translation` key rather than as a resource of its own.

## Consequences

- Admin CRUD is configuration (routes + grids), not code.
- Host applications can swap any model, factory, repository or form through standard Sylius resource
  configuration.
- Resource events (`madcoders_sylius_brand.brand.pre_create` and friends) exist for free, which is
  what the logo upload listener hooks into.
- Entities must be `ResourceInterface` implementations with the identifier accessible.

## Rules

1. New persistent entity → register it as a resource; do not hand-roll a controller.
2. Custom controller actions only where the stock resource controller genuinely cannot express the
   behaviour, and then as a separate, narrowly scoped controller. The shop-side brand pages are such
   a case: they need channel filtering and a product pager the CRUD controller has no concept of.
3. Never reference a concrete model class where the interface exists; resolve models through the
   resource configuration (`%madcoders_sylius_brand.model.brand.class%`).
