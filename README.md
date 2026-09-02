<h1 align="center">Sylius Brand Plugin</h1>

<p align="center">Brands for Sylius 2.x: a brand overview page, per-brand product listings, and brand
badges on the product page and product tiles - driven by a product attribute you already have.</p>

<p align="center">
    <a href="https://github.com/mad-coders/sylius-brand-plugin/actions/workflows/ci.yaml"><img src="https://github.com/mad-coders/sylius-brand-plugin/actions/workflows/ci.yaml/badge.svg?branch=1.0" alt="CI"></a>
    <a href="LICENSE"><img src="https://img.shields.io/badge/license-EUPL--1.2-blue.svg" alt="License"></a>
</p>

> **Status: 1.0 release candidate.** Functionally complete and green across the supported
> Sylius/Symfony/database matrix. The API - service ids, settings paths, table names - is frozen for
> 1.0; what it still wants is real-world use. Please report anything you hit.
>
> ```bash
> composer require madcoders/sylius-brand-plugin:^1.0@RC
> ```
>
> The brand hookable is reusable on your own product grids - see
> [Putting brands on your own product grids](#putting-brands-on-your-own-product-grids).

## What it does

- **Brands as a first-class resource.** Code, logo, translatable name, slug and description, an
  enabled flag and four display toggles, all managed from a Sylius admin grid.
- **Products map to brands through a product attribute.** Most catalogues already carry the brand
  as an attribute (from a PIM, an import or a feed). Point the plugin at that attribute and it
  resolves each product to a brand - no re-tagging, no second source of truth.
- **A mapping table for messy data.** `"Nike"`, `"Nike Inc."` and `"NIKE Sportswear"` can all map
  onto the single `nike` brand. Without a mapping the plugin assumes a 1:1 match between the
  attribute value and the brand code.
- **Shop pages.** A brand overview at `/brands`, a per-brand product listing at `/brands/{slug}`,
  a brand strip on the homepage, a brand badge on the product page and on product tiles - each one
  switched on per brand.
- **Reusable on your own grids.** The brand hookable is configurable, so you can attach it to any
  hook that has a product in context without copying a template - see
  [below](#putting-brands-on-your-own-product-grids). Twig helpers are provided for custom markup.
- **One feature toggle.** The whole feature can be turned off from the admin without touching
  configuration files or removing the bundle.

Configuration lives in the admin, through
[MonsieurBiz' Settings plugin](https://github.com/monsieurbiz/SyliusSettingsPlugin).

## Requirements

| | |
|---|---|
| PHP | `^8.3` |
| Sylius | `^2.0` (tested against `~2.0`, `~2.1`, `~2.2`) |
| Symfony | `^6.4 \|\| ^7.0` |
| Settings | `monsieurbiz/sylius-settings-plugin ^2.0` |
| Database | MySQL 8.4 or MariaDB 11.4. **PostgreSQL is not supported** - see below. |

> **PostgreSQL.** This plugin's own migration is platform-neutral, but
> `monsieurbiz/sylius-settings-plugin` - a hard dependency - creates its table with raw MySQL DDL
> and no platform guard, so migrations fail on PostgreSQL before this plugin is reached. Nothing
> here can work around that; it has to be fixed upstream.

## Installation

```bash
composer require madcoders/sylius-brand-plugin
```

Then follow [`docs/INSTALLATION.md`](docs/INSTALLATION.md) - the plugin needs its bundle registered,
its configuration and routes imported, its entities declared, and the `brand` extension applied to
your `Product`.

## How a product finds its brand

```
product attribute value        mapping (optional)        brand
"Nike Inc."          ───────►  nike            ───────►  Brand(code: "nike")
"NIKE Sportswear"    ───────►  nike            ───────►
"adidas"             ───────►  (no entry: 1:1) ───────►  Brand(code: "adidas")
```

The attribute stays the source of truth, but the resolved brand is **denormalised onto
`sylius_product.brand_id`** so listings, filters and sorting are ordinary indexed queries. The
column is kept in sync whenever a product is saved through the resource layer, and
`bin/console madcoders:brand:resync-products` rebuilds it in bulk after an import or a change to
the mapping. The reasoning is in
[`docs/adr-log/0004-brand-resolution-from-a-product-attribute.md`](docs/adr-log/0004-brand-resolution-from-a-product-attribute.md).

## Configuration

Admin → Settings → **Brands**:

| Setting | Scope | Meaning |
|---|---|---|
| `enabled` | per channel | Display toggle. When off, the shop routes 404 and every brand hook renders nothing. Products still resolve to their brand, so the data stays correct underneath. |
| `brand_attribute` | global | Code of the product attribute carrying the brand. |
| `brand_mapping` | global | Attribute value → brand code pairs. Empty means a 1:1 match. |

The last two are global on purpose: a product has one `brand_id`, so a per-channel attribute would
have no single right answer. They are only offered on the "all channels" tab, and a channel-scoped
value left behind by an older install is ignored.

### Page sizes

Two things are set in configuration rather than in the admin, because they are decisions about the
theme rather than about the shop:

```yaml
# config/packages/madcoders_sylius_brand.yaml
madcoders_sylius_brand:
    products_per_page: 12       # products listed on a brand page before paginating
    homepage_brands_limit: 12   # brands in the homepage strip
```

Both default to 12, so you only need this file if you are changing one.

### Demo fixtures

The plugin ships five demo brands - three with a logo, two without - in their own suite. They are
**not** added to Sylius' `default` suite, so installing the plugin never puts brands you did not
create into your own `sylius:fixtures:load`. To use them:

```yaml
# config/packages/madcoders_sylius_brand.yaml
imports:
    - { resource: "@MadcodersSyliusBrandPlugin/config/fixtures.yaml" }
```

```bash
bin/console sylius:fixtures:load madcoders_brand
```

## Putting brands on your own product grids

The plugin ships the brand on three surfaces out of the box - the homepage strip, the product page
and Sylius' product card. Anywhere else - a custom listing, search results, a cross-sell carousel,
your own theme's card - you attach it yourself. There is nothing to copy: the same template the
plugin uses is configurable, so you point a hook at it and pass configuration.

```yaml
# config/packages/madcoders_sylius_brand.yaml
sylius_twig_hooks:
    hooks:
        # any hook that has a product in its context
        'sylius_shop.product.index.content.body.main.products':
            madcoders_brand:
                template: '@MadcodersSyliusBrandPlugin/shop/product/brand.html.twig'
                configuration:
                    surface: product_tile
                priority: 50
```

| configuration | default | what it does |
|---|---|---|
| `context_key` | `product` | where the product lives in the hook's context, if it isn't called `product` |
| `surface` | `product_tile` | which display toggle to respect: `homepage`, `product_page`, `product_tile`, `brand_overview`, or `any` to ignore the toggles |
| `show_logo` | `false` | render the brand logo before the name (on for the plugin's own product-page and product-tile hooks) |
| `logo_height` | `32` | maximum logo height in px |
| `logo_width` | `96` | maximum logo width in px |
| `logo_filter` | `madcoders_sylius_brand_logo_thumbnail` | LiipImagine filter set for the logo |
| `link` | `true` | link to the brand page (falls back to plain text when that brand's page is not reachable) |
| `label` | `false` | prefix with a translated "Brand:" label |
| `class` | muted small line | CSS classes for the wrapper |
| `link_class` | `fw-semibold text-reset` | CSS classes for the name itself |

The template renders **nothing** when the feature is off, the product has no brand, the brand is
disabled, or that brand's toggle for the chosen surface is off - so you never have to guard the hook
yourself.

### Twig helpers

If you want your own markup, use the helpers directly. These are public API.

| function | returns |
|---|---|
| `madcoders_brand_for(product, surface = 'product_tile')` | the brand to show for that product on that surface, or `null` |
| `madcoders_brands_enabled()` | whether the feature is on for the current channel |
| `madcoders_homepage_brands(limit = 12)` | brands flagged for the homepage, ordered by position |

```twig
{% set brand = madcoders_brand_for(product, 'any') %}
{% if brand is not null %}
    <a href="{{ path('madcoders_sylius_brand_shop_brand_show', { slug: brand.slug }) }}">{{ brand.name }}</a>
{% endif %}
```

**Use `madcoders_brand_for()` rather than reading `product.brand`.** It applies the feature toggle,
the brand's enabled flag and the surface toggle for you - and, less obviously, it answers every row
of a listing from a single query. Reading `product.brand` directly initialises a Doctrine proxy per
row and loads the brand *and* its translation, which on a 12-product grid is up to 24 extra queries.

### Admin helpers

| function | returns |
|---|---|
| `madcoders_brand_product_count(brand)` | how many products point at that brand |
| `madcoders_brand_diagnostics(product)` | the resolution chain: configured attribute, the product's value for it, what it maps to, and the resulting brand |

## Development

```bash
make setup          # deps + docker (MySQL on 3307) + assets + database
make install-hooks  # pre-commit quality gate and commit template
make verify         # fast gate: composer validate + phpstan + ecs + unit tests
make test           # phpunit + behat
make help           # every available target
```

Contributor guide: [`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md). Working on this with an AI agent?
Start from [`AGENTS.md`](AGENTS.md).

The primary branch is **`1.0`** - this repository has no `main` or `master`, following the Sylius
version-branch model.

## Credits

Built and maintained by [Madcoders](https://www.madcoders.co).

## License

[EUPL-1.2](LICENSE).
