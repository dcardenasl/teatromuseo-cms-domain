# TASKS — ci4-website-builder-domain

> Fuente de verdad para trabajo abierto en este repositorio.
> Los entregables cerrados están en [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).
> Seguimiento global: [`../TASKS.md`](../TASKS.md).
> Tracker depurado el 2026-07-21; no se conservan notas de conversación ni bitácoras de participantes.

## 🔴 En progreso

*(vacío)*

## 🟡 Próximo

*(vacío — las fases Controller→Model y la auditoría de bloques owner-scoped quedaron cerradas;
las decisiones de producto pendientes se mantienen en el tracker global.)*

## ✅ Completadas

- **I18N-SEED-001 — Completar traducciones FR/PT faltantes en seeders de contenido demo (2026-08-01):**
  La auditoría de traducciones del admin (`/admin/cms/translations/audit`) mostraba FR/PT al 77%
  (212/276). Las 6 páginas/recursos que solo seedeaban `es`/`en` eran `SitePortfolioPageSeeder`,
  `SiteComponentsPageSeeder`, `SiteMediaPageSeeder`, `SiteLandingPageSeeder`,
  `PortfolioCollectionSeeder` (colección + 2 categorías + 2 etiquetas + 2 entradas) y
  `CmsSocialLinksChildrenSeeder` (contact page social links). Se agregaron traducciones `fr`/`pt`
  completas a cada bloque/campo y se actualizaron los `foreach (['es','en'] as ...)` hardcodeados
  a `['es','en','fr','pt']` en los helpers `seedChildBlocks`/loops de child-block translation
  (afecta `card_item`, `gallery_item`, `pricing_plan`, entradas de portafolio). De paso se corrigió
  un bug preexistente en `PortfolioCollectionSeeder` donde el caption del bloque `image` de cada
  entrada llevaba el prefijo "Proyecto finalizado: " hardcodeado en español para todos los idiomas
  (ahora usa un mapa de prefijos por idioma). Verificado: `php spark db:seed` de los 6 seeders
  corrió limpio y la auditoría del admin pasó a 100% (276/276) en los 4 idiomas.

- **NULL-CLEAR-001 — Fix "no se puede limpiar un campo nullable vía update" en 8 *UpdateRequestDTO (2026-07-30):**
  `BlockTypeUpdateRequestDTO`, `BlockInstanceUpdateRequestDTO`, `EntryUpdateRequestDTO`,
  `TagUpdateRequestDTO`, `CategoryUpdateRequestDTO`, `CollectionUpdateRequestDTO`,
  `RedirectUpdateRequestDTO`, `MenuUpdateRequestDTO` — mismo bug transversal encontrado en
  event-domain/catalog-domain/Hub: `array_filter($v !== null)` descartaba cualquier campo
  enviado como `null`. Corregido con ternario de una línea por propiedad + `array_key_exists()`
  + acumulador `$mappedFields`, NOT NULL vs nullable decidido por `DESCRIBE` real de cada tabla
  (`cms_content_blocks`, `cms_block_instances`, `cms_entries`, `cms_tags`, `cms_categories`,
  `cms_collections`, `cms_redirects`, `cms_menus`). Caso especial en `CollectionUpdateRequestDTO`:
  `block_template`/`wizard_config` son columnas JSON codificadas manualmente (no vía el cast
  `json` de la Entity) — ahora se pasa un `null` real al limpiar en vez de codificar el string
  literal `"null"`. `MenuUpdateRequestDTO` ya usaba `array_key_exists`/`mappedFields` pero con
  if/else de dos sentencias por campo; se restructuró a ternario de una línea por consistencia
  (comportamiento observable sin cambios). No se tocaron `PageUpdateRequestDTO`,
  `LanguageUpdateRequestDTO`, `SettingUpdateRequestDTO`, `MenuItemUpdateRequestDTO`,
  `FormUpdateRequestDTO`/`FormFieldUpdateRequestDTO` — ya usaban un patrón correcto.
  Este repo escanea `app/DTO` en `phpstan.neon` (a diferencia de event-domain/catalog-domain) y
  ya tenía el `ignoreErrors` scoped para `property.readOnlyAssignNotInConstructor` — el ternario
  de una línea se usó de todas formas por consistencia entre repos, no porque hiciera falta aquí.
  Verificado: `composer cs-fix` limpio, `composer phpstan` → **[OK] No errors** (268 archivos,
  nivel 8), `vendor/bin/phpunit` → 516/516 tests (tras corregir un estado de test-DB obsoleto con
  `php spark tests:prepare-db`, no relacionado con este fix).

