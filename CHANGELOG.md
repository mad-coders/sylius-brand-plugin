# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Production-readiness fixes ahead of 1.0.0. The migration namespace change is the reason this has to
land before the stable tag rather than after it.

### Fixed

- **The plugin's migrations no longer occupy the generic `DoctrineMigrations` namespace.** They are
  registered as `Madcoders\SyliusBrandPlugin\Migrations`, alongside every other package's own
  namespace. `doctrine_migrations.migrations_paths` is a map keyed by namespace, so sharing the key
  that the stock Symfony Flex recipe uses for the application's own migrations meant one of the two
  paths silently won and the other's migrations never ran - the brand tables were never created, or
  the application's own migrations were skipped, with no error at migrate time. CI never saw it
  because the Sylius test application uses `App\Migrations`. **This must be applied before any
  install has run the old migration**: the version is recorded in `sylius_migrations` under its
  namespace, so an install that already ran `DoctrineMigrations\Version20260807090000` will try to
  run the renamed class again and fail on `CREATE TABLE`.
- `symfony/lock` is now `^6.4 || ^7.0` instead of `^6.4 || ^7.4`. The old constraint excluded
  Symfony 7.0 to 7.3, and Flex pins every `symfony/*` package to the application's own Symfony
  version, so the plugin was simply uninstallable on a shop pinned to any of them. The only use is
  `LockableTrait`, which is unchanged across the whole range.
