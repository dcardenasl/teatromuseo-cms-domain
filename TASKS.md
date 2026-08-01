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

- **CLEANUP-001 — `EntryService`/`PageService` no limpiaban sus `cms_block_instances` al borrar (2026-08-01):**
  `DELETE /cms/entries/{id}` y `DELETE /cms/pages/{id}` soft-eliminan la fila (`useSoftDeletes =
  true` en ambos modelos) pero nunca tocaban sus `cms_block_instances` (`useSoftDeletes = false`
  — esa tabla no tiene soft-delete), dejándolas huérfanas. Como esas filas seguían existiendo,
  `FileUsageService::getUsagesByHubFileId()` (que lee `cms_file_references`, no
  `cms_block_instances` directamente) seguía reportando "en uso" para cualquier archivo del Hub
  referenciado por un bloque de una entrada/página ya "borrada", bloqueando
  `DELETE /files/{id}` en el hub con 409 indefinidamente. Encontrado limpiando datos de prueba
  de `legacy:apply` (ver LEGACY-MAP-015). Creada `App\Libraries\Cms\BlockInstancePurger`
  (`purgeForOwner(string $ownerType, int $ownerId): int`), que replica el alcance exacto de
  `IdempotentSeederSupport::resetPageBlocks()` (borra `cms_block_instance_translations` y luego
  `cms_block_instances`) — **sin** tocar `cms_file_references` explícitamente, porque
  `cms_file_references.block_instance_id` ya tiene `ON DELETE CASCADE` hacia
  `cms_block_instances.id` (`2026-06-11-070008_CreateCmsBlocks.php`), confirmado con un test de
  integración que verifica que `FileUsageService` reporta 0 usos tras el purge. Se corrigió
  tanto `EntryService::afterDelete()` como `PageService::afterDelete()` — el mismo bug existía
  en ambos, no sólo en entries. Wiring vía `CmsDomainServices::blockInstancePurger()`.
  Verificado: `composer quality` ✅ (PHPStan 0 errores, 501/501 tests — 2 nuevos en
  `BlockInstancePurgerTest`, más las aserciones de mock actualizadas en
  `EntryServiceTest`/`PageServiceTest::testDestroyInvalidatesCache` — 1 skip preexistente no
  relacionado).

- **TEST-ISO-001 — `PublicEntryControllerTest` dependía de una llamada HTTP real al Hub (2026-08-01):**
  `testListingIncludesFeaturedImage` y `testShowIncludesFeaturedImage` seedeaban
  `featured_file_id` (42 y 99) y esperaban que `FileUrlResolver::normalizeMediaReference()`
  usara el `featured_image_url` almacenado como fallback — pero eso sólo se cumple cuando
  `HubClient::resolvePublicFileMeta()` no encuentra ese file_id. Ninguno de los dos tests
  mockeaba `HubClient`, así que en la práctica dependían de que el Hub real (si estaba
  corriendo en `localhost:8180`, como durante LEGACY-MAP-015) no tuviera un archivo con ese ID
  — una condición de carrera silenciosa contra el estado de un servidor externo, no una
  aserción hermética. Encontrado exactamente así: falló `testListingIncludesFeaturedImage`
  porque mi propio Hub de dev, corriendo con datos reales del ETL legacy, sí tenía un archivo
  en `file_id=42`. Corregido siguiendo el patrón ya establecido en
  `CollectionControllerTest`/`SettingConnectionControllerTest`/`WizardConfigControllerTest`
  (`Services::injectMock('hubClient', $stub)` con una subclase anónima de `HubClient`): se
  agregó el helper privado `mockHubClientWithNoFiles()` que stubea
  `resolvePublicFileMeta()` para devolver `[]` siempre, y se llama al inicio de ambos tests.
  También se agregó `Services::reset()` a `tearDown()` (faltaba) para que el mock no se filtre
  al resto de la suite. Revisado el resto de `tests/Feature/` por el mismo patrón
  (`featured_file_id`/`hub_file` con IDs reales) — sólo estos dos tests lo tenían; los usos en
  `PublicSettingControllerTest`/`PublicMenuControllerTest` son `file_id: 0|null`, que
  `FileUrlResolver::resolve()` descarta antes de llamar al Hub, así que no son frágiles.
  Verificado: `composer quality` ✅ (PHPStan 0 errores, 499/499 tests, 1 skip preexistente no
  relacionado — sin el fallo intermitente ya sea que el Hub real esté corriendo o no).

