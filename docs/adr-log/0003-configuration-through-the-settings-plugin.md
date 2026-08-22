# 0003 - Configuration through MonsieurBiz Settings

**Status:** accepted

## Context

Three things have to be configurable at runtime, by a shop operator rather than a developer:

1. a **feature toggle** for the whole brand feature,
2. the **product attribute** that carries the brand,
3. a **mapping** from attribute values to brand codes.

None of these can live in `config/packages/*.yaml`: (2) and (3) change when the catalogue or the
supplier feed changes, which is an operations event, not a deployment. They also differ per channel
- a marketplace channel and a B2B channel can perfectly well read different attributes.

Sylius 2.x has no built-in settings store. The options were a plugin-owned configuration entity (as
the gift card plugin does per channel), or an existing settings plugin.

## Decision

Use [`monsieurbiz/sylius-settings-plugin`](https://github.com/monsieurbiz/SyliusSettingsPlugin)
`^2.0` as a **hard dependency**, and declare one settings section under the alias
`madcoders_brand.default`:

| path | type | meaning |
|---|---|---|
| `enabled` | boolean | feature toggle for the whole plugin |
| `brand_attribute` | text | product attribute code carrying the brand |
| `brand_mapping` | json | list of `{source, brand}` pairs; empty means 1:1 |

`enabled` is read **per channel**; `brand_attribute` and `brand_mapping` are read at the **global
scope only**. See the rules below - this is enforced, not merely recommended.

The declaration is **prepended** by the plugin's extension rather than left to the host application,
so `composer require` plus the bundle registration is enough to get a working settings screen. The
prepend is guarded on `hasExtension('monsieurbiz_sylius_settings')` so a host that has not yet
registered the bundle gets a clear failure at install time instead of an unresolvable container.

Every read goes through `BrandSettingsProvider`, never through the plugin's
`SettingsProviderInterface` directly.

## Consequences

- Configuration is per channel and per locale for free, and editable in the admin.
- The settings plugin becomes part of the plugin's public dependency surface: a host application
  must register `MonsieurBizSyliusSettingsPlugin` and run its migrations.
- `getSettingValue()` returns `mixed` and throws `SettingsException` when the alias is unknown.
  Narrowing and error handling are centralised in `BrandSettingsProvider` so that no caller has to
  think about it.
- The alias format is dictated by the settings plugin: exactly `<applicationName>.<name>`, split on
  a single dot. `madcoders_brand.default` satisfies it; `madcoders.sylius.brand` would not.
- The settings form does not render `brand_attribute` or `brand_mapping` on channel tabs, and the
  provider reads them with a null channel. A value left at channel scope by an older install or by
  `monsieurbiz:settings:set --channel=...` is ignored rather than silently honoured.
- Reads are memoised per request and the service is tagged `kernel.reset`. This is not premature:
  `isEnabled()` cost two queries per call - one to resolve a channel, one for the setting - and the
  product tile hook calls it once per tile.

## Rules

1. New configurable value → a new path under `madcoders_brand.default`, a field on `SettingsType`,
   a typed accessor on `BrandSettingsProvider`, and a row in the README table.
2. **Never call `SettingsProviderInterface` outside `BrandSettingsProvider`.** One reader means one
   place where a missing setting has a defined fallback.
3. A missing or malformed setting must degrade to the documented default, never throw at request
   time. The feature toggle defaults to *off*: an unconfigured plugin must be invisible.
4. Mapping keys are normalised (trimmed, lower-cased) on read, so the admin does not have to be
   careful about whitespace and case.
5. **A setting is either per channel or global, and the form must agree with the provider.** A
   field offered on a channel tab whose value is then read globally is worse than no field at all.
   `brand_attribute` and `brand_mapping` are global because there is one `brand_id` per product;
   `enabled` is per channel because it only controls display.
6. Anything memoised here gets `reset()` and a `kernel.reset` tag, or a worker serves the value it
   read on its first message forever.
