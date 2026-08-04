<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Moves the institutional page cleanup from the public renderer into CMS data.
 *
 * Spanish is the editorial source for this page. The migration preserves its
 * existing text and media, removes only the known imported presentation blocks,
 * and fills the other locales through the same persisted block instances.
 *
 * @cms-content-data-migration
 */
final class UnifyAboutPageLocales extends Migration
{
    /** @var list<string> */
    private const PAGE_SLUGS = ['nosotros', 'quienes-somos', 'about', 'about-us', 'a-propos', 'sobre-nos'];

    /** @var list<string> */
    private const CANONICAL_ROOT_BLOCKS = ['page_header', 'hero_slider', 'rich_text', 'cards_grid', 'team_grid', 'cta'];

    /** @var list<string> */
    private const IMPORTED_ROOT_BLOCKS = ['hero_banner', 'cards_slider', 'asset_showcase', 'accordion'];

    public function up(): void
    {
        $pageId = $this->pageId();
        if ($pageId === null) {
            return;
        }

        $instances = $this->rootInstances($pageId);
        $kept = [];
        $seen = [];
        foreach ($instances as $instance) {
            $key = (string) ($instance['block_key'] ?? '');
            $id = (int) ($instance['id'] ?? 0);
            if ($id <= 0 || in_array($key, self::IMPORTED_ROOT_BLOCKS, true) || ! in_array($key, self::CANONICAL_ROOT_BLOCKS, true) || isset($seen[$key])) {
                if ($id > 0) {
                    $this->db->table('cms_block_instances')->where('id', $id)->delete();
                }
                continue;
            }

            $seen[$key] = true;
            $kept[$key] = $id;
        }

        if (isset($kept['rich_text'])) {
            $this->ensureSpanishHeading((int) $kept['rich_text']);
            $this->writeTranslatedRichText((int) $kept['rich_text']);
        }

        if (isset($kept['cards_grid'])) {
            $this->normalizeCards((int) $kept['cards_grid']);
        }

        if (isset($kept['team_grid'])) {
            $this->mergeConfig((int) $kept['team_grid'], ['columns' => '3']);
        }
    }

    public function down(): void
    {
        // Forward-only content migration. The removed imported blocks are not
        // part of the canonical institutional page model.
    }

    private function pageId(): ?int
    {
        $result = $this->db->table('cms_pages p')
            ->select('p.id')
            ->join('cms_page_translations pt', 'pt.page_id = p.id')
            ->where('p.deleted_at IS NULL', null, false)
            ->whereIn('pt.slug', self::PAGE_SLUGS)
            ->orderBy('p.id', 'ASC')
            ->get();
        $row = $result === false ? null : $result->getRowArray();

        return is_array($row) ? (int) $row['id'] : null;
    }

    /** @return list<array<string, mixed>> */
    private function rootInstances(int $pageId): array
    {
        $result = $this->db->table('cms_block_instances i')
            ->select('i.id, b.block_key')
            ->join('cms_content_blocks b', 'b.id = i.block_id')
            ->where('i.owner_type', 'page')
            ->where('i.owner_id', $pageId)
            ->where('i.parent_instance_id IS NULL', null, false)
            ->orderBy('i.sort_order', 'ASC')
            ->orderBy('i.id', 'ASC')
            ->get();

        return $result === false ? [] : array_values($result->getResultArray());
    }

    private function ensureSpanishHeading(int $instanceId): void
    {
        $translation = $this->translation($instanceId, 'es');
        if ($translation === null) {
            return;
        }

        $content = (string) ($translation['content'] ?? '');
        if (str_contains($content, '<h2>Sobre Nosotros</h2>')) {
            return;
        }

        $this->saveTranslation($instanceId, 'es', ['content' => '<h2>Sobre Nosotros</h2>' . $content]);
    }

    private function writeTranslatedRichText(int $instanceId): void
    {
        $translations = [
            'en' => '<h2>About Us</h2><p>Since 2007, the Teatromuseo Puppet and Clown Foundation has promoted, disseminated, and professionalized these performing arts in Chile through a national and international training school, a specialized museum, and a theatre with a permanent family programme.</p><p>We are a team of artists and cultural-management professionals who believe in life and laughter as tools for human development.</p>',
            'fr' => '<h2>À propos de nous</h2><p>Depuis 2007, la Fondation Teatromuseo de la marionnette et du clown promeut, diffuse et professionnalise ces arts de la scène au Chili grâce à une école de formation nationale et internationale, un musée spécialisé et une salle de théâtre proposant une programmation familiale permanente.</p><p>Nous sommes une équipe d’artistes et de professionnels de la gestion culturelle qui croyons en la vie et au rire comme outils de développement humain.</p>',
            'pt' => '<h2>Sobre Nós</h2><p>Desde 2007, a Fundação Teatromuseo do teatro de bonecos e do palhaço promove, difunde e profissionaliza essas artes da representação no Chile por meio de uma escola de formação nacional e internacional, um museu especializado e uma sala de teatro com programação familiar permanente.</p><p>Somos uma equipe de artistas e profissionais da gestão cultural que acredita na vida e no riso como ferramentas de desenvolvimento humano.</p>',
        ];

        foreach ($translations as $language => $content) {
            $this->saveTranslation($instanceId, $language, ['content' => $content]);
        }
    }