- **LEGACY-MAP-015 — Fix crítico: `wizard_extra` nunca poblaba `block_data` en ningún dominio TeatroMuseo (2026-08-01):**
  Descubierto al verificar el contenido real tras la primera ejecución material de
  `legacy:apply --slice A`: las 15 entradas creadas (compañías/obras/videos) tenían su bloque
  primario (`compania_ficha`/`obra_ficha`/`video_ficha`) con `block_data` **vacío** en los 4
  idiomas, pese a que `wizard_extra` llegaba con los campos correctos. Causa raíz en
  `EntryBlockTemplateInitializer::initialize()`: `$schemaDef = is_array($rawSchema) ? $rawSchema
  : []` sobre `$blockType->schema_definition`, que está cast como `'json'` en `BlockTypeEntity`
  — CI4 lo decodifica a `stdClass` (recursivamente), nunca a array, así que `is_array()` era
  siempre falso y `$schemaFields` siempre `[]`. El bug es transversal a **todo** wizard_extra de
  **todo** bloque de **todo** dominio TeatroMuseo, no específico de `director` (log confirmó el
  mismo `wizard_extra key(s) with no matching block field` para `name/summary/description`,
  `venue/company/audience/...` de `obra_ficha`, y `provider/video_id/...` de `video_ficha`).
  Corregido usando `JsonCastNormalizer::toArray($blockType->schema_definition ?? null)` — el
  helper que el propio `CLAUDE.md` de este repo documenta exactamente para este caso ("Shallow
  `(array)` casting on any Entity property cast as `'json'`"). Verificado con datos reales: la
  ficha "Liberarte" ahora tiene `director: "Sergio Liberona Díaz"` y el resto de campos
  correctamente poblados tras re-aplicar Slice A. `composer quality`: PHPStan 0 errores, 499
  tests con 1 fallo **preexistente y no relacionado** (`PublicEntryControllerTest::
  testListingIncludesFeaturedImage` — hace una llamada HTTP real a `HubClient` en vez de
  mockearlo; falla solo porque hoy había un Hub de dev real corriendo en `localhost:8180` con
  un archivo real en el `file_id=42` que el test hardcodea — problema de aislamiento de tests
  preexistente, no introducido aquí, no corregido por estar fuera de alcance). Detalle completo
  en `../docs/legacy-cms-pilot-mapping.md` sección 10.2.

- **LEGACY-MAP-014 — Cerrar gap de `director_compania` sin destino en `compania_ficha` (2026-08-01):**
  Auditoría de estado óptimo del ETL legacy (`docs/legacy-cms-pilot-mapping.md`) detectó que
  `compania_ficha` no tenía ningún campo para `sn_compania.director_compania`, causando pérdida
  silenciosa de dato si se corría `legacy:apply`. Se agregaron dos campos en
  `TeatroMuseoBlockTypeSeeder::blocks()`: `director` (string, valor legacy crudo) y `director_ref`
  (`entry_reference` → colección `personas`, deliberadamente sin poblar por el ETL). Razón de
  separar ambos: ~235 filas de `sn_compania` en el dump y una parte significativa de
  `director_compania` es texto placeholder del editor legacy (`"Director El Árbol de Ko"`,
  `"Director"` a secas), no un nombre real — vincular automáticamente a `personas` habría
  contaminado la colección con fichas basura. `director_ref` queda como campo de curación manual
  editorial. Ver mapeo actualizado en `../docs/legacy-cms-pilot-mapping.md` sección "Compañía:
  sn_compania". Verificado: `composer quality` ✅ (PHPStan 0 errores, 499+17+19 tests, 1 skip
  preexistente no relacionado), schema persistido confirmado por query directa a
  `cms_content_blocks.schema_definition`.

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
