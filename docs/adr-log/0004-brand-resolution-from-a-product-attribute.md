# 0004 - Brand resolved from a product attribute, denormalised onto the product

**Status:** accepted

## Context

Shops that want brand pages almost always already know each product's brand - as a product
attribute, filled by a PIM export, a supplier feed or a CSV import. Asking them to re-tag the
catalogue against a new association is a migration project, and it immediately drifts from whatever
keeps filling the attribute.

So the plugin reads the brand from an attribute. That leaves the question of *when*.

**Option A - resolve at query time.** Every "products of brand X" query joins
`sylius_product_attribute_value` and filters on its value column. No schema change, nothing to keep
in sync.

**Option B - denormalise the resolved brand onto `sylius_product.brand_id`.** Listings become an
ordinary indexed foreign-key query, at the cost of derived state that can drift.

Option A looked cheaper until the query was written out. `sylius_product_attribute_value.text` is a
`LONGTEXT` in Sylius - it cannot carry a useful index on MySQL without a prefix index, the join
multiplies rows per locale, and the mapping means a single brand can correspond to *n* source
values, so the filter is an `IN` over a set computed in PHP on every request. Sorting and
paginating that is exactly the shape of query that falls over at catalogue scale, and it cannot be
reused by a grid filter or an API filter later.

## Decision

Option B, with the drift answered head-on.

- The **attribute value is the source of truth.** The `brand_id` column is a *derived index*, never
  edited directly and never authoritative.
- `ProductBrandResolver` maps a product to a `BrandInterface|null` using the configured attribute
  and mapping.
- `ProductBrandSynchronizer` is the **only** writer of the column, and reports whether it changed.
- A resource event listener on `sylius.product.pre_create` / `sylius.product.pre_update` keeps
  admin-side edits in sync.
- `bin/console madcoders:brand:resync-products` recomputes the column for the whole catalogue. It is
  the documented answer after an import, a mapping change or an attribute change, and it is
  idempotent.

Resolution is **not** gated on the `enabled` setting. That toggle is per channel and controls
display; resolution writes one `brand_id` per product and has to give the same answer wherever it
runs. Gating on it would make a product resolve differently in the admin (current channel) than in
the resync command (first enabled channel), and would leave the column stale for as long as the
feature was switched off. The shop hides brands via the toggle; the column stays correct underneath.

Resolution rules:

1. Read the value of the configured attribute. Multi-valued (select) attributes use their first
   value; checkbox attributes are ignored, as a boolean cannot name a brand.
2. Look the value up in the mapping, comparing trimmed and lower-cased.
3. No mapping entry → the value **is** the brand code (the 1:1 case).
4. No brand with that code → `null`. Never a fuzzy match, never a newly created brand.

## Consequences

- "Products of this brand" is `WHERE p.brand = :brand` against an indexed column, with normal
  Sylius channel and enabled filtering on top.
- The column can drift when products are written outside the resource layer. That is acceptable
  because it is always recomputable, and the command is a documented operational step.
- The plugin adds a nullable column and a foreign key to `sylius_product`. The host applies the
  trait; the migration adds the column.
- Auto-creating brands from unknown attribute values was deliberately rejected: it would let a typo
  in a supplier feed publish a brand page.

## Rules

1. `sylius_product.brand_id` is derived state. **Only `ProductBrandSynchronizer` writes it.**
2. Resolution is deterministic and total: same input, same output, and `null` rather than a guess.
   In particular it must not depend on the current channel, because the same product must resolve
   identically in the admin, in the shop and on the CLI.
3. Any new way for products to enter the system (an importer, a message handler, an API endpoint)
   must either go through the synchronizer or be covered by the resync command - and say which in
   its own documentation.
4. The resolver must not throw on a catalogue it does not understand. An unknown attribute code, a
   missing attribute value, an unexpected value type: all resolve to `null`.
