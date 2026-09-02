# Walkthrough: create a brand

**Who this is for:** a store administrator.
**What you end up with:** a new brand in the Brands grid, ready to be shown in the shop.

This walkthrough was replayed against a running store. Every step below completed.

## 1. Open the Brands grid

In the admin menu, open **Catalog** then **Brands**.

![The Brands grid](../assets/journeys/create-a-brand-01-open-catalog-then-brands-in-the-admin-menu-.png)

## 2. Start a new brand

Choose **Create**. The **New Brand** form opens.

![The new brand form](../assets/journeys/create-a-brand-02-choose-create-to-open-the-new-brand-form-.png)

## 3. Give the brand a code

Fill in **Code**. In this run the value was `northern-forge`.

This is what the brand mapping and your imports refer to, so keep it stable. Letters, digits,
hyphens and underscores only.

![The Code field filled in](../assets/journeys/create-a-brand-03-give-the-brand-a-code-this-is-what-the-brand-mappi.png)

## 4. Enter the name

Fill in **Name** in your default locale section, under **Translations**. In this run the value was `Northern Forge`.

This is the name shoppers see. It is also what the **Slug** is generated from if you leave that
field blank.

![The Name field filled in](../assets/journeys/create-a-brand-04-enter-the-name-shoppers-will-see-.png)

## 5. Describe the brand

Fill in **Description** if you want one. In this run the value was
`Hand-finished workwear from the north.`

This text appears on the brand's own page, under its name.

![The Description field filled in](../assets/journeys/create-a-brand-05-optionally-describe-the-brand-this-text-appears-on.png)

## 6. Review the toggles before saving

Scroll down to **Enabled** and the four display toggles. Nothing in the shop shows this brand until
**Enabled** is on, and each of **Display on the homepage**, **Display on the product page**,
**Display on product tiles** and **Display on the brand overview** decides one place it can appear.

New brands start with **Display on the product page** and **Display on the brand overview** on, and
the other two off. See [Managing brands](../features/managing-brands.md) for what each one does.

## 7. Save

Choose **Create**.

The admin confirms with **Brand has been successfully created.** and takes you to the new brand's
edit page, where you can add the logo, adjust **Position** and fill in the other locales.

![The success message on the new brand's edit page](../assets/journeys/create-a-brand-07-save-the-brand-.png)

## 8. Check the result

The brand now exists and is listed in **Catalog** then **Brands**.

![The saved brand](../assets/journeys/create-a-brand-08-the-new-brand-is-now-listed-in-the-brands-grid-.png)

## What happens next

Creating a brand does not attach any product to it. Products reach a brand through their brand
attribute value, so:

1. Make sure a [brand attribute is configured](../features/brand-settings.md).
2. Make sure the products' attribute value matches this brand's code, or add a
   [brand mapping](../features/brand-settings.md) row that points the value at it.
3. Run [`madcoders:brand:resync-products`](../features/resyncing-product-brands.md) if the products
   were not saved through the admin since.