- The documented admin routing (and the test application's) uses
  `prefix: '/%sylius_admin.path_name%'` rather than a hardcoded `/admin`. A shop that renamed its
  admin path - a routine hardening step - mounted the brand CRUD at `/admin`, outside
  `sylius.security.admin_regex`, leaving those routes outside the admin firewall.
- Brand listings no longer select translations with a null or empty slug. The column is nullable and
  non-form writes (fixtures, imports, an auto-created empty translation) can leave one behind;
  `path()` throws on a null route parameter, so such a row would have 500'd a public page. The shared
  `shop/brand/_link.html.twig` partial guards the same case for hosts that render it with a brand of
  their own.

### Added

- `docs/` ships in the installed package. It was `export-ignore`d, so `composer require` produced a
  `vendor/` copy whose only installation instruction was a dead relative link to
  `docs/INSTALLATION.md` - no bundle registration, no routing, no mention that three entity classes
  are mandatory. Only `docs/PLAN.md` is still excluded.
- `ResyncProductBrandsCommand` accepts an optional `LockFactory`, wired to `lock.default.factory`
  with `on-invalid="null"`. `LockableTrait` otherwise builds its own over `SemaphoreStore` /
  `FlockStore`, both host-local, so on a multi-pod deployment a cron on one node and a deploy hook on
  another each took their own lock and walked the catalogue at the same time. Hosts that have
  configured `framework.lock` with a shared store now get a real distributed lock; hosts that have
  not keep the previous local behaviour.
- Functional coverage for `madcoders:brand:resync-products`, which had none: exit codes, that
  `--dry-run` really writes nothing, that a run exits without work while another holds the lock, and
  that an invalid `--batch-size` degrades to a working run.
- Behat coverage for brand-page pagination, asserting the status code directly. Out-of-range pages
  are converted to 404 by `babdev/pagerfanta-bundle` (a hard Sylius dependency); the behaviour was
  correct but rested on a transitive dependency and was untested.
- CI runs PHP 8.4 on the newest supported stack. `composer.json` declares `^8.3` and the matrix only
  covered 8.3, so the upper half of the declared range shipped untested - and the dependency graph
  genuinely differs there, since `doctrine/instantiator 2.1.0` requires `^8.4`.
- CI has a `lowest` job running `composer update --prefer-lowest --prefer-stable`. Without it the
  declared floors - notably `monsieurbiz/sylius-settings-plugin ^2.0`, the plugin's tightest
  coupling - were assertions rather than tested facts.

## [1.0.0-RC.2] - 2026-08-25

Second release candidate. Makes the brand hookable reusable on any product grid, and closes the
regression-coverage gaps that RC.1 shipped with.

### Added

- The shop brand hookable is configurable and reusable: attach
  `@MadcodersSyliusBrandPlugin/shop/product/brand.html.twig` to any hook that has a product in
  context and drive it through `hookable_metadata.configuration` (`context_key`, `surface`,
  `show_logo`, `link`, `label`, `class`, `link_class`). The plugin's own product-page badge and
  product-tile label are now that same template with different configuration.
- `madcoders_brand_for(product, surface)` replaces the internal tile helper and is documented as
  public API, alongside `madcoders_brands_enabled()` and `madcoders_homepage_brands()`. It applies
  the feature toggle, the brand's enabled flag and the surface toggle, and answers a whole listing
  from one query.
- README section "Putting brands on your own product grids", with the configuration table and the
  helper reference.
- Integration coverage for the hookable's configuration surface - including `context_key`, the one
  option the plugin's own hooks never exercise - rendering the real template through Twig. The suite
  provisions its own channel and locale, so it does not depend on fixtures having been loaded first.
- Behat coverage for all three shop surfaces: the homepage strip, the product tile and their
  toggles, including that each renders nothing when the feature is off.

### Fixed

- `link: false` on the brand hookable was ignored. Twig's `default` filter substitutes on any
  *empty* value, and `false` is empty, so it read back as `true`. Every configuration option now
  uses `??`, which only substitutes when the key is absent.
- `make phpunit` and `make phpunit-non-unit` (and the CI step) raise the memory limit. Booting the
  full Sylius container exceeds the default, and the run died with an out-of-memory fatal naming a
  compiled container file rather than anything resembling a test failure.

### Changed

- `templates/shop/product/card/brand.html.twig` and `templates/shop/product/show/brand.html.twig`
  are consolidated into `templates/shop/product/brand.html.twig`. A host that re-pointed a hook at
  either of the old paths needs to switch to the new one and pass `configuration`.
- The shop-side test attribute is now `data-test-madcoders-product-brand="<brand code>"` on both
  surfaces, replacing the separate tile and product-page markers.

## [1.0.0-RC.1] - 2026-08-24

First release candidate. Verified on PHP 8.3 across Sylius ~2.0, ~2.1 and ~2.2, Symfony ^6.4 and
^7.4, on MySQL 8.4 and MariaDB 11.4.

### Added

- Project bootstrap: composer package, Sylius 2.x test application wiring, plugin bundle and
  dependency injection extension.
- Toolchain driven through Make: PHPStan (level max, no baseline), ECS, Rector, PHPUnit and Behat.
- GitHub Actions CI: a fast static/unit job plus a Sylius/Symfony/database matrix built with
  `SyliusLabs/BuildTestAppAction`.
- Conventional Commits template and a pre-commit hook running the fast quality gate.
- Project plan (`docs/PLAN.md`), architectural decision log (`docs/adr-log/`), agent and contributor
  guides.
- Brand domain model: `Brand` with a unique code, an enabled flag, a position, four display toggles
  and a logo, plus translatable name, slug, description and SEO meta per locale.
- Doctrine XML mapping, Sylius resource registration (translatable, with an image resource) and the
  first migration, written against the Schema API so it stays platform-neutral. Supported databases
  are MySQL and MariaDB; PostgreSQL is blocked by MySQL-only DDL in the settings plugin's own
  migration, not by anything here.
- A `Product` extension trait carrying the resolved brand, denormalised onto
  `sylius_product.brand_id` so "products of this brand" is an ordinary indexed query.
- Configuration through [MonsieurBiz' Settings plugin](https://github.com/monsieurbiz/SyliusSettingsPlugin):
  a feature toggle, the product attribute carrying the brand, and a value-to-brand mapping - all
  editable in the admin, per channel. The section is prepended by the plugin's extension, so there
  is no YAML for a host application to copy.
- `BrandSettingsProvider`, the single reader of those settings: it narrows the settings plugin's
  `mixed` returns, normalises mapping keys (trimmed, case-insensitive), and degrades to "feature
  off" rather than throwing when the plugin is unconfigured.
- `ProductBrandResolver`: resolves a product to a brand from its attribute value, routed through the
  mapping and falling back to a 1:1 match. Handles text and select attributes, tries both a select
  choice's key and its labels, and returns null rather than guessing.
- `ProductBrandSynchronizer` - the only writer of `brand_id` - wired to `sylius.product.pre_create`
  and `pre_update`, plus `bin/console madcoders:brand:resync-products` for imports, mapping changes
  and anything else that writes products outside the resource layer.
- Admin: a brand grid showing the logo, name, slug, code, description and where the brand is
  displayed; create and update forms with translations and a logo upload; a Catalog menu entry; and
  automatic slug generation that never overwrites an existing slug.
- Shop: a brand overview at `/brands`, a paginated per-brand product listing at `/brands/{slug}`, a
  homepage brand strip, a brand badge on the product page and a brand line on product tiles - each
  switched on per brand, and all inert when the feature toggle is off.
- LiipImagine filter sets for brand logos, using `inset` so a logo is never cropped.
- Sylius fixtures for brands, wired into the default suite, covering the house brand, an
  overview-only brand, a brand deliberately hidden from the overview and a disabled one.
- Unit tests for the settings provider, the resolver, the synchronizer, the slug generator and the
  model, and Behat coverage of the admin flow, the shop pages and attribute-based resolution.
- Validation on the models rather than the form types, so fixtures and imports are held to the same
  rules: a brand code is required, bounded, restricted to URL-safe characters and unique, and a slug
  is unique per locale. A duplicate code or slug is a field error instead of a 500 at flush.
- Slugs are generated inside the form, before validation runs, so the uniqueness constraint sees the
  generated value; the resource-event listener still covers writes that never touch the form.
- `madcoders:brand:resync-products` takes a lock, so a nightly cron and a deploy hook cannot walk
  the catalogue at the same time; the second run exits immediately.
- The settings split into two scopes and the split is now enforced, not just documented. `enabled`
  stays per channel; `brand_attribute` and `brand_mapping` are global, are only offered on the "all
  channels" tab, and are read with a null channel so a stale channel-scoped value is ignored.
- Resolution no longer depends on the `enabled` toggle. That toggle is per channel and controls
  display; gating resolution on it made the same product resolve differently in the admin than on
  the CLI, and left `brand_id` stale while the feature was off.
- Admin supportability: the product show page explains how a product got its brand - which attribute
  is configured, what the product holds for it, what that maps to, and whether such a brand exists -
  and the product grid gained a brand column and filter.
- The brand grid shows how many products point at each brand, and the delete confirmation says how
  many will be unbranded before you confirm.
- Performance: product tiles resolve their brand from a set loaded once per page instead of
  initialising a Doctrine proxy per tile, and the feature toggle and resolved channel are memoised.
  Measured: reading the brand for every tile went from ~2 queries per tile to zero.

### Notes for host applications

- An application that supplies its own `BrandTranslation` entity **must** override
  `Brand::createTranslation()`. See `docs/INSTALLATION.md`.
- `brand_attribute` and `brand_mapping` are global settings, configured on the "all channels" tab.
  A channel-scoped value is ignored.
- Turning the feature off hides brands in the shop but does not stop products resolving, so no
  resync is needed when switching it back on.