    private function normalizeCards(int $instanceId): void
    {
        $cardTypeResult = $this->db->table('cms_content_blocks')->select('id')->where('block_key', 'card_item')->get();
        $cardType = $cardTypeResult === false ? null : $cardTypeResult->getRowArray();
        if (! is_array($cardType)) {
            return;
        }

        $childrenResult = $this->db->table('cms_block_instances')
            ->where('parent_instance_id', $instanceId)
            ->where('block_id', (int) $cardType['id'])
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
        $children = $childrenResult === false ? [] : $childrenResult->getResultArray();

        foreach (array_slice($children, 2) as $child) {
            $this->db->table('cms_block_instances')->where('id', (int) $child['id'])->delete();
        }

        $this->mergeConfig($instanceId, ['columns_desktop' => '2', 'variant' => 'institutional']);
        $copy = [
            1 => [
                'en' => ['title' => 'Mission', 'description' => 'Strengthen, disseminate, and develop puppet and clown arts, enriching Chile’s cultural heritage and training new exponents through networks, schools, encounters, publications, and theatres.'],
                'fr' => ['title' => 'Mission', 'description' => 'Renforcer, diffuser et développer les arts de la marionnette et du clown, en enrichissant le patrimoine culturel du Chili et en formant de nouveaux artistes par des réseaux, écoles, rencontres, publications et salles de théâtre.'],
                'pt' => ['title' => 'Missão', 'description' => 'Fortalecer, difundir e desenvolver a arte do teatro de bonecos e do palhaço, enriquecendo o patrimônio cultural do Chile e formando novos artistas por meio de redes, escolas, encontros, publicações e salas de teatro.'],
            ],
            2 => [
                'en' => ['title' => 'Vision', 'description' => 'Establish the Teatromuseo Foundation as a space for research and development in these arts, so Valparaíso is recognized nationally and internationally as the cultural capital of puppetry and clowning.'],
                'fr' => ['title' => 'Vision', 'description' => 'Consolider la Fondation Teatromuseo comme un espace de recherche et de développement de ces arts, afin que Valparaíso soit reconnue nationalement et internationalement comme la capitale culturelle de la marionnette et du clown.'],
                'pt' => ['title' => 'Visão', 'description' => 'Consolidar a Fundação Teatromuseo como um espaço de pesquisa e desenvolvimento dessas artes, fazendo com que Valparaíso seja reconhecida nacional e internacionalmente como a capital cultural do teatro de bonecos e do palhaço.'],
            ],
        ];

        foreach (array_slice($children, 0, 2) as $index => $child) {
            foreach ($copy[$index + 1] ?? [] as $language => $data) {
                $this->saveTranslation((int) $child['id'], $language, $data);
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function translation(int $instanceId, string $language): ?array
    {
        $result = $this->db->table('cms_block_instance_translations t')
            ->select('t.id, t.block_data')
            ->join('cms_languages l', 'l.id = t.language_id')
            ->where('t.instance_id', $instanceId)
            ->where('l.code', $language)
            ->get();
        $row = $result === false ? null : $result->getRowArray();
        if (! is_array($row)) {
            return null;
        }

        $data = json_decode((string) ($row['block_data'] ?? '{}'), true);

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $data */
    private function saveTranslation(int $instanceId, string $language, array $data): void
    {
        $languageResult = $this->db->table('cms_languages')->select('id')->where('code', $language)->get();
        $languageRow = $languageResult === false ? null : $languageResult->getRowArray();
        if (! is_array($languageRow)) {
            return;
        }

        $languageId = (int) $languageRow['id'];
        $existingResult = $this->db->table('cms_block_instance_translations')
            ->where('instance_id', $instanceId)
            ->where('language_id', $languageId)
            ->get();
        $existing = $existingResult === false ? null : $existingResult->getRowArray();
        $current = is_array($existing) ? json_decode((string) ($existing['block_data'] ?? '{}'), true) : [];
        $payload = [
            'block_data' => json_encode(array_merge(is_array($current) ? $current : [], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'is_published' => 1,
        ];

        if (is_array($existing)) {
            $this->db->table('cms_block_instance_translations')->where('id', (int) $existing['id'])->update($payload);
        } else {
            $this->db->table('cms_block_instance_translations')->insert($payload + ['instance_id' => $instanceId, 'language_id' => $languageId]);
        }
    }

    /** @param array<string, mixed> $config */
    private function mergeConfig(int $instanceId, array $config): void
    {
        $result = $this->db->table('cms_block_instances')->select('block_config')->where('id', $instanceId)->get();
        $row = $result === false ? null : $result->getRowArray();
        $current = is_array($row) ? json_decode((string) ($row['block_config'] ?? '{}'), true) : [];
        $this->db->table('cms_block_instances')->where('id', $instanceId)->update([
            'block_config' => json_encode(array_merge(is_array($current) ? $current : [], $config), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }
}
