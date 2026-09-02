<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Stores cache invalidation events in the same transaction as the CMS write.
 *
 * A separate dispatcher delivers committed events to Web. This avoids making a
 * remote HTTP call from a database transaction while ensuring a rolled-back
 * content change can never invalidate a snapshot.
 */
final class CreateCacheInvalidationOutboxTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('cache_invalidation_outbox')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'event_key' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'payload' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'source' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'default'    => 'cms_automatic',
            ],
            'attempts' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'default'    => 0,
            ],
            'available_at' => [
                'type'    => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'lock_token' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'lock_expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'dispatched_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_error' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('event_key');
        $this->forge->addKey(['dispatched_at', 'available_at']);
        $this->forge->addKey(['lock_expires_at']);
        $this->forge->createTable('cache_invalidation_outbox');
    }

    public function down(): void
    {
        $this->forge->dropTable('cache_invalidation_outbox', true);
    }
}
