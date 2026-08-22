<h1 align="center">Sylius Brand Plugin</h1>

<p align="center">Brands for Sylius 2.x: a brand overview page, per-brand product listings, and brand
badges on the product page and product tiles - driven by a product attribute you already have.</p>

<p align="center">
    <a href="https://github.com/mad-coders/sylius-brand-plugin/actions/workflows/ci.yaml"><img src="https://github.com/mad-coders/sylius-brand-plugin/actions/workflows/ci.yaml/badge.svg?branch=1.0" alt="CI"></a>
    <a href="LICENSE"><img src="https://img.shields.io/badge/license-EUPL--1.2-blue.svg" alt="License"></a>
</p>

> **Status: in development.** The 1.0 line is being built phase by phase - see
> [`docs/PLAN.md`](docs/PLAN.md) for the roadmap and what has landed.

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
- **One feature toggle.** The whole feature can be turned off from the admin without touching
  configuration files or removing the bundle.

Configuration lives in the admin, through
[MonsieurBiz' Settings plugin](https://github.com/monsieurbiz/SyliusSettingsPlugin).

## Requirements

| | |
|---|---|
| PHP | `^8.3` |
| Sylius | `^2.0` (tested against `~2.0`, `~2.1`, `~2.2`) |
| Symfony | `^6.4 \|\| ^7.4` |
| Settings | `monsieurbiz/sylius-settings-plugin ^2.0` |

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
