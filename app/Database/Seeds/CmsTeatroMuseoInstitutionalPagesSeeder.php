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
            'hero_slider',
            'slide_banner',
            'rich_text',
            'cards_grid',
            'card_item',
            'timeline',
            'timeline_item',
            'image',
            'metrics_grid',
            'metric_item',
            'cta',
            'team_grid',
        ]);

        if (! isset($languages['es'], $languages['en'], $languages['fr'], $languages['pt'])) {
            echo "CmsTeatroMuseoInstitutionalPagesSeeder: missing languages; skipping.\n";

            return;
        }

        if (! isset($blockIds['page_header'], $blockIds['hero_slider'], $blockIds['slide_banner'], $blockIds['rich_text'], $blockIds['cards_grid'], $blockIds['card_item'], $blockIds['timeline'], $blockIds['timeline_item'], $blockIds['image'], $blockIds['metrics_grid'], $blockIds['metric_item'], $blockIds['cta'], $blockIds['team_grid'])) {
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
            ],
            'en' => [
                'heading' => 'About Us',
                'subheading' => 'An editorial platform for memory, programming, and mediation.',
                'breadcrumb_label' => 'Home',
            ],
            'fr' => [
                'heading' => 'À propos',
                'subheading' => 'Une plateforme éditoriale pour la mémoire, la programmation et la médiation.',
                'breadcrumb_label' => 'Accueil',
            ],
            'pt' => [
                'heading' => 'Sobre Nós',
                'subheading' => 'Uma plataforma editorial para memória, programação e mediação.',
                'breadcrumb_label' => 'Início',
            ],
        ], $languages);

        $heroSliderId = $this->upsertBlock($pageId, $blockIds, 'hero_slider', 2, [
            'autoplay' => true,
            'interval' => 5000,
            'transition' => 'fade',
            'overlay_opacity' => '20',
            'caption_position' => 'below',
            'controls_position' => 'below',
            'css_class' => '',
        ], [
            'es' => [],
            'en' => [],
            'fr' => [],
            'pt' => [],
        ], $languages);

        $this->seedChildBlocks($pageId, $heroSliderId, 'slide_banner', [
            [
                'sort_order' => 1,
                'config' => [
                    'image' => $this->mediaReference('https://picsum.photos/id/1041/1920/1080'),
                    'navigation_mode' => 'none',
                    'text_color' => '#0f172a',
                    'overlay_color' => 'rgba(255, 255, 255, 0.0)',
                ],
                'es' => ['heading' => 'Museo', 'subtitle' => '', 'cta_label' => 'Ver más', 'external_url' => ''],
                'en' => ['heading' => 'Museum', 'subtitle' => '', 'cta_label' => 'Learn more', 'external_url' => ''],
                'fr' => ['heading' => 'Musée', 'subtitle' => '', 'cta_label' => 'Voir plus', 'external_url' => ''],
                'pt' => ['heading' => 'Museu', 'subtitle' => '', 'cta_label' => 'Ver mais', 'external_url' => ''],
            ],
        ], $blockIds, $languages);

        $this->upsertBlock($pageId, $blockIds, 'rich_text', 3, [
            'css_class' => '',
        ], [
            'es' => [
                'content' => <<<'HTML'
<h2>Sobre Nosotros</h2><p>Desde el año 2007, la Fundación Teatromuseo del títere y el payaso se ha dedicado a promover, difundir y profesionalizar estas artes de la representación en nuestro país. A través de una escuela de formación nacional e internacional, un museo especializado y una sala de teatro con cartelera familiar permanente.</p>
<p>Somos un equipo de artistas y profesionales de la gestión cultural que creemos en la vida y la risa como herramientas de desarrollo humano.</p>
HTML,
            ],
            'en' => [
                'content' => <<<'HTML'
<h2>About Us</h2><p>Since 2007, the Teatromuseo Puppet and Clown Foundation has promoted, disseminated, and professionalized these performing arts in Chile through a national and international training school, a specialized museum, and a theatre with a permanent family programme.</p>
<p>We are a team of artists and cultural-management professionals who believe in life and laughter as tools for human development.</p>
HTML,
            ],
            'fr' => [
                'content' => <<<'HTML'
<h2>À propos de nous</h2><p>Depuis 2007, la Fondation Teatromuseo de la marionnette et du clown promeut, diffuse et professionnalise ces arts de la scène au Chili grâce à une école de formation nationale et internationale, un musée spécialisé et une salle de théâtre proposant une programmation familiale permanente.</p>
<p>Nous sommes une équipe d’artistes et de professionnels de la gestion culturelle qui croyons en la vie et au rire comme outils de développement humain.</p>
HTML,
            ],
            'pt' => [
                'content' => <<<'HTML'
<h2>Sobre Nós</h2><p>Desde 2007, a Fundação Teatromuseo do teatro de bonecos e do palhaço promove, difunde e profissionaliza essas artes da representação no Chile por meio de uma escola de formação nacional e internacional, um museu especializado e uma sala de teatro com programação familiar permanente.</p>
<p>Somos uma equipe de artistas e profissionais da gestão cultural que acredita na vida e no riso como ferramentas de desenvolvimento humano.</p>
HTML,
            ],
        ], $languages);

        $cardsGridId = $this->upsertBlock($pageId, $blockIds, 'cards_grid', 4, [
            'columns_desktop' => '2',
            'variant' => 'institutional',
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
                    'title' => 'Nuestra Misión',
                    'description' => 'Fortalecer, difundir y desarrollar el arte del títere y el payaso, enriqueciendo el patrimonio cultural de nuestro país y formando nuevos exponentes mediante redes, escuelas, encuentros, publicaciones y salas de teatro.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'en' => [
                    'title' => 'Mission',
                    'description' => 'Strengthen, disseminate, and develop puppet and clown arts, enriching Chile’s cultural heritage and training new exponents through networks, schools, encounters, publications, and theatres.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'fr' => [
                    'title' => 'Mission',
                    'description' => 'Renforcer, diffuser et développer les arts de la marionnette et du clown, en enrichissant le patrimoine culturel du Chili et en formant de nouveaux artistes par des réseaux, écoles, rencontres, publications et salles de théâtre.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'pt' => [
                    'title' => 'Missão',
                    'description' => 'Fortalecer, difundir e desenvolver a arte do teatro de bonecos e do palhaço, enriquecendo o patrimônio cultural do Chile e formando novos artistas por meio de redes, escolas, encontros, publicações e salas de teatro.',
                    'link_label' => '',
                    'link_url' => '',
                ],
            ],
            [
                'sort_order' => 2,
                'config' => [],
                'es' => [
                    'title' => 'Nuestra Visión',
                    'description' => 'Consolidar a la Fundación Teatromuseo como un espacio de investigación y desarrollo de estas artes, logrando que Valparaíso sea reconocido nacional e internacionalmente como la capital cultural del títere y el payaso.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'en' => [
                    'title' => 'Vision',
                    'description' => 'Establish the Teatromuseo Foundation as a space for research and development in these arts, so Valparaíso is recognized nationally and internationally as the cultural capital of puppetry and clowning.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'fr' => [
                    'title' => 'Vision',
                    'description' => 'Consolider la Fondation Teatromuseo comme un espace de recherche et de développement de ces arts, afin que Valparaíso soit reconnue nationalement et internationalement comme la capitale culturelle de la marionnette et du clown.',
                    'link_label' => '',
                    'link_url' => '',
                ],
                'pt' => [
                    'title' => 'Visão',
                    'description' => 'Consolidar a Fundação Teatromuseo como um espaço de pesquisa e desenvolvimento dessas artes, fazendo com que Valparaíso seja reconhecida nacional e internacionalmente como a capital cultural do teatro de bonecos e do palhaço.',
                    'link_label' => '',
                    'link_url' => '',
                ],
            ],
        ], $blockIds, $languages);

        $this->upsertBlock($pageId, $blockIds, 'team_grid', 8, [
            'source_collection' => 'personas',
            'items_limit' => 15,
            'filter_names' => 'Víctor Quiroga,Paulina Beltrán,Constanza Valenzuela,Diego Zúñiga,Claudio Palacios,Felipe Lira,Tomás Arce,Barbara Quiroga,Kevin Zamora,Javiera Silva',
                'columns' => '3',
            'css_class' => '',
        ], [
            'es' => ['title' => 'Nuestro gran equipo', 'description' => 'Artistas y profesionales que hacen posible el trabajo de TeatroMuseo.'],
            'en' => ['title' => 'Our team', 'description' => 'Artists and professionals who make TeatroMuseo possible.'],
            'fr' => ['title' => 'Notre équipe', 'description' => 'Artistes et professionnels qui rendent TeatroMuseo possible.'],
            'pt' => ['title' => 'Nossa equipe', 'description' => 'Artistas e profissionais que tornam o TeatroMuseo possível.'],
        ], $languages);

        $this->upsertBlock($pageId, $blockIds, 'cta', 9, [
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

        // Historia is intentionally authoritative: remove stale/demo blocks
        // before rebuilding the canonical two-block page below. This also
        // removes nested timeline/metrics children left by older seed runs.
        $this->clearPageBlocks($pageId);

        $this->upsertBlock($pageId, $blockIds, 'page_header', 1, [
            'bg_color' => 'bg-slate-100',
            'css_class' => '',
        ], [
            'es' => [
                'heading' => 'Historia',
                'subheading' => 'La historia de una institución dedicada al títere y el payaso.',
                'breadcrumb_label' => 'Inicio',
            ],
            'en' => [
                'heading' => 'History',
                'subheading' => 'A brief timeline of the archive, programming, and platform.',
                'breadcrumb_label' => 'Home',
            ],
            'fr' => [
                'heading' => 'Histoire',
                'subheading' => 'Une chronologie brève de l’archive, de la programmation et de la plateforme.',
                'breadcrumb_label' => 'Accueil',
            ],
            'pt' => [
                'heading' => 'Nossa História',
                'subheading' => 'Uma cronologia breve do arquivo, da programação e da plataforma.',
                'breadcrumb_label' => 'Início',
            ],
        ], $languages);

        $this->upsertBlock($pageId, $blockIds, 'rich_text', 2, [
            'css_class' => '',
        ], [
            'es' => [
                'content' => <<<'HTML'
<p>Desde el año 2005 los cimientos de este proyecto están anclados en la pasión, visión, creatividad y entrega de sus fundadores, la compañía El Faro —Víctor Quiroga y Paulina Beltrán—, quienes junto a la Agrupación Sonrisa emprendieron este sueño. El 25 de julio de 2007 se inaugura el Teatromuseo del Títere y el Payaso en la antigua capilla San Judas Tadeo, ubicada en la Plaza Bismark del cerro Cárcel de Valparaíso.</p>
<p>Del 2008 al 2010 se crea el Colectivo Teatromuseo del Títere y el Payaso, organización informal que reúne a titiriteros y payasos de Valparaíso y de Chile. Su objetivo fue fortalecer y valorar ambos oficios artísticos a través de la asociatividad, la formación, la mantención y la promoción de nuevas audiencias que descubran en el arte de la risa y la animación un espacio de participación cultural.</p>
<p>Con el fruto de su trabajo, el Colectivo logra crear, implementar, mantener y habilitar un espacio cultural que cuenta con una sala de teatro para 100 personas, espacio de exposiciones, una pequeña biblioteca y dos salas-talleres.</p>
<p>En 2011 se crea la Fundación Teatromuseo del Títere y el Payaso, organización sin fines de lucro cuyo fin es desarrollar un espacio cultural que combine virtuosamente la formación, creación, producción, distribución, consumo y conservación de los oficios artísticos del títere y el payaso. La Fundación contribuye al fortalecimiento del sector y a su valoración por parte de la ciudadanía, promoviendo el reconocimiento nacional e internacional de Valparaíso como Capital Cultural del Títere y el Payaso.</p>
<p>Luego de 10 años de exitosa gestión, en su sala se han realizado ininterrumpidamente, y con la participación de compañías nacionales e internacionales, casi 2.000 funciones de teatro de muñecos y de clown.</p>
<p>A la fecha se han realizado 5 ANIMATE y 5 UPA CHALUPA, encuentros internacionales de titiriteros y payasos que son espacios de intercambio y cooperación entre compañías nacionales e internacionales. En el departamento de Teatroescuela se han realizado más de 80 talleres de profesionalización y formación de nuevos exponentes del arte del títere y el payaso para adultos, jóvenes y niños, en varias comunas del Gran Valparaíso y ciudades de Chile.</p>
<p>Gracias a su departamento de extensión y museo se han realizado funciones, intervenciones callejeras y visitas guiadas en todas las regiones del país, completando más de 1.500 acciones y beneficiando aproximadamente a 50.000 personas.</p>
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

        // The old image, timeline, metrics and CTA blocks are deliberately not
        // recreated: the official history is now presented as one clean text
        // block and the reset above removes any previous copies.
        return;

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

    /** Remove all block instances owned by a page, including nested children. */
    private function clearPageBlocks(int $pageId): void
    {
        $this->db->table('cms_block_instances')
            ->where('owner_type', 'page')
            ->where('owner_id', $pageId)
            ->delete();
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
