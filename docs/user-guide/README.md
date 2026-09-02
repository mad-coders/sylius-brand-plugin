# Brand Plugin user guide

This guide covers what the Sylius Brand Plugin adds to your store: a **Brands** resource in the
admin, a settings section that decides which product attribute carries the brand, brand pages in
the shop, and a console command that rebuilds the link between products and brands in bulk.

Everything else you see in the screenshots is stock Sylius and is not described here.

Installing the plugin is a separate job with its own document. See
[`docs/INSTALLATION.md`](../INSTALLATION.md). This guide assumes the plugin is already installed
and that you can sign in to the admin.

## How the plugin fits together

Three things have to be true before a brand shows up in the shop:

1. A **brand** exists in **Catalog** then **Brands**, is enabled, and has the display toggle on for
   the place you want it to appear.
2. The feature is switched on in **Settings** then **Brands**, and a **brand attribute** is chosen
   there.
3. Each product **resolves** to a brand: the value of its brand attribute matches a brand code,
   either directly or through the **brand mapping**.

A product is never attached to a brand by hand. The brand comes from the product's attribute value,
which means an import or a PIM feed that already carries the brand needs no extra work.

## Where things live

| Where | What you get |
|---|---|
| Admin, **Catalog** then **Brands** | The brand grid, and the create and edit form |
| Admin, **Settings** then **Brands** | The feature toggle, the brand attribute, the brand mapping |
| Admin, **Catalog** then **Products** | A **Brand** column and a **Brand** filter on the product grid |
| Admin, a single product's page | A **Brand** panel showing how that product resolved |
| Shop, `/brands` | The brand overview |
| Shop, `/brands/{slug}` | One brand's products |
| Shop, the homepage | A strip of brand logos above the rest of the page |
| Shop, a product page and product tiles | The brand badge |
| Command line | `bin/console madcoders:brand:resync-products` |

## Feature pages

- [Managing brands](features/managing-brands.md) - the Brands grid, and every field on the brand
  form, including the four display toggles.
- [Brand settings](features/brand-settings.md) - the feature toggle, the brand attribute and the
  brand mapping, and which of them are per channel.
- [Brands in the shop](features/brands-in-the-shop.md) - the brand overview, per-brand product
  listings, the homepage strip and the brand badge.
- [Resyncing product brands](features/resyncing-product-brands.md) - the
  `madcoders:brand:resync-products` command, and how to check what a product resolved to.

## Walkthroughs

- [Create a brand](journeys/create-a-brand.md)
- [Point the plugin at your brand attribute](journeys/point-the-plugin-at-your-brand-attribute.md)
- [Browse brands in the shop](journeys/browse-brands-in-the-shop.md)
- [See the brand on a product](journeys/see-the-brand-on-a-product.md)

## Turning the whole feature off

The **Enable brands** setting is off until you turn it on, and turning it back off is a complete
off switch for the shop side:

- `/brands` and `/brands/{slug}` return 404.
- The homepage strip, the product page badge and the product tile badge render nothing at all.
  There is no empty heading or blank row left behind.

Products still resolve to their brand while the feature is off, so nothing has to be rebuilt when
you turn it back on. The data underneath stays correct.

## A note on the screenshots

The screenshots in this guide were captured from a development store with sample data. Brand names
such as Sylius, Modern Wear and Quiet Supplier are fixtures, not real suppliers.
