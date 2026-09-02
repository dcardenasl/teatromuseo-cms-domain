<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Materializes per-entry, per-language field values used as `filter_by`/
 * `order_by=field:...` in public listings, so they can be filtered/ordered
 * with real WHERE/ORDER BY + LIMIT instead of loading every candidate entry
 * into PHP (see docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md §2.A).
 *
 * One row per (block instance, language, field) that resolves to a non-empty
 * scalar. `field_key` is written twice per candidate field: once namespaced
 * (`block.<block_key>.<field>`) and once bare (`<field>`), matching the two
 * reference styles `filter_by`/`order_by=field:...` already accept — see
 * PublicEntryReader::classifyField()'s `facet` branch, resolved against this
 * table by joinFacetSubquery(). When two blocks in the same entry share a
 * bare field name, the resolved-value
 * subquery in PublicEntryReader picks one deterministically via MAX() — an
 * explicit, documented tie-break, not a silent ambiguity (the previous PHP
 * implementation picked "whichever DB row order returned first", which was
 * effectively undefined).
 */
class CreateCmsEntryFacetValues extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'entry_id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'block_instance_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'language_id'       => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'field_key'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'value_type'        => ['type' => 'ENUM', 'constraint' => ['string', 'date', 'numeric'], 'default' => 'string'],
            'value_string'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'value_date'        => ['type' => 'DATETIME', 'null' => true],
            'value_numeric'     => ['type' => 'DECIMAL', 'constraint' => '20,6', 'null' => true],
        ]);
        $this->forge->addField('`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->forge->addField('`updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP');

        $this->forge->addPrimaryKey('id');
        // Natural key for the delete+reinsert cycle on every block save
        // (EntryFacetValueSynchronizer::sync() deletes by block_instance_id +
        // language_id, so this also guards against accidental duplicate inserts).
        $this->forge->addUniqueKey(['block_instance_id', 'language_id', 'field_key'], 'uk_facet_instance_lang_field');
        $this->forge->addKey('entry_id', false, false, 'idx_facet_entry');
        // One composite index per value column: filter/order always narrows by
        // field_key first, then range/equality-scans the typed value column,
        // then reads entry_id straight from the index (covering for the join).
        $this->forge->addKey(['field_key', 'value_date', 'entry_id'], false, false, 'idx_facet_key_date');
        $this->forge->addKey(['field_key', 'value_string', 'entry_id'], false, false, 'idx_facet_key_string');
        $this->forge->addKey(['field_key', 'value_numeric', 'entry_id'], false, false, 'idx_facet_key_numeric');
        $this->forge->addForeignKey('block_instance_id', 'cms_block_instances', 'id', '', 'CASCADE', 'fk_facet_block_instance');
        $this->forge->addForeignKey('language_id', 'cms_languages', 'id', '', 'CASCADE', 'fk_facet_language');

        $this->forge->createTable('cms_entry_facet_values', false, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('cms_entry_facet_values', true);
    }
}
