<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Completes only missing primary photos on the already-created team children. */
final class CompleteAboutTeamPrimaryMedia extends Migration
{
    /** @var array<string, string> */
    private const PRIMARY_MEDIA = [
        'Víctor Quiroga' => 'https://teatromuseo.cl/images/team/victor-quiroga.png',
        'Paulina Beltrán' => 'https://teatromuseo.cl/images/team/paulina-beltran.png',
        'Tomás Arce' => 'https://teatromuseo.cl/images/team/6713faca50701.png',
        'Kevin Zamora' => 'https://teatromuseo.cl/images/team/6713f9d87ce79.png',
        'Javiera Silva' => 'https://teatromuseo.cl/images/team/67128c3937d9c.png',
    ];

    public function up(): void
    {
        $parentId = $this->parentId();
        if ($parentId === null) {
            return;
        }
        $children = $this->db->table('cms_block_instances c')
            ->select('c.id, c.block_config, t.block_data')
            ->join('cms_content_blocks b', 'b.id=c.block_id')
            ->join('cms_block_instance_translations t', 't.instance_id=c.id')
            ->join('cms_languages l', 'l.id=t.language_id AND l.code="es"')
            ->where('c.parent_instance_id', $parentId)
            ->where('b.block_key', 'team_member')
            ->get();
        if ($children === false) {
            return;
        }

        foreach ($children->getResultArray() as $child) {
            $data = json_decode((string) ($child['block_data'] ?? '{}'), true);
            $name = is_array($data) ? (string) ($data['name'] ?? '') : '';
            $photo = self::PRIMARY_MEDIA[$name] ?? null;
            if ($photo === null) {
                continue;
            }

            $config = json_decode((string) ($child['block_config'] ?? '{}'), true);
            $config = is_array($config) ? $config : [];
            $current = is_array($config['photo'] ?? null) ? $config['photo'] : [];
            if (trim((string) ($current['url'] ?? '')) !== '') {
                continue;
            }
            $config['photo'] = ['source_kind' => 'external_url', 'file_id' => null, 'url' => $photo];
            $this->db->table('cms_block_instances')->where('id', (int) $child['id'])->update([
                'block_config' => json_encode($config, JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function down(): void
    {
        // Preserve editorial media on rollback.
    }

    private function parentId(): ?int
    {
        $row = $this->db->table('cms_block_instances i')
            ->select('i.id')
            ->join('cms_content_blocks b', 'b.id=i.block_id')
            ->join('cms_pages p', 'p.id=i.owner_id')
            ->join('cms_page_translations pt', 'pt.page_id=p.id')
            ->where('i.owner_type', 'page')
            ->where('i.parent_instance_id IS NULL', null, false)
            ->where('b.block_key', 'team_grid')
            ->whereIn('pt.slug', ['nosotros', 'quienes-somos', 'about', 'about-us', 'a-propos', 'sobre-nos'])
            ->where('p.deleted_at IS NULL', null, false)
            ->orderBy('i.id', 'ASC')
            ->get()->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }
}
