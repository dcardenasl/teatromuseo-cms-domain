<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

/**
 * Creates the Landing Page with the following blocks:
 *   page_header,
 *   anchor_nav,
 *   features_grid,
 *   process_steps,
 *   pricing_grid + pricing_plan children,
 *   video_gallery,
 *   faq_accordion,
 *   cta.
 */
class SiteLandingPageSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $langIds = $this->langIds(['es', 'en', 'fr', 'pt']);
        if (! isset($langIds['es'], $langIds['en'])) {
            echo "SiteLandingPageSeeder: missing languages. Seed CmsLanguageSeeder first.\n";
            return;
        }

        $landingPageId = $this->upsertPage();
        $this->upsertPageTranslation($landingPageId, $langIds['es'], [
            'slug'             => 'landing',
            'title'            => 'Landing Page',
            'excerpt'          => 'Página de presentación interactiva de nuestros servicios.',
            'meta_title'       => 'Página de Destino | Mi Sitio',
            'meta_description' => 'Un escaparate interactivo de nuestras principales funcionalidades, planes y preguntas frecuentes.',
            'canonical_url'    => null,
            'robots'           => 'index, follow',
            'schema_data'      => null,
        ]);
        $this->upsertPageTranslation($landingPageId, $langIds['en'], [
            'slug'             => 'landing',
            'title' => 'Landing Page',
            'excerpt'          => 'Interactive showcase page of our services.',
            'meta_title'       => 'Landing Page | My Site',
            'meta_description' => 'An interactive showcase of our core features, plans, and frequently asked questions.',
            'canonical_url'    => null,
            'robots'           => 'index, follow',
            'schema_data'      => null,
        ]);
        if (isset($langIds['fr'])) {
            $this->upsertPageTranslation($landingPageId, $langIds['fr'], [
                'slug'             => 'landing',
                'title'            => 'Page de Destination',
                'excerpt'          => 'Page de présentation interactive de nos services.',
                'meta_title'       => 'Page de Destination | Mon Site',
                'meta_description' => "Une vitrine interactive de nos principales fonctionnalités, plans et questions fréquentes.",
                'canonical_url'    => null,
                'robots'           => 'index, follow',
                'schema_data'      => null,
            ]);
        }
        if (isset($langIds['pt'])) {
            $this->upsertPageTranslation($landingPageId, $langIds['pt'], [
                'slug'             => 'landing',
                'title'            => 'Página de Destino',
                'excerpt'          => 'Página de apresentação interativa de nossos serviços.',
                'meta_title'       => 'Página de Destino | Meu Site',
                'meta_description' => 'Uma vitrine interativa de nossos principais recursos, planos e perguntas frequentes.',
                'canonical_url'    => null,
                'robots'           => 'index, follow',
                'schema_data'      => null,
            ]);
        }

        $this->resetPageBlocks($landingPageId);

        $blockIds = $this->blockIds([
            'page_header',
            'anchor_nav',
            'features_grid',
            'process_steps',
            'pricing_grid',
            'pricing_plan',
            'video_gallery',
            'faq_accordion',
            'cta',
        ]);

        // ── 1. page_header ────────────────────────────────────────────────────
        $this->upsertBlock(
            $landingPageId,
            $blockIds,
            'page_header',
            1,
            ['bg_color' => 'bg-slate-50', 'css_class' => ''],
            [
                'es' => [
                    'heading'          => 'Página de Destino',
                    'subheading'       => 'Una landing page interactiva con menú de anclas, precios, FAQs y videos.',
                    'breadcrumb_label' => 'Inicio',
                    'breadcrumb_url'   => '/',
                ],
                'en' => [
                    'heading'          => 'Landing Page',
                    'subheading'       => 'An interactive landing page featuring anchor navigation, pricing, FAQs, and videos.',
                    'breadcrumb_label' => 'Home',
                    'breadcrumb_url'   => '/',
                ],
                'fr' => [
                    'heading'          => 'Page de Destination',
                    'subheading'       => "Une landing page interactive avec navigation par ancres, tarifs, FAQ et vidéos.",
                    'breadcrumb_label' => 'Accueil',
                    'breadcrumb_url'   => '/',
                ],
                'pt' => [
                    'heading'          => 'Página de Destino',
                    'subheading'       => 'Uma landing page interativa com menu de âncoras, preços, FAQs e vídeos.',
                    'breadcrumb_label' => 'Início',
                    'breadcrumb_url'   => '/',
                ],
            ],
            $langIds
        );

        // ── 2. anchor_nav ────────────────────────────────────────────────────
        $this->upsertBlock(
            $landingPageId,
            $blockIds,
            'anchor_nav',
            2,
            ['css_class' => ''],
            [
                'es' => [
                    'anchors' => [
                        ['label' => 'Características', 'anchor_id' => '#features'],
                        ['label' => 'Proceso', 'anchor_id' => '#process'],
                        ['label' => 'Precios', 'anchor_id' => '#pricing'],
                        ['label' => 'Videos', 'anchor_id' => '#timeline-item-videos'],
                        ['label' => 'Preguntas Frecuentes', 'anchor_id' => '#faq'],
                    ],
                ],
                'en' => [
                    'anchors' => [
                        ['label' => 'Features', 'anchor_id' => '#features'],
                        ['label' => 'Process', 'anchor_id' => '#process'],
                        ['label' => 'Pricing', 'anchor_id' => '#pricing'],
                        ['label' => 'Videos', 'anchor_id' => '#timeline-item-videos'],
                        ['label' => 'FAQ', 'anchor_id' => '#faq'],
                    ],
                ],
                'fr' => [
                    'anchors' => [
                        ['label' => 'Fonctionnalités', 'anchor_id' => '#features'],
                        ['label' => 'Processus', 'anchor_id' => '#process'],
                        ['label' => 'Tarifs', 'anchor_id' => '#pricing'],
                        ['label' => 'Vidéos', 'anchor_id' => '#timeline-item-videos'],
                        ['label' => 'FAQ', 'anchor_id' => '#faq'],
                    ],
                ],
                'pt' => [
                    'anchors' => [
                        ['label' => 'Recursos', 'anchor_id' => '#features'],
                        ['label' => 'Processo', 'anchor_id' => '#process'],
                        ['label' => 'Preços', 'anchor_id' => '#pricing'],
                        ['label' => 'Vídeos', 'anchor_id' => '#timeline-item-videos'],
                        ['label' => 'Perguntas Frequentes', 'anchor_id' => '#faq'],
                    ],
                ],
            ],
            $langIds
        );

        // ── 3. features_grid ─────────────────────────────────────────────────
        $this->upsertBlock(
            $landingPageId,
            $blockIds,
            'features_grid',
            3,
            ['columns' => '3', 'css_class' => 'my-12'],
            [
                'es' => [
                    'title'       => '¿Por qué elegirnos?',
                    'description' => 'Ofrecemos soluciones robustas e innovadoras diseñadas para potenciar tu productividad y escalabilidad.',
                    'features'    => [
                        [
                            'icon_name'   => 'zap',
                            'title'       => 'Rendimiento Extremo',
                            'description' => 'Desarrollado sobre CodeIgniter 4 para garantizar tiempos de respuesta por debajo de los 50ms.',
                        ],
                        [
                            'icon_name'   => 'shield',
                            'title'       => 'Seguridad Integrada',
                            'description' => 'Protección nativa contra CSRF, XSS e inyecciones SQL en todas las capas del sistema.',
                        ],
                        [
                            'icon_name'   => 'layout',
                            'title'       => 'Diseño Adaptativo',
                            'description' => 'Componentes maquetados con Tailwind CSS que se visualizan perfectos en móviles y desktops.',
                        ],
                    ],
                ],
                'en' => [
                    'title'       => 'Why choose us?',
                    'description' => 'We offer robust and innovative solutions designed to boost your productivity and scalability.',
                    'features'    => [
                        [
                            'icon_name'   => 'zap',
                            'title'       => 'Extreme Performance',
                            'description' => 'Built on CodeIgniter 4 to guarantee response times below 50ms.',
                        ],
                        [
                            'icon_name'   => 'shield',
                            'title'       => 'Built-in Security',
                            'description' => 'Native protection against CSRF, XSS, and SQL injection across all layers.',
                        ],
                        [
                            'icon_name'   => 'layout',
                            'title'       => 'Responsive Layouts',
                            'description' => 'Components designed with Tailwind CSS that look pixel-perfect on mobile and desktop.',
                        ],
                    ],
                ],
                'fr' => [
                    'title'       => 'Pourquoi nous choisir ?',
                    'description' => "Nous proposons des solutions robustes et innovantes conçues pour booster votre productivité et votre évolutivité.",
                    'features'    => [
                        [
                            'icon_name'   => 'zap',
                            'title'       => 'Performance Extrême',
                            'description' => "Développé sur CodeIgniter 4 pour garantir des temps de réponse inférieurs à 50 ms.",
                        ],
                        [
                            'icon_name'   => 'shield',
                            'title'       => 'Sécurité Intégrée',
                            'description' => 'Protection native contre le CSRF, le XSS et les injections SQL à tous les niveaux du système.',
                        ],
                        [
                            'icon_name'   => 'layout',
                            'title'       => 'Design Adaptatif',
                            'description' => 'Composants réalisés avec Tailwind CSS, parfaitement affichés sur mobile et sur ordinateur.',
                        ],
                    ],
                ],
                'pt' => [
                    'title'       => 'Por que nos escolher?',
                    'description' => 'Oferecemos soluções robustas e inovadoras projetadas para impulsionar sua produtividade e escalabilidade.',
                    'features'    => [
                        [
                            'icon_name'   => 'zap',
                            'title'       => 'Desempenho Extremo',
                            'description' => 'Desenvolvido sobre o CodeIgniter 4 para garantir tempos de resposta abaixo de 50ms.',
                        ],
                        [
                            'icon_name'   => 'shield',
                            'title'       => 'Segurança Integrada',
                            'description' => 'Proteção nativa contra CSRF, XSS e injeções SQL em todas as camadas do sistema.',
                        ],
                        [
                            'icon_name'   => 'layout',
                            'title'       => 'Design Adaptativo',
                            'description' => 'Componentes desenvolvidos com Tailwind CSS que ficam perfeitos em dispositivos móveis e desktops.',
                        ],
                    ],
                ],
            ],
            $langIds
        );

        // ── 4. process_steps ─────────────────────────────────────────────────
        $this->upsertBlock(
            $landingPageId,
            $blockIds,
            'process_steps',
            4,
            ['css_class' => 'my-12'],
            [
                'es' => [
                    'title'       => 'Cómo Comenzar',
                    'description' => 'Tres sencillos pasos para activar tu sitio web y empezar a publicar contenidos estructurados.',
                    'steps'       => [
                        [
                            'step_number' => '1',
                            'title'       => 'Registrar Cuenta',
                            'description' => 'Crea tu usuario administrador e inicializa los esquemas por defecto.',
                        ],
                        [
                            'step_number' => '2',
                            'title'       => 'Configurar Bloques',
                            'description' => 'Arrastra y personaliza los bloques de contenido visuales en tus páginas.',
                        ],
                        [
                            'step_number' => '3',
                            'title'       => 'Publicar Sitio',
                            'description' => 'Presiona publicar para sincronizar tus contenidos en producción de inmediato.',
                        ],
                    ],
                ],
                'en' => [
                    'title'       => 'How to Get Started',
                    'description' => 'Three simple steps to activate your website and start publishing structured content.',
                    'steps'       => [
                        [
                            'step_number' => '1',
                            'title'       => 'Register Account',
                            'description' => 'Create your administrator user and initialize the default database schemes.',
                        ],
                        [
                            'step_number' => '2',
                            'title'       => 'Configure Blocks',
                            'description' => 'Drag, drop, and customize visual content blocks on your pages.',
                        ],
                        [
                            'step_number' => '3',
                            'title'       => 'Publish Site',
                            'description' => 'Hit publish to synchronize your contents to production immediately.',
                        ],
                    ],
                ],
                'fr' => [
                    'title'       => 'Comment Commencer',
                    'description' => 'Trois étapes simples pour activer votre site web et commencer à publier du contenu structuré.',
                    'steps'       => [
                        [
                            'step_number' => '1',
                            'title'       => 'Créer un Compte',
                            'description' => "Créez votre utilisateur administrateur et initialisez les schémas de base de données par défaut.",
                        ],
                        [
                            'step_number' => '2',
                            'title'       => 'Configurer les Blocs',
                            'description' => 'Glissez-déposez et personnalisez les blocs de contenu visuels sur vos pages.',
                        ],
                        [
                            'step_number' => '3',
                            'title'       => 'Publier le Site',
                            'description' => 'Cliquez sur publier pour synchroniser immédiatement vos contenus en production.',
                        ],
                    ],
                ],
                'pt' => [
                    'title'       => 'Como Começar',
                    'description' => 'Três passos simples para ativar seu site e começar a publicar conteúdo estruturado.',
                    'steps'       => [
                        [
                            'step_number' => '1',
                            'title'       => 'Registrar Conta',
                            'description' => 'Crie seu usuário administrador e inicialize os esquemas padrão do banco de dados.',
                        ],
                        [
                            'step_number' => '2',
                            'title'       => 'Configurar Blocos',
                            'description' => 'Arraste e personalize os blocos de conteúdo visuais em suas páginas.',
                        ],
                        [
                            'step_number' => '3',
                            'title'       => 'Publicar Site',
                            'description' => 'Pressione publicar para sincronizar seus conteúdos em produção imediatamente.',
                        ],
                    ],
                ],
            ],
            $langIds
        );

        // ── 5. pricing_grid ──────────────────────────────────────────────────
        $pricingGridId = $this->upsertBlock(
            $landingPageId,
            $blockIds,
            'pricing_grid',
            5,
            ['css_class' => 'my-12 bg-slate-50/50 rounded-3xl p-8 border border-slate-200/50'],
            [
                'es' => [
                    'title'       => 'Planes y Precios',
                    'description' => 'Elige el plan ideal para tu organización. Todos los planes incluyen actualizaciones de seguridad periódicas.',
                ],
                'en' => [
                    'title'       => 'Plans & Pricing',
                    'description' => 'Choose the ideal plan for your organization. All plans include regular security updates.',
                ],
                'fr' => [
                    'title'       => 'Offres et Tarifs',
                    'description' => "Choisissez le plan idéal pour votre organisation. Tous les plans incluent des mises à jour de sécurité régulières.",
                ],
                'pt' => [
                    'title'       => 'Planos e Preços',
                    'description' => 'Escolha o plano ideal para sua organização. Todos os planos incluem atualizações de segurança periódicas.',
                ],
            ],
            $langIds
        );

        $pricingPlans = [
            [
                'sort_order' => 1,
                'featured'   => false,
                'es' => [
                    'name'        => 'Básico',
                    'price'       => '$19',
                    'period'      => '/ mes',
                    'description' => 'Ideal para sitios personales o startups en etapa temprana.',
                    'features'    => '<ul><li>Hasta 5 páginas</li><li>3 usuarios editores</li><li>Soporte vía email</li><li>SSL gratuito</li></ul>',
                    'cta_label'   => 'Iniciar plan Básico',
                    'cta_url'     => '/registro?plan=basic',
                ],
                'en' => [
                    'name'        => 'Basic',
                    'price'       => '$19',
                    'period'      => '/ mo',
                    'description' => 'Perfect for personal sites or early-stage startups.',
                    'features'    => '<ul><li>Up to 5 pages</li><li>3 editor users</li><li>Email support</li><li>Free SSL</li></ul>',
                    'cta_label'   => 'Start Basic plan',
                    'cta_url'     => '/register?plan=basic',
                ],
                'fr' => [
                    'name'        => 'Basique',
                    'price'       => '19 $',
                    'period'      => '/ mois',
                    'description' => 'Idéal pour les sites personnels ou les startups en phase de démarrage.',
                    'features'    => '<ul><li>Jusqu\'à 5 pages</li><li>3 utilisateurs éditeurs</li><li>Support par e-mail</li><li>SSL gratuit</li></ul>',
                    'cta_label'   => 'Démarrer le plan Basique',
                    'cta_url'     => '/inscription?plan=basic',
                ],
                'pt' => [
                    'name'        => 'Básico',
                    'price'       => 'US$ 19',
                    'period'      => '/ mês',
                    'description' => 'Ideal para sites pessoais ou startups em fase inicial.',
                    'features'    => '<ul><li>Até 5 páginas</li><li>3 usuários editores</li><li>Suporte por e-mail</li><li>SSL grátis</li></ul>',
                    'cta_label'   => 'Iniciar plano Básico',
                    'cta_url'     => '/registro?plan=basic',
                ],
            ],
            [
                'sort_order' => 2,
                'featured'   => true,
                'es' => [
                    'name'        => 'Profesional',
                    'price'       => '$49',
                    'period'      => '/ mes',
                    'description' => 'Para organizaciones medianas con múltiples colaboradores.',
                    'features'    => '<ul><li>Páginas ilimitadas</li><li>10 usuarios editores</li><li>Soporte 24/7 prioritario</li><li>CDN y optimización de imágenes</li><li>Traducciones multi-idioma</li></ul>',
                    'cta_label'   => 'Iniciar plan Profesional',
                    'cta_url'     => '/registro?plan=pro',
                ],
                'en' => [
                    'name'        => 'Professional',
                    'price'       => '$49',
                    'period'      => '/ mo',
                    'description' => 'For medium-sized organizations with multiple editors.',
                    'features'    => '<ul><li>Unlimited pages</li><li>10 editor users</li><li>24/7 priority support</li><li>CDN & image optimization</li><li>Multi-language translation</li></ul>',
                    'cta_label'   => 'Start Professional plan',
                    'cta_url'     => '/register?plan=pro',
                ],
                'fr' => [
                    'name'        => 'Professionnel',
                    'price'       => '49 $',
                    'period'      => '/ mois',
                    'description' => 'Pour les organisations de taille moyenne avec plusieurs collaborateurs.',
                    'features'    => '<ul><li>Pages illimitées</li><li>10 utilisateurs éditeurs</li><li>Support prioritaire 24/7</li><li>CDN et optimisation des images</li><li>Traductions multilingues</li></ul>',
                    'cta_label'   => 'Démarrer le plan Professionnel',
                    'cta_url'     => '/inscription?plan=pro',
                ],
                'pt' => [
                    'name'        => 'Profissional',
                    'price'       => 'US$ 49',
                    'period'      => '/ mês',
                    'description' => 'Para organizações de médio porte com múltiplos colaboradores.',
                    'features'    => '<ul><li>Páginas ilimitadas</li><li>10 usuários editores</li><li>Suporte prioritário 24/7</li><li>CDN e otimização de imagens</li><li>Traduções multilíngues</li></ul>',
                    'cta_label'   => 'Iniciar plano Profissional',
                    'cta_url'     => '/registro?plan=pro',
                ],
            ],
        ];

        foreach ($pricingPlans as $plan) {
            $planBlockId = $blockIds['pricing_plan'] ?? null;
            if ($planBlockId === null) {
                continue;
            }

            $pInstanceId = $this->upsertRecord('cms_block_instances', [
                'block_id'           => $planBlockId,
                'owner_type'         => 'page',
                'owner_id'           => $landingPageId,
                'parent_instance_id' => $pricingGridId,
                'sort_order'         => $plan['sort_order'],
            ], [
                'column_index' => null,
                'is_active'    => 1,
                'block_config'  => json_encode(['featured' => $plan['featured']], JSON_UNESCAPED_UNICODE),
            ]);

            if ($pInstanceId === null) {
                continue;
            }

            foreach (['es', 'en', 'fr', 'pt'] as $lang) {
                $langId = $langIds[$lang] ?? null;
                if ($langId === null || ! isset($plan[$lang])) {
                    continue;
                }
                $this->upsertTranslation($pInstanceId, $langId, $plan[$lang]);
            }
        }

        // ── 6. video_gallery ─────────────────────────────────────────────────
        $this->upsertBlock(
            $landingPageId,
            $blockIds,
            'video_gallery',
            6,
            ['columns' => '3', 'css_class' => 'my-12 scroll-mt-16'],
            [
                'es' => [
                    'title'    => 'Colección de Videos del Starter',
                    'subtitle' => 'Una muestra de videos de YouTube y Vimeo integrados en una grilla interactiva.',
                    'videos'   => [
                        [
                            'video_url'   => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                            'title'       => 'Rick Astley - Never Gonna Give You Up',
                            'description' => 'Un clásico de la cultura de internet para pruebas de reproducción.',
                            'poster'      => $this->mediaReference('https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?w=600&auto=format&fit=crop&q=60'),
                        ],
                        [
                            'video_url'   => 'https://vimeo.com/76979871',
                            'title'       => 'The Mountain (Timelapse)',
                            'description' => 'Un espectacular timelapse de montañas de Vimeo.',
                            'poster'      => $this->mediaReference('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&auto=format&fit=crop&q=60'),
                        ],
                        [
                            'video_url'   => 'https://www.youtube.com/watch?v=ScMzIvxBSi4',
                            'title'       => 'Lo-Fi Beats to Study/Relax',
                            'description' => 'Música ambiental y relajante en vivo.',
                            'poster'      => $this->mediaReference('https://images.unsplash.com/photo-1518609878373-06d740f60d8b?w=600&auto=format&fit=crop&q=60'),
                        ],
                    ],
                ],
                'en' => [
                    'title'    => 'Starter Video Collection',
                    'subtitle' => 'A selection of YouTube and Vimeo videos integrated into a responsive grid.',
                    'videos'   => [
                        [
                            'video_url'   => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                            'title'       => 'Rick Astley - Never Gonna Give You Up',
                            'description' => 'A classic internet culture video for playback testing.',
                            'poster'      => $this->mediaReference('https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?w=600&auto=format&fit=crop&q=60'),
                        ],
                        [
                            'video_url'   => 'https://vimeo.com/76979871',
                            'title'       => 'The Mountain (Timelapse)',
                            'description' => 'A stunning mountain timelapse from Vimeo.',
                            'poster'      => $this->mediaReference('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&auto=format&fit=crop&q=60'),
                        ],
                        [
                            'video_url'   => 'https://www.youtube.com/watch?v=ScMzIvxBSi4',
                            'title'       => 'Lo-Fi Beats to Study/Relax',
                            'description' => 'Ambient chill music stream.',
                            'poster'      => $this->mediaReference('https://images.unsplash.com/photo-1518609878373-06d740f60d8b?w=600&auto=format&fit=crop&q=60'),
                        ],
                    ],
                ],
                'fr' => [
                    'title'    => 'Collection de Vidéos du Starter',
                    'subtitle' => 'Une sélection de vidéos YouTube et Vimeo intégrées dans une grille interactive.',
                    'videos'   => [
                        [
                            'video_url'   => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                            'title'       => 'Rick Astley - Never Gonna Give You Up',
                            'description' => 'Un classique de la culture internet pour tester la lecture vidéo.',
                            'poster'      => $this->mediaReference('https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?w=600&auto=format&fit=crop&q=60'),
                        ],
                        [
                            'video_url'   => 'https://vimeo.com/76979871',
                            'title'       => 'The Mountain (Timelapse)',
                            'description' => 'Un time-lapse spectaculaire de montagnes issu de Vimeo.',
                            'poster'      => $this->mediaReference('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&auto=format&fit=crop&q=60'),
                        ],
                        [
                            'video_url'   => 'https://www.youtube.com/watch?v=ScMzIvxBSi4',
                            'title'       => 'Lo-Fi Beats to Study/Relax',
                            'description' => 'Un flux musical relaxant en direct.',
                            'poster'      => $this->mediaReference('https://images.unsplash.com/photo-1518609878373-06d740f60d8b?w=600&auto=format&fit=crop&q=60'),
                        ],
                    ],
                ],
                'pt' => [
                    'title'    => 'Coleção de Vídeos do Starter',
                    'subtitle' => 'Uma amostra de vídeos do YouTube e Vimeo integrados em uma grade interativa.',
                    'videos'   => [
                        [
                            'video_url'   => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                            'title'       => 'Rick Astley - Never Gonna Give You Up',
                            'description' => 'Um clássico da cultura da internet para testes de reprodução.',
                            'poster'      => $this->mediaReference('https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?w=600&auto=format&fit=crop&q=60'),
                        ],
                        [
                            'video_url'   => 'https://vimeo.com/76979871',
                            'title'       => 'The Mountain (Timelapse)',
                            'description' => 'Um espetacular timelapse de montanhas do Vimeo.',
                            'poster'      => $this->mediaReference('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&auto=format&fit=crop&q=60'),
                        ],
                        [
                            'video_url'   => 'https://www.youtube.com/watch?v=ScMzIvxBSi4',
                            'title'       => 'Lo-Fi Beats to Study/Relax',
                            'description' => 'Música ambiente e relaxante em transmissão ao vivo.',
                            'poster'      => $this->mediaReference('https://images.unsplash.com/photo-1518609878373-06d740f60d8b?w=600&auto=format&fit=crop&q=60'),
                        ],
                    ],
                ],
            ],
            $langIds
        );

        // ── 7. faq_accordion ─────────────────────────────────────────────────
        $this->upsertBlock(
            $landingPageId,
            $blockIds,
            'faq_accordion',
            7,
            ['css_class' => 'my-12'],
            [
                'es' => [
                    'title'       => 'Preguntas Frecuentes',
                    'description' => 'Resolvemos tus dudas principales acerca del CMS y la inicialización de la base de datos.',
                    'faqs'        => [
                        [
                            'question' => '¿Qué versión de PHP requiere este proyecto?',
                            'answer'   => '<p>El proyecto requiere PHP 8.1 o superior, junto con las extensiones curl, intl, mbstring y mysql habilitadas en el servidor.</p>',
                        ],
                        [
                            'question' => '¿Cómo puedo compilar los assets del sitio web?',
                            'answer'   => '<p>El starter utiliza Tailwind CSS para compilar las hojas de estilo. Puedes ejecutar <code>npm run dev</code> o <code>npm run build</code> en la raíz del tema.</p>',
                        ],
                        [
                            'question' => '¿Tiene soporte para bases de datos SQLite?',
                            'answer'   => '<p>Actualmente el proyecto está optimizado y configurado para ejecutarse sobre MySQL / MariaDB por cuestiones de estabilidad.</p>',
                        ],
                    ],
                ],
                'en' => [
                    'title'       => 'Frequently Asked Questions',
                    'description' => 'Answering your main questions about the CMS and database initialization.',
                    'faqs'        => [
                        [
                            'question' => 'What PHP version does this project require?',
                            'answer'   => '<p>The project requires PHP 8.1 or higher, with curl, intl, mbstring, and mysql extensions enabled on your server.</p>',
                        ],
                        [
                            'question' => 'How can I compile the website assets?',
                            'answer'   => '<p>The starter package uses Tailwind CSS for stylesheet compilation. You can run <code>npm run dev</code> or <code>npm run build</code> inside the theme root.</p>',
                        ],
                        [
                            'question' => 'Does it support SQLite databases?',
                            'answer'   => '<p>Currently, the project is optimized and configured to run on MySQL / MariaDB for production stability.</p>',
                        ],
                    ],
                ],
                'fr' => [
                    'title'       => 'Questions Fréquentes',
                    'description' => "Nous répondons à vos principales questions sur le CMS et l'initialisation de la base de données.",
                    'faqs'        => [
                        [
                            'question' => 'Quelle version de PHP ce projet requiert-il ?',
                            'answer'   => '<p>Le projet nécessite PHP 8.1 ou supérieur, avec les extensions curl, intl, mbstring et mysql activées sur le serveur.</p>',
                        ],
                        [
                            'question' => 'Comment puis-je compiler les assets du site web ?',
                            'answer'   => "<p>Le starter utilise Tailwind CSS pour compiler les feuilles de style. Vous pouvez exécuter <code>npm run dev</code> ou <code>npm run build</code> à la racine du thème.</p>",
                        ],
                        [
                            'question' => 'Prend-il en charge les bases de données SQLite ?',
                            'answer'   => "<p>Actuellement, le projet est optimisé et configuré pour fonctionner sur MySQL / MariaDB pour des raisons de stabilité en production.</p>",
                        ],
                    ],
                ],
                'pt' => [
                    'title'       => 'Perguntas Frequentes',
                    'description' => 'Respondemos às suas principais dúvidas sobre o CMS e a inicialização do banco de dados.',
                    'faqs'        => [
                        [
                            'question' => 'Qual versão do PHP este projeto requer?',
                            'answer'   => '<p>O projeto requer PHP 8.1 ou superior, com as extensões curl, intl, mbstring e mysql habilitadas no servidor.</p>',
                        ],
                        [
                            'question' => 'Como posso compilar os assets do site?',
                            'answer'   => '<p>O starter utiliza Tailwind CSS para compilar as folhas de estilo. Você pode executar <code>npm run dev</code> ou <code>npm run build</code> na raiz do tema.</p>',
                        ],
                        [
                            'question' => 'Há suporte para bancos de dados SQLite?',
                            'answer'   => '<p>Atualmente, o projeto está otimizado e configurado para rodar em MySQL / MariaDB por questões de estabilidade em produção.</p>',
                        ],
                    ],
                ],
            ],
            $langIds
        );

        // ── 8. cta ───────────────────────────────────────────────────────────
        $this->upsertBlock(
            $landingPageId,
            $blockIds,
            'cta',
            8,
            ['variant' => 'blue', 'css_class' => ''],
            [
                'es' => [
                    'heading' => '¿Listo para lanzar tu proyecto?',
                    'text'    => 'Descarga el starter kit y comienza a construir interfaces premium hoy mismo.',
                    'label'   => 'Comenzar Ahora',
                    'url'     => '/es/contacto',
                ],
                'en' => [
                    'heading' => 'Ready to launch your project?',
                    'text'    => 'Download the starter kit and start building premium web layouts today.',
                    'label'   => 'Get Started Now',
                    'url'     => '/en/contact',
                ],
                'fr' => [
                    'heading' => 'Prêt à lancer votre projet ?',
                    'text'    => "Téléchargez le starter kit et commencez dès aujourd'hui à construire des interfaces web premium.",
                    'label'   => 'Commencer Maintenant',
                    'url'     => '/fr/contact',
                ],
                'pt' => [
                    'heading' => 'Pronto para lançar seu projeto?',
                    'text'    => 'Baixe o starter kit e comece a construir interfaces web premium hoje mesmo.',
                    'label'   => 'Começar Agora',
                    'url'     => '/pt/contato',
                ],
            ],
            $langIds
        );
    }

    private function upsertPage(): int
    {
        $existing = $this->db->table('cms_pages')
            ->select('cms_pages.id')
            ->join('cms_page_translations', 'cms_page_translations.page_id = cms_pages.id')
            ->where('cms_page_translations.slug', 'landing')
            ->where('cms_pages.deleted_at IS NULL', null, false)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            $pageId = (int) $existing['id'];
            $updatePayload = [
                'page_type'          => 'generic',
                'status'             => 'published',
                'published_at'       => date('Y-m-d H:i:s'),
                'scheduled_at'       => null,
                'sort_order'         => 38,
                'sitemap_priority'   => '0.8',
                'sitemap_changefreq' => 'weekly',
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
                'sort_order'         => 38,
                'sitemap_priority'   => '0.8',
                'sitemap_changefreq' => 'weekly',
                'is_in_sitemap'      => 1,
                'deleted_at'         => null,
            ]);
        }

        if ($pageId === null) {
            throw new \RuntimeException('SiteLandingPageSeeder: unable to seed landing page.');
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

    /** @param string[] $keys  @return array<string, int> */
    private function blockIds(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $rows = $this->db->table('cms_content_blocks')
            ->select('id, block_key')
            ->whereIn('block_key', $keys)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['block_key']] = (int) $row['id'];
        }

        return $map;
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
            echo "SiteLandingPageSeeder: block type '{$blockKey}' not found — skipped.\n";
            return 0;
        }

        $instanceId = $this->upsertRecord('cms_block_instances', [
            'block_id'           => $blockId,
            'owner_type'         => 'page',
            'owner_id'           => $pageId,
            'parent_instance_id' => $parentInstanceId,
            'sort_order'         => $sortOrder,
        ], [
            'column_index'       => null,
            'is_active'          => 1,
            'block_config'       => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
}
