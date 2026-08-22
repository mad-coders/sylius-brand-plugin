# 0008 - Display toggles live on the brand, not in configuration

**Status:** accepted

## Context

A brand can appear in four places in the shop:

- the homepage brand strip,
- the product page (a badge under the product name),
- the product tile in listings,
- the brand overview page at `/brands`.

Shops do not want all brands in all places. A house brand belongs on the homepage; a long tail of
supplier brands belongs on the overview page and nowhere else; a white-label brand may need to exist
for filtering but never be shown at all.

The choice was between global switches in the plugin configuration ("show brands on the PDP") and
per-brand flags.

## Decision

Four boolean columns on the brand itself: `displayOnHomepage`, `displayOnProductPage`,
`displayOnProductTile`, `displayOnBrandOverview`, edited on the brand form and shown in the admin
grid.

The plugin-wide `enabled` setting stays, but it is a **feature toggle**, not a display preference:
when it is off the plugin is inert everywhere.

`enabled` on the brand and the toggles compose as an AND: a disabled brand is invisible regardless
of its toggles.

`displayOnBrandOverview` carries one extra responsibility - it also gates the brand's own listing
page at `/brands/{slug}`, which returns 404 when the toggle is off. The overview page and the pages
it links to are one feature; a brand that is deliberately absent from the index should not be
reachable by guessing its slug.

## Consequences

- Four extra columns and four checkboxes, per brand.
- The overview toggle's double duty is surprising enough to need documenting - it appears in the
  README, the admin form help text and the installation guide.
- Every shop-side hook template has to check both the feature toggle and the brand's own toggle. The
  check is in the template rather than only in the service, because a hook can be re-pointed by a
  host application at a template of its own.

## Rules

1. New display surface → a new toggle on the brand, defaulting to the value that keeps existing
   installations looking the way they did.
2. Toggles are additive and independent. A toggle that is off changes exactly one surface - and if
   a new one ever has to do more, it is documented in all three places above.
3. A disabled brand is never rendered, whatever its toggles say.
