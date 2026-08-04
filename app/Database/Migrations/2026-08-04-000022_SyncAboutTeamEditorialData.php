<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Synchronizes the Spanish About-page roster with the approved public roster. */
final class SyncAboutTeamEditorialData extends Migration
{
    /** @var array<string, array{profession: string, position: string, email: string}> */
    private const TEAM = [
        'Víctor Quiroga' => ['profession' => 'Payaso', 'position' => 'Presidente fundación', 'email' => 'direccion@teatromuseo.cl'],
        'Paulina Beltrán' => ['profession' => 'Titiritera', 'position' => 'Encargada de proyectos', 'email' => 'proyectos@teatromuseo.cl'],
        'Constanza Valenzuela' => ['profession' => 'Diseñadora', 'position' => 'Encargada de difusión', 'email' => 'diseno@teatromuseo.cl'],
        'Diego Zuñiga' => ['profession' => 'Actor, payaso', 'position' => 'Encargado de extensión y ventas', 'email' => 'extension@teatromuseo.cl'],
        'Claudio Palacios' => ['profession' => 'Payaso', 'position' => 'Secretario Academico', 'email' => 'teatroescuela@teatromuseo.cl'],
        'Felipe Lira' => ['profession' => 'Bailarín titiritero', 'position' => 'Encargado de programación', 'email' => 'programacion@teatromuseo.cl'],
        'Tomás Arce' => ['profession' => 'Gestor cultural', 'position' => 'Encargado de comunicaciones', 'email' => 'difusion@teatromuseo.cl'],
        'Barbara Quiroga' => ['profession' => 'Secretaria', 'position' => 'Encargada de sala y museo', 'email' => 'sala@teatromuseo.cl'],
        'Kevin Zamora' => ['profession' => 'Técnico', 'position' => 'Jefe técnico', 'email' => 'tecnico@teatromuseo.cl'],
        'Javiera Silva' => ['profession' => 'Periodista', 'position' => 'Editora Revista 795', 'email' => 'editorial@teatromuseo.cl'],
    ];

    public function up(): void
    {
        $parentId = $this->parentId();
        $languageId = $this->languageId('es');
        if ($parentId === null || $languageId === null) {
            return;
        }

        $children = $this->db->table('cms_block_instances c')
            ->select('c.id, t.id AS translation_id, t.block_data')
            ->join('cms_content_blocks b', 'b.id = c.block_id')
            ->join('cms_block_instance_translations t', 't.instance_id = c.id AND t.language_id = ' . $languageId, 'left')
            ->where('c.parent_instance_id', $parentId)
            ->where('b.block_key', 'team_member')
            ->get()
            ->getResultArray();

        foreach ($children as $child) {
            $current = json_decode((string) ($child['block_data'] ?? '{}'), true);
            $name = is_array($current) ? trim((string) ($current['name'] ?? '')) : '';
            $person = self::TEAM[$name] ?? null;
            if ($person === null) {
                continue;
            }

            $data = is_array($current) ? $current : [];
            $data['profession'] = $person['profession'];
            $data['position'] = $person['position'];
            $data['email'] = $person['email'];
            // Keep profession and primary position out of the additional
            // roles repeater; they already have dedicated fields.
            $data['roles'] = [];

            $payload = ['block_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
            if (! empty($child['translation_id'])) {
                $this->db->table('cms_block_instance_translations')
                    ->where('id', (int) $child['translation_id'])
                    ->update($payload);
            } else {
                $this->db->table('cms_block_instance_translations')->insert($payload + [
                    'instance_id' => (int) $child['id'],
                    'language_id' => $languageId,
                    'is_published' => 1,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Preserve editorial corrections on rollback.
    }

    private function parentId(): ?int
    {
        $row = $this->db->table('cms_block_instances i')
            ->select('i.id')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->join('cms_pages p', 'p.id = i.owner_id')
            ->join('cms_page_translations pt', 'pt.page_id = p.id')
            ->where('i.owner_type', 'page')
            ->where('i.parent_instance_id IS NULL', null, false)
            ->where('b.block_key', 'team_grid')
            ->whereIn('pt.slug', ['quienes-somos', 'nosotros', 'about', 'about-us', 'a-propos', 'sobre-nos'])
            ->where('p.deleted_at IS NULL', null, false)
            ->orderBy('i.id', 'DESC')
            ->get()
            ->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function languageId(string $code): ?int
    {
        $row = $this->db->table('cms_languages')->select('id')->where('code', $code)->get()->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }
}
