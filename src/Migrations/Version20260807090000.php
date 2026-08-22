<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the brand tables and adds the resolved brand to the product table.
 *
 * Written against the Schema API rather than raw SQL so the same migration works on MySQL, MariaDB
 * and PostgreSQL - all three are covered by CI.
 *
 * Indexes carry the names Doctrine's ORM derives from the table and column names rather than
 * readable ones, so that `doctrine:schema:validate` reports a host application in sync after
 * migrating.
 */
final class Version20260807090000 extends AbstractMigration
{
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
        $product = $schema->getTable('sylius_product');
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
        $product = $schema->getTable('sylius_product');
        $product->removeForeignKey('FK_677B9B7444F5D008');
        $product->dropIndex('IDX_677B9B7444F5D008');
        $product->dropColumn('brand_id');

        $schema->dropTable('madcoders_brand__brand_image');
        $schema->dropTable('madcoders_brand__brand_translation');
        $schema->dropTable('madcoders_brand__brand');
    }
}
