# Installation

## 1. Require the package

```bash
composer require madcoders/sylius-brand-plugin
```

This pulls in [`monsieurbiz/sylius-settings-plugin`](https://github.com/monsieurbiz/SyliusSettingsPlugin),
which the brand plugin uses for all of its runtime configuration.

## 2. Register the bundles

Both bundles are needed. The brand plugin registers its settings section with the settings plugin at
container build time, so the settings plugin has to be registered too.

```php
# config/bundles.php

return [
    // ...
    MonsieurBiz\SyliusSettingsPlugin\MonsieurBizSyliusSettingsPlugin::class => ['all' => true],
    Madcoders\SyliusBrandPlugin\MadcodersSyliusBrandPlugin::class => ['all' => true],
];
```

## 3. Import the configuration

```yaml
# config/packages/madcoders_sylius_brand.yaml

imports:
    - { resource: "@MonsieurBizSyliusSettingsPlugin/Resources/config/config.yaml" }
    - { resource: "@MadcodersSyliusBrandPlugin/config/config.yaml" }
```

The plugin's settings section and its LiipImagine filter sets are **not** in that file - they are
prepended by the plugin's extension, so there is nothing else to copy.

## 4. Import the routes

```yaml
# config/routes/madcoders_sylius_brand.yaml

monsieurbiz_sylius_settings_admin:
    resource: "@MonsieurBizSyliusSettingsPlugin/Resources/config/routes/admin.yaml"
    prefix: /admin

madcoders_sylius_brand_admin:
    resource: "@MadcodersSyliusBrandPlugin/config/routes/admin.yaml"
    prefix: /admin

madcoders_sylius_brand_shop:
    resource: "@MadcodersSyliusBrandPlugin/config/routes/shop.yaml"
    prefix: /{_locale}
    requirements:
        _locale: ^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$
```

## 5. Declare the brand entities

The plugin's models are Doctrine **mapped superclasses**, so your application supplies the concrete
entities. Create three classes:

```php
# src/Entity/Brand/Brand.php

namespace App\Entity\Brand;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusBrandPlugin\Model\Brand as BaseBrand;
use Sylius\Resource\Model\TranslationInterface;

#[ORM\Entity]
#[ORM\Table(name: 'madcoders_brand__brand')]
class Brand extends BaseBrand
{
    protected function createTranslation(): TranslationInterface
    {
        return new BrandTranslation();
    }
}
```

> **Do not skip the `createTranslation()` override.** `getTranslation()` calls it to build a
> translation for a locale that has none yet, and the plugin's own implementation can only return
> the plugin's model class - Doctrine then rejects it with *"Expected value of type
> App\Entity\Brand\BrandTranslation ... got Madcoders\SyliusBrandPlugin\Model\BrandTranslation"*.
> This is the same contract Sylius' own translatable models have; it applies whenever an
> application supplies both a model and its translation.

```php
# src/Entity/Brand/BrandTranslation.php

namespace App\Entity\Brand;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusBrandPlugin\Model\BrandTranslation as BaseBrandTranslation;

#[ORM\Entity]
#[ORM\Table(name: 'madcoders_brand__brand_translation')]
class BrandTranslation extends BaseBrandTranslation
{
}
```

```php
# src/Entity/Brand/BrandImage.php

namespace App\Entity\Brand;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusBrandPlugin\Model\BrandImage as BaseBrandImage;

#[ORM\Entity]
#[ORM\Table(name: 'madcoders_brand__brand_image')]
class BrandImage extends BaseBrandImage
{
}
```

Then point the resources at them:

```yaml
# config/packages/madcoders_sylius_brand.yaml

sylius_resource:
    resources:
        madcoders_sylius_brand.brand:
            classes:
                model: App\Entity\Brand\Brand
            translation:
                classes:
                    model: App\Entity\Brand\BrandTranslation
        madcoders_sylius_brand.brand_image:
            classes:
                model: App\Entity\Brand\BrandImage
```

## 6. Extend your Product

The plugin adds one field to Sylius' `Product`: the brand it resolved from the product attribute.
Apply the interface and the trait to your own entity - the trait carries its own Doctrine mapping,
so there is nothing else to map.

```php
# src/Entity/Product/Product.php

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Madcoders\SyliusBrandPlugin\Model\ProductInterface as BrandAwareProductInterface;
use Madcoders\SyliusBrandPlugin\Model\ProductTrait as BrandAwareProductTrait;
use Sylius\Component\Core\Model\Product as BaseProduct;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product')]
class Product extends BaseProduct implements BrandAwareProductInterface
{
    use BrandAwareProductTrait;
}
```

If your application does not already override the Sylius product, register the override too:

```yaml
sylius_product:
    resources:
        product:
            classes:
                model: App\Entity\Product\Product
```

> **If your entities use XML or YAML mapping rather than attributes**, the trait's attributes will
> not be read. Map `brand` yourself as a nullable many-to-one on
> `Madcoders\SyliusBrandPlugin\Model\BrandInterface` with the join column `brand_id`, `ON DELETE SET
> NULL`.

A complete worked example lives in `tests/TestApplication/` in this repository.

## 7. Run the migrations

```bash
bin/console doctrine:migrations:migrate
```

This creates `madcoders_brand__brand`, `madcoders_brand__brand_translation` and
`madcoders_brand__brand_image`, and adds the `brand_id` column to `sylius_product`. The migration is
written against the Schema API, so it runs on MySQL, MariaDB and PostgreSQL alike.

The settings plugin has migrations of its own; they run in the same command.

## 8. Configure the feature

Everything the plugin needs at runtime is configured in the admin, under **Settings → Brands**:

| Setting | What to put there |
|---|---|
| **Enable brands** | Off by default. Nothing is shown in the shop until this is on. |
| **Brand attribute** | The product attribute that carries the brand. Only text, textarea, select, integer and percent attributes are offered - a checkbox or a date cannot name a brand. |
| **Brand mapping** | Attribute value → brand code pairs, for catalogues where one brand is spelled several ways. Leave it empty for a 1:1 match between the attribute value and the brand code. |

**Enable brands** is per channel - a shop can show brands on its retail channel and not on its B2B
one. The other two are global, and are only offered on the "all channels" tab: a product has one
resolved brand, so a per-channel attribute or mapping would have no single right answer.

Turning **Enable brands** off hides brands in the shop but does not stop products resolving, so the
data stays correct and switching it back on needs no resync.

### Create the brands

Admin → Catalog → **Brands**. The `code` is what the mapping points at (and what an attribute value
has to equal when there is no mapping), so it is worth choosing deliberately.

Note that **Display on the brand overview** does double duty: with it off, the brand is neither
listed at `/brands` nor reachable at `/brands/{slug}`.

### Attach the existing catalogue

Products are attached to brands when they are saved through the admin. For a catalogue that already
exists - or after changing the attribute or the mapping - run:

```bash
bin/console madcoders:brand:resync-products
```

Add `--dry-run` to see what would change first. The command is idempotent and takes a lock, so it is
safe to run from a deployment script and a cron job at once - the second one exits immediately
rather than walking the catalogue in parallel. It is the documented follow-up to any import that
writes products outside the resource layer.

Deleting a brand sets `brand_id` to null on its products; they keep their attribute value, so
creating a replacement brand and re-running this command reattaches them. The admin shows the
affected product count before you confirm a deletion.

## 9. Optional: adjust the logo sizes

The plugin registers three LiipImagine filter sets: `madcoders_sylius_brand_logo` (brand page),
`madcoders_sylius_brand_logo_tile` (overview and homepage) and
`madcoders_sylius_brand_logo_thumbnail` (badges and the admin grid). All three use `inset`, so a
logo is never cropped. Override any of them in your own `liip_imagine` configuration.
