<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Creates the Media / Multimedia page that showcases gallery presentation
 * modes, video playback, and a lightweight editorial structure for mixed
 * media content.
 *
 * The goal is to keep every public presentation mode represented in a real,
 * navigable page so the starter can be audited visually without hidden routes
 * or compatibility fallbacks.
 */
class SiteMediaPageSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $langIds = $this->langIds(['es', 'en', 'fr', 'pt']);
        if (! isset($langIds['es'], $langIds['en'])) {
            echo "SiteMediaPageSeeder: missing languages. Seed CmsLanguageSeeder first.\n";
            return;
        }

        $pageId = $this->upsertPage();
        $this->upsertPageTranslation($pageId, $langIds['es'], [
            'slug'             => 'multimedia',
            'title'            => 'Multimedia',
            'excerpt'          => 'Galerías, video y recursos visuales representados en una página pública real.',
            'meta_title'       => 'Multimedia | Mi Sitio',
            'meta_description' => 'Explora las distintas formas de presentar galerías, videos y recursos visuales.',
            'canonical_url'    => null,
            'robots'           => 'index, follow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($pageId, $langIds['en'], [
            'slug'             => 'media',
            'title'            => 'Media',
            'excerpt'          => 'Galleries, video, and visual resources represented on a real public page.',
            'meta_title'       => 'Media | My Site',
            'meta_description' => 'Explore different ways to present galleries, videos, and visual resources.',
            'canonical_url'    => null,
            'robots'           => 'index, follow',
            'schema_data'      => null,
        ]);
        if (isset($langIds['fr'])) {
            $this->upsertPageTranslation($pageId, $langIds['fr'], [
                'slug'             => 'multimedia',
                'title'            => 'Multimédia',
                'excerpt'          => "Galeries, vidéo et ressources visuelles présentées sur une véritable page publique.",
                'meta_title'       => 'Multimédia | Mon Site',
                'meta_description' => "Découvrez les différentes façons de présenter des galeries, vidéos et ressources visuelles.",
                'canonical_url'    => null,
                'robots'           => 'index, follow',
                'schema_data'      => null,
            ]);
        }
        if (isset($langIds['pt'])) {
            $this->upsertPageTranslation($pageId, $langIds['pt'], [
                'slug'             => 'multimidia',
                'title'            => 'Multimídia',
                'excerpt'          => 'Galerias, vídeo e recursos visuais representados em uma página pública real.',
                'meta_title'       => 'Multimídia | Meu Site',
                'meta_description' => 'Explore as diferentes formas de apresentar galerias, vídeos e recursos visuais.',
                'canonical_url'    => null,
                'robots'           => 'index, follow',
                'schema_data'      => null,
            ]);
        }

        $this->resetPageBlocks($pageId);

        $blockIds = $this->blockIds([
            'page_header',
            'rich_text',
            'gallery',
            'gallery_item',
            'video_player',
            'document_gallery',
            'pdf_viewer',
            'external_links',
            'alert',
            'cta',
        ]);

        $this->upsertBlock(
            $pageId,
            $blockIds,
            'page_header',
            1,
            ['bg_color' => 'bg-gray-100', 'css_class' => ''],
            [
                'es' => [
                    'heading'          => 'Multimedia',
                    'subheading'       => 'Una página pública para auditar galerías, video y recursos visuales.',
                    'breadcrumb_label' => 'Inicio',
                    'breadcrumb_url'   => '/',
                ],
                'en' => [
                    'heading'          => 'Media',
                    'subheading'       => 'A public page to audit galleries, video, and visual resources.',
                    'breadcrumb_label' => 'Home',
                    'breadcrumb_url'   => '/',
                ],
                'fr' => [
                    'heading'          => 'Multimédia',
                    'subheading'       => "Une page publique pour auditer les galeries, la vidéo et les ressources visuelles.",
                    'breadcrumb_label' => 'Accueil',
                    'breadcrumb_url'   => '/',
                ],
                'pt' => [
                    'heading'          => 'Multimídia',
                    'subheading'       => 'Uma página pública para auditar galerias, vídeo e recursos visuais.',
                    'breadcrumb_label' => 'Início',
                    'breadcrumb_url'   => '/',
                ],
            ],
            $langIds
        );

        $this->upsertBlock(
            $pageId,
            $blockIds,
            'rich_text',
            2,
            ['css_class' => ''],
            [
                'es' => [
                    'content' => '<p>Esta página muestra de forma explícita los modos de presentación del bloque de galería y el bloque de video. Así el starter no depende de conocimientos implícitos ni de rutas escondidas para demostrar sus capacidades multimedia.</p>',
                ],
                'en' => [
                    'content' => '<p>This page explicitly shows the gallery presentation modes and the video block. That keeps the starter from depending on implicit knowledge or hidden routes to demonstrate its media capabilities.</p>',
                ],
                'fr' => [
                    'content' => "<p>Cette page présente explicitement les modes de présentation du bloc galerie et le bloc vidéo. Cela évite au starter de dépendre de connaissances implicites ou de routes cachées pour démontrer ses capacités multimédias.</p>",
                ],
                'pt' => [
                    'content' => '<p>Esta página mostra explicitamente os modos de apresentação do bloco de galeria e o bloco de vídeo. Assim o starter não depende de conhecimento implícito nem de rotas ocultas para demonstrar suas capacidades multimídia.</p>',
                ],
            ],
            $langIds
        );

        $this->upsertBlock(
            $pageId,
            $blockIds,
            'alert',
            3,
            ['type' => 'info', 'dismissible' => false, 'css_class' => 'my-8'],
            [
                'es' => [
                    'title'   => 'Cobertura multimedia',
                    'message' => '<p>Si un modo visual existe en el CMS, debería poder verse aquí sin intervención manual. La página está pensada para detectar regresiones de UI y de contenido con una sola visita.</p>',
                ],
                'en' => [
                    'title'   => 'Media coverage',
                    'message' => '<p>If a visual mode exists in the CMS, it should be visible here without manual intervention. This page is designed to catch UI and content regressions in a single visit.</p>',
                ],
                'fr' => [
                    'title'   => 'Couverture multimédia',
                    'message' => "<p>Si un mode visuel existe dans le CMS, il doit être visible ici sans intervention manuelle. Cette page est conçue pour détecter les régressions d'UI et de contenu en une seule visite.</p>",
                ],
                'pt' => [
                    'title'   => 'Cobertura multimídia',
                    'message' => '<p>Se um modo visual existe no CMS, ele deve poder ser visto aqui sem intervenção manual. Esta página foi criada para detectar regressões de UI e de conteúdo em uma única visita.</p>',
                ],
            ],
            $langIds
        );

        $gridGalleryId = $this->upsertBlock(
            $pageId,
            $blockIds,
            'gallery',
            4,
            ['presentation_mode' => 'grid', 'columns' => '4', 'gap' => 'medium', 'css_class' => 'my-10'],
            ['es' => [], 'en' => []],
            $langIds
        );
        $this->seedChildBlocks($pageId, $gridGalleryId, 'gallery_item', [
            [
                'sort_order' => 1,
                'config'     => ['image' => $this->mediaReference('https://picsum.photos/id/1040/1200/900')],
                'es' => [
                    'alt'       => 'Galería en cuadrícula 1',
                    'caption'   => 'Vista en cuadrícula para series cortas y descubrimiento rápido.',
                ],
                'en' => [
                    'alt'       => 'Grid gallery 1',
                    'caption'   => 'Grid view for short series and quick discovery.',
                ],
                'fr' => [
                    'alt'       => 'Galerie en grille 1',
                    'caption'   => 'Vue en grille pour les courtes séries et une découverte rapide.',
                ],
                'pt' => [
                    'alt'       => 'Galeria em grade 1',
                    'caption'   => 'Visualização em grade para séries curtas e descoberta rápida.',
                ],
            ],
            [
                'sort_order' => 2,
                'config'     => ['image' => $this->mediaReference('https://picsum.photos/id/1041/1200/900')],
                'es' => [
                    'alt'       => 'Galería en cuadrícula 2',
                    'caption'   => 'Cada tarjeta mantiene una jerarquía clara y respirable.',
                ],
                'en' => [
                    'alt'       => 'Grid gallery 2',
                    'caption'   => 'Each card keeps a clear and breathable hierarchy.',
                ],
                'fr' => [
                    'alt'       => 'Galerie en grille 2',
                    'caption'   => 'Chaque carte conserve une hiérarchie claire et aérée.',
                ],
                'pt' => [
                    'alt'       => 'Galeria em grade 2',
                    'caption'   => 'Cada cartão mantém uma hierarquia clara e arejada.',
                ],
            ],
            [
                'sort_order' => 3,
                'config'     => ['image' => $this->mediaReference('https://picsum.photos/id/1042/1200/900')],
                'es' => [
                    'alt'       => 'Galería en cuadrícula 3',
                    'caption'   => 'Ideal para grillas editoriales o colecciones de referencia.',
                ],
                'en' => [
                    'alt'       => 'Grid gallery 3',
                    'caption'   => 'Ideal for editorial grids or reference collections.',
                ],
                'fr' => [
                    'alt'       => 'Galerie en grille 3',
                    'caption'   => 'Idéal pour les grilles éditoriales ou les collections de référence.',
                ],
                'pt' => [
                    'alt'       => 'Galeria em grade 3',
                    'caption'   => 'Ideal para grades editoriais ou coleções de referência.',
                ],
            ],
            [
                'sort_order' => 4,
                'config'     => ['image' => $this->mediaReference('https://picsum.photos/id/1043/1200/900')],
                'es' => [
                    'alt'       => 'Galería en cuadrícula 4',
                    'caption'   => 'La relación de aspecto se mantiene consistente.',
                ],
                'en' => [
                    'alt'       => 'Grid gallery 4',
                    'caption'   => 'Aspect ratio stays consistent.',
                ],
                'fr' => [
                    'alt'       => 'Galerie en grille 4',
                    'caption'   => "Le ratio d'aspect reste cohérent.",
                ],
                'pt' => [
                    'alt'       => 'Galeria em grade 4',
                    'caption'   => 'A proporção da imagem se mantém consistente.',
                ],
            ],
        ], $blockIds, $langIds);

        $this->upsertBlock(
            $pageId,
            $blockIds,
            'rich_text',
            5,
            ['css_class' => ''],
            [
                'es' => [
                    'content' => '<h2>Previsualización integrada</h2><p>Este modo permite revisar una imagen destacada sin abrir un modal. Es útil cuando quieres mantener la lectura dentro del flujo de la página y darle prioridad a una pieza visual a la vez.</p>',
                ],
                'en' => [
                    'content' => '<h2>Integrated preview</h2><p>This mode lets you inspect a featured image without opening a modal. It works well when you want to keep the reading flow on the page and focus on one visual at a time.</p>',
                ],
                'fr' => [
                    'content' => "<h2>Aperçu intégré</h2><p>Ce mode permet d'examiner une image en vedette sans ouvrir de fenêtre modale. Il est utile lorsque vous souhaitez préserver le flux de lecture de la page et concentrer l'attention sur un seul visuel à la fois.</p>",
                ],
                'pt' => [
                    'content' => '<h2>Pré-visualização integrada</h2><p>Este modo permite revisar uma imagem em destaque sem abrir um modal. É útil quando você quer manter a leitura dentro do fluxo da página e dar prioridade a uma peça visual por vez.</p>',
                ],
            ],
            $langIds
        );

        $inlineGalleryId = $this->upsertBlock(
            $pageId,
            $blockIds,
            'gallery',
            6,
            ['presentation_mode' => 'inline_preview', 'columns' => '3', 'gap' => 'large', 'css_class' => 'my-10'],
            ['es' => [], 'en' => []],
            $langIds
        );
        $this->seedChildBlocks($pageId, $inlineGalleryId, 'gallery_item', [
            [
                'sort_order' => 1,
                'config'     => ['image' => $this->mediaReference('https://picsum.photos/id/1050/1200/900')],
                'es' => [
                    'alt'       => 'Vista inline 1',
                    'caption'   => 'La previsualización cambia sin abandonar la página.',
                ],
                'en' => [
                    'alt'       => 'Inline preview 1',
                    'caption'   => 'The preview updates without leaving the page.',
                ],
                'fr' => [
                    'alt'       => 'Aperçu intégré 1',
                    'caption'   => "L'aperçu se met à jour sans quitter la page.",
                ],
                'pt' => [
                    'alt'       => 'Pré-visualização integrada 1',
                    'caption'   => 'A pré-visualização muda sem sair da página.',
                ],
            ],
            [
                'sort_order' => 2,
                'config'     => ['image' => $this->mediaReference('https://picsum.photos/id/1051/1200/900')],
                'es' => [
                    'alt'       => 'Vista inline 2',
                    'caption'   => 'Sirve para colecciones seleccionadas o pequeñas galerías.',
                ],
                'en' => [
                    'alt'       => 'Inline preview 2',
                    'caption'   => 'Useful for curated collections or small galleries.',
                ],
                'fr' => [
                    'alt'       => 'Aperçu intégré 2',
                    'caption'   => 'Utile pour les collections sélectionnées ou les petites galeries.',
                ],
                'pt' => [
                    'alt'       => 'Pré-visualização integrada 2',
                    'caption'   => 'Serve para coleções selecionadas ou pequenas galerias.',
                ],
            ],
            [
                'sort_order' => 3,
                'config'     => ['image' => $this->mediaReference('https://picsum.photos/id/1052/1200/900')],
                'es' => [
                    'alt'       => 'Vista inline 3',
                    'caption'   => 'La jerarquía del preview guía la atención.',
                ],
                'en' => [
                    'alt'       => 'Inline preview 3',
                    'caption'   => 'The preview hierarchy guides attention.',
                ],
                'fr' => [
                    'alt'       => 'Aperçu intégré 3',
                    'caption'   => "La hiérarchie de l'aperçu guide l'attention.",
                ],
                'pt' => [
                    'alt'       => 'Pré-visualização integrada 3',
                    'caption'   => 'A hierarquia da pré-visualização guia a atenção.',
                ],
            ],
        ], $blockIds, $langIds);

        $this->upsertBlock(
            $pageId,
            $blockIds,
            'rich_text',
            7,
            ['css_class' => ''],
            [
                'es' => [
                    'content' => '<h2>Vista modal</h2><p>Cuando la imagen necesita más foco, el modal permite navegar sin perder el contexto de la grilla. Es la opción más útil para colecciones visuales con material ampliable.</p>',
                ],
                'en' => [
                    'content' => '<h2>Modal view</h2><p>When an image needs more focus, the modal lets you navigate without losing the grid context. It is the best option for visual collections with expandable material.</p>',
                ],
                'fr' => [
                    'content' => "<h2>Vue modale</h2><p>Lorsqu'une image nécessite plus d'attention, la fenêtre modale permet de naviguer sans perdre le contexte de la grille. C'est la meilleure option pour les collections visuelles avec du contenu extensible.</p>",
                ],
                'pt' => [
                    'content' => '<h2>Visualização modal</h2><p>Quando a imagem precisa de mais destaque, o modal permite navegar sem perder o contexto da grade. É a opção mais útil para coleções visuais com material ampliável.</p>',
                ],
            ],
            $langIds
        );

        $modalGalleryId = $this->upsertBlock(
            $pageId,
            $blockIds,
            'gallery',
            8,
            ['presentation_mode' => 'modal_preview', 'columns' => '3', 'gap' => 'large', 'css_class' => 'my-10'],
            ['es' => [], 'en' => []],
            $langIds
        );
        $this->seedChildBlocks($pageId, $modalGalleryId, 'gallery_item', [
            [
                'sort_order' => 1,
                'config'     => ['image' => $this->mediaReference('https://picsum.photos/id/1060/1200/900')],
                'es' => [
                    'alt'       => 'Vista modal 1',
                    'caption'   => 'El modal resalta una pieza a la vez.',
                    'link_url'  => '/es/portafolio',
                    'link_label' => 'Ver portafolio',
                ],
                'en' => [
                    'alt'       => 'Modal view 1',
                    'caption'   => 'The modal highlights one piece at a time.',
                    'link_url'  => '/en/portfolio',
                    'link_label' => 'View portfolio',
                ],
                'fr' => [
                    'alt'       => 'Vue modale 1',
                    'caption'   => "La fenêtre modale met en valeur une pièce à la fois.",
                    'link_url'  => '/fr/portefeuille',
                    'link_label' => 'Voir le portefeuille',
                ],
                'pt' => [
                    'alt'       => 'Visualização modal 1',
                    'caption'   => 'O modal destaca uma peça por vez.',
                    'link_url'  => '/pt/portfolio',
                    'link_label' => 'Ver portfólio',
                ],
            ],
            [
                'sort_order' => 2,
                'config'     => ['image' => $this->mediaReference('https://picsum.photos/id/1061/1200/900')],
                'es' => [
                    'alt'       => 'Vista modal 2',
                    'caption'   => 'Ideal para fotografía, posters y material editorial.',
                ],
                'en' => [
                    'alt'       => 'Modal view 2',
                    'caption'   => 'Ideal for photography, posters, and editorial material.',
                ],
                'fr' => [
                    'alt'       => 'Vue modale 2',
                    'caption'   => 'Idéal pour la photographie, les affiches et le matériel éditorial.',
                ],
                'pt' => [
                    'alt'       => 'Visualização modal 2',
                    'caption'   => 'Ideal para fotografia, pôsteres e material editorial.',
                ],
            ],
            [
                'sort_order' => 3,
                'config'     => ['image' => $this->mediaReference('https://picsum.photos/id/1062/1200/900')],
                'es' => [
                    'alt'       => 'Vista modal 3',
                    'caption'   => 'El modal preserva el foco visual sin recargar la página.',
                ],
                'en' => [
                    'alt'       => 'Modal view 3',
                    'caption'   => 'The modal preserves visual focus without overloading the page.',
                ],
                'fr' => [
                    'alt'       => 'Vue modale 3',
                    'caption'   => "La fenêtre modale préserve la concentration visuelle sans surcharger la page.",
                ],
                'pt' => [
                    'alt'       => 'Visualização modal 3',
                    'caption'   => 'O modal preserva o foco visual sem sobrecarregar a página.',
                ],
            ],
        ], $blockIds, $langIds);

        $this->upsertBlock(
            $pageId,
            $blockIds,
            'video_player',
            9,
            ['autoplay' => false, 'mute' => false, 'loop' => false, 'aspect_ratio' => '16/9', 'css_class' => 'my-12'],
            [
                'es' => [
                    'heading'   => 'Video de demostración',
                    'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                ],
                'en' => [
                    'heading'   => 'Demo video',
                    'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                ],
                'fr' => [
                    'heading'   => 'Vidéo de démonstration',
                    'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                ],
                'pt' => [
                    'heading'   => 'Vídeo de demonstração',
                    'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                ],
            ],
            $langIds
        );

        // ── 10. document_gallery ─────────────────────────────────────────────
        $this->upsertBlock(
            $pageId,
            $blockIds,
            'document_gallery',
            10,
            ['layout' => 'grid_cards', 'show_file_meta' => true, 'open_in_new_tab' => true, 'css_class' => 'my-12'],
            [
                'es' => [
                    'title'       => 'Repositorio de Documentos',
                    'description' => 'Listado de descargas con iconos adaptativos según extensión de archivo (PDF, Word, ZIP, Excel).',
                    'documents'   => [
                        [
                            'file'        => $this->mediaReference('http://localhost:8186/assets/docs/policies-handbook-demo.pdf'),
                            'title'       => 'Manual de Políticas Generales',
                            'description' => 'Documento PDF que describe los lineamientos y políticas fundamentales.',
                        ],
                        [
                            'file'        => $this->mediaReference('https://calibre-ebook.com/downloads/demos/demo.docx'),
                            'title'       => 'Formulario de Afiliación',
                            'description' => 'Plantilla de Word editable para completar tus datos.',
                        ],
                        [
                            'file'        => $this->mediaReference('https://www.learningcontainer.com/wp-content/uploads/2020/05/sample-zip-file.zip'),
                            'title'       => 'Paquete de Recursos Gráficos',
                            'description' => 'Archivo ZIP comprimido con logos oficiales, manual de marca e iconos.',
                        ],
                    ],
                ],
                'en' => [
                    'title'       => 'Document Repository',
                    'description' => 'Download list with adaptive icons matching file extensions (PDF, Word, ZIP, Excel).',
                    'documents'   => [
                        [
                            'file'        => $this->mediaReference('http://localhost:8186/assets/docs/policies-handbook-demo.pdf'),
                            'title'       => 'General Policies Handbook',
                            'description' => 'PDF document covering the core guidelines and company rules.',
                        ],
                        [
                            'file'        => $this->mediaReference('https://calibre-ebook.com/downloads/demos/demo.docx'),
                            'title'       => 'Affiliation Form',
                            'description' => 'Editable Word document template to fill in your personal data.',
                        ],
                        [
                            'file'        => $this->mediaReference('https://www.learningcontainer.com/wp-content/uploads/2020/05/sample-zip-file.zip'),
                            'title'       => 'Brand Asset Bundle',
                            'description' => 'ZIP compressed package containing official logos, styleguides, and icons.',
                        ],
                    ],
                ],
                'fr' => [
                    'title'       => 'Répertoire de Documents',
                    'description' => "Liste de téléchargements avec icônes adaptatives selon l'extension du fichier (PDF, Word, ZIP, Excel).",
                    'documents'   => [
                        [
                            'file'        => $this->mediaReference('http://localhost:8186/assets/docs/policies-handbook-demo.pdf'),
                            'title'       => 'Manuel des Politiques Générales',
                            'description' => 'Document PDF décrivant les lignes directrices et politiques fondamentales.',
                        ],
                        [
                            'file'        => $this->mediaReference('https://calibre-ebook.com/downloads/demos/demo.docx'),
                            'title'       => "Formulaire d'Affiliation",
                            'description' => 'Modèle Word modifiable pour renseigner vos données.',
                        ],
                        [
                            'file'        => $this->mediaReference('https://www.learningcontainer.com/wp-content/uploads/2020/05/sample-zip-file.zip'),
                            'title'       => 'Pack de Ressources Graphiques',
                            'description' => 'Archive ZIP compressée contenant les logos officiels, la charte graphique et les icônes.',
                        ],
                    ],
                ],
                'pt' => [
                    'title'       => 'Repositório de Documentos',
                    'description' => 'Lista de downloads com ícones adaptativos conforme a extensão do arquivo (PDF, Word, ZIP, Excel).',
                    'documents'   => [
                        [
                            'file'        => $this->mediaReference('http://localhost:8186/assets/docs/policies-handbook-demo.pdf'),
                            'title'       => 'Manual de Políticas Gerais',
                            'description' => 'Documento PDF que descreve as diretrizes e políticas fundamentais.',
                        ],
                        [
                            'file'        => $this->mediaReference('https://calibre-ebook.com/downloads/demos/demo.docx'),
                            'title'       => 'Formulário de Filiação',
                            'description' => 'Modelo de Word editável para preencher seus dados.',
                        ],
                        [
                            'file'        => $this->mediaReference('https://www.learningcontainer.com/wp-content/uploads/2020/05/sample-zip-file.zip'),
                            'title'       => 'Pacote de Recursos Gráficos',
                            'description' => 'Arquivo ZIP compactado com logos oficiais, manual de marca e ícones.',
                        ],
                    ],
                ],
            ],
            $langIds
        );

        // ── 11. pdf_viewer ───────────────────────────────────────────────────
        $this->upsertBlock(
            $pageId,
            $blockIds,
            'pdf_viewer',
            11,
            [
                'pdf_file'      => $this->mediaReference('http://localhost:8186/assets/docs/policies-handbook-demo.pdf'),
                'height'        => '600px',
                'allow_download' => true,
                'css_class'     => 'my-12',
            ],
            [
                'es' => [
                    'heading' => 'Previsualización del Manual de Políticas',
                ],
                'en' => [
                    'heading' => 'Policies Handbook Preview',
                ],
                'fr' => [
                    'heading' => 'Aperçu du Manuel des Politiques',
                ],
                'pt' => [
                    'heading' => 'Pré-visualização do Manual de Políticas',
                ],
            ],
            $langIds
        );

        // ── 12. external_links ───────────────────────────────────────────────
        $this->upsertBlock(
            $pageId,
            $blockIds,
            'external_links',
            12,
            ['layout_columns' => '3', 'open_in_new_tab' => true, 'css_class' => 'my-12'],
            [
                'es' => [
                    'title'       => 'Directorio de Sitios Recomendados',
                    'description' => 'Enlaces útiles a recursos externos y portales oficiales del ecosistema.',
                    'links'       => [
                        [
                            'label'       => 'Documentación Oficial',
                            'url'         => 'https://codeigniter.com',
                            'description' => 'Sitio web principal y manuales técnicos de CodeIgniter 4.',
                            'icon_name'   => 'book-open',
                        ],
                        [
                            'label'       => 'Repositorio GitHub',
                            'url'         => 'https://github.com',
                            'description' => 'Acceso al código fuente, incidencias y control de versiones.',
                            'icon_name'   => 'github',
                        ],
                        [
                            'label'       => 'Canal de Soporte',
                            'url'         => 'https://slack.com',
                            'description' => 'Comunidad y salas de chat en vivo para soporte técnico.',
                            'icon_name'   => 'message-square',
                        ],
                    ],
                ],
                'en' => [
                    'title'       => 'Recommended Sites Directory',
                    'description' => 'Useful links to external resources and official ecosystem portals.',
                    'links'       => [
                        [
                            'label'       => 'Official Documentation',
                            'url'         => 'https://codeigniter.com',
                            'description' => 'Main website and technical user guides for CodeIgniter 4.',
                            'icon_name'   => 'book-open',
                        ],
                        [
                            'label'       => 'GitHub Repository',
                            'url'         => 'https://github.com',
                            'description' => 'Access to source code, issue tracking, and version history.',
                            'icon_name'   => 'github',
                        ],
                        [
                            'label'       => 'Support Chat Channel',
                            'url'         => 'https://slack.com',
                            'description' => 'Community chatrooms for live technical support and discussion.',
                            'icon_name'   => 'message-square',
                        ],
                    ],
                ],
                'fr' => [
                    'title'       => 'Répertoire de Sites Recommandés',
                    'description' => "Liens utiles vers des ressources externes et des portails officiels de l'écosystème.",
                    'links'       => [
                        [
                            'label'       => 'Documentation Officielle',
                            'url'         => 'https://codeigniter.com',
                            'description' => 'Site web principal et guides techniques de CodeIgniter 4.',
                            'icon_name'   => 'book-open',
                        ],
                        [
                            'label'       => 'Dépôt GitHub',
                            'url'         => 'https://github.com',
                            'description' => 'Accès au code source, au suivi des problèmes et à l\'historique des versions.',
                            'icon_name'   => 'github',
                        ],
                        [
                            'label'       => 'Canal de Support',
                            'url'         => 'https://slack.com',
                            'description' => 'Salons communautaires pour le support technique et les échanges en direct.',
                            'icon_name'   => 'message-square',
                        ],
                    ],
                ],
                'pt' => [
                    'title'       => 'Diretório de Sites Recomendados',
                    'description' => 'Links úteis para recursos externos e portais oficiais do ecossistema.',
                    'links'       => [
                        [
                            'label'       => 'Documentação Oficial',
                            'url'         => 'https://codeigniter.com',
                            'description' => 'Site principal e guias técnicos do CodeIgniter 4.',
                            'icon_name'   => 'book-open',
                        ],
                        [
                            'label'       => 'Repositório GitHub',
                            'url'         => 'https://github.com',
                            'description' => 'Acesso ao código-fonte, rastreamento de problemas e histórico de versões.',
                            'icon_name'   => 'github',
                        ],
                        [
                            'label'       => 'Canal de Suporte',
                            'url'         => 'https://slack.com',
                            'description' => 'Comunidade e salas de chat ao vivo para suporte técnico.',
                            'icon_name'   => 'message-square',
                        ],
                    ],
                ],
            ],
            $langIds
        );

        // ── 13. cta ──────────────────────────────────────────────────────────
        $this->upsertBlock(
            $pageId,
            $blockIds,
            'cta',
            13,
            ['variant' => 'blue', 'css_class' => ''],
            [
                'es' => [
                    'heading' => '¿Quieres revisar otra página demo?',
                    'text'    => 'Pasa por el portafolio, las noticias o quiénes somos para ver más combinaciones de bloques funcionando en vivo.',
                    'label'   => 'Ir al portafolio',
                    'url'     => '/es/portafolio',
                ],
                'en' => [
                    'heading' => 'Want to review another demo page?',
                    'text'    => 'Visit the portfolio, news, or about pages to see more block combinations working live.',
                    'label'   => 'Go to portfolio',
                    'url'     => '/en/portfolio',
                ],
                'fr' => [
                    'heading' => 'Vous voulez découvrir une autre page de démonstration ?',
                    'text'    => "Visitez le portefeuille, les actualités ou la page à propos pour voir d'autres combinaisons de blocs fonctionner en direct.",
                    'label'   => 'Aller au portefeuille',
                    'url'     => '/fr/portefeuille',
                ],
                'pt' => [
                    'heading' => 'Quer revisar outra página demo?',
                    'text'    => 'Visite o portfólio, as notícias ou a página sobre nós para ver mais combinações de blocos funcionando ao vivo.',
                    'label'   => 'Ir para o portfólio',
                    'url'     => '/pt/portfolio',
                ],
            ],
            $langIds
        );
    }

    private function upsertPage(): int
    {
        // Create a dedicated demo page instead of reusing the first generic row.
        // The bootstrap seeds many generic pages, so a wide upsert would be
        // unstable and could overwrite a different demo page.
        $existing = $this->db->table('cms_pages')
            ->select('cms_pages.id')
            ->join('cms_page_translations', 'cms_page_translations.page_id = cms_pages.id')
            ->where('cms_pages.deleted_at IS NULL', null, false)
            ->whereIn('cms_page_translations.slug', ['multimedia', 'media'])
            ->orderBy('cms_pages.id', 'ASC')
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            $pageId = (int) $existing['id'];
            $updatePayload = [
                'page_type'          => 'generic',
                'status'             => 'published',
                'published_at'       => date('Y-m-d H:i:s'),
                'scheduled_at'       => null,
                'sort_order'         => 35,
                'sitemap_priority'   => '0.6',
                'sitemap_changefreq' => 'monthly',
                'is_in_sitemap'      => 1,
                'deleted_at'         => null,
            ];
            if ($this->db->fieldExists('updated_at', 'cms_pages')) {
                $updatePayload['updated_at'] = date('Y-m-d H:i:s');
            }

            $this->db->table('cms_pages')
                ->where('id', $pageId)
                ->update($updatePayload);
        } else {
            $pageId = $this->createRecord('cms_pages', [
                'page_type'          => 'generic',
                'status'             => 'published',
                'published_at'       => date('Y-m-d H:i:s'),
                'scheduled_at'       => null,
                'sort_order'         => 35,
                'sitemap_priority'   => '0.6',
                'sitemap_changefreq' => 'monthly',
                'is_in_sitemap'      => 1,
                'deleted_at'         => null,
            ]);
        }

        if ($pageId === null) {
            throw new \RuntimeException('SiteMediaPageSeeder: unable to seed media page.');
        }

        return $pageId;
    }

    /** @param array<string, mixed> $translationData */
    private function upsertPageTranslation(int $pageId, int $languageId, array $translationData): void
    {
        $slug = (string) ($translationData['slug'] ?? '');
        if ($slug !== '') {
            $conflict = $this->db->table('cms_page_translations')
                ->where('language_id', $languageId)
                ->where('slug', $slug)
                ->get()
                ->getRowArray();
            if ($conflict !== null && (int) $conflict['page_id'] !== $pageId) {
                return;
            }
        }

        $this->upsertRecord('cms_page_translations', [
            'page_id'     => $pageId,
            'language_id' => $languageId,
        ], $translationData);
    }

    /**
     * @param array<string, int>                  $blockIds
     * @param array<string, mixed>                $config
     * @param array<string, array<string, mixed>> $translations
     * @param array<string, int>                  $langIds
     */
    private function upsertBlock(
        int    $pageId,
        array  $blockIds,
        string $blockKey,
        int    $sortOrder,
        array  $config,
        array  $translations,
        array  $langIds,
        ?int   $parentInstanceId = null
    ): int {
        $blockId = $blockIds[$blockKey] ?? null;
        if ($blockId === null) {
            echo "SiteMediaPageSeeder: block type '{$blockKey}' not found — skipped.\n";
            return 0;
        }

        $instanceId = $this->upsertRecord('cms_block_instances', [
            'block_id'           => $blockId,
            'owner_type'         => 'page',
            'owner_id'           => $pageId,
            'parent_instance_id' => $parentInstanceId,
            'sort_order'         => $sortOrder,
        ], [
            'column_index' => null,
            'is_active'    => 1,
            'block_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        foreach ($translations as $langCode => $data) {
            $langId = $langIds[$langCode] ?? null;
            if ($langId === null || ! is_array($data) || $data === []) {
                continue;
            }
            $this->upsertTranslation($instanceId, $langId, $data);
        }

        return $instanceId;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, int>               $blockIds
     * @param array<string, int>               $langIds
     */
    private function seedChildBlocks(int $pageId, int $parentInstanceId, string $blockKey, array $items, array $blockIds, array $langIds): void
    {
        $blockId = $blockIds[$blockKey] ?? null;
        if ($blockId === null) {
            return;
        }

        foreach ($items as $item) {
            $instanceId = $this->upsertRecord('cms_block_instances', [
                'block_id'           => $blockId,
                'owner_type'         => 'page',
                'owner_id'           => $pageId,
                'parent_instance_id' => $parentInstanceId,
                'sort_order'         => (int) $item['sort_order'],
            ], [
                'column_index' => null,
                'is_active'    => 1,
                'block_config' => json_encode(
                    is_array($item['config'] ?? null) ? $item['config'] : [],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);

            if ($instanceId === null) {
                continue;
            }

            foreach (['es', 'en', 'fr', 'pt'] as $lang) {
                $langId = $langIds[$lang] ?? null;
                if ($langId === null || ! isset($item[$lang])) {
                    continue;
                }
                $this->upsertTranslation($instanceId, $langId, $item[$lang]);
            }
        }
    }

    /** @param array<string, mixed> $blockData */
    private function upsertTranslation(int $instanceId, int $languageId, array $blockData): void
    {
        $this->upsertRecord('cms_block_instance_translations', [
            'instance_id' => $instanceId,
            'language_id' => $languageId,
        ], [
            'block_data'   => json_encode($blockData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_published' => 1,
        ]);
    }

    /** @param string[] $keys  @return array<string, int> */
    private function blockIds(array $keys): array
    {
        $rows = $this->db->table('cms_content_blocks')
            ->whereIn('block_key', $keys)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['block_key']] = (int) $row['id'];
        }

        return $map;
    }

    /** @param string[] $codes  @return array<string, int> */
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
}
