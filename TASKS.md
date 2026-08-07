# TASKS — teatromuseo-cms-domain

> Fuente de verdad para trabajo abierto en este repositorio.
> Los entregables cerrados están en [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).
> Seguimiento global: [`../TASKS.md`](../TASKS.md).
> Tracker depurado el 2026-07-21; no se conservan notas de conversación ni bitácoras de participantes.

## 🔴 En progreso

*(vacío)*

## 🟡 Próximo

> Saneamiento arquitectónico — auditoría del 2026-08-05.
> **Contexto, evidencia y rutas exactas:** [`../docs/plan/2026-08-05-saneamiento-arquitectonico.md`](../docs/plan/2026-08-05-saneamiento-arquitectonico.md)
> Orden y dependencias cross-repo: [`../TASKS.md`](../TASKS.md)


### Fase 1 — Seguridad


### Fase 2 — Configuración y CI

- [ ] **CFG-02 (residual) — 27 de 28 variables siguen sin documentar.** `HUB_INTERNAL_SECRET` ya
  se documentó (2026-08-06, ver Completadas). Faltan `hub.adminToken`, `WEB_API_KEY`,
  `HUB_URL`/`HUB_API_KEY`/`HUB_APP_CODE`, `QUEUE_REDIS_*`, `cms.eventListingPath`. Nota: este repo
  lee **tres convenciones de configuración en paralelo** (`hub.url`-style, `HUB_*` y
  `DB_*`/`MYSQL_*` de la ruta Docker). Unificar.
- [x] ~~CFG-03~~ — **completado 2026-08-06.** `public/swagger.json` dejó de estar en `.gitignore`
  y quedó commiteado. Ver Completadas.
- [x] ~~CFG-05~~ — **completado 2026-08-06.** Umbral de cobertura subido a 60 %, alineado con la
  política única. Ver Completadas.
- [ ] **CFG-08 — Único repo en CI4 v4.7.4** (los otros 7 en v4.7.3) y único con un requisito
  explícito de `guzzlehttp/psr7` (`^2.12.1`, resuelto en 2.12.4 vs 2.13.0 del resto).

### Fase 3 — Extracción a `ci4-api-core`

- [x] ~~CORE-02 (filtros)~~ — **completado 2026-08-06.** `PermissionFilter`, `HubSignatureFilter` y
  `WebAppKeyRequiredFilter` extienden ahora las bases del paquete (`ci4-api-core` v1.3.0). Ver
  Completadas.
- [ ] **CORE-02 (residual) — Reconciliar las migraciones de infra.** Este repo es el que **más
  diverge**: `jobs`, `request_logs`, `audit_logs` e `idempotency_keys` difieren de las copias de
  api/catalog/event en las cuatro → schema drift real de columnas/índices. `core:install` del
  paquete **no resuelve esto** — solo escribe una migración cuando el consumidor no tiene ya una
  clase con ese nombre, y las 4 apps ya la tienen, así que sigue siendo reconciliación manual.
  También quedan sin base compartida: `AuditRepository` (a propósito, el paquete solo expone la
  interfaz), `MetricModel`, `RequestLogModel`, `AuditLogModel`.
- [x] ~~CORE-03~~ — **completado 2026-08-06.** `app/Config/Api.php` ahora extiende
  `dcardenasl\Ci4ApiCore\Config\Api`. Ver Completadas.
- [x] ~~CORE-05~~ — **completado 2026-08-06.** `JsonCastNormalizer` local eliminado; el paquete
  añadió la rama de string en v1.3.0. Ver Completadas.
- [ ] **CORE-06 — Convención de permisos.** Hoy `cms.<plural>.<read|write|admin>`, incompatible con
  catalog (`catalog.<camelCaseSingular>.<crud>`) y event (`event.<kebab-plural>.<read|write|delete>`).
  **Confirmado fuera de alcance de `ci4-api-core`** — es config local (`DomainPermissions.php`) más
  una migración de datos en `permissions`/`role_permissions` del hub, no código de paquete.
  ⚠️ **`domain:sync-permissions` es insert-if-missing, no upsert**: renombrar un código sin migración
  SQL manual deja huérfanas las filas viejas y sus bindings de rol — los roles pierden el permiso en
  silencio. Requiere ventana de mantenimiento — ver decisiones pendientes en el tracker global.
  **No tocar sin confirmación explícita.**

### Fase 4 — Coherencia de capas

