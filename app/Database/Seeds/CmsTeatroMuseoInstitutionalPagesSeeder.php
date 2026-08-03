<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

final class CmsTeatroMuseoInstitutionalPagesSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $languages = $this->languageIds();
        $blockIds = $this->blockIds([
            'page_header',
            'hero_banner',
            'rich_text',
            'cards_grid',
            'card_item',
            'timeline',
            'timeline_item',
            'image',
            'metrics_grid',
            'metric_item',
            'cards_slider',
            'slide_card',
            'asset_showcase',
            'asset_item',
            'accordion',
            'accordion_item',
            'cta',
        ]);

        if (! isset($languages['es'], $languages['en'], $languages['fr'], $languages['pt'])) {
            echo "CmsTeatroMuseoInstitutionalPagesSeeder: missing languages; skipping.\n";

            return;
        }

        if (! isset($blockIds['page_header'], $blockIds['hero_banner'], $blockIds['rich_text'], $blockIds['cards_grid'], $blockIds['card_item'], $blockIds['timeline'], $blockIds['timeline_item'], $blockIds['image'], $blockIds['metrics_grid'], $blockIds['metric_item'], $blockIds['cards_slider'], $blockIds['slide_card'], $blockIds['asset_showcase'], $blockIds['asset_item'], $blockIds['accordion'], $blockIds['accordion_item'], $blockIds['cta'])) {
            echo "CmsTeatroMuseoInstitutionalPagesSeeder: missing block types; skipping.\n";

            return;
        }

        $this->seedAboutPage($languages, $blockIds);
        $this->seedHistoryPage($languages, $blockIds);
    }

    /**
     * @param array<string, int> $languages
     * @param array<string, int> $blockIds
     */
    private function seedAboutPage(array $languages, array $blockIds): void
    {
        $pageId = $this->upsertPage(['nosotros', 'about', 'a-propos', 'sobre-nos'], 25, 'about');

        $this->upsertPageTranslations($pageId, [
            'es' => [
                'slug' => 'nosotros',
                'title' => 'Quiénes Somos',
                'excerpt' => 'Conoce la misión, visión y equipo detrás de TeatroMuseo.',
                'meta_title' => 'Quiénes Somos | TeatroMuseo',
                'meta_description' => 'Descubre cómo TeatroMuseo organiza su archivo, programación y mediación.',
            ],
            'en' => [
                'slug' => 'about',
                'title' => 'About Us',
                'excerpt' => 'Meet the mission, vision, and team behind TeatroMuseo.',
                'meta_title' => 'About Us | TeatroMuseo',
                'meta_description' => 'See how TeatroMuseo organizes its archive, programming, and mediation.',
            ],
            'fr' => [
                'slug' => 'a-propos',
                'title' => 'À propos',
                'excerpt' => 'Découvrez la mission, la vision et l’équipe derrière TeatroMuseo.',
                'meta_title' => 'À propos | TeatroMuseo',
                'meta_description' => 'Découvrez comment TeatroMuseo organise son archive, sa programmation et sa médiation.',
            ],
            'pt' => [
                'slug' => 'sobre-nos',
                'title' => 'Sobre Nós',
                'excerpt' => 'Conheça a missão, a visão e a equipe por trás do TeatroMuseo.',
                'meta_title' => 'Sobre Nós | TeatroMuseo',
                'meta_description' => 'Veja como o TeatroMuseo organiza seu arquivo, programação e mediação.',
            ],
        ], $languages);

        $this->upsertBlock($pageId, $blockIds, 'page_header', 1, [
            'bg_color' => 'bg-slate-100',
            'css_class' => '',
        ], [
            'es' => [
                'heading' => 'Quiénes Somos',
                'subheading' => 'Una plataforma editorial para memoria, programación y mediación.',
                'breadcrumb_label' => 'Inicio',
                'breadcrumb_url' => '/',
            ],
            'en' => [
                'heading' => 'About Us',
                'subheading' => 'An editorial platform for memory, programming, and mediation.',
                'breadcrumb_label' => 'Home',
                'breadcrumb_url' => '/',
            ],
            'fr' => [
                'heading' => 'À propos',
                'subheading' => 'Une plateforme éditoriale pour la mémoire, la programmation et la médiation.',
                'breadcrumb_label' => 'Accueil',
                'breadcrumb_url' => '/',
            ],
            'pt' => [
                'heading' => 'Sobre Nós',
                'subheading' => 'Uma plataforma editorial para memória, programação e mediação.',
                'breadcrumb_label' => 'Início',
                'breadcrumb_url' => '/',
            ],
        ], $languages);

        $this->upsertBlock($pageId, $blockIds, 'hero_banner', 2, [
            'image' => $this->mediaReference('https://picsum.photos/id/1041/1920/1080'),
            'css_class' => '',
            'text_color' => '#ffffff',
            'overlay_color' => 'rgba(15, 23, 42, 0.4)',
        ], [
            'es' => [
                'alt' => 'Archivo y equipo de TeatroMuseo trabajando en la colección',
                'heading' => 'Un archivo vivo para la memoria escénica',
                'subheading' => 'Programación, mediación y colecciones al servicio de artistas, investigadores y públicos.',
                'cta_label' => 'Conoce nuestra historia',
                'cta_url' => '/historia',
            ],
            'en' => [
                'alt' => 'TeatroMuseo team working on the archive collection',
                'heading' => 'A living archive for scenic memory',
                'subheading' => 'Programming, mediation, and collections in service of artists, researchers, and audiences.',
                'cta_label' => 'Learn our history',
                'cta_url' => '/history',
            ],
            'fr' => [
                'alt' => 'Équipe de TeatroMuseo travaillant sur la collection d’archives',
                'heading' => 'Une archive vivante pour la mémoire scénique',
                'subheading' => 'Programmation, médiation et collections au service des artistes, chercheurs et publics.',
                'cta_label' => 'Découvrir notre histoire',
                'cta_url' => '/histoire',
            ],
            'pt' => [
                'alt' => 'Equipe do TeatroMuseo trabalhando no acervo do arquivo',
                'heading' => 'Um arquivo vivo para a memória cênica',
                'subheading' => 'Programação, mediação e coleções a serviço de artistas, pesquisadores e públicos.',
                'cta_label' => 'Conheça nossa história',
                'cta_url' => '/nossa-historia',
            ],
        ], $languages);

        $this->upsertBlock($pageId, $blockIds, 'rich_text', 3, [
            'css_class' => '',
        ], [
            'es' => [
                'content' => <<<'HTML'
<p>TeatroMuseo reúne memoria, programación y mediación en un mismo espacio editorial. La idea es simple: que el sitio público muestre el archivo vivo del proyecto y ofrezca contenidos claros, navegables y siempre actualizados.</p>
<p>La plataforma conecta colecciones, páginas institucionales, noticias y recursos audiovisuales para que el sitio no solo informe, sino que también acerque a artistas, equipos y audiencias a una misma experiencia cultural.</p>
HTML,
            ],
            'en' => [
                'content' => <<<'HTML'
<p>TeatroMuseo brings memory, programming, and mediation into a single editorial space. The goal is simple: let the public site show the project's living archive and offer clear, navigable, always-up-to-date content.</p>
<p>The platform connects collections, institutional pages, news, and audiovisual resources so the site does more than inform. It brings artists, teams, and audiences together around a shared cultural experience.</p>
HTML,
            ],
            'fr' => [
                'content' => <<<'HTML'
<p>TeatroMuseo rassemble mémoire, programmation et médiation dans un même espace éditorial. L’objectif est simple : faire du site public l’archive vivante du projet et proposer des contenus clairs, navigables et toujours à jour.</p>
<p>La plateforme relie collections, pages institutionnelles, actualités et ressources audiovisuelles afin que le site ne se limite pas à informer. Il rapproche artistes, équipes et publics autour d’une expérience culturelle commune.</p>
HTML,
            ],
            'pt' => [
                'content' => <<<'HTML'
<p>TeatroMuseo reúne memória, programação e mediação em um único espaço editorial. O objetivo é simples: fazer o site público mostrar o arquivo vivo do projeto e oferecer conteúdos claros, navegáveis e sempre atualizados.</p>
<p>A plataforma conecta coleções, páginas institucionais, notícias e recursos audiovisuais para que o site não apenas informe. Ele aproxima artistas, equipes e públicos em torno de uma mesma experiência cultural.</p>
HTML,
            ],
        ], $languages);

        $cardsGridId = $this->upsertBlock($pageId, $blockIds, 'cards_grid', 4, [
            'columns_desktop' => '3',
            'variant' => 'bordered',
            'css_class' => '',
        ], [
            'es' => [],
            'en' => [],
            'fr' => [],
            'pt' => [],
        ], $languages);

        $this->seedChildBlocks($pageId, $cardsGridId, 'card_item', [
            [
                'sort_order' => 1,
                'config' => [],
                'es' => [
                    'title' => 'Misión',
                    'description' => 'Construir una presencia digital cuidada, accesible y fácil de mantener para TeatroMuseo.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'en' => [
                    'title' => 'Mission',
                    'description' => 'Build a careful, accessible, and easy-to-maintain digital presence for TeatroMuseo.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'fr' => [
                    'title' => 'Mission',
                    'description' => 'Construire une présence numérique soignée, accessible et facile à maintenir pour TeatroMuseo.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'pt' => [
                    'title' => 'Missão',
                    'description' => 'Construir uma presença digital cuidadosa, acessível e fácil de manter para o TeatroMuseo.',
                    'link_label' => '',
                    'link_url' => '',
                ],
            ],
            [
                'sort_order' => 2,
                'config' => [],
                'es' => [
                    'title' => 'Visión',
                    'description' => 'Ser un archivo vivo, multilingüe y confiable para la comunidad que sigue TeatroMuseo.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'en' => [
                    'title' => 'Vision',
                    'description' => 'Be a living, multilingual, and trusted archive for the community around TeatroMuseo.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'fr' => [
                    'title' => 'Vision',
                    'description' => 'Être une archive vivante, multilingue et fiable pour la communauté qui suit TeatroMuseo.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'pt' => [
                    'title' => 'Visão',
                    'description' => 'Ser um arquivo vivo, multilíngue e confiável para a comunidade que acompanha o TeatroMuseo.',
                    'link_label' => '',
                    'link_url' => '',
                ],
            ],
            [
                'sort_order' => 3,
                'config' => [],
                'es' => [
                    'title' => 'Comunidad',
                    'description' => 'Poner colecciones, noticias y documentos al servicio de artistas, investigadores y visitantes.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'en' => [
                    'title' => 'Community',
                    'description' => 'Put collections, news, and documents at the service of artists, researchers, and visitors.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'fr' => [
                    'title' => 'Communauté',
                    'description' => 'Mettre collections, actualités et documents au service des artistes, chercheurs et visiteurs.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'pt' => [
                    'title' => 'Comunidade',
                    'description' => 'Colocar coleções, notícias e documentos a serviço de artistas, pesquisadores e visitantes.',
                    'link_label' => '',
                    'link_url' => '',
                ],
            ],
        ], $blockIds, $languages);

        $testimonialsSliderId = $this->upsertBlock($pageId, $blockIds, 'cards_slider', 5, [
            'layout' => 'slider',
            'autoplay' => true,
            'interval' => 5000,
            'visible_count' => '1',
            'card_variant' => 'testimonial',
            'css_class' => '',
        ], [
            'es' => [],
            'en' => [],
            'fr' => [],
            'pt' => [],
        ], $languages);

        $this->seedChildBlocks($pageId, $testimonialsSliderId, 'slide_card', [
            [
                'sort_order' => 1,
                'config' => [],
                'es' => ['eyebrow' => 'Testimonio', 'body' => 'El archivo de TeatroMuseo me permitió reconstruir la historia de una compañía que ya no existe. Es un recurso invaluable para la investigación escénica.', 'meta_title' => 'Investigadora, Universidad de Chile', 'meta_description' => 'Usuaria del archivo', 'rating' => '5'],
                'en' => ['eyebrow' => 'Testimonial', 'body' => 'The TeatroMuseo archive let me reconstruct the history of a company that no longer exists. It is an invaluable resource for performing arts research.', 'meta_title' => 'Researcher, Universidad de Chile', 'meta_description' => 'Archive user', 'rating' => '5'],
                'fr' => ['eyebrow' => 'Témoignage', 'body' => 'Les archives de TeatroMuseo m’ont permis de reconstituer l’histoire d’une compagnie qui n’existe plus. C’est une ressource inestimable pour la recherche sur les arts de la scène.', 'meta_title' => 'Chercheuse, Universidad de Chile', 'meta_description' => 'Utilisatrice des archives', 'rating' => '5'],
                'pt' => ['eyebrow' => 'Depoimento', 'body' => 'O arquivo do TeatroMuseo me permitiu reconstruir a história de uma companhia que não existe mais. É um recurso valioso para a pesquisa cênica.', 'meta_title' => 'Pesquisadora, Universidad de Chile', 'meta_description' => 'Usuária do arquivo', 'rating' => '5'],
            ],
            [
                'sort_order' => 2,
                'config' => [],
                'es' => ['eyebrow' => 'Testimonio', 'body' => 'Colaborar con TeatroMuseo para digitalizar nuestro archivo institucional fue una experiencia impecable, con criterios claros de catalogación y mediación.', 'meta_title' => 'Compañía de Teatro Popular', 'meta_description' => 'Aliada institucional', 'rating' => '5'],
                'en' => ['eyebrow' => 'Testimonial', 'body' => 'Partnering with TeatroMuseo to digitize our institutional archive was a seamless experience, with clear cataloging and mediation criteria.', 'meta_title' => 'Compañía de Teatro Popular', 'meta_description' => 'Institutional partner', 'rating' => '5'],
                'fr' => ['eyebrow' => 'Témoignage', 'body' => 'Collaborer avec TeatroMuseo pour numériser nos archives institutionnelles a été une expérience sans faille, avec des critères clairs de catalogage et de médiation.', 'meta_title' => 'Compañía de Teatro Popular', 'meta_description' => 'Partenaire institutionnel', 'rating' => '5'],
                'pt' => ['eyebrow' => 'Depoimento', 'body' => 'Colaborar com o TeatroMuseo para digitalizar nosso arquivo institucional foi uma experiência impecável, com critérios claros de catalogação e mediação.', 'meta_title' => 'Compañía de Teatro Popular', 'meta_description' => 'Parceira institucional', 'rating' => '5'],
            ],
        ], $blockIds, $languages);

        $logoShowcaseId = $this->upsertBlock($pageId, $blockIds, 'asset_showcase', 6, [
            'layout' => 'marquee',
            'speed' => 'normal',
            'grayscale' => true,
            'css_class' => '',
        ], [
            'es' => [],
            'en' => [],
            'fr' => [],
            'pt' => [],
        ], $languages);

        $this->seedChildBlocks($pageId, $logoShowcaseId, 'asset_item', [
            [
                'sort_order' => 1,
                'config' => [],
                'es' => ['name' => 'Ministerio de las Culturas', 'link_url' => ''],
                'en' => ['name' => 'Ministerio de las Culturas', 'link_url' => ''],
                'fr' => ['name' => 'Ministerio de las Culturas', 'link_url' => ''],
                'pt' => ['name' => 'Ministerio de las Culturas', 'link_url' => ''],
            ],
            [
                'sort_order' => 2,
                'config' => [],
                'es' => ['name' => 'Consejo de la Cultura', 'link_url' => ''],
                'en' => ['name' => 'Consejo de la Cultura', 'link_url' => ''],
                'fr' => ['name' => 'Consejo de la Cultura', 'link_url' => ''],
                'pt' => ['name' => 'Consejo de la Cultura', 'link_url' => ''],
            ],
            [
                'sort_order' => 3,
                'config' => [],
                'es' => ['name' => 'Red de Archivos Escénicos', 'link_url' => ''],
                'en' => ['name' => 'Red de Archivos Escénicos', 'link_url' => ''],
                'fr' => ['name' => 'Red de Archivos Escénicos', 'link_url' => ''],
                'pt' => ['name' => 'Red de Archivos Escénicos', 'link_url' => ''],
            ],
        ], $blockIds, $languages);

        $faqId = $this->upsertBlock($pageId, $blockIds, 'accordion', 7, [
            'css_class' => '',
        ], [
            'es' => [],
            'en' => [],
            'fr' => [],
            'pt' => [],
        ], $languages);

        $this->seedChildBlocks($pageId, $faqId, 'accordion_item', [
            [
                'sort_order' => 1,
                'config' => ['is_open' => true],
                'es' => ['title' => '¿Cómo puedo consultar el archivo de TeatroMuseo?', 'content' => '<p>El catálogo público está disponible en el sitio, y el equipo de mediación puede orientarte para consultas de investigación más específicas a través del formulario de contacto.</p>'],
                'en' => ['title' => 'How can I consult the TeatroMuseo archive?', 'content' => '<p>The public catalog is available on the site, and the mediation team can guide you for more specific research inquiries through the contact form.</p>'],
                'fr' => ['title' => 'Comment puis-je consulter les archives de TeatroMuseo ?', 'content' => '<p>Le catalogue public est disponible sur le site, et l’équipe de médiation peut vous orienter pour des demandes de recherche plus spécifiques via le formulaire de contact.</p>'],
                'pt' => ['title' => 'Como posso consultar o arquivo do TeatroMuseo?', 'content' => '<p>O catálogo público está disponível no site, e a equipe de mediação pode orientá-lo para consultas de pesquisa mais específicas através do formulário de contato.</p>'],
            ],
            [
                'sort_order' => 2,
                'config' => ['is_open' => false],
                'es' => ['title' => '¿TeatroMuseo recibe donaciones de material de archivo?', 'content' => '<p>Sí. Escríbenos a través de contacto describiendo el material y nuestro equipo evaluará su incorporación siguiendo los criterios de catalogación vigentes.</p>'],
                'en' => ['title' => 'Does TeatroMuseo accept archival material donations?', 'content' => '<p>Yes. Write to us through the contact page describing the material and our team will evaluate its inclusion following current cataloging criteria.</p>'],
                'fr' => ['title' => 'TeatroMuseo accepte-t-il des dons de matériel d’archives ?', 'content' => '<p>Oui. Écrivez-nous via la page de contact en décrivant le matériel, et notre équipe évaluera son intégration selon les critères de catalogage en vigueur.</p>'],
                'pt' => ['title' => 'O TeatroMuseo recebe doações de material de arquivo?', 'content' => '<p>Sim. Escreva para nós através do contato descrevendo o material, e nossa equipe avaliará sua incorporação seguindo os critérios de catalogação vigentes.</p>'],
            ],
        ], $blockIds, $languages);

        $this->upsertBlock($pageId, $blockIds, 'cta', 8, [
            'variant' => 'blue',
            'css_class' => '',
        ], [
            'es' => [
                'heading' => '¿Quieres conocer más de TeatroMuseo?',
                'text' => 'Escríbenos y te orientamos sobre programación, archivo y contenidos editoriales.',
                'label' => 'Ir a contacto',
                'url' => '/contacto',
            ],
            'en' => [
                'heading' => 'Want to learn more about TeatroMuseo?',
                'text' => 'Write to us and we will help you with programming, archive, and editorial content.',
                'label' => 'Go to contact',
                'url' => '/contact',
            ],
            'fr' => [
                'heading' => 'Vous voulez en savoir plus sur TeatroMuseo ?',
                'text' => 'Écrivez-nous et nous vous guiderons sur la programmation, les archives et le contenu éditorial.',
                'label' => 'Aller au contact',
                'url' => '/contact',
            ],
            'pt' => [
                'heading' => 'Quer conhecer melhor o TeatroMuseo?',
                'text' => 'Escreva para nós e vamos orientar você sobre programação, arquivo e conteúdo editorial.',
                'label' => 'Ir para contato',
                'url' => '/contato',
            ],
        ], $languages);
    }

    /**
     * @param array<string, int> $languages
     * @param array<string, int> $blockIds
     */
    private function seedHistoryPage(array $languages, array $blockIds): void
    {
        $pageId = $this->upsertPage(['historia', 'history', 'histoire', 'nossa-historia'], 26, 'history');

        $this->upsertPageTranslations($pageId, [
            'es' => [
                'slug' => 'historia',
                'title' => 'Historia',
                'excerpt' => 'Recorre los hitos que han dado forma a TeatroMuseo.',
                'meta_title' => 'Historia | TeatroMuseo',
                'meta_description' => 'Conoce la evolución editorial y cultural de TeatroMuseo.',
            ],
            'en' => [
                'slug' => 'history',
                'title' => 'History',
                'excerpt' => 'Walk through the milestones that shaped TeatroMuseo.',
                'meta_title' => 'History | TeatroMuseo',
                'meta_description' => 'Learn about the editorial and cultural evolution of TeatroMuseo.',
            ],
            'fr' => [
                'slug' => 'histoire',
                'title' => 'Histoire',
                'excerpt' => 'Parcourez les étapes qui ont façonné TeatroMuseo.',
                'meta_title' => 'Histoire | TeatroMuseo',
                'meta_description' => 'Découvrez l’évolution éditoriale et culturelle de TeatroMuseo.',
            ],
            'pt' => [
                'slug' => 'nossa-historia',
                'title' => 'Nossa História',
                'excerpt' => 'Percorra os marcos que deram forma ao TeatroMuseo.',
                'meta_title' => 'Nossa História | TeatroMuseo',
                'meta_description' => 'Conheça a evolução editorial e cultural do TeatroMuseo.',
            ],
        ], $languages);

        $this->upsertBlock($pageId, $blockIds, 'page_header', 1, [
            'bg_color' => 'bg-slate-100',
            'css_class' => '',
        ], [
            'es' => [
                'heading' => 'Historia',
                'subheading' => 'Una cronología breve del archivo, la programación y la plataforma.',
                'breadcrumb_label' => 'Inicio',
                'breadcrumb_url' => '/',
            ],
            'en' => [
                'heading' => 'History',
                'subheading' => 'A brief timeline of the archive, programming, and platform.',
                'breadcrumb_label' => 'Home',
                'breadcrumb_url' => '/',
            ],
            'fr' => [
                'heading' => 'Histoire',
                'subheading' => 'Une chronologie brève de l’archive, de la programmation et de la plateforme.',
                'breadcrumb_label' => 'Accueil',
                'breadcrumb_url' => '/',
            ],
            'pt' => [
                'heading' => 'Nossa História',
                'subheading' => 'Uma cronologia breve do arquivo, da programação e da plataforma.',
                'breadcrumb_label' => 'Início',
                'breadcrumb_url' => '/',
            ],
        ], $languages);

        $this->upsertBlock($pageId, $blockIds, 'rich_text', 2, [
            'css_class' => '',
        ], [
            'es' => [
                'content' => <<<'HTML'
<p>TeatroMuseo nace de una práctica de archivo y programación que conecta memoria, escena y mediación. Esta página resume cómo el proyecto fue ordenando sus contenidos para que el sitio público refleje con claridad la vida de la institución.</p>
<p>Cada hito marca una decisión editorial: documentar mejor, publicar con más orden y cuidar la experiencia de quienes visitan el sitio en cualquiera de sus idiomas.</p>
HTML,
            ],
            'en' => [
                'content' => <<<'HTML'
<p>TeatroMuseo grew out of an archive and programming practice that connects memory, stage work, and mediation. This page summarizes how the project organized its content so the public site can clearly reflect the life of the institution.</p>
<p>Each milestone marks an editorial decision: document better, publish with more clarity, and care for the experience of anyone visiting the site in any of its languages.</p>
HTML,
            ],
            'fr' => [
                'content' => <<<'HTML'
<p>TeatroMuseo est né d’une pratique d’archive et de programmation qui relie mémoire, scène et médiation. Cette page résume la façon dont le projet a organisé ses contenus afin que le site public reflète clairement la vie de l’institution.</p>
<p>Chaque étape marque une décision éditoriale : mieux documenter, publier avec plus de clarté et soigner l’expérience des personnes qui visitent le site dans l’une de ses langues.</p>
HTML,
            ],
            'pt' => [
                'content' => <<<'HTML'
<p>TeatroMuseo nasceu de uma prática de arquivo e programação que conecta memória, cena e mediação. Esta página resume como o projeto organizou seus conteúdos para que o site público reflita com clareza a vida da instituição.</p>
<p>Cada marco representa uma decisão editorial: documentar melhor, publicar com mais clareza e cuidar da experiência de quem visita o site em qualquer um dos seus idiomas.</p>
HTML,
            ],
        ], $languages);

        $this->upsertBlock($pageId, $blockIds, 'image', 3, [
            'image' => $this->mediaReference('https://picsum.photos/id/1019/800/600'),
            'aspect_ratio' => '16/9',
            'css_class' => '',
        ], [
            'es' => [
                'alt' => 'Imagen histórica del archivo de TeatroMuseo',
                'caption' => 'Los primeros pasos de una historia que sigue escribiéndose.',
            ],
            'en' => [
                'alt' => 'Historical image from the TeatroMuseo archive',
                'caption' => 'The first steps of a story that continues to be written.',
            ],
            'fr' => [
                'alt' => 'Image historique des archives de TeatroMuseo',
                'caption' => 'Les premiers pas d’une histoire qui continue de s’écrire.',
            ],
            'pt' => [
                'alt' => 'Imagem histórica do arquivo do TeatroMuseo',
                'caption' => 'Os primeiros passos de uma história que continua sendo escrita.',
            ],
        ], $languages);

        $timelineId = $this->upsertBlock($pageId, $blockIds, 'timeline', 4, [
            'layout' => 'alternating',
            'css_class' => 'bg-slate-50/50',
        ], [
            'es' => [
                'section_title' => 'Trayectoria',
                'description' => 'Un recorrido breve por los hitos que ordenan la memoria de TeatroMuseo.',
            ],
            'en' => [
                'section_title' => 'Timeline',
                'description' => 'A brief journey through the milestones that organize TeatroMuseo’s memory.',
            ],
            'fr' => [
                'section_title' => 'Chronologie',
                'description' => 'Un bref parcours des étapes qui organisent la mémoire de TeatroMuseo.',
            ],
            'pt' => [
                'section_title' => 'Cronologia',
                'description' => 'Um breve percurso pelos marcos que organizam a memória do TeatroMuseo.',
            ],
        ], $languages);

        $this->seedChildBlocks($pageId, $timelineId, 'timeline_item', [
            [
                'sort_order' => 1,
                'config' => [],
                'es' => [
                    'date_label' => '2012',
                    'title' => 'Nace el archivo inicial',
                    'description' => 'Los primeros materiales editoriales y curatoriales se ordenan para documentar el proyecto desde sus inicios.',
                ],
                'en' => [
                    'date_label' => '2012',
                    'title' => 'The initial archive takes shape',
                    'description' => 'The first editorial and curatorial materials are organized to document the project from the start.',
                ],
                'fr' => [
                    'date_label' => '2012',
                    'title' => 'L’archive initiale prend forme',
                    'description' => 'Les premiers matériaux éditoriaux et curatoriaux sont organisés pour documenter le projet dès ses débuts.',
                ],
                'pt' => [
                    'date_label' => '2012',
                    'title' => 'O arquivo inicial ganha forma',
                    'description' => 'Os primeiros materiais editoriais e curatoriais são organizados para documentar o projeto desde o início.',
                ],
            ],
            [
                'sort_order' => 2,
                'config' => [],
                'es' => [
                    'date_label' => '2016',
                    'title' => 'La programación llega a más públicos',
                    'description' => 'Las colecciones y la mediación empiezan a dialogar con nuevos públicos y formatos de difusión.',
                ],
                'en' => [
                    'date_label' => '2016',
                    'title' => 'Programming reaches broader audiences',
                    'description' => 'Collections and mediation start reaching new audiences and new formats of dissemination.',
                ],
                'fr' => [
                    'date_label' => '2016',
                    'title' => 'La programmation touche de nouveaux publics',
                    'description' => 'Les collections et la médiation commencent à dialoguer avec de nouveaux publics et de nouveaux formats de diffusion.',
                ],
                'pt' => [
                    'date_label' => '2016',
                    'title' => 'A programação chega a mais públicos',
                    'description' => 'As coleções e a mediação começam a dialogar com novos públicos e formatos de divulgação.',
                ],
            ],
            [
                'sort_order' => 3,
                'config' => [],
                'es' => [
                    'date_label' => '2020',
                    'title' => 'La plataforma se vuelve digital',
                    'description' => 'El trabajo editorial incorpora flujos digitales para organizar páginas, entradas y contenidos transversales.',
                ],
                'en' => [
                    'date_label' => '2020',
                    'title' => 'The platform goes digital',
                    'description' => 'Editorial work adopts digital workflows to organize pages, entries, and cross-cutting content.',
                ],
                'fr' => [
                    'date_label' => '2020',
                    'title' => 'La plateforme devient numérique',
                    'description' => 'Le travail éditorial adopte des flux numériques pour organiser les pages, les entrées et les contenus transversaux.',
                ],
                'pt' => [
                    'date_label' => '2020',
                    'title' => 'A plataforma se torna digital',
                    'description' => 'O trabalho editorial adota fluxos digitais para organizar páginas, entradas e conteúdos transversais.',
                ],
            ],
            [
                'sort_order' => 4,
                'config' => [],
                'es' => [
                    'date_label' => '2024',
                    'title' => 'Se unifican colecciones e idiomas',
                    'description' => 'El CMS consolida el catálogo, los menús y los idiomas en una base editorial coherente.',
                ],
                'en' => [
                    'date_label' => '2024',
                    'title' => 'Collections and languages are unified',
                    'description' => 'The CMS brings the catalog, menus, and languages together into one coherent editorial base.',
                ],
                'fr' => [
                    'date_label' => '2024',
                    'title' => 'Les collections et les langues sont unifiées',
                    'description' => 'Le CMS réunit le catalogue, les menus et les langues dans une base éditoriale cohérente.',
                ],
                'pt' => [
                    'date_label' => '2024',
                    'title' => 'Coleções e idiomas são unificados',
                    'description' => 'O CMS reúne o catálogo, os menus e os idiomas em uma base editorial coerente.',
                ],
            ],
        ], $blockIds, $languages);

        $statsSectionId = $this->upsertBlock($pageId, $blockIds, 'metrics_grid', 5, [
            'variant' => 'dark',
            'css_class' => '',
        ], [
            'es' => [],
            'en' => [],
            'fr' => [],
            'pt' => [],
        ], $languages);

        $this->seedChildBlocks($pageId, $statsSectionId, 'metric_item', [
            [
                'sort_order' => 1,
                'config' => [],
                'es' => ['number' => '2012', 'label' => 'Año de fundación', 'description' => 'Inicio formal del archivo.', 'source_label' => '', 'icon' => 'calendar'],
                'en' => ['number' => '2012', 'label' => 'Year founded', 'description' => 'Formal start of the archive.', 'source_label' => '', 'icon' => 'calendar'],
                'fr' => ['number' => '2012', 'label' => 'Année de fondation', 'description' => 'Début officiel des archives.', 'source_label' => '', 'icon' => 'calendar'],
                'pt' => ['number' => '2012', 'label' => 'Ano de fundação', 'description' => 'Início formal do arquivo.', 'source_label' => '', 'icon' => 'calendar'],
            ],
            [
                'sort_order' => 2,
                'config' => [],
                'es' => ['number' => '10', 'suffix' => '+', 'label' => 'Años de trayectoria', 'description' => 'Programación editorial continua.', 'source_label' => '', 'icon' => 'clock'],
                'en' => ['number' => '10', 'suffix' => '+', 'label' => 'Years of history', 'description' => 'Continuous editorial programming.', 'source_label' => '', 'icon' => 'clock'],
                'fr' => ['number' => '10', 'suffix' => '+', 'label' => 'Années d’existence', 'description' => 'Programmation éditoriale continue.', 'source_label' => '', 'icon' => 'clock'],
                'pt' => ['number' => '10', 'suffix' => '+', 'label' => 'Anos de trajetória', 'description' => 'Programação editorial contínua.', 'source_label' => '', 'icon' => 'clock'],
            ],
            [
                'sort_order' => 3,
                'config' => [],
                'es' => ['number' => '9', 'label' => 'Colecciones activas', 'description' => 'Catálogo editorial disponible al público.', 'source_label' => '', 'icon' => 'briefcase'],
                'en' => ['number' => '9', 'label' => 'Active collections', 'description' => 'Editorial catalog available to the public.', 'source_label' => '', 'icon' => 'briefcase'],
                'fr' => ['number' => '9', 'label' => 'Collections actives', 'description' => 'Catalogue éditorial accessible au public.', 'source_label' => '', 'icon' => 'briefcase'],
                'pt' => ['number' => '9', 'label' => 'Coleções ativas', 'description' => 'Catálogo editorial disponível ao público.', 'source_label' => '', 'icon' => 'briefcase'],
            ],
            [
                'sort_order' => 4,
                'config' => [],
                'es' => ['number' => '4', 'label' => 'Idiomas disponibles', 'description' => 'Contenido publicado en español, inglés, francés y portugués.', 'source_label' => '', 'icon' => 'users'],
                'en' => ['number' => '4', 'label' => 'Languages available', 'description' => 'Content published in Spanish, English, French, and Portuguese.', 'source_label' => '', 'icon' => 'users'],
                'fr' => ['number' => '4', 'label' => 'Langues disponibles', 'description' => 'Contenu publié en espagnol, anglais, français et portugais.', 'source_label' => '', 'icon' => 'users'],
                'pt' => ['number' => '4', 'label' => 'Idiomas disponíveis', 'description' => 'Conteúdo publicado em espanhol, inglês, francês e português.', 'source_label' => '', 'icon' => 'users'],
            ],
        ], $blockIds, $languages);

        $this->upsertBlock($pageId, $blockIds, 'cta', 6, [
            'variant' => 'blue',
            'css_class' => '',
        ], [
            'es' => [
                'heading' => 'La historia sigue creciendo',
                'text' => 'Si necesitas sumar contenido, revisar una fecha o actualizar un hito, el CMS está listo para seguir creciendo con TeatroMuseo.',
                'label' => 'Ir a contacto',
                'url' => '/contacto',
            ],
            'en' => [
                'heading' => 'The story keeps growing',
                'text' => 'If you need to add content, review a date, or update a milestone, the CMS is ready to keep growing with TeatroMuseo.',
                'label' => 'Go to contact',
                'url' => '/contact',
            ],
            'fr' => [
                'heading' => 'L’histoire continue de grandir',
                'text' => 'Si vous devez ajouter du contenu, revoir une date ou mettre à jour une étape, le CMS est prêt à continuer de grandir avec TeatroMuseo.',
                'label' => 'Aller au contact',
                'url' => '/contact',
            ],
            'pt' => [
                'heading' => 'A história continua crescendo',
                'text' => 'Se você precisar adicionar conteúdo, revisar uma data ou atualizar um marco, o CMS está pronto para continuar crescendo com o TeatroMuseo.',
                'label' => 'Ir para contato',
                'url' => '/contato',
            ],
        ], $languages);
    }

    /**
     * @param array<int, string> $lookupSlugs
     */
    private function upsertPage(array $lookupSlugs, int $sortOrder, string $pageKey): int
    {
        $existing = $this->db->table('cms_pages')
            ->select('cms_pages.id')
            ->join('cms_page_translations', 'cms_page_translations.page_id = cms_pages.id')
            ->where('cms_pages.deleted_at IS NULL', null, false)
            ->whereIn('cms_page_translations.slug', $lookupSlugs)
            ->orderBy('cms_pages.id', 'ASC')
            ->get()
            ->getRowArray();

        $payload = [
            'page_type' => 'generic',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'scheduled_at' => null,
            'sort_order' => $sortOrder,
            'sitemap_priority' => '0.6',
            'sitemap_changefreq' => 'monthly',
            'is_in_sitemap' => 1,
            'deleted_at' => null,
        ];

        if ($pageKey === 'history') {
            $payload['sitemap_priority'] = '0.5';
            $payload['sitemap_changefreq'] = 'yearly';
        }

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $pageId = $this->createRecord('cms_pages', $payload);
        if ($pageId === null) {
            throw new \RuntimeException(sprintf('CmsTeatroMuseoInstitutionalPagesSeeder: unable to seed %s page.', $pageKey));
        }

        return $pageId;
    }

    /**
     * @param array<string, array<string, string>> $translations
     * @param array<string, int> $languages
     */
    private function upsertPageTranslations(int $pageId, array $translations, array $languages): void
    {
        foreach ($translations as $languageCode => $translation) {
            $languageId = $languages[$languageCode] ?? null;
            if ($languageId === null) {
                continue;
            }

            $slug = (string) ($translation['slug'] ?? '');
            if ($slug !== '') {
                $conflict = $this->db->table('cms_page_translations')
                    ->where('language_id', $languageId)
                    ->where('slug', $slug)
                    ->get()
                    ->getRowArray();

                if ($conflict !== null && (int) $conflict['page_id'] !== $pageId) {
                    continue;
                }
            }

            $this->upsertRecord('cms_page_translations', [
                'page_id' => $pageId,
                'language_id' => $languageId,
            ], $translation);
        }
    }

    /**
     * @param array<string, int> $blockIds
     * @param array<string, array<string, mixed>> $translations
     * @param array<string, int> $languages
     */
    private function upsertBlock(
        int $pageId,
        array $blockIds,
        string $blockKey,
        int $sortOrder,
        array $config,
        array $translations,
        array $languages
    ): int {
        $blockId = $blockIds[$blockKey] ?? null;
        if ($blockId === null) {
            return 0;
        }

        $instanceId = $this->upsertRecord('cms_block_instances', [
            'block_id' => $blockId,
            'owner_type' => 'page',
            'owner_id' => $pageId,
            'parent_instance_id' => null,
            'sort_order' => $sortOrder,
        ], [
            'column_index' => null,
            'is_active' => 1,
            'block_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        foreach ($translations as $languageCode => $translation) {
            $languageId = $languages[$languageCode] ?? null;
            if ($languageId === null || ! is_array($translation) || $translation === []) {
                continue;
            }

            $this->upsertTranslation($instanceId, $languageId, $translation);
        }

        return $instanceId ?? 0;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, int> $blockIds
     * @param array<string, int> $languages
     */
    private function seedChildBlocks(int $pageId, int $parentInstanceId, string $blockKey, array $items, array $blockIds, array $languages): void
    {
        $blockId = $blockIds[$blockKey] ?? null;
        if ($blockId === null) {
            return;
        }

        foreach ($items as $item) {
            $instanceId = $this->upsertRecord('cms_block_instances', [
                'block_id' => $blockId,
                'owner_type' => 'page',
                'owner_id' => $pageId,
                'parent_instance_id' => $parentInstanceId,
                'sort_order' => (int) $item['sort_order'],
            ], [
                'column_index' => null,
                'is_active' => 1,
                'block_config' => json_encode(is_array($item['config'] ?? null) ? $item['config'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            if ($instanceId === null) {
                continue;
            }

            foreach (['es', 'en', 'fr', 'pt'] as $languageCode) {
                $languageId = $languages[$languageCode] ?? null;
                if ($languageId === null || ! isset($item[$languageCode]) || ! is_array($item[$languageCode])) {
                    continue;
                }

                $this->upsertTranslation($instanceId, $languageId, $item[$languageCode]);
            }
        }
    }

    /**
     * @param array<string, mixed> $blockData
     */
    private function upsertTranslation(int $instanceId, int $languageId, array $blockData): void
    {
        $this->upsertRecord('cms_block_instance_translations', [
            'instance_id' => $instanceId,
            'language_id' => $languageId,
        ], [
            'block_data' => json_encode($blockData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_published' => 1,
        ]);
    }

    /**
     * @param list<string> $keys
     * @return array<string, int>
     */
    private function blockIds(array $keys): array
    {
        $rows = $this->db->table('cms_content_blocks')
            ->whereIn('block_key', $keys)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['block_key']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * @param list<string> $codes
     * @return array<string, int>
     */
    private function languageIds(array $codes = ['es', 'en', 'fr', 'pt']): array
    {
        $rows = $this->db->table('cms_languages')
            ->whereIn('code', $codes)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['code']] = (int) $row['id'];
        }

        return $map;
    }
}
