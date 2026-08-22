# 0002 - Doctrine mapping as XML mapped superclasses

**Status:** accepted

## Context

A Sylius plugin must not own its entities: the host application has to be able to extend or replace
any model, and two plugins mapping the same table would collide. Sylius solves this by shipping
**mapped superclasses** and letting the application declare the concrete entities.

The plugin also extends a Sylius model (`Product` gains a `brand` association), which the host
applies to *its own* entity class.

## Decision

- Every model in `src/Model/` is a Doctrine **mapped superclass**, mapped in **XML** under
  `config/doctrine/`. No attributes, no annotations on model classes.
- The mapping directory is registered explicitly by the plugin's extension (`prependDoctrineMappings`)
  rather than relying on DoctrineBundle's bundle auto-detection, which assumes mapped classes live
  under `<BundleNamespace>\Entity`.
- The extension to `Product` ships as an interface + trait pair (`ProductInterface`, `ProductTrait`)
  with its **own** XML mapping keyed on the trait's owning model, so the host application only has
  to `use` the trait.
- Associations target **interfaces** (`Sylius\Component\Core\Model\ProductInterface`,
  `Madcoders\SyliusBrandPlugin\Model\BrandInterface`), resolved through Sylius resource metadata.

## Consequences

- The host application declares one small entity class per model and points the resource
  configuration at it. `docs/INSTALLATION.md` carries the exact snippets.
- Schema changes need a migration in `src/Migrations/`; there is no `doctrine:schema:update` path
  because the plugin does not own the tables.
- Migrations are written against the **Schema API** rather than raw SQL, so they stay
  platform-neutral. CI runs MySQL and MariaDB; PostgreSQL is blocked by a dependency's MySQL-only
  migration, not by ours.

## Rules

1. New model → XML mapped superclass in `config/doctrine/`, never an attribute-mapped entity.
2. **Never map `locale` or `translatable` on a translation model.** The resource bundle's
   `LoadORMMetadataSubscriber` adds both; mapping them by hand produces duplicate columns and a
   schema that never validates.
3. Extending a Sylius model → interface + trait + its own mapping file, plus an installation step.
4. Every schema change gets a migration. Index names follow the ones Doctrine derives, so
   `doctrine:schema:validate` reports a migrated host application as in sync.