- [ ] **LAYER-02 — 12 controladores se saltan el DTO.** Prioritarios:
  `SettingConnectionController.php:47` **rompe el sobre `ApiResponse`** devolviendo
  `setJSON(['ok'=>true,'data'=>$data])` directo; `TranslationAuditController.php:46-60` lleva la
  paginación y validación en el controlador; `BlockInstanceController.php:29` hace routing con
  `service('request')->getUri()->getSegments()`.
- [ ] **LAYER-03 — Servicios con builder crudo:** `BlockInstanceTranslationAuditor` (l.272, 291, 311),
  `EntryService` (l.359, 527-539, 564-572), `FormSubmissionService:258`, `FormService` (l.187, 213),
  `FileUsageService:40`, `BlockTypeService` (l.86, 179, 295), `EntryBlockTemplateInitializer:41`.
  El test de arquitectura **no prohíbe**: solo congela un conteo base
  (`ServiceModelDependencyConventionsTest.php:145-147`), es decir ratifica las violaciones en vez de
  eliminarlas. Convertirlo en una regla real.
- [ ] **LAYER-05 — `app/Models/PageViewModel.php` (221 líneas) es un servicio de analítica dentro de
  un modelo**: `getTotalViews`, `getUniqueVisitors`, `getTopPages`, `getTopReferrers`,
  `getDeviceBreakdown`, `getBrowserBreakdown`, `getDailyTrend`, con `AnalyticsController`/
  `AnalyticsService` encima. Mover la agregación a los servicios.
- [ ] **LAYER-07 — 8 controladores sin referencia en `tests/`:** `FileTranslationController`,
  `PublicFormSubmissionController`, `AnalyticsController`, `PublicTrackingController`,
  `PublicTagController`, `FormSubmissionController`, `FileUsageController`, `PublicCategoryController`.
  Sin tests también `Cms/FileTranslationService`.

### Fase 5 — Migraciones y datos

- [ ] **MIG-01 — 41 de 67 migraciones editan contenido editorial, no esquema** (ninguna llamada a
  `forge->`): `NormalizeTheatreSchoolLabels`, `NormalizeTheatreSchoolPageTitles`,
  `PersistAboutSpanishEditorialContent`, `CreateAboutTeamChildren`, `RetireObrasCollection`,
  `NormalizeSiteSettings`… (secuencia contigua de `2026-08-03-120000` a `2026-08-05-000001`).
  **Efecto real: `php spark migrate` revierte en silencio los cambios que un editor hizo en el admin.**
  Hay además cadenas donde cada migración parchea la anterior:
  `ConsolidateAboutTeamBlocks` → `SyncAboutTeamEditorialData` → `RestoreAboutTeamBlockCompatibilityId`
  → `NormalizeAboutTeamAdditionalRoles` (4 pasadas sobre el mismo bloque);
  `RenamePublicationsToEditorial` → `CanonicalizeEditorialRoutes` → `PreserveEditorialEntryRoutes`
  → `ConsolidateEditorialIndexPage` (4 sobre el mismo renombrado);
  `AddListingFieldProjection` → `BackfillListingProjections` → `NormalizeListingProjectionReferences`;
  `NormalizeBlockNavigationSchemas` → `NormalizeExistingBlockNavigationSchemas`.
  **Plan acordado:** (1) consolidar cada cadena en una operación única con el estado final;
  (2) convertirlas en seeders/comandos spark idémpotentes que solo corren si se invocan;
  (3) dejar las 26 de esquema como únicas migraciones; (4) **coordinar con producción** — las 41 ya
  se aplicaron, el paso de conversión debe registrarlas como ejecutadas sin re-ejecutar;
  (5) añadir un test de arquitectura que falle si una migración nueva no llama a `forge->`.
- [ ] **MIG-02 — Otras inconsistencias:** huecos de numeración (`2026-06-11-070011` → `070013`;
  `2026-08-04-000007` → `000010`), `deleted_at` en 17 migraciones pero solo 3 de 34 modelos con
  `useSoftDeletes = true`, `ENUM` crudo en 14 migraciones (event y catalog usan otros dos enfoques
  para lo mismo), y la tabla **`cms_search_index` completamente huérfana** — creada en
  `2026-06-11-070013_CreateCmsSearchIndex.php:36` y referenciada en ningún otro sitio de `app/` ni
  `tests/`.
