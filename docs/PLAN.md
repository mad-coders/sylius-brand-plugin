# Project plan - madcoders/sylius-brand-plugin

Brand plugin for **Sylius 2.x**. Repository conventions, tooling and CI are modelled on
[mad-coders/sylius-giftcard-plugin](https://github.com/mad-coders/sylius-giftcard-plugin).

The plugin exists because Sylius has no brand concept, while nearly every catalogue already carries
one - usually as a product attribute fed by a PIM, an import or a supplier feed. Rather than asking
shops to re-tag their catalogue, the plugin **reads the brand they already have** and turns it into
a first-class, presentable entity.

## Target stack

| | |
|---|---|
| PHP | `^8.3` |
| Sylius | `^2.0` (CI matrix: `~2.0.0`, `~2.1.0`, `~2.2.0`) |
| Symfony | `^6.4 \|\| ^7.4` |
| Settings | `monsieurbiz/sylius-settings-plugin ^2.0` |
| Test application | `sylius/test-application` (the Sylius 2.x plugin convention) |
| Quality gates | PHPStan (level max), ECS, Rector, PHPUnit, Behat |
| Databases covered in CI | MySQL 8.4, MariaDB 11.4 (PostgreSQL blocked upstream - see README) |

## Branching

Trunkless, version-branch model (same as Sylius itself):

- **`1.0` is the primary branch** and the repository default. There is no `main`/`master`.
- Work happens on short-lived branches (`feat/...`, `fix/...`, `docs/...`) merged into `1.0` via
  pull requests.
- Commits follow [Conventional Commits](https://www.conventionalcommits.org/) - see
  `docs/adr-log/0007-conventional-commits.md`.

## Domain model

```
Brand
├── code                     unique, immutable identity; the mapping target
├── enabled                  a disabled brand is invisible everywhere in the shop
├── position                 ordering on the overview page
├── displayOnHomepage        show in the homepage brand strip
├── displayOnProductPage     show the brand badge on the PDP
├── displayOnProductTile     show the brand name on product tiles
├── displayOnBrandOverview   list on /brands - and gate the brand's own listing page
├── images                   Collection<BrandImage>, type "logo"
└── translations             Collection<BrandTranslation>

BrandTranslation
├── locale
├── name
├── slug                     unique per locale; generated from the name when left blank
├── description
├── metaKeywords
└── metaDescription

BrandImage                   Sylius ImageInterface, owner = Brand
```

Extension points on Sylius models (traits the host application applies to its own entities):

| Sylius model | Added by the plugin |
|---|---|
| `Product` | `brand: ?Brand` - the resolved brand, denormalised from the attribute |

### Why a denormalised `brand_id` on the product

The attribute value is the *source of truth*; the column is a *derived index*. Resolving the brand
on every listing query would mean joining `sylius_product_attribute_value` and filtering on a
`LONGTEXT` column for every page of every brand - unindexable, and impossible to sort or paginate
efficiently. Writing the resolved brand onto the product turns "products of this brand" into an
ordinary indexed foreign-key query, and keeps the door open for grid filters and API filtering
later.

The trade-off is that the column can drift when products change outside the resource layer (bulk
SQL, a direct import). That is answered by making the column *always recomputable*:
`bin/console madcoders:brand:resync-products` rebuilds it for the whole catalogue and reports what
changed. See `docs/adr-log/0004-brand-resolution-from-a-product-attribute.md`.

## How resolution works

```
Product
  └─ attribute value for the configured attribute code   e.g. "Nike Inc."
        │
        ├─ brand_mapping has "nike inc." → "nike"        (case-insensitive, trimmed)
        └─ no entry → 1:1: the value itself is the code
                │
                └─ Brand repository lookup by code → Brand | null
```

- `BrandSettingsProvider` is the only reader of the Settings plugin. It narrows `mixed` to typed
  values and applies the fallbacks (feature off, no attribute configured, empty mapping).
- `ProductBrandResolver` turns a product into a `Brand|null`. It handles text, select and checkbox
  attribute types, and takes the first value of a multi-valued attribute.
- `ProductBrandSynchronizer` writes the result onto the product, and reports whether it changed.
- A resource event listener on `sylius.product.pre_create` / `pre_update` keeps admin edits in sync;
  the console command covers everything else.

## Delivery phases

Each phase is a pull request into `1.0`, green on the quality gate before merge.

| # | Phase | Scope |
|---|---|---|
| 0 | **Bootstrap** | Repository, composer package, test application wiring, tooling (PHPStan/ECS/Rector/PHPUnit/Behat), Makefile, CI, git hooks, docs and ADR log, this plan. |
| 1 | **Model & persistence** | `Brand`, `BrandTranslation`, `BrandImage`, the `Product` extension trait, Doctrine XML mapping, Sylius resource registration, repositories, first migration. |
| 2 | **Settings & resolution** | Settings plugin declaration, settings form, `BrandSettingsProvider`, `ProductBrandResolver`, `ProductBrandSynchronizer`, resource listener, resync command. |
| 3 | **Admin** | Grid, create/update forms with translations and logo upload, menu entry, Imagine filter sets. |
| 4 | **Shop** | Brand overview page, per-brand product listing, homepage strip, PDP badge, product tile label, twig hooks and templates. |
| 5 | **Fixtures & tests** | Brand fixtures in the default suite, unit tests, Behat features for the admin and shop flows. |
| 6 | **Docs & polish** | Usage documentation, translations beyond English, API Platform resources if demand appears. |

Deferred beyond 1.0: API Platform resources, brand-aware grid filters on the admin product grid,
brand landing-page content blocks, per-channel brand visibility.

## Definition of done (per phase)

- `make verify` is green (composer validate + PHPStan + ECS + unit tests).
- New user-visible behaviour has a Behat feature; new services have unit tests.
- Load-bearing decisions are recorded as an ADR in `docs/adr-log/`.
- `CHANGELOG.md` updated under `[Unreleased]`.
