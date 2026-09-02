<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Database\Seeds\Concerns\IdempotentSeederSupport;
use CodeIgniter\Database\Seeder;

final class CmsTeatroMuseoLegalPagesSeeder extends Seeder
{
    use IdempotentSeederSupport;

    public function run(): void
    {
        $languages = $this->languageIds();
        $blockIds = $this->blockIds(['page_header', 'rich_text']);

        if (! isset($languages['es'], $languages['en'], $languages['fr'], $languages['pt'])) {
            echo "CmsTeatroMuseoLegalPagesSeeder: missing languages; skipping.\n";

            return;
        }

        if (! isset($blockIds['page_header'], $blockIds['rich_text'])) {
            echo "CmsTeatroMuseoLegalPagesSeeder: missing block types; skipping.\n";

            return;
        }

        $pageIds = [];
        foreach (array_filter($this->definitions(), static fn (array $definition): bool => $definition['key'] !== 'transparency') as $definition) {
            $pageIds[$definition['key']] = $this->seedLegalPage($definition, $languages, $blockIds);
        }

        $this->seedLegalMenu($languages, $pageIds);
    }

    /**
     * @return list<array{
     *     key: string,
     *     lookup_slugs: list<string>,
     *     sort_order: int,
     *     translations: array<string, array<string, string>>
     * }>
     */
    private function definitions(): array
    {
        return [
            [
                'key' => 'legal_notice',
                'lookup_slugs' => ['aviso-legal', 'legal-notice', 'mentions-legales', 'aviso-juridico'],
                'sort_order' => 100,
                'translations' => [
                    'es' => [
                        'slug' => 'aviso-legal',
                        'title' => 'Aviso Legal',
                        'excerpt' => 'Información legal y de propiedad del sitio de TeatroMuseo.',
                        'meta_title' => 'Aviso Legal | TeatroMuseo',
                        'meta_description' => 'Información legal, propiedad intelectual y uso del sitio de TeatroMuseo.',
                        'heading' => 'Aviso Legal',
                        'subheading' => 'Información legal y de propiedad del sitio de TeatroMuseo.',
                        'content' => <<<'HTML'
<p>Este sitio es administrado por el equipo editorial de TeatroMuseo. El contenido se publica con fines informativos, culturales y de archivo.</p>
<p>Todo texto, imagen, identidad visual, estructura editorial y material audiovisual original está protegido por los derechos correspondientes. La reproducción total o parcial requiere autorización previa.</p>
<p>Para cualquier consulta legal o de uso de contenidos, utiliza la página de contacto del sitio.</p>
HTML,
                    ],
                    'en' => [
                        'slug' => 'legal-notice',
                        'title' => 'Legal Notice',
                        'excerpt' => 'Legal and ownership information for the TeatroMuseo site.',
                        'meta_title' => 'Legal Notice | TeatroMuseo',
                        'meta_description' => 'Legal information, intellectual property, and usage terms for TeatroMuseo.',
                        'heading' => 'Legal Notice',
                        'subheading' => 'Legal and ownership information for the TeatroMuseo site.',
                        'content' => <<<'HTML'
<p>This site is operated by the TeatroMuseo editorial team. Content is published for informational, cultural, and archival purposes.</p>
<p>All original text, imagery, visual identity, editorial structure, and audiovisual material are protected by the relevant rights. Reproduction in whole or in part requires prior authorization.</p>
<p>For legal questions or content-use requests, please use the site contact page.</p>
HTML,
                    ],
                    'fr' => [
                        'slug' => 'mentions-legales',
                        'title' => 'Mentions légales',
                        'excerpt' => 'Informations légales et de propriété du site TeatroMuseo.',
                        'meta_title' => 'Mentions légales | TeatroMuseo',
                        'meta_description' => 'Informations juridiques, propriété intellectuelle et conditions d’utilisation de TeatroMuseo.',
                        'heading' => 'Mentions légales',
                        'subheading' => 'Informations légales et de propriété du site TeatroMuseo.',
                        'content' => <<<'HTML'
<p>Ce site est administré par l’équipe éditoriale de TeatroMuseo. Les contenus sont publiés à des fins informatives, culturelles et archivistiques.</p>
<p>Tout texte, image, identité visuelle, structure éditoriale et matériel audiovisuel original est protégé par les droits correspondants. Toute reproduction totale ou partielle nécessite une autorisation préalable.</p>
<p>Pour toute question juridique ou demande liée à l’usage des contenus, utilisez la page de contact du site.</p>
HTML,
                    ],
                    'pt' => [
                        'slug' => 'aviso-juridico',
                        'title' => 'Aviso Jurídico',
                        'excerpt' => 'Informações legais e de propriedade do site do TeatroMuseo.',
                        'meta_title' => 'Aviso Jurídico | TeatroMuseo',
                        'meta_description' => 'Informações jurídicas, propriedade intelectual e uso do site do TeatroMuseo.',
                        'heading' => 'Aviso Jurídico',
                        'subheading' => 'Informações legais e de propriedade do site do TeatroMuseo.',
                        'content' => <<<'HTML'
<p>Este site é administrado pela equipe editorial do TeatroMuseo. O conteúdo é publicado para fins informativos, culturais e de arquivo.</p>
<p>Todo texto, imagem, identidade visual, estrutura editorial e material audiovisual original está protegido pelos direitos correspondentes. A reprodução total ou parcial requer autorização prévia.</p>
<p>Para dúvidas jurídicas ou solicitações de uso de conteúdo, utilize a página de contato do site.</p>
HTML,
                    ],
                ],
            ],
            [
                'key' => 'privacy_policy',
                'lookup_slugs' => ['politica-privacidad', 'privacy-policy', 'politique-confidentialite', 'politica-privacidade'],
                'sort_order' => 110,
                'translations' => [
                    'es' => [
                        'slug' => 'politica-privacidad',
                        'title' => 'Política de Privacidad',
                        'excerpt' => 'Cómo recopilamos, usamos y protegemos tus datos personales.',
                        'meta_title' => 'Política de Privacidad | TeatroMuseo',
                        'meta_description' => 'Tratamiento de datos personales y privacidad en TeatroMuseo.',
                        'heading' => 'Política de Privacidad',
                        'subheading' => 'Cómo recopilamos, usamos y protegemos tus datos personales.',
                        'content' => <<<'HTML'
<p>Recopilamos únicamente los datos que nos entregas de forma voluntaria mediante formularios de contacto, suscripción o gestión editorial.</p>
<p>Usamos esa información para responder consultas, gestionar solicitudes, mejorar el sitio y cumplir obligaciones legales. No vendemos tus datos ni los usamos para fines ajenos al proyecto.</p>
<p>Puedes solicitar acceso, rectificación, actualización o eliminación de tus datos escribiendo desde la página de contacto.</p>
HTML,
                    ],
                    'en' => [
                        'slug' => 'privacy-policy',
                        'title' => 'Privacy Policy',
                        'excerpt' => 'How we collect, use, and protect your personal data.',
                        'meta_title' => 'Privacy Policy | TeatroMuseo',
                        'meta_description' => 'Personal data processing and privacy at TeatroMuseo.',
                        'heading' => 'Privacy Policy',
                        'subheading' => 'How we collect, use, and protect your personal data.',
                        'content' => <<<'HTML'
<p>We only collect the data you voluntarily provide through contact forms, subscriptions, or editorial workflows.</p>
<p>We use that information to answer inquiries, handle requests, improve the site, and meet legal obligations. We do not sell your data or use it for unrelated purposes.</p>
<p>You can request access, correction, update, or deletion of your data by writing through the contact page.</p>
HTML,
                    ],
                    'fr' => [
                        'slug' => 'politique-confidentialite',
                        'title' => 'Politique de confidentialité',
                        'excerpt' => 'Comment nous collectons, utilisons et protégeons vos données personnelles.',
                        'meta_title' => 'Politique de confidentialité | TeatroMuseo',
                        'meta_description' => 'Traitement des données personnelles et confidentialité chez TeatroMuseo.',
                        'heading' => 'Politique de confidentialité',
                        'subheading' => 'Comment nous collectons, utilisons et protégeons vos données personnelles.',
                        'content' => <<<'HTML'
<p>Nous collectons uniquement les données que vous fournissez volontairement via les formulaires de contact, d’abonnement ou les flux éditoriaux.</p>
<p>Nous utilisons ces informations pour répondre aux demandes, traiter les requêtes, améliorer le site et respecter les obligations légales. Nous ne vendons pas vos données et ne les utilisons pas à des fins étrangères au projet.</p>
<p>Vous pouvez demander l’accès, la correction, la mise à jour ou la suppression de vos données en écrivant via la page de contact.</p>
HTML,
                    ],
                    'pt' => [
                        'slug' => 'politica-privacidade',
                        'title' => 'Política de Privacidade',
                        'excerpt' => 'Como coletamos, usamos e protegemos seus dados pessoais.',
                        'meta_title' => 'Política de Privacidade | TeatroMuseo',
                        'meta_description' => 'Tratamento de dados pessoais e privacidade no TeatroMuseo.',
                        'heading' => 'Política de Privacidade',
                        'subheading' => 'Como coletamos, usamos e protegemos seus dados pessoais.',
                        'content' => <<<'HTML'
<p>Coletamos apenas os dados que você fornece voluntariamente por meio de formulários de contato, assinatura ou fluxos editoriais.</p>
<p>Usamos essas informações para responder solicitações, tratar pedidos, melhorar o site e cumprir obrigações legais. Não vendemos seus dados nem os usamos para finalidades alheias ao projeto.</p>
<p>Você pode solicitar acesso, correção, atualização ou exclusão dos seus dados escrevendo pela página de contato.</p>
HTML,
                    ],
                ],
            ],
            [
                'key' => 'cookie_policy',
                'lookup_slugs' => ['politica-cookies', 'cookie-policy', 'politique-cookies', 'politica-cookies'],
                'sort_order' => 120,
                'translations' => [
                    'es' => [
                        'slug' => 'politica-cookies',
                        'title' => 'Política de Cookies',
                        'excerpt' => 'Qué cookies usamos y cómo puedes administrarlas.',
                        'meta_title' => 'Política de Cookies | TeatroMuseo',
                        'meta_description' => 'Uso de cookies y tecnologías similares en TeatroMuseo.',
                        'heading' => 'Política de Cookies',
                        'subheading' => 'Qué cookies usamos y cómo puedes administrarlas.',
                        'content' => <<<'HTML'
<p>Usamos cookies técnicas para que el sitio funcione correctamente y cookies de preferencia para recordar opciones como idioma o consentimiento.</p>
<p>Cuando corresponda, también podemos usar cookies de analítica para entender qué secciones del sitio se visitan más y mejorar la experiencia editorial.</p>
<p>Puedes borrar o bloquear cookies desde tu navegador. Si lo haces, algunas funciones del sitio podrían verse limitadas.</p>
HTML,
                    ],
                    'en' => [
                        'slug' => 'cookie-policy',
                        'title' => 'Cookie Policy',
                        'excerpt' => 'What cookies we use and how you can manage them.',
                        'meta_title' => 'Cookie Policy | TeatroMuseo',
                        'meta_description' => 'Cookie and similar technology usage at TeatroMuseo.',
                        'heading' => 'Cookie Policy',
                        'subheading' => 'What cookies we use and how you can manage them.',
                        'content' => <<<'HTML'
<p>We use technical cookies to keep the site working properly and preference cookies to remember choices such as language or consent.</p>
<p>When relevant, we may also use analytics cookies to understand which areas of the site are visited most and improve the editorial experience.</p>
<p>You can delete or block cookies from your browser. If you do, some site features may be limited.</p>
HTML,
                    ],
                    'fr' => [
                        'slug' => 'politique-cookies',
                        'title' => 'Politique de cookies',
                        'excerpt' => 'Les cookies utilisés et la façon de les gérer.',
                        'meta_title' => 'Politique de cookies | TeatroMuseo',
                        'meta_description' => 'Utilisation des cookies et technologies similaires chez TeatroMuseo.',
                        'heading' => 'Politique de cookies',
                        'subheading' => 'Les cookies utilisés et la façon de les gérer.',
                        'content' => <<<'HTML'
<p>Nous utilisons des cookies techniques pour assurer le bon fonctionnement du site et des cookies de préférence pour mémoriser des choix comme la langue ou le consentement.</p>
<p>Le cas échéant, nous pouvons aussi utiliser des cookies d’analyse pour comprendre quelles sections du site sont les plus consultées et améliorer l’expérience éditoriale.</p>
<p>Vous pouvez supprimer ou bloquer les cookies depuis votre navigateur. Dans ce cas, certaines fonctionnalités du site peuvent être limitées.</p>
HTML,
                    ],
                    'pt' => [
                        'slug' => 'politica-cookies',
                        'title' => 'Política de Cookies',
                        'excerpt' => 'Quais cookies usamos e como você pode administrá-los.',
                        'meta_title' => 'Política de Cookies | TeatroMuseo',
                        'meta_description' => 'Uso de cookies e tecnologias semelhantes no TeatroMuseo.',
                        'heading' => 'Política de Cookies',
                        'subheading' => 'Quais cookies usamos e como você pode administrá-los.',
                        'content' => <<<'HTML'
<p>Usamos cookies técnicos para manter o site funcionando corretamente e cookies de preferência para lembrar opções como idioma ou consentimento.</p>
<p>Quando necessário, também podemos usar cookies de análise para entender quais áreas do site são mais visitadas e melhorar a experiência editorial.</p>
<p>Você pode excluir ou bloquear cookies no navegador. Nesse caso, alguns recursos do site podem ficar limitados.</p>
HTML,
                    ],
                ],
            ],
            [
                'key' => 'data_rights',
                'lookup_slugs' => ['derechos-datos', 'data-rights', 'droits-donnees', 'direitos-dados'],
                'sort_order' => 130,
                'translations' => [
                    'es' => [
                        'slug' => 'derechos-datos',
                        'title' => 'Derechos de Datos',
                        'excerpt' => 'Cómo ejercer tus derechos sobre la información personal.',
                        'meta_title' => 'Derechos de Datos | TeatroMuseo',
                        'meta_description' => 'Derechos de acceso, rectificación, eliminación y oposición en TeatroMuseo.',
                        'heading' => 'Derechos de Datos',
                        'subheading' => 'Cómo ejercer tus derechos sobre la información personal.',
                        'content' => <<<'HTML'
<p>Si nos enviaste datos personales, puedes ejercer tus derechos de acceso, rectificación, actualización, eliminación y oposición.</p>
<p>Para hacerlo, escribe desde la página de contacto indicando qué dato quieres revisar o modificar y te responderemos por el mismo canal.</p>
<p>También puedes pedir que dejemos de usar tus datos para comunicaciones futuras si ya no deseas recibirlas.</p>
HTML,
                    ],
                    'en' => [
                        'slug' => 'data-rights',
                        'title' => 'Data Rights',
                        'excerpt' => 'How to exercise your rights over personal information.',
                        'meta_title' => 'Data Rights | TeatroMuseo',
                        'meta_description' => 'Access, correction, deletion, and objection rights at TeatroMuseo.',
                        'heading' => 'Data Rights',
                        'subheading' => 'How to exercise your rights over personal information.',
                        'content' => <<<'HTML'
<p>If you have shared personal data with us, you can exercise your rights of access, correction, update, deletion, and objection.</p>
<p>To do so, write through the contact page and tell us which data you want to review or change. We will reply through the same channel.</p>
<p>You can also ask us to stop using your data for future communications if you no longer want to receive them.</p>
HTML,
                    ],
                    'fr' => [
                        'slug' => 'droits-donnees',
                        'title' => 'Droits sur les données',
                        'excerpt' => 'Comment exercer vos droits sur les informations personnelles.',
                        'meta_title' => 'Droits sur les données | TeatroMuseo',
                        'meta_description' => 'Droits d’accès, de correction, de suppression et d’opposition chez TeatroMuseo.',
                        'heading' => 'Droits sur les données',
                        'subheading' => 'Comment exercer vos droits sur les informations personnelles.',
                        'content' => <<<'HTML'
<p>Si vous nous avez transmis des données personnelles, vous pouvez exercer vos droits d’accès, de rectification, de mise à jour, de suppression et d’opposition.</p>
<p>Pour cela, écrivez via la page de contact en précisant les données que vous souhaitez consulter ou modifier. Nous répondrons par le même canal.</p>
<p>Vous pouvez aussi demander que nous cessions d’utiliser vos données pour de futures communications si vous ne souhaitez plus les recevoir.</p>
HTML,
                    ],
                    'pt' => [
                        'slug' => 'direitos-dados',
                        'title' => 'Direitos sobre os Dados',
                        'excerpt' => 'Como exercer seus direitos sobre informações pessoais.',
                        'meta_title' => 'Direitos sobre os Dados | TeatroMuseo',
                        'meta_description' => 'Direitos de acesso, correção, exclusão e oposição no TeatroMuseo.',
                        'heading' => 'Direitos sobre os Dados',
                        'subheading' => 'Como exercer seus direitos sobre informações pessoais.',
                        'content' => <<<'HTML'
<p>Se você compartilhou dados pessoais conosco, pode exercer seus direitos de acesso, correção, atualização, exclusão e oposição.</p>
<p>Para isso, escreva pela página de contato indicando quais dados deseja revisar ou alterar. Responderemos pelo mesmo canal.</p>
<p>Você também pode pedir que deixemos de usar seus dados para comunicações futuras caso não queira mais recebê-las.</p>
HTML,
                    ],
                ],
            ],
            [
                'key' => 'terms_of_service',
                'lookup_slugs' => ['terminos-servicio', 'terms-of-service', 'conditions-utilisation', 'termos-uso'],
                'sort_order' => 140,
                'translations' => [
                    'es' => [
                        'slug' => 'terminos-servicio',
                        'title' => 'Términos de Servicio',
                        'excerpt' => 'Reglas básicas para usar el sitio y sus contenidos.',
                        'meta_title' => 'Términos de Servicio | TeatroMuseo',
                        'meta_description' => 'Condiciones de uso del sitio de TeatroMuseo.',
                        'heading' => 'Términos de Servicio',
                        'subheading' => 'Reglas básicas para usar el sitio y sus contenidos.',
                        'content' => <<<'HTML'
<p>El sitio está pensado para uso personal, cultural y educativo. No está permitido alterar el contenido, suplantar identidades o usar el material para fines ilícitos.</p>
<p>TeatroMuseo puede actualizar contenidos, enlaces y estructura editorial cuando sea necesario para mantener el sitio vigente y confiable.</p>
<p>Si detectas un error, una omisión o un problema de acceso, escríbenos desde la página de contacto.</p>
HTML,
                    ],
                    'en' => [
                        'slug' => 'terms-of-service',
                        'title' => 'Terms of Service',
                        'excerpt' => 'Basic rules for using the site and its content.',
                        'meta_title' => 'Terms of Service | TeatroMuseo',
                        'meta_description' => 'Usage terms for the TeatroMuseo site.',
                        'heading' => 'Terms of Service',
                        'subheading' => 'Basic rules for using the site and its content.',
                        'content' => <<<'HTML'
<p>The site is intended for personal, cultural, and educational use. It is not allowed to alter the content, impersonate identities, or use the material for unlawful purposes.</p>
<p>TeatroMuseo may update content, links, and editorial structure whenever needed to keep the site current and reliable.</p>
<p>If you notice an error, omission, or access problem, please write to us through the contact page.</p>
HTML,
                    ],
                    'fr' => [
                        'slug' => 'conditions-utilisation',
                        'title' => 'Conditions d’utilisation',
                        'excerpt' => 'Règles de base pour utiliser le site et ses contenus.',
                        'meta_title' => 'Conditions d’utilisation | TeatroMuseo',
                        'meta_description' => 'Conditions d’utilisation du site TeatroMuseo.',
                        'heading' => 'Conditions d’utilisation',
                        'subheading' => 'Règles de base pour utiliser le site et ses contenus.',
                        'content' => <<<'HTML'
<p>Le site est destiné à un usage personnel, culturel et éducatif. Il est interdit de modifier les contenus, d’usurper une identité ou d’utiliser le matériel à des fins illicites.</p>
<p>TeatroMuseo peut mettre à jour les contenus, les liens et la structure éditoriale lorsque cela est nécessaire pour maintenir un site actuel et fiable.</p>
<p>Si vous constatez une erreur, une omission ou un problème d’accès, écrivez-nous via la page de contact.</p>
HTML,
                    ],
                    'pt' => [
                        'slug' => 'termos-uso',
                        'title' => 'Termos de Uso',
                        'excerpt' => 'Regras básicas para usar o site e seus conteúdos.',
                        'meta_title' => 'Termos de Uso | TeatroMuseo',
                        'meta_description' => 'Condições de uso do site do TeatroMuseo.',
                        'heading' => 'Termos de Uso',
                        'subheading' => 'Regras básicas para usar o site e seus conteúdos.',
                        'content' => <<<'HTML'
<p>O site é destinado ao uso pessoal, cultural e educativo. Não é permitido alterar conteúdos, se passar por outra pessoa ou usar o material para fins ilícitos.</p>
<p>O TeatroMuseo pode atualizar conteúdos, links e estrutura editorial sempre que necessário para manter o site atual e confiável.</p>
<p>Se você notar um erro, omissão ou problema de acesso, escreva para nós pela página de contato.</p>
HTML,
                    ],
                ],
            ],
            [
                'key' => 'transparency',
                'lookup_slugs' => ['transparencia', 'transparency', 'transparence', 'transparencia'],
                'sort_order' => 150,
                'translations' => [
                    'es' => [
                        'slug' => 'transparencia',
                        'title' => 'Transparencia',
                        'excerpt' => 'Cómo organizamos la información y mantenemos el sitio actualizado.',
                        'meta_title' => 'Transparencia | TeatroMuseo',
                        'meta_description' => 'Criterios editoriales, actualizaciones y transparencia del sitio TeatroMuseo.',
                        'heading' => 'Transparencia',
                        'subheading' => 'Cómo organizamos la información y mantenemos el sitio actualizado.',
                        'content' => <<<'HTML'
<p>TeatroMuseo publica su contenido con criterios editoriales claros: cada colección, página y entrada debe ser útil, comprensible y fácil de recorrer.</p>
<p>Cuando algo cambia, buscamos actualizar primero la información visible y luego las referencias internas para que el sitio mantenga coherencia entre idiomas y secciones.</p>
<p>Si encuentras un dato desactualizado, puedes avisarnos desde la página de contacto para revisarlo.</p>
HTML,
                    ],
                    'en' => [
                        'slug' => 'transparency',
                        'title' => 'Transparency',
                        'excerpt' => 'How we organize information and keep the site updated.',
                        'meta_title' => 'Transparency | TeatroMuseo',
                        'meta_description' => 'Editorial criteria, updates, and transparency at TeatroMuseo.',
                        'heading' => 'Transparency',
                        'subheading' => 'How we organize information and keep the site updated.',
                        'content' => <<<'HTML'
<p>TeatroMuseo publishes content using clear editorial criteria: each collection, page, and entry should be useful, understandable, and easy to browse.</p>
<p>When something changes, we try to update the visible information first and then the internal references so the site stays coherent across languages and sections.</p>
<p>If you spot outdated information, you can let us know through the contact page so we can review it.</p>
HTML,
                    ],
                    'fr' => [
                        'slug' => 'transparence',
                        'title' => 'Transparence',
                        'excerpt' => 'Comment nous organisons l’information et gardons le site à jour.',
                        'meta_title' => 'Transparence | TeatroMuseo',
                        'meta_description' => 'Critères éditoriaux, mises à jour et transparence chez TeatroMuseo.',
                        'heading' => 'Transparence',
                        'subheading' => 'Comment nous organisons l’information et gardons le site à jour.',
                        'content' => <<<'HTML'
<p>TeatroMuseo publie ses contenus selon des critères éditoriaux clairs : chaque collection, page et entrée doit être utile, compréhensible et facile à parcourir.</p>
<p>Lorsque quelque chose change, nous essayons de mettre à jour d’abord l’information visible puis les références internes afin que le site reste cohérent entre les langues et les sections.</p>
<p>Si vous repérez une information obsolète, vous pouvez nous en informer via la page de contact pour que nous la révisions.</p>
HTML,
                    ],
                    'pt' => [
                        'slug' => 'transparencia',
                        'title' => 'Transparência',
                        'excerpt' => 'Como organizamos as informações e mantemos o site atualizado.',
                        'meta_title' => 'Transparência | TeatroMuseo',
                        'meta_description' => 'Critérios editoriais, atualizações e transparência no TeatroMuseo.',
                        'heading' => 'Transparência',
                        'subheading' => 'Como organizamos as informações e mantemos o site atualizado.',
                        'content' => <<<'HTML'
<p>O TeatroMuseo publica seus conteúdos seguindo critérios editoriais claros: cada coleção, página e entrada deve ser útil, compreensível e fácil de navegar.</p>
<p>Quando algo muda, tentamos atualizar primeiro as informações visíveis e depois as referências internas para que o site permaneça coerente entre idiomas e seções.</p>
<p>Se você encontrar uma informação desatualizada, pode avisar pela página de contato para que possamos revisá-la.</p>
HTML,
                    ],
                ],
            ],
            [
                'key' => 'accessibility',
                'lookup_slugs' => ['accesibilidad', 'accessibility', 'accessibilite', 'acessibilidade'],
                'sort_order' => 160,
                'translations' => [
                    'es' => [
                        'slug' => 'accesibilidad',
                        'title' => 'Accesibilidad',
                        'excerpt' => 'Nuestro compromiso para que el sitio sea usable por más personas.',
                        'meta_title' => 'Accesibilidad | TeatroMuseo',
                        'meta_description' => 'Compromisos y mejoras de accesibilidad del sitio TeatroMuseo.',
                        'heading' => 'Accesibilidad',
                        'subheading' => 'Nuestro compromiso para que el sitio sea usable por más personas.',
                        'content' => <<<'HTML'
<p>Trabajamos para que el sitio sea claro, navegable y usable con teclado, lectores de pantalla y distintos dispositivos.</p>
<p>Priorizamos contraste suficiente, textos comprensibles, estructura semántica y contenido alternativo cuando corresponde.</p>
<p>Si encuentras una barrera de acceso, escríbenos desde la página de contacto y la revisaremos para mejorarla.</p>
HTML,
                    ],
                    'en' => [
                        'slug' => 'accessibility',
                        'title' => 'Accessibility',
                        'excerpt' => 'Our commitment to make the site usable for more people.',
                        'meta_title' => 'Accessibility | TeatroMuseo',
                        'meta_description' => 'Accessibility commitments and improvements for the TeatroMuseo site.',
                        'heading' => 'Accessibility',
                        'subheading' => 'Our commitment to make the site usable for more people.',
                        'content' => <<<'HTML'
<p>We work to keep the site clear, navigable, and usable with a keyboard, screen readers, and different devices.</p>
<p>We prioritize sufficient contrast, understandable text, semantic structure, and alternative content when needed.</p>
<p>If you find an access barrier, please write to us through the contact page and we will review it to improve the experience.</p>
HTML,
                    ],
                    'fr' => [
                        'slug' => 'accessibilite',
                        'title' => 'Accessibilité',
                        'excerpt' => 'Notre engagement pour rendre le site utilisable par plus de personnes.',
                        'meta_title' => 'Accessibilité | TeatroMuseo',
                        'meta_description' => 'Engagements et améliorations d’accessibilité du site TeatroMuseo.',
                        'heading' => 'Accessibilité',
                        'subheading' => 'Notre engagement pour rendre le site utilisable par plus de personnes.',
                        'content' => <<<'HTML'
<p>Nous travaillons pour que le site soit clair, navigable et utilisable au clavier, avec des lecteurs d’écran et sur différents appareils.</p>
<p>Nous privilégions un contraste suffisant, des textes compréhensibles, une structure sémantique et des contenus alternatifs lorsque c’est nécessaire.</p>
<p>Si vous trouvez un obstacle d’accès, écrivez-nous via la page de contact et nous le réviserons pour l’améliorer.</p>
HTML,
                    ],
                    'pt' => [
                        'slug' => 'acessibilidade',
                        'title' => 'Acessibilidade',
                        'excerpt' => 'Nosso compromisso para que o site seja útil para mais pessoas.',
                        'meta_title' => 'Acessibilidade | TeatroMuseo',
                        'meta_description' => 'Compromissos e melhorias de acessibilidade do site TeatroMuseo.',
                        'heading' => 'Acessibilidade',
                        'subheading' => 'Nosso compromisso para que o site seja útil para mais pessoas.',
                        'content' => <<<'HTML'
<p>Trabalhamos para que o site seja claro, navegável e utilizável com teclado, leitores de tela e diferentes dispositivos.</p>
<p>Priorizamos contraste suficiente, textos compreensíveis, estrutura semântica e conteúdo alternativo quando necessário.</p>
<p>Se você encontrar uma barreira de acesso, escreva para nós pela página de contato e vamos revisá-la para melhorar a experiência.</p>
HTML,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array{
     *     key: string,
     *     lookup_slugs: list<string>,
     *     sort_order: int,
     *     translations: array<string, array<string, string>>
     * } $definition
     * @param array<string, int> $languages
     * @param array<string, int> $blockIds
     */
    private function seedLegalPage(array $definition, array $languages, array $blockIds): int
    {
        $pageId = $this->upsertPage($definition['lookup_slugs'], $definition['sort_order'], $definition['key']);
        $this->upsertPageTranslations($pageId, $definition['translations'], $languages);
        $this->upsertBlock($pageId, $blockIds, 'page_header', 1, [
            'bg_color' => 'bg-slate-100',
            'css_class' => '',
        ], $this->headerTranslations($definition['translations']), $languages);

        $this->upsertBlock($pageId, $blockIds, 'rich_text', 2, [
            'css_class' => '',
        ], $this->bodyTranslations($definition['translations']), $languages);

        return $pageId;
    }

    /**
     * @param list<string> $lookupSlugs
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
            'sitemap_priority' => '0.4',
            'sitemap_changefreq' => 'monthly',
            'is_in_sitemap' => 1,
            'deleted_at' => null,
        ];

        if ($pageKey === 'accessibility') {
            $payload['sitemap_priority'] = '0.3';
        }

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $pageId = $this->createRecord('cms_pages', $payload);
        if ($pageId === null) {
            throw new \RuntimeException(sprintf('CmsTeatroMuseoLegalPagesSeeder: unable to seed %s page.', $pageKey));
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

            $pageTranslation = [
                'slug' => $translation['slug'],
                'title' => $translation['title'],
                'excerpt' => $translation['excerpt'],
                'meta_title' => $translation['meta_title'],
                'meta_description' => $translation['meta_description'],
                'canonical_url' => null,
                'robots' => 'index, follow',
                'schema_data' => null,
            ];

            $slug = (string) $pageTranslation['slug'];
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
            ], $pageTranslation);
        }
    }

    /**
     * @param array<string, array<string, string>> $translations
     * @return array<string, array<string, string>>
     */
    private function headerTranslations(array $translations): array
    {
        $headers = [];
        foreach ($translations as $language => $translation) {
            $headers[$language] = [
                'heading' => $translation['heading'],
                'subheading' => $translation['subheading'],
            ];
        }

        return $headers;
    }

    /**
     * @param array<string, array<string, string>> $translations
     * @return array<string, array<string, string>>
     */
    private function bodyTranslations(array $translations): array
    {
        $body = [];
        foreach ($translations as $language => $translation) {
            $body[$language] = [
                'content' => $translation['content'],
            ];
        }

        return $body;
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

    /**
     * @param array<string, int> $languages
     * @param array<string, int> $pageIds
     */
    private function seedLegalMenu(array $languages, array $pageIds): void
    {
        $menuId = $this->upsertMenu('legal', 'footer_secondary', [
            'es' => 'Legal',
            'en' => 'Legal',
            'fr' => 'Légal',
            'pt' => 'Legal',
        ], $languages);

        $keepIds = [];

        $definitions = [
            [
                'key' => 'legal_notice',
                'sort_order' => 1,
                'labels' => [
                    'es' => 'Aviso Legal',
                    'en' => 'Legal Notice',
                    'fr' => 'Mentions légales',
                    'pt' => 'Aviso Jurídico',
                ],
            ],
            [
                'key' => 'privacy_policy',
                'sort_order' => 2,
                'labels' => [
                    'es' => 'Política de Privacidad',
                    'en' => 'Privacy Policy',
                    'fr' => 'Politique de confidentialité',
                    'pt' => 'Política de Privacidade',
                ],
            ],
            [
                'key' => 'cookie_policy',
                'sort_order' => 3,
                'labels' => [
                    'es' => 'Política de Cookies',
                    'en' => 'Cookie Policy',
                    'fr' => 'Politique de cookies',
                    'pt' => 'Política de Cookies',
                ],
            ],
            [
                'key' => 'data_rights',
                'sort_order' => 4,
                'labels' => [
                    'es' => 'Derechos de Datos',
                    'en' => 'Data Rights',
                    'fr' => 'Droits sur les données',
                    'pt' => 'Direitos sobre os Dados',
                ],
            ],
            [
                'key' => 'terms_of_service',
                'sort_order' => 5,
                'labels' => [
                    'es' => 'Términos de Servicio',
                    'en' => 'Terms of Service',
                    'fr' => 'Conditions d’utilisation',
                    'pt' => 'Termos de Uso',
                ],
            ],
            [
                'key' => 'accessibility',
                'sort_order' => 7,
                'labels' => [
                    'es' => 'Accesibilidad',
                    'en' => 'Accessibility',
                    'fr' => 'Accessibilité',
                    'pt' => 'Acessibilidade',
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $pageId = $pageIds[$definition['key']] ?? null;
            if ($pageId === null) {
                continue;
            }

            $itemId = $this->upsertMenuItem($menuId, 'page', [
                'parent_id' => null,
                'page_id' => $pageId,
                'entry_id' => null,
                'collection_id' => null,
                'sort_order' => $definition['sort_order'],
            ], $definition['labels'], $languages);

            if ($itemId !== null) {
                $keepIds[] = $itemId;
            }
        }

        $this->pruneMenuItems($menuId, $keepIds);
    }

    /**
     * @param array<string, string> $translations
     */
    private function upsertMenu(string $menuKey, string $location, array $translations, array $languages): int
    {
        $menuId = $this->upsertRecord('cms_menus', ['menu_key' => $menuKey], [
            'location' => $location,
            'is_active' => 1,
            'deleted_at' => null,
        ]);

        if ($menuId === null) {
            throw new \RuntimeException(sprintf('Unable to seed menu "%s".', $menuKey));
        }

        foreach ($translations as $languageCode => $name) {
            $languageId = $languages[$languageCode] ?? null;
            if ($languageId === null) {
                continue;
            }

            $this->upsertRecord('cms_menu_translations', [
                'menu_id' => $menuId,
                'language_id' => $languageId,
            ], ['name' => $name]);
        }

        return $menuId;
    }

    /**
     * @param array{parent_id: int|null, page_id: int|null, entry_id: int|null, collection_id: int|null, sort_order: int} $references
     * @param array<string, string> $translations
     */
    private function upsertMenuItem(int $menuId, string $linkType, array $references, array $translations, array $languages): ?int
    {
        $itemId = $this->upsertRecord('cms_menu_items', [
            'menu_id' => $menuId,
            'parent_id' => $references['parent_id'],
            'link_type' => $linkType,
            'page_id' => $references['page_id'],
            'entry_id' => $references['entry_id'],
            'collection_id' => $references['collection_id'],
            'sort_order' => $references['sort_order'],
        ], [
            'link_target' => '_self',
            'icon' => null,
            'css_class' => null,
            'is_active' => 1,
        ]);

        if ($itemId === null) {
            throw new \RuntimeException(sprintf('Unable to seed menu item "%s".', $linkType));
        }

        foreach ($translations as $languageCode => $label) {
            $languageId = $languages[$languageCode] ?? null;
            if ($languageId === null) {
                continue;
            }

            $this->upsertRecord('cms_menu_item_translations', [
                'menu_item_id' => $itemId,
                'language_id' => $languageId,
            ], [
                'label' => $label,
                'custom_url' => null,
            ]);
        }

        return $itemId;
    }

    /**
     * @param list<int> $keepIds
     */
    private function pruneMenuItems(int $menuId, array $keepIds): void
    {
        // Custom legal links are editorial content and must survive bootstrap.
        unset($menuId, $keepIds);
    }
}