- [ ] **MIG-03 — Cuatro mecanismos para un solo slider:** `LegacyHomeHeroSliderSeeder`,
  `LegacyHistoryHeroSliderSeeder`, el comando `RestoreHomeHeroSlider` y las migraciones `120008` y
  `120009`. Consolidar en uno. Decidir además si `AnalyticsSeeder` (datos sintéticos) es fixture de
  test (→ `tests/`) o bootstrap real.
- [ ] **HYG-01 — Purgar y rotar `writable/debugbar` (3,9 GB, el mayor de la flota) y
  `writable/logs` (64 MB).**

### Fase 6 — Docs

- [ ] **DOC-01 — Deriva documental:** 1 mención a `ci4-website-builder*` y 4 a `ci4-*-starter` en
  `CLAUDE.md`, más el puerto 8080 (debe ser 8180 para el hub / 8190 para esta app).

### Fase 7 — Estabilidad de tests

- [ ] **TEST-01 — Flakiness no determinística en la suite completa, no reproducible en
  ejecuciones aisladas (encontrada 2026-08-06 durante el commit-flow de la auditoría, no causada
  por esos commits).**
  1. `tests/Feature/Controllers/Cms/FormSubmissionControllerTest.php::testIndexListsSubmissions`/
     `testIndexFilteredByStatus` fallan de forma intermitente (a veces 0 resultados en `index()`
     pese a que las filas existen en la DB, confirmado con debug directo). El propio test ya
     documenta un bug real que sí se corrigió (`FormSubmissionService::list()` no reseteaba el
     query builder) y un retry-loop de 5 intentos como mitigación, pero el retry no siempre
     alcanza.
  2. Corriendo la suite **completa** (>560 tests contra MySQL real, no en ejecuciones aisladas por
     carpeta/clase), ~13 tests de seeders (`SiteBootstrapSeederTest`,
     `SiteBootstrapPublicBlockCoverageTest`, `SiteBootstrapContentTest`,
     `CmsCollectionGridAspectRatioSeederTest`, `CmsTeatroMuseoNavigationSeederTest`,
     `ScheduledPublishingJobTest`) fallan con `RuntimeException` de
     `FileReferenceSynchronizer::replaceReferences()` ("no se pudieron sincronizar referencias de
     archivos CMS"). Confirmado que `FileReferenceSynchronizer.php` no tiene cambios desde el
     commit inicial de kickstart — no es un bug de lógica introducido recientemente.
  **Hipótesis a investigar:** transacciones anidadas (`$db->transStart()`/`transComplete()` dentro
  de `FileReferenceSynchronizer`, posiblemente dentro de una transacción ya abierta por el test) o
  agotamiento de conexiones MySQL al escalar el número de tests en un solo proceso PHPUnit.

## ✅ Completadas

### CFG-03 + CFG-05 + CFG-02 (parcial) — swagger, cobertura y HUB_INTERNAL_SECRET (2026-08-06)

- **CFG-03:** `public/swagger.json` dejó de estar en `.gitignore` y se commiteó el archivo
  generado — el gate `swagger-validate` (`git diff --exit-code` sobre ese archivo) ahora puede
  detectar drift real, igual que en `teatromuseo-api`.
- **CFG-05:** `coverage:check` subido de 35,05 % a 60 %, alineado con la política única de la
  flota.
- **CFG-02 (parcial):** documentado `HUB_INTERNAL_SECRET` en `.env.example`. Las otras 27
  variables listadas en el ítem CFG-02 (residual) de arriba siguen sin documentar.

### CORE-02 (filtros) + CORE-05 — bases del paquete v1.3.0 (2026-08-06, segunda pasada)

- **CORE-02:** `PermissionFilter` extiende `AbstractPermissionFilter` (`ci4-api-core` v1.3.0), con
  `superAdminBypassCode()` devolviendo `'iam.superadmin-access'` — antes solo event tenía este
  bypass; ahora las tres apps se comportan igual. Como `app/Language/{es,en}/Auth.php` no define
  `authRequired`/`insufficientPermissions` (solo `rateLimitExceeded`), se sobrescribieron
  `unauthenticatedMessage()`/`forbiddenMessage()` para seguir leyendo `Api.authRequired`/
  `Api.insufficientPermissions` en español, en vez de caer al `Auth.php` en inglés del paquete.
  `HubSignatureFilter` y `WebAppKeyRequiredFilter` ahora extienden
  `AbstractHubSignatureFilter`/`AbstractWebAppKeyRequiredFilter` — mismo HMAC y mismo fail-closed
  de antes, sin la copia manual.
  `App\Traits\Controllers\HasCrudActions.php` **resultó ser código muerto**, no boilerplate en
  uso: ningún controlador real lo consumía (los controladores escritos a mano necesitan
  `$context->hasPermission(...)` por acción, que ni la versión local ni la del paquete soportan).
  Se eliminó en vez de migrarse.
- **CORE-05:** eliminado `app/Libraries/Cms/JsonCastNormalizer.php` (52 líneas) y su test; el
  paquete añadió en v1.3.0 la rama de string que faltaba (`json_decode` con fallback a `[]`),
  semánticamente idéntica a la copia local. 7 llamadores actualizados
  (`BlockTypeResponseDTO`, `BlockTemplateCatalog`, `BlockSchemaIntrospector`, `RepairSlugs`,
  `WizardConfigService`, `PublicEntryReader`, `EntryBlockTemplateInitializer`) más el `CLAUDE.md`
  de esta app, que lo citaba como ejemplo en "Common pitfalls".

**Verificación:** 523 tests / 2.068 assertions ✅ (suite principal), PHPStan sin errores.

### CORE-03 + saneamiento de la suite de tests (2026-08-06)

- **CORE-03:** `app/Config/Api.php` pasa de 148 líneas copiadas verbatim a extender
  `dcardenasl\Ci4ApiCore\Config\Api`. Se elimina `accessPolicyBypassRoutes` apuntando a
  `auth/resend-verification`, una ruta que no existe en esta app.
- **`ci4-api-core` subido a v1.2.0.**
- **La suite estaba roja y ahora está verde: 529 tests ✅** (antes 1 error + 4 fallos). Los cinco se
  verificaron como preexistentes reproduciéndolos con core v1.1.1 y el `Config/Api` original:
  - **19 migraciones de contenido sin `@cms-content-data-migration`.** Caían en la rama de esquema
    de `CleanDatabaseBootstrapConventionsTest`, que exige `Create*` + `createTable(`. Se comprobó
    que **ninguna de las 19 toca esquema** y se etiquetaron; la lista de verbos permitidos se amplió
    al conjunto realmente en uso. Es un parche honesto para que el guardrail refleje la realidad de
    hoy — **no sustituye a `MIG-01`**, que sigue pendiente.
  - **`TranslationAuditServiceTest` se contradecía consigo mismo.** Un test esperaba `outdated` sobre
    el idioma por defecto; su hermano `testDefaultLanguageIsNotMarkedOutdatedAgainstItsOwnSource...`
    monta el mismo escenario y afirma `complete`. La regla del idioma por defecto es deliberada y
    está documentada, así que el caso obsoleto ahora usa un idioma no-predeterminado.
  - **Campo inventado en un fixture:** el mismo archivo usaba `alt_text`, pero solo se auditan los
    campos de `AUDITABLE_BLOCK_STRING_FIELDS` y los esquemas reales usan `alt` — el bloque se omitía
    entero y las aserciones caían en un índice inexistente.
  - **`BlockInstanceServiceTest` esperaba 2 lecturas donde el código hace 3.** `beforeUpdate()` ahora
    comparte la instancia por referencia entre `validateSlideNavigation()` y
    `normalizeEntryReferencesFromPayload()`, así que un payload que traiga `block_id` no lee nada.
    La tercera lectura (`BaseCrudService` carga la entidad antes del hook y solo la pasa a
    `setEntityContext()`, que no la expone) solo se elimina cambiando la firma de `beforeUpdate()`
    en el paquete — queda anotado en el código y en `CORE-02`.

**Verificación:** 529 tests / 2.068 assertions ✅, PHPStan sin errores.
⚠️ Quedan **2 fallos preexistentes** en la suite `SeederContracts`, verificados como previos: falta
cobertura pública del bloque `hero_banner`, y `collection_timeline#1264` lleva un `collection_id`
que su esquema no declara. Son decisiones de contenido → `MIG-03`.

- **CFG-01 — Puertos canónicos de CMS y hub (2026-08-05):** `.env.example`, `.env.docker.example`,
  PHPUnit, Compose y `init.sh` ahora usan CMS `8190` y hub `8180`. Composer, YAML, Bash y los
  gates de estilo/PHPStan/Swagger pasan; `composer quality` conserva únicamente el fallo
  arquitectónico preexistente de `2026-08-04-000012_UnifyAboutPageLocales.php`.

- **SEC-02 — Alinear `PermissionFilter` con event-domain (2026-08-05):** CMS ahora permite que un
  superadmin de plataforma atraviese filtros de permisos de dominio sin alterar los casos 401/403.
  Se añadieron seis regresiones unitarias. CS-Fixer, PHPStan, Swagger y el test dirigido pasan; el
  gate completo conserva fallos arquitectónicos y unitarios preexistentes no relacionados.

- **SEC-01 — Proteger `GET cms/public/languages` (2026-08-05):** la ruta quedó dentro del grupo
  `webappkey + throttle`; se corrigió además el `declare` de la ruta y se añadió regresión de 401 sin
  clave. Verificado: `composer test:feature -- --filter=PublicLanguageControllerTest` ✅ (2 tests).
  Estilo, PHPStan y Swagger ✅; `composer quality` queda bloqueado por el fallo arquitectónico
  preexistente del nombre `2026-08-04-000012_UnifyAboutPageLocales.php`, perteneciente a MIG-01.

- **BLOCK-002 — Campo `image_aspect_ratio` configurable en `collection_listing` (2026-08-02):**
  David pidió poder definir, por bloque, la proporción de las imágenes de portada en el listado
  público, y que esa proporción afecte solo el alto de la tarjeta (el ancho ya lo define la
  cuadrícula). Nuevo `config_field` tipo `select` en `CmsBlockTypeSeeder.php` (options `16/9`,
  `4/3`, `1/1`, `3/4`, `2/3`, default `16/9`), mismo patrón ya usado por `map_embed.aspect_ratio`.
  La mitad de la corrección (ViewModel + plantilla en `teatromuseo-web`, mapeo a clases Tailwind
  literales — necesario porque el build no puede compilar una clase `aspect-[...]` construida en
  tiempo de ejecución) vive en `teatromuseo-web`. Ver BLOCK-003 para el ajuste real por colección
  contra datos de portadas reales (el default `16/9` que dejé aquí sin verificar resultó
  incorrecto para varias colecciones).

- **BLOCK-003 — Ajuste de `image_aspect_ratio` en cada página que usa `collection_listing`
  (2026-08-02):** David pidió revisar todas las páginas que usan el bloque `collection_listing`
  (campo `image_aspect_ratio` agregado en BLOCK-002, mismo día) y ajustar la proporción
  configurada a la que realmente tienen sus portadas. Medí el ancho/alto real de cada
  `featured_image`/`cover_file_id` en la tabla `files` del hub (no solo revisé visualmente) y
  calculé la moda/mediana por colección:
  - `noticias` (52/68 portadas ~1:1), `obras` (291/365 ~1:1, pero la página pública
    `/es/obras` está redirigida a `/cartelera` desde antes — dato legacy, no afecta nada visible),
    `publicaciones` (13/15 ~1:1), `festivales` (única muestra, 1:1), cartelera/`event_items`
    (292/366 portadas ~1:1) → **1/1**.
  - `cursos` (11/20 portadas ~4:5, 9/20 ~1:1) → **3/4** (mejor ajuste que el 16/9 que dejé por
    defecto en BLOCK-002 sin verificar contra datos reales — corregido aquí).
  - `personas` (10/10 portadas ~450×550=0.82) → **3/4**.
  - `exposiciones` (4/5 portadas ~0.56, retrato marcado) → **2/3** (el preset disponible más
    cercano; ninguno de los 5 valores fijos es un match exacto para 0.56).
  - `companias` y `videos`: **sin cambios** — 0 entradas con portada en ambas colecciones
    (verificado en vivo, 0 `<img>` en la grilla), no hay datos que informen una proporción mejor
    que el default.
  - `museo/coleccion` (`catalog_items`): **sin cambios** — colección vacía (0 ítems activos,
    ninguno con `cover_file_id` — catalog-domain nunca tuvo contenido legacy real, ver
    limpieza de seeders de ejemplo del 2026-08-02).
  - Bloque huérfano `id=83` (`owner_id=28`, `collection_id=10`): página 28 no existe en
    `cms_pages` y la colección 10 no existe — instancia de bloque muerta de algún estado previo,
    no tocada (fuera de alcance; podría limpiarse en una tarea aparte).
  - Aplicado vía `PUT /cms/pages/{id}/blocks/{id}` (8 bloques actualizados), caché de
    `teatromuseo-web` invalidada. Verificado en vivo por HTML crudo (`grep` de la clase
    `aspect-*` renderizada) y por `getBoundingClientRect()` en el navegador: el ancho del
    contenedor de imagen no cambia entre proporciones (siempre lo define la cuadrícula), solo
    el alto — confirmado exacto para `cursos` (3/4), `personas` (3/4), `exposiciones` (2/3),
    `noticias`/`festivales`/cartelera (1/1).

- **CURSOS-002 — display_date real para cursos en el listado público (2026-08-02):** David
  reportó que la fecha mostrada en las tarjetas de `/es/cursos` no correspondía al `Start Date`
  real del curso y que debía ordenarse por esa fecha. El orden ya era correcto
  (`sortCursosUpcomingFirst()`, CURSOS-001) pero la fecha *mostrada* en la tarjeta venía de
  `published_at`/`created_at` (genérico para toda colección), no del `start_date` real del bloque
  `curso_ficha`. Fix: `PublicEntryReader::listPublic()` reutiliza el mismo mapa de fechas ya
  resuelto por `sortCursosUpcomingFirst()` (sin query adicional) y lo expone como
  `display_date` en cada entry de la colección `cursos`. Nuevo test en
  `PublicEntryControllerTest::testCursosCollectionOrdersUpcomingFirstThenMostRecentPast`
  verifica `display_date` además del orden. Ver también `teatromuseo-web` TASKS.md para la
  mitad de la corrección (formato de fecha localizado + preferencia por `display_date` en las
  plantillas). `composer quality` ✅ (506 tests + 8 de seeds, 0 fallos).

- **CURSOS-001 — Portadas/galerías de cursos, unificación actual/histórico, y orden por
  fecha (2026-08-02):** David pidió revisar qué pasó con las portadas e imágenes de galería
  de la colección `cursos`, confirmar que los cursos actuales e históricos quedan unificados
  en un solo listado, y ordenar la colección con los próximos primero (ascendente) y luego el
  resto por fecha descendente — el mismo criterio ya aplicado a la Cartelera pública
  (event-domain EVT-DOM-007).
  - **Portadas/galería:** confirmado con `LegacyAssetResolver` contra el dump real que los 20
    `sn_cursos.image_cover` existentes apuntaban a archivos bajo `/images/escuela/` que
    nunca se descargaron al asset-root local durante la preparación original
    (LEGACY-MAP-022) — pero seguían disponibles en `https://teatromuseo.cl/images/escuela/`
    (200 OK los 20). Descargados y re-corrida `legacy:apply --slice B`: portadas de cursos
    100→19/48 (los 20 con `image_cover` real menos 1 que no corresponde a un curso visible
    actualmente; el resto de los 48 cursos genuinamente no tiene imagen en el dump legacy —
    confirmado, no es un bug). Galería (`sn_escuela_img`) ya estaba correctamente migrada.
    Idempotencia verificada con una segunda corrida (0 archivos nuevos).
  - **Unificación actual/histórico:** confirmado que NO hay dos tablas legacy separadas —
    "Curso Actuales" (`/teatroescuela`) y "Curso Históricos" (`/teatroescuela-historico`) en
    el sitio legacy son solo dos filtros de navegación sobre la misma fuente (`sn_escuela`),
    igual que "Cartelera Actual"/"Cartelera Histórica" resultaron ser sobre `sn_obra`
    (ver histórico de LEGACY-MAP en `../teatromuseo-api/TASKS.md`). Ya migrados como una sola
    colección `cursos`, un solo listado público `/cursos` — nada que unificar, el diseño
    actual ya es correcto.
  - **Orden por fecha:** a diferencia de eventos (donde `start_time` es una columna real de
    `events`), en cursos la fecha vive dentro del `block_data` traducido del bloque
    `curso_ficha`, no en una columna de `cms_entries` — un `ORDER BY` no puede expresarlo.
    Nuevo `PublicEntryReader::sortCursosUpcomingFirst()` (activado solo cuando
    `collection_key === 'cursos'`, sin tocar el orden genérico de las demás colecciones):
    recorre todas las filas que matchean los filtros existentes, resuelve el `start_date` de
    cada una en una sola query batch (`batchResolveCursoStartDates()`, N+1-safe, mismo patrón
    que los otros resolvers de este reader), ordena en PHP y pagina el resultado. Requirió
    agregar `blockInstanceTranslationModel()` (mismo patrón lazy-getter que los otros 6
    modelos del archivo) — actualizado el baseline de
    `ServiceModelDependencyConventionsTest` para `PublicEntryReader.php` (`model_call` 6→7,
    documentado y justificado: reemplaza lo que habría sido un `Database::connect()` directo,
    no una violación nueva del patrón).
  - **Verificado:** nuevo test `testCursosCollectionOrdersUpcomingFirstThenMostRecentPast`
    (16/16 tests en `PublicEntryControllerTest.php`), `composer quality` ✅. Orden verificado
    contra los 48 `start_date` reales en BD (no solo "se ve plausible en el navegador") —
    coincide exactamente salvo un empate legítimo entre dos cursos con la misma fecha
    (2020-01-20). Confirmado en vivo en `http://localhost:8184/es/cursos`: el único curso con
    fecha futura ("La Escuela de los Nuevos Comediantes") aparece primero.

- **CLEANUP-001 — Eliminar seeders y datos de ejemplo mezclados con contenido real (2026-08-02):**
  David notó (a raíz del carrusel de inicio, ya corregido en LEGACY-MAP-021/026) que había
  contenido de ejemplo mezclado con el migrado desde la BD legacy de teatromuseo.cl — pidió
  limpiar todo y eliminar los seeders que agregan ejemplos, en cms-domain/event-domain/
  catalog-domain. Auditoría directa (cruce contra `legacy_migration_map` del hub + inspección
  de cada seeder) confirmó:
  - `CmsTeatroMuseoPilotSeeder` ("synthetic pilot entries... deliberately separate from the
    legacy ETL") creaba 2 entradas "piloto mínima/completa" en **cada una** de las 9
    colecciones reales (ids 3-20, ej. "Compañía piloto completa" visible en el listado
    público de Compañías).
  - `PortfolioCollectionSeeder`+`SitePortfolioPageSeeder`: colección "portafolio" 100% demo
    (2 entradas genéricas de e-commerce/banca digital), sin respaldo legacy — colección y
    página eliminadas por completo.
  - `SiteComponentsPageSeeder`/`SiteMediaPageSeeder`/`SiteLandingPageSeeder`: páginas de
    showcase del starter kit (`/bloques`, `/multimedia`, `/landing`), ninguna en el nav real.
  - `CmsHeroSliderChildrenSeeder`: si se re-ejecuta, borra los slides reales del carrusel de
    inicio y los reemplaza por "Bienvenidos a TeatroMuseo" + foto de picsum.photos — el
    carrusel ya tenía los 5 slides reales (LEGACY-MAP-021/026), pero el seeder quedaba como
    bomba de tiempo.
  - `NewsCollectionSeeder`: nunca se ejecutó en esta BD, pero comparte la MISMA
    `collection_key` ('noticias') que las 70 noticias reales — mismo riesgo si alguien lo
    corría manualmente.
  - `SiteAboutPageSeeder`/`SiteHistoryPageSeeder`: nunca llamados por `SiteBootstrapSeeder` —
    el Nosotros/Historia real viene de `CmsTeatroMuseoInstitutionalPagesSeeder`.
  - `WizardConfigSeeder`: solo reparaba el `block_template` de Noticias/Portafolio demo.

  **Casi-error evitado:** `Concerns/CollectionBlockPresets.php` se borró primero por error —
  `CmsTeatroMuseoCollectionSeeder` (estructural, real) en verdad depende de su preset
  `news()` para la ESTRUCTURA (block_template/wizard_config) de la colección `noticias` real
  (no es solo contenido de ejemplo). Restaurado; solo se quitó el preset `portfolio()`
  (100% muerto tras borrar esa colección).

  **Ejecutado:** las 18 entradas piloto + 2 de portafolio + 3 páginas demo borradas vía
  `DELETE /cms/entries|pages|collections/{id}` (soft-delete, sin filas huérfanas). Los 12
  archivos de seeder/tests demo eliminados; `SiteBootstrapSeeder::run()` ya no los llama.
  3 tests de contrato (`SiteBootstrapSeederTest`, `SiteBootstrapContentTest`,
  `SiteBootstrapPublicBlockCoverageTest`) actualizados para reflejar la nueva realidad de un
  bootstrap limpio: `cms_pages` 31→27, `cms_collections` 10→9, `cms_entries` 20→0; y se quitó
  `gallery`/`video_player`/`tabs`/`alert`/`container` de la lista de bloques con cobertura a
  nivel página (solo se demostraban en las páginas demo eliminadas — `gallery` sigue en uso
  real a nivel de entrada en obras/festivales, los otros cuatro no se usan en ningún lado).

  **Verificado:** `composer quality` ✅ (PHPStan sin errores, suite completa incl. los 8 tests
  de `SeederContracts`). En vivo: home muestra el carrusel real, `/bloques` da 404,
  `/companias` y `/noticias` ya no muestran entradas "piloto".

  **Trabajo relacionado en otros repos (mismo día):** `teatromuseo-event-domain` eliminó
  `TeatroMuseoEventSeeder` (13 eventos falsos mezclados con los 368 reales) y
  `teatromuseo-catalog-domain` eliminó `TeatroMuseoCatalogSeeder` por completo (los 4
  `collection_items` eran 100% demo, cero contenido real migrado a ese dominio — `/museo/
  colección` queda vacío hasta que se decida qué contenido legacy real, si existe, va ahí).

- **IMPORT-001 — Vía de importación autenticada para `cms_form_submissions` (2026-08-01):**
  Necesaria para `LEGACY-MAP-017` en `teatromuseo-api/TASKS.md` (backfill de 157 mensajes de
  contacto legacy con PII real). El endpoint público existente (`POST public/submissions`)
  siempre pisa `created_at`=ahora y `status`=new, así que no servía para preservar el histórico
  real. Nuevo `POST /api/v1/cms/submissions/import` (admin, `cms.submissions.write`, ya
  existente — no hizo falta sincronizar permisos nuevos): `FormSubmissionImportRequestDTO` +
  `FormSubmissionService::import()`, sin CAPTCHA ni jobs de notificación por email (no aplica a
  un backfill histórico). `useTimestamps=true` en `FormSubmissionModel` pisa cualquier
  `created_at` pasado a `insert()`/`update()`, así que `import()` inserta normal y luego corrige
  la fecha con `$this->model->builder()->where('id', $id)->update(['created_at' => ...])` —
  pasa por el modelo ya inyectado, no viola el guardrail de `ServiceModelDependencyConventionsTest`
  (baseline de `model()` calls sin cambios: la resolución de `form_id` por `form_key` se movió al
  DTO, igual patrón que `FormSubmissionCreateRequestDTO`, en vez de duplicarla en el Service).
  `composer quality` ✅ (522/522 tests, 1 skip preexistente).

- **AUDIT-001 — Falso positivo "outdated" en la auditoría de traducciones a nivel de entrada (2026-08-01):**
  Mismo patrón ya diagnosticado el 2026-07-21 para bloques (ver comentario en
  `TranslationAuditSupport::collapseForBlockBadge()`, confirmado con David en su momento), pero
  nunca corregido en la raíz para entradas — sólo enmascarado en la UI del editor de bloques.
  `EntryService::afterStore()` escribía las traducciones de la entrada y **después** hacía un
  `UPDATE` de housekeeping para limpiar `wizard_extra`; si ese segundo write caía en el segundo
  siguiente (granularidad `DATETIME` de MySQL), `cms_entries.updated_at` quedaba después que
  `cms_entry_translations.updated_at`, y `TranslationAuditSupport::evaluateTranslationState()`
  marcaba una traducción perfectamente completa como "Desactualizado". Encontrado en 3 entradas
  reales durante la migración legacy (`LEGACY-MAP-019` en `teatromuseo-api/TASKS.md`).
  Corregido reordenando `afterStore()`: las traducciones ahora se escriben después del
  housekeeping de `wizard_extra`, no antes — la tabla `cms_entry_translations` nunca vuelve a
  quedar temporalmente "antes" que `cms_entries` por culpa de un write interno sin implicación
  de contenido. Las 3 entradas ya afectadas se repararon alineando su `updated_at`. No se tocó
  la decisión deliberada de `collapseForBlockBadge()` (sigue colapsando 'outdated' a 'complete'
  sólo en la UI de bloques; la auditoría sitewide sigue mostrando 'outdated' verbatim cuando es
  real). Verificado: auditoría de traducciones en 100%/100%/100%/100%, 0 issues. `composer
  quality` ✅ (PHPStan 0 errores, 501/501 tests, 1 skip preexistente no relacionado).

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
