# Coding rules

Conventions for `madcoders/sylius-brand-plugin`. These are the rules the quality gates and code
review enforce. Read `AGENTS.md` first for orientation and commands.

## Naming

| Thing | Convention | Example |
|---|---|---|
| PHP namespace | `Madcoders\SyliusBrandPlugin\` | `Madcoders\SyliusBrandPlugin\Model\Brand` |
| Test namespace | `Tests\Madcoders\SyliusBrandPlugin\` | `Tests\Madcoders\SyliusBrandPlugin\Unit\...` |
| Bundle | `MadcodersSyliusBrandPlugin` | |
| DI alias / config root | `madcoders_sylius_brand` | |
| Service id | `madcoders_sylius_brand.<concern>.<name>` | `madcoders_sylius_brand.resolver.product_brand` |
| Resource | `madcoders_sylius_brand.<resource>` | `madcoders_sylius_brand.brand` |
| Settings alias | `madcoders_brand.default` | (the Settings plugin requires exactly `<app>.<name>`) |
| Table | `madcoders_brand__<name>` | `madcoders_brand__brand` |
| Route | `madcoders_sylius_brand_<section>_<action>` | `madcoders_sylius_brand_shop_brand_show` |
| Translation key | `madcoders_sylius_brand.<domain>.<key>` | `madcoders_sylius_brand.ui.display_on_homepage` |
| Template namespace | `@MadcodersSyliusBrandPlugin/...` | |
| Imagine filter set | `madcoders_sylius_brand_logo*` | `madcoders_sylius_brand_logo_thumbnail` |

## PHP

- `declare(strict_types=1);` in every file.
- Classes are `final` unless they are a model intended for host extension, or an abstract base.
- Constructor property promotion; `readonly` where the collaborator never changes.
- Type everything. PHPStan runs at `level: max` with **no baseline** - if it complains, fix the
  code, don't record the complaint.
- Depend on interfaces, never on concrete plugin classes, across service boundaries.
- Throw domain exceptions from `src/Exception/`, not bare `\RuntimeException`, for anything a caller
  might reasonably want to catch.

## Models and persistence

See `docs/adr-log/0002-doctrine-xml-mapped-superclasses.md`.

- Doctrine mapping in **XML** under `config/doctrine/`, as mapped superclasses. No attributes, no
  annotations on model classes.
- **Never map `locale` or `translatable` on a translation model** - the resource bundle's Doctrine
  subscriber adds both, and mapping them by hand produces a duplicate-column schema.
- **Never write a translated field through the model's setters while the fallback locale points
  somewhere else.** `getTranslation()` falls back for reads, so the write lands on the fallback
  translation. Either set the fallback to the locale you are writing, or write to the translation
  object directly. See the `Brand` class docblock.
- Extensions to Sylius models ship as interface + trait pairs the host application applies.
- Every schema change gets a migration in `src/Migrations/`, written against the **Schema API** (not
  raw SQL) so it stays platform-neutral. Never `doctrine:schema:update`. CI covers MySQL and
  MariaDB; PostgreSQL is blocked by a dependency, so keep our own migrations neutral regardless -
  the constraint is not ours and may go away.
- Associations target interfaces, resolved through Sylius resource metadata.

## Services

See `docs/adr-log/0005-service-wiring.md`.

- Declared explicitly in XML under `config/services/`, one file per concern, with explicit ids and
  arguments.
- Business logic lives in the service. Framework wiring - event listeners, controllers, Twig
  extensions - stays thin and delegates.

## Brand invariants

These are the rules the domain must never violate. If a change makes one of them conditional, that
needs an ADR.

1. `code` is the brand's identity: unique, never blank, and never changed by the plugin itself.
2. A brand's slug is unique **per locale**, and a brand always has a slug in every locale it has a
   translation for - generated from the name when the admin leaves it blank.
3. `sylius_product.brand_id` is **derived state**. Only `ProductBrandSynchronizer` writes it; every
   other read path treats it as read-only. It is always recomputable from the attribute value plus
   the current mapping.
4. Resolution is deterministic: the same attribute value and mapping always produce the same brand,
   and an unmapped value that matches no brand code resolves to `null` - never to a guess.
5. Mapping keys are compared **case-insensitively and trimmed**. Two mapping entries that differ
   only in case or surrounding whitespace are the same entry.
6. Every display toggle is additive: a toggle that is off hides the brand in that one place and
   changes nothing else. The single exception is documented -
   `displayOnBrandOverview` also gates the brand's own listing page.
7. When the feature toggle is off the plugin is invisible: shop routes 404 and hooks render nothing.
   Resolution deliberately keeps running, so `brand_id` stays correct and switching the feature back
   on needs no resync - and so the same product cannot resolve differently per channel. The toggle
   is per channel; resolution is global.

## Presentation

- Twig hooks (`sylius_twig_hooks`) under `config/twig_hooks/` - the 1.x `sylius.ui` template event
  system does not exist in Sylius 2.x.
- Templates live in `templates/`, addressed as `@MadcodersSyliusBrandPlugin/...`, structured
  `admin/` and `shop/` to mirror Sylius.
- **No hardcoded user-facing strings.** Everything goes through a translation key in
  `translations/messages.en.yaml`; other locales are added as translations land.
- Grids and routes in YAML under `config/grids/` and `config/routes/`.
- Every shop-side hook template must render nothing when the feature is off or the brand's toggle
  for that surface is off. Check the toggle in the template, not only in the service.

## Tests

- Unit tests mirror the `src/` namespace under `tests/Unit/`, and must not boot the kernel or touch
  a database - they run in the fast gate.
- Behat features describe behaviour in the shop/admin user's language, one feature file per flow.
- Test behaviour, not implementation: assert observable outcomes and public contracts, including
  edge and error paths. Mock only boundary collaborators. A test should survive a behaviour-
  preserving refactor.

## Commits and changelog

- Conventional Commits; scope names the area (`brand`, `admin`, `shop`, `settings`, `deps`).
- One logical change per commit.
- Notable changes go into `CHANGELOG.md` under `[Unreleased]` in the same commit.