- **FILE-GUARD-001 — Endpoints internal/files/* para el Hub (usage-check + invalidate-cache) (2026-07-30):**
  `App\Filters\HubSignatureFilter` (nuevo alias `hubsignature`) verifica llamadas HMAC-firmadas
  del Hub (`hub.internalSecret`/env `HUB_INTERNAL_SECRET`, fail-closed si no está configurado).
  `InternalFileController::usage()`/`invalidateCache()` bajo `internal/files/*`, reutilizando el
  `FileUsageService` ya existente (antes solo expuesto en `permission:cms.entries.read`, sin
  consumidor real). Cierra el gap: el Hub ahora ve usages de este domain antes de borrar un
  archivo, y `HubClient::invalidateFileMetaCache()` (dead code hasta hoy) finalmente se llama.
  Fix de path: `php spark serve` expone rutas bajo `/index.php/...`, lo que rompía la firma
  HMAC — `HubSignatureFilter::normalizePath()` lo normaliza para que la misma firma valide
  igual en dev (spark serve) y producción (rewrite limpio). `InternalFileController` extiende
  `\CodeIgniter\Controller` (no `ApiController`, igual que `HealthController`) y se agregó como
  excepción documentada en `ControllerDtoRequestContractsTest::CONTROLLER_EXCEPTIONS`.
  Verificado end-to-end real contra el Hub (no solo tests): 409 al intentar borrar un archivo en
  uso, invalidación de caché reflejada sin esperar TTL tras `replace()`. `composer quality` ✅
  (516/516 tests).

- **CMS-TEMPLATES-002 — Auditoría de robustez post-implementación de Plantillas Dinámicas (2026-07-29):**
  Segunda pasada tras el cierre de la Fase 3 (por-tipo, config de ruta, fix de migración): la
  migración ALTER de `page_type` (`2026-07-28-000002`) había reaparecido en un commit posterior,
  violando de nuevo `CleanDatabaseBootstrapConventionsTest` (migraciones create-only). Se plegó
  definitivamente en `CreateCmsPages` y se eliminó el ALTER. Se detectaron y corrigieron 2 casos de
  schema drift real entre seeders y `CmsBlockTypeSeeder`/`TeatroMuseoBlockTypeSeeder`: el
  `collection_listing` y los bloques `catalog_item_header`/`event_item_header`/`*_gallery`
  escribían `fallback_image_url`/`fallback_gallery_images`/`fallback_title` en `block_config`/
  `block_data` sin declararlos en su `schema_definition` — ahora declarados. Se detectó que
  commits posteriores añadieron `catalog_item_details`/`catalog_item_content`/`event_item_details`/
  `event_item_content` a las plantillas sin actualizar los tests de contrato del seeder — tests
  actualizados a la estructura real de 4 bloques por plantilla. `composer quality` 100% verde
  (PHPStan L8, CS-Fixer, swagger, arch-drift, i18n-check, seeder contracts: 17 tests / 6309
  assertions).

- **CMS-ENTRY-REF-001 — Bloques de referencia cruzada entre entradas (`entry_reference`):**
  `EntryReferenceResolver`, `BlockReferenceValidator`, `EntryRelationSynchronizer` +
  9 presets de colección propios de TeatroMuseo (`TeatroMuseoCollectionPresets`, separado del
  motor genérico `CollectionBlockPresets` para no acoplar el starter kit a este cliente).
  Auditoría posterior: se revirtió una activación prematura de `fr`/`pt` sin contenido real,
  se envolvió `EntryRelationSynchronizer::sync()` en transacción, y se agregaron tests de
  integración para el resolver y el sincronizador (fallback de idioma, exclusión de
  auto-referencia, limpieza de relaciones huérfanas).

## ⚪ Backlog

*(vacío)*

## 🏗️ Contratos de arquitectura

- **DTO-First:** todo Controller in/out usa DTOs; evitar arrays sin contrato.
- **Services puros:** no conocen HTTP; reciben DTOs y devuelven DTOs o excepciones de dominio.
- **Controllers delgados:** usar `ApiController::handleRequest()`.
- **Autenticación:** este repositorio delega introspección y emisión de tokens al hub.
- **HubClient:** es el único punto de comunicación con el hub.
- **Permisos:** usar separador `.` y rutas por dominio en `app/Config/Routes/v1/`.
- **No tabla users:** usuarios e IAM viven en el hub.
- **Tests:** todo endpoint nuevo necesita Feature test.
- **CRUD nuevo:** preferir `php spark make:crud {Resource} --domain {Domain} --route {slug}`.
- **Calidad:** ejecutar `composer quality` antes de cerrar una tarea.

## 🔧 Referencias

- Histórico: [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md)
- Tracker global: [`../TASKS.md`](../TASKS.md)
