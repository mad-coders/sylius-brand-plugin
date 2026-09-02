<?php

declare(strict_types=1);

namespace Madcoders\SyliusBrandPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the brand tables and adds the resolved brand to the product table.
 *
 * Written against the Schema API rather than raw SQL, so it is platform-neutral. CI covers MySQL and
 * MariaDB; PostgreSQL is not currently reachable because a hard dependency
 * (monsieurbiz/sylius-settings-plugin) ships MySQL-only DDL in its own migration and fails first.
 * Keeping this one neutral costs nothing and means the plugin is ready the day that is fixed.
 *
 * Indexes carry the names Doctrine's ORM derives from the table and column names rather than
 * readable ones, so that `doctrine:schema:validate` reports a host application in sync after
 * migrating.
 */
final class Version20260807090000 extends AbstractMigration
{
    /**
     * Sylius' own product table, which this migration extends rather than owns.
     *
     * Named here rather than inline so an application that renamed it has one place to change. The
     * ORM mapping is not readable from a migration, so this cannot be derived automatically.
     */
    private const string PRODUCT_TABLE = 'sylius_product';

    public function getDescription(): string
    {
        return 'Add brands, their translations and logos, and the resolved brand on the product.';
    }

    public function up(Schema $schema): void
    {
        // No column defaults anywhere below: the models set every one of these in their
        // constructor, and a DB-level default the ORM mapping does not declare makes
        // `doctrine:schema:validate` report a freshly migrated host application as out of sync.
        $brand = $schema->createTable('madcoders_brand__brand');
        $brand->addColumn('id', 'integer', ['autoincrement' => true]);
        $brand->addColumn('code', 'string', ['length' => 64]);
        $brand->addColumn('position', 'integer');
        $brand->addColumn('enabled', 'boolean');
        $brand->addColumn('display_on_homepage', 'boolean');
        $brand->addColumn('display_on_product_page', 'boolean');
        $brand->addColumn('display_on_product_tile', 'boolean');
        $brand->addColumn('display_on_brand_overview', 'boolean');
        $brand->addColumn('created_at', 'datetime', ['notnull' => false]);
        $brand->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $brand->setPrimaryKey(['id']);
        $brand->addUniqueIndex(['code'], 'UNIQ_67AAC16877153098');

        $brandTranslation = $schema->createTable('madcoders_brand__brand_translation');
        $brandTranslation->addColumn('id', 'integer', ['autoincrement' => true]);
        $brandTranslation->addColumn('translatable_id', 'integer');
        $brandTranslation->addColumn('locale', 'string', ['length' => 255]);
        $brandTranslation->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
        $brandTranslation->addColumn('slug', 'string', ['length' => 255, 'notnull' => false]);
        $brandTranslation->addColumn('description', 'text', ['notnull' => false]);
        $brandTranslation->addColumn('meta_keywords', 'string', ['length' => 255, 'notnull' => false]);
        $brandTranslation->addColumn('meta_description', 'string', ['length' => 255, 'notnull' => false]);
        $brandTranslation->setPrimaryKey(['id']);
        $brandTranslation->addIndex(['translatable_id'], 'IDX_22D568A22C2AC5D3');
        // A brand has at most one translation per locale - the resource bundle indexes the
        // collection by locale and would silently drop the extras. The constraint name is the one
        // the resource bundle derives from the table name; anything else drifts on validate.
        $brandTranslation->addUniqueIndex(['translatable_id', 'locale'], 'madcoders_brand__brand_translation_uniq_trans');
        $brandTranslation->addUniqueIndex(['locale', 'slug'], 'madcoders_brand_slug_uidx');
        $brandTranslation->addForeignKeyConstraint('madcoders_brand__brand', ['translatable_id'], ['id'], ['onDelete' => 'CASCADE']);

        $brandImage = $schema->createTable('madcoders_brand__brand_image');
        $brandImage->addColumn('id', 'integer', ['autoincrement' => true]);
        $brandImage->addColumn('owner_id', 'integer');
        $brandImage->addColumn('type', 'string', ['length' => 255, 'notnull' => false]);
        $brandImage->addColumn('path', 'string', ['length' => 255]);
        $brandImage->setPrimaryKey(['id']);
        $brandImage->addIndex(['owner_id'], 'IDX_1099805B7E3C61F9');
        $brandImage->addForeignKeyConstraint('madcoders_brand__brand', ['owner_id'], ['id'], ['onDelete' => 'CASCADE']);

        // The resolved brand, added by the plugin's ProductTrait. Derived state - always
        // recomputable with `bin/console madcoders:brand:resync-products`.
        //
        // Guarded because the column is added to a table this plugin does not own. A shop that
        // already carries a `brand_id` on its products - hand-rolled, or left behind by another
        // brand plugin - would otherwise abort the whole migration with ColumnAlreadyExists, and
        // take the three tables above down with it.
        $product = $schema->getTable(self::PRODUCT_TABLE);

        if ($product->hasColumn('brand_id')) {
            $this->write(\sprintf(
                'Skipped adding %s.brand_id: the column already exists. Check that it means what this plugin expects before running the resync command.',
                self::PRODUCT_TABLE,
            ));

            return;
        }

        $product->addColumn('brand_id', 'integer', ['notnull' => false]);
        $product->addIndex(['brand_id'], 'IDX_677B9B7444F5D008');
        // Named explicitly so down() can drop it: an auto-generated constraint name differs per
        // platform, and MySQL refuses to drop a column that is still the target of a foreign key.
        $product->addForeignKeyConstraint(
            'madcoders_brand__brand',
            ['brand_id'],
            ['id'],
            ['onDelete' => 'SET NULL'],
            'FK_677B9B7444F5D008',
        );
    }

    public function down(Schema $schema): void
    {
        $product = $schema->getTable(self::PRODUCT_TABLE);

        // Mirrors the guard in up(): on an install where the column was already there, up() left it
        // alone, so down() must not remove someone else's column.
        if ($product->hasForeignKey('FK_677B9B7444F5D008')) {
            $product->removeForeignKey('FK_677B9B7444F5D008');
            $product->dropIndex('IDX_677B9B7444F5D008');
            $product->dropColumn('brand_id');
        }

        $schema->dropTable('madcoders_brand__brand_image');
        $schema->dropTable('madcoders_brand__brand_translation');
        $schema->dropTable('madcoders_brand__brand');
    }
}
