<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

class SitePagesSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $langIds = $this->langIds(['es', 'en', 'fr', 'pt']);
        if (! isset($langIds['es'], $langIds['en'], $langIds['fr'], $langIds['pt'])) {
            echo "SitePagesSeeder: missing languages. Seed CmsLanguageSeeder first.\n";
            return;
        }

        $now = date('Y-m-d H:i:s');

        $homeId = $this->upsertPage('home', [
            'page_type'          => 'home',
            'status'             => 'published',
            'published_at'       => $now,
            'scheduled_at'       => null,
            'sort_order'         => 10,
            'sitemap_priority'   => '0.8',
            'sitemap_changefreq' => 'weekly',
            'is_in_sitemap'      => 1,
        ]);

        $this->upsertPageTranslation($homeId, $langIds['es'], [
            'slug'             => 'home',
            'title'            => 'Inicio',
            'excerpt'          => 'Página principal de TeatroMuseo.',
            'meta_title'       => 'Inicio | TeatroMuseo',
            'meta_description' => 'Bienvenido a TeatroMuseo.',
            'canonical_url'    => null,
            'robots'           => 'index, follow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($homeId, $langIds['en'], [
            'slug'             => 'home',
            'title'            => 'Home',
            'excerpt'          => 'The main landing page of TeatroMuseo.',
            'meta_title'       => 'Home | TeatroMuseo',
            'meta_description' => 'Welcome to TeatroMuseo.',
            'canonical_url'    => null,
            'robots'           => 'index, follow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($homeId, $langIds['fr'], [
            'slug'             => 'home',
            'title'            => 'Accueil',
            'excerpt'          => 'Page d’accueil principale de TeatroMuseo.',
            'meta_title'       => 'Accueil | TeatroMuseo',
            'meta_description' => 'Bienvenue sur TeatroMuseo.',
            'canonical_url'    => null,
            'robots'           => 'index, follow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($homeId, $langIds['pt'], [
            'slug'             => 'home',
            'title'            => 'Início',
            'excerpt'          => 'Página principal do TeatroMuseo.',
            'meta_title'       => 'Início | TeatroMuseo',
            'meta_description' => 'Bem-vindo ao TeatroMuseo.',
            'canonical_url'    => null,
            'robots'           => 'index, follow',
            'schema_data'      => null,
        ]);

        $contactId = $this->upsertPage('contact', [
            'page_type'          => 'contact',
            'status'             => 'published',
            'published_at'       => $now,
            'scheduled_at'       => null,
            'sort_order'         => 20,
            'sitemap_priority'   => '0.6',
            'sitemap_changefreq' => 'monthly',
            'is_in_sitemap'      => 1,
        ]);

        $this->upsertPageTranslation($contactId, $langIds['es'], [
            'slug'             => 'contacto',
            'title'            => 'Contacto',
            'excerpt'          => 'Formulario y datos de contacto de TeatroMuseo.',
            'meta_title'       => 'Contacto | TeatroMuseo',
            'meta_description' => 'Ponte en contacto con TeatroMuseo.',
            'canonical_url'    => null,
            'robots'           => 'index, follow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($contactId, $langIds['en'], [
            'slug'             => 'contact',
            'title'            => 'Contact',
            'excerpt'          => 'Contact form and details for TeatroMuseo.',
            'meta_title'       => 'Contact | TeatroMuseo',
            'meta_description' => 'Get in touch with TeatroMuseo.',
            'canonical_url'    => null,
            'robots'           => 'index, follow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($contactId, $langIds['fr'], [
            'slug'             => 'contact',
            'title'            => 'Contact',
            'excerpt'          => 'Formulaire et coordonnées de TeatroMuseo.',
            'meta_title'       => 'Contact | TeatroMuseo',
            'meta_description' => 'Contactez TeatroMuseo.',
            'canonical_url'    => null,
            'robots'           => 'index, follow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($contactId, $langIds['pt'], [
            'slug'             => 'contato',
            'title'            => 'Contato',
            'excerpt'          => 'Formulário e dados de contato do TeatroMuseo.',
            'meta_title'       => 'Contato | TeatroMuseo',
            'meta_description' => 'Entre em contato com o TeatroMuseo.',
            'canonical_url'    => null,
            'robots'           => 'index, follow',
            'schema_data'      => null,
        ]);

        // ── 404 Page ────────────────────────────────────────────────────────
        $notFoundId = $this->upsertPage('404', [
            'page_type'          => '404',
            'status'             => 'published',
            'published_at'       => $now,
            'scheduled_at'       => null,
            'sort_order'         => 900,
            'sitemap_priority'   => '0.1',
            'sitemap_changefreq' => 'never',
            'is_in_sitemap'      => 0,
        ]);

        $this->upsertPageTranslation($notFoundId, $langIds['es'], [
            'slug'             => '404',
            'title'            => 'Página no encontrada',
            'excerpt'          => 'La página que buscas no existe o ha sido movida.',
            'meta_title'       => 'Página no encontrada | TeatroMuseo',
            'meta_description' => 'La página solicitada no está disponible.',
            'canonical_url'    => null,
            'robots'           => 'noindex, nofollow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($notFoundId, $langIds['en'], [
            'slug'             => '404',
            'title'            => 'Page Not Found',
            'excerpt'          => 'The page you are looking for does not exist or has been moved.',
            'meta_title'       => 'Page Not Found | TeatroMuseo',
            'meta_description' => 'The requested page is not available.',
            'canonical_url'    => null,
            'robots'           => 'noindex, nofollow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($notFoundId, $langIds['fr'], [
            'slug'             => '404',
            'title'            => 'Page introuvable',
            'excerpt'          => 'La page recherchée n’existe pas ou a été déplacée.',
            'meta_title'       => 'Page introuvable | TeatroMuseo',
            'meta_description' => 'La page demandée n’est pas disponible.',
            'canonical_url'    => null,
            'robots'           => 'noindex, nofollow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($notFoundId, $langIds['pt'], [
            'slug'             => '404',
            'title'            => 'Página não encontrada',
            'excerpt'          => 'A página que você procura não existe ou foi movida.',
            'meta_title'       => 'Página não encontrada | TeatroMuseo',
            'meta_description' => 'A página solicitada não está disponível.',
            'canonical_url'    => null,
            'robots'           => 'noindex, nofollow',
            'schema_data'      => null,
        ]);

        // ── 500 Page ────────────────────────────────────────────────────────
        $internalErrorId = $this->upsertPage('500', [
            'page_type'          => '500',
            'status'             => 'published',
            'published_at'       => $now,
            'scheduled_at'       => null,
            'sort_order'         => 910,
            'sitemap_priority'   => '0.1',
            'sitemap_changefreq' => 'never',
            'is_in_sitemap'      => 0,
        ]);

        $this->upsertPageTranslation($internalErrorId, $langIds['es'], [
            'slug'             => '500',
            'title'            => 'Error interno del servidor',
            'excerpt'          => 'Ha ocurrido un error inesperado. Estamos trabajando para solucionarlo.',
            'meta_title'       => 'Error interno | TeatroMuseo',
            'meta_description' => 'Error interno en el servidor.',
            'canonical_url'    => null,
            'robots'           => 'noindex, nofollow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($internalErrorId, $langIds['en'], [
            'slug'             => '500',
            'title'            => 'Internal Server Error',
            'excerpt'          => 'An unexpected error has occurred. We are working to fix it.',
            'meta_title'       => 'Internal Server Error | TeatroMuseo',
            'meta_description' => 'Internal server error occurred.',
            'canonical_url'    => null,
            'robots'           => 'noindex, nofollow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($internalErrorId, $langIds['fr'], [
            'slug'             => '500',
            'title'            => 'Erreur interne du serveur',
            'excerpt'          => 'Une erreur inattendue est survenue. Nous travaillons à la corriger.',
            'meta_title'       => 'Erreur interne | TeatroMuseo',
            'meta_description' => 'Une erreur interne du serveur est survenue.',
            'canonical_url'    => null,
            'robots'           => 'noindex, nofollow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($internalErrorId, $langIds['pt'], [
            'slug'             => '500',
            'title'            => 'Erro interno do servidor',
            'excerpt'          => 'Ocorreu um erro inesperado. Estamos trabalhando para corrigi-lo.',
            'meta_title'       => 'Erro interno | TeatroMuseo',
            'meta_description' => 'Ocorreu um erro interno no servidor.',
            'canonical_url'    => null,
            'robots'           => 'noindex, nofollow',
            'schema_data'      => null,
        ]);

        // ── Maintenance Page ────────────────────────────────────────────────
        $maintenanceId = $this->upsertPage('maintenance', [
            'page_type'          => 'maintenance',
            'status'             => 'published',
            'published_at'       => $now,
            'scheduled_at'       => null,
            'sort_order'         => 920,
            'sitemap_priority'   => '0.1',
            'sitemap_changefreq' => 'never',
            'is_in_sitemap'      => 0,
        ]);

        $this->upsertPageTranslation($maintenanceId, $langIds['es'], [
            'slug'             => 'mantenimiento',
            'title'            => 'Sitio en mantenimiento',
            'excerpt'          => 'Estamos realizando tareas de mantenimiento programado. Volveremos pronto.',
            'meta_title'       => 'Mantenimiento | TeatroMuseo',
            'meta_description' => 'El sitio web se encuentra en mantenimiento temporal.',
            'canonical_url'    => null,
            'robots'           => 'noindex, nofollow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($maintenanceId, $langIds['en'], [
            'slug'             => 'maintenance',
            'title'            => 'Site Under Maintenance',
            'excerpt'          => 'We are currently undergoing scheduled maintenance. We will be back shortly.',
            'meta_title'       => 'Maintenance | TeatroMuseo',
            'meta_description' => 'The website is temporarily offline for maintenance.',
            'canonical_url'    => null,
            'robots'           => 'noindex, nofollow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($maintenanceId, $langIds['fr'], [
            'slug'             => 'maintenance',
            'title'            => 'Site en maintenance',
            'excerpt'          => 'Nous effectuons une maintenance planifiée. Nous revenons bientôt.',
            'meta_title'       => 'Maintenance | TeatroMuseo',
            'meta_description' => 'Le site web est temporairement indisponible pour maintenance.',
            'canonical_url'    => null,
            'robots'           => 'noindex, nofollow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($maintenanceId, $langIds['pt'], [
            'slug'             => 'maintenance',
            'title'            => 'Site em manutenção',
            'excerpt'          => 'Estamos realizando manutenção programada. Voltaremos em breve.',
            'meta_title'       => 'Manutenção | TeatroMuseo',
            'meta_description' => 'O site está temporariamente fora do ar para manutenção.',
            'canonical_url'    => null,
            'robots'           => 'noindex, nofollow',
            'schema_data'      => null,
        ]);
    }

    /**
     * @param array<int, string> $codes
     * @return array<string, int>
     */
    private function langIds(array $codes): array
    {
        $rows = $this->db->table('cms_languages')
            ->whereIn('code', $codes)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['code']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $pageData
     */
    private function upsertPage(string $pageType, array $pageData): int
    {
        $pageId = $this->upsertRecord('cms_pages', [
            'page_type'  => $pageType,
            'deleted_at' => null,
        ], array_merge($pageData, [
            'page_type'  => $pageType,
            'deleted_at' => null,
        ]));

        if ($pageId === null) {
            throw new \RuntimeException(sprintf('SitePagesSeeder: unable to seed page "%s".', $pageType));
        }

        return $pageId;
    }

    /**
     * @param array<string, mixed> $translationData
     */
    private function upsertPageTranslation(int $pageId, int $languageId, array $translationData): void
    {
        $slug = (string) ($translationData['slug'] ?? '');
        if ($slug !== '') {
            $slugConflict = $this->db->table('cms_page_translations')
                ->where('language_id', $languageId)
                ->where('slug', $slug)
                ->get()
                ->getRowArray();
            if ($slugConflict !== null && (int) $slugConflict['page_id'] !== $pageId) {
                // A slug owned by another page is editorial data; never steal it.
                return;
            }
        }

        $this->upsertRecord('cms_page_translations', [
            'page_id'     => $pageId,
            'language_id' => $languageId,
        ], $translationData);
    }
}
