# Brand settings

The plugin's configuration lives in the admin under **Settings**, in the **Brands** section. There
is nothing to edit in a configuration file.

![The Brands settings form](../assets/admin-settings-edit-madcoders_brand.default.png)

The page is titled **Settings of Brands** and has one tab per scope:

- **By default**, headed **Sylius' default values**. This is the "all channels" tab, and the one
  that opens first.
- One tab per channel, for example **Fashion Web Store**, holding that channel's own values.

Choose **Update** to save, or **Cancel** to leave the form.

## The settings

| Setting | Scope | Default |
|---|---|---|
| **Enable brands** | Per channel | Off |
| **Brand attribute** | Global | No attribute selected |
| **Brand mapping** | Global | Empty |

### Enable brands

The feature toggle. Its help text reads: *When this is off the brand pages return 404 and no brand
is shown anywhere in the shop.*

While it is off:

- `/brands` and `/brands/{slug}` return 404.
- The homepage strip, the product page badge and the product tile badge render nothing. Not an
  empty section, nothing at all.

Products still resolve to their brand while the feature is off. The link between a product and its
brand is stored on the product and is kept up to date regardless of this setting, so turning the
feature back on shows the correct brands immediately, with nothing to rebuild.

This setting appears on every tab. On a channel tab it comes with a **Use default value** checkbox:
leave it ticked to follow the **By default** tab, or untick it to set a value for that channel
alone. That is how you run brands on a retail channel while keeping them off a B2B one.

### Brand attribute

A drop-down listing the product attributes that could name a brand, each shown as
`Attribute name (attribute_code)`, for example `Jeans brand (jeans_brand)`. The stored value is the
code. The placeholder entry is **No attribute selected**.

Only attributes whose value can identify a brand are offered: text, textarea, select, integer and
percent. Checkbox and date attributes are left out, because a boolean or a date cannot name a
brand.

With no attribute selected, no product resolves to a brand at all.

### Brand mapping

A table of rows, each with two fields:

| Field | What it holds |
|---|---|
| **Attribute value** | The value as it appears on the product attribute, for example `Nike Inc.`. Matching ignores case and surrounding spaces |
| **Brand code** | The code of the brand this value belongs to. Several values may point at the same brand |

Choose **Add** to append a row, and the **Delete** button on a row to remove it. A row with either
half left blank is discarded when you save.

Leave the mapping empty to match attribute values against brand codes directly. A value with no
matching row is still tried against the brand codes as it stands, so you only need a row where the
attribute value and the brand code differ.

For example, with a brand whose code is `nike`:

| Attribute value | Brand code |
|---|---|
| `Nike Inc.` | `nike` |
| `NIKE Sportswear` | `nike` |

A product whose attribute value is already `nike` needs no row.

## Why two of the settings are global

**Brand attribute** and **Brand mapping** are only offered on the **By default** tab. That is
deliberate: a product is linked to exactly one brand, so a per-channel attribute or mapping would
have no single right answer. A channel-scoped value left behind by an older install is ignored
rather than half-honoured.

**Enable brands** is the one setting that genuinely is a per-channel decision, because it is about
display rather than about which brand a product belongs to.

## After changing the attribute or the mapping

Changing **Brand attribute** or **Brand mapping** changes what every product in the catalogue
resolves to, but it does not rewrite the stored links on its own. Run
[`madcoders:brand:resync-products`](resyncing-product-brands.md) afterwards.

## Accessibility

Every field on this form has a visible label and help text. No image on the page is missing
alternative text.

## Related

- [Managing brands](managing-brands.md)
- [Resyncing product brands](resyncing-product-brands.md)
- Walkthrough:
  [Point the plugin at your brand attribute](../journeys/point-the-plugin-at-your-brand-attribute.md)
