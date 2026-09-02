# TASKS_ARCHIVE — ci4-website-builder

> Historial de tareas completadas. Movido desde TASKS.md para mantener el tracker activo liviano.
> Última actualización: 2026-08-20

## ✅ Cierres 2026-08-11..18 — archivados 2026-08-20

`QA-01..04` y `ADM-DASH-01` no se re-archivan aquí: eran espejos locales de
tareas raíz ya cerradas y documentadas en `../TASKS_ARCHIVE.md` (y `QA-01/02`
ya estaban en este archivo desde antes) — se retiraron de `TASKS.md` sin
duplicar evidencia.

### CMS-EDITOR-04 — Contrato de autorización editorial

Cerrada 2026-08-18. Se registraron `cms.file-translations.read/write/admin`,
se corrigieron los permisos de bloques anidados de entries y se dejó la
eliminación de traducciones bajo `.admin`. Verificado con la suite completa y
`composer quality` (560 tests, 2.717 asserts, 1 skip); OpenAPI regenerado y
`domain:sync-permissions` aplicado al Hub.

### CMS-PR-06 — Retirar el HTTP público propio (Fase 3)

Cerrada 2026-08-14 después de verificar el gate de preview firmado y la
estabilidad de Fase 2. Se retiraron `PublicReadController`,
`PublicCategoryController`, `PublicTagController`, sus rutas
`public-read`/categorías/tags, DTOs, lectores, interfaces, envelope,
documentación OpenAPI y tests específicos. Se conservaron las rutas públicas
CMS-only y el CRUD autenticado. Se quitaron los dos path-repositories y
requisitos Composer de lectura pública; el lock y `vendor/` ya no contienen
esos paquetes. Las carpetas físicas superseded se eliminaron después en
`PKG-CLEANUP-01`. Se añadió la nota de coordinación cross-repo en `CLAUDE.md`.
El preview positivo se verificó con curl real en CMS `8190` y BFF `8188`
usando el mismo secreto efectivo de 64 bytes; las rutas CMS retiradas
responden `404` y el endpoint de página conservado responde `200`. Se
corrigieron cinco migraciones MySQL con foreign keys auto-referenciadas para
aplicarlas después de `createTable()`. `composer quality` oficial: CS-Fixer
limpio, PHPStan 0, arquitectura verde, 558 tests / 2.628 assertions / 1
skipped; contratos de seed: 15 tests / 3.472 assertions. Swagger regenerado:
25 paths / 59 schemas.

### PERF-04 — Endpoints compuestos `layout` y `page-bootstrap` en PublicRead

Cerrada 2026-08-13. Enmienda ADR 004 §1/§6 vía
[`../docs/adr/006-public-read-composite-bootstrap-endpoints.md`](../docs/adr/006-public-read-composite-bootstrap-endpoints.md):
bajo hosting sin paralelismo real confirmado, el costo dominante de una carga
fría de Web es el número de round-trips HTTP, no el diseño de composición —
y Web es el único consumidor confirmado de las 5 rutas involucradas. Se
agregaron `GET public-read/{locale}/layout` y
`GET public-read/{locale}/page-bootstrap/{path}` como composición pura sobre
los lectores/servicios existentes, sin duplicar su lógica de query, sin tocar
el esquema. Verificado: 620/620 tests, PHPStan 0 errores, CS-Fixer limpio,
contrato OpenAPI verde.

### PERF-03 — Retirar la ruta pública duplicada `public/{lang}/entries/{collection}`

Cerrada 2026-08-13. Al investigar antes de borrar se encontró que
`PublicEntryController` no era un duplicado puro: tenía modo preview de
borradores vía firma, y `teatromuseo-web` ya enviaba esos parámetros pero
`PublicReadEntryShowRequestDTO` los descartaba en silencio — bug real y vivo,
corregido antes de borrar nada. El alias `cursos`→`teatroescuela` no se portó
(cero llamadores reales). Con la brecha de preview cerrada, se borró
`PublicEntryController`, sus rutas y los métodos que delegaban una línea.
Verificado: 608/608 tests, PHPStan 0 errores, CS-Fixer limpio.

### PERF-01 — Materializar facetas/orden de listados públicos, eliminar `findAll()` sin límite

Cerrada 2026-08-13. Tabla `cms_entry_facet_values` +
`EntryFacetValueSynchronizer` enganchado en los dos write-paths de
`block_data`, backfill idempotente corrido contra dev (61.208 filas).
`PublicEntryReader::listPublic()` reescrito: los dos `findAll()` sin límite
reemplazados por WHERE/ORDER BY/LIMIT reales; `SELECT *` reemplazado por
proyección explícita; `include=listing_content.<sub>` soporta selección
parcial. Bug preexistente corregido de paso en
`BlockInstanceService::blockSchemaDefinition()`. Verificado: 608/608 tests,
PHPStan 0 errores, CS-Fixer limpio.

### CMS-PR-01..05 — Análisis de lectores públicos bajo el diseño superseded de paquete compartido

Cerradas 2026-08-13, bajo un diseño que el plan luego abandonó (revisión de
diseño 2026-08-13, decisión #5): proponían extraer los lectores públicos a
un paquete Composer compartido `teatromuseo-cms-public-read` en
`ci4-platform/`, consumido de vuelta por este repo. Con un solo consumidor
final (el BFF), el paquete no aportaba nada — el BFF terminó escribiendo su
propia implementación en `teatromuseo-bff/app/PublicRead/Cms/`, usando el
código de este repo como referencia de lectura, no como dependencia. El
análisis de cuáles lectores ya eran `BaseConnection`-only, el desacople de
`PublicReadLayoutReader` de `CollectionService`, los lectores nuevos de
categorías/tags, y el split de `PublicRedirectResolver::resolve()`/
`recordHit()` (necesario porque `recordHit()` escribe, incompatible con un
usuario MySQL solo-`SELECT`) siguió siendo válido y es lo que informó esa
implementación final — no se perdió el trabajo, solo cambió dónde vive el
código resultante. `CMS-PR-06` (ver arriba) retiró el paquete/path-repo que
esta serie sí llegó a crear.

### ADM-DASH-02 — Proyección CMS alineada al esquema real

Cerrada 2026-08-11. La consulta de traducciones usa únicamente `title` y
`slug`, recibe permisos antes de contar/leer y cuenta con prueba de
integración contra `cms_page_translations`.

---

## ✅ QA-01 — PublicRead y OpenAPI — cerrado 2026-08-10

Envelope versionado, `X-App-Key`, fallback, regresión CRUD y documentación
OpenAPI del CMS verificados. Evidencia cross-repo en
[`../docs/audits/2026-08-10-qa-01-contractos-openapi.md`](../docs/audits/2026-08-10-qa-01-contractos-openapi.md).

## ✅ QA-02 — EXPLAIN, índices y budgets SQL — cerrado 2026-08-10

Listing CMS medido con fixtures MySQL volumétricos, presupuesto de queries y
duración, EXPLAIN sobre la query real y regresión contra N+1. El índice
`idx_page_status` existente cubre el filtro público.
Evidencia en [`../docs/audits/2026-08-10-qa-02-explain-indexes.md`](../docs/audits/2026-08-10-qa-02-explain-indexes.md).

---

## ✅ Scaffold inicial + integración hub (Milestone domain-starter v0.1, 2026-05-07)

| ID | Descripción | Estado |
|---|---|---|
| DOM-001 | Scaffold base: clonado desde ci4-api-starter, eliminados módulos Auth/IAM/Users/Files/Identity/Admin. Agregados `Config\Hub`, `Config\DomainPermissions`, `HubClient`, `DomainAuthFilter` (alias `domainauth`), `SyncPermissions`, `Config\Scaffolding` override. Módulo Items de ejemplo generado con make-crud. PHPStan L8 limpio. | ✅ |
| DOM-002 | Integración end-to-end con hub: login → JWT → POST a domain → 201. Negative check: user sin permisos → 403. DomainAuthFilter llama `/auth/introspect` con `X-App-Key`, hub re-resuelve scope por `application_id`. | ✅ |
| DOM-003 | `domain:sync-permissions` rediseñado con `--admin-token` flag. `HubClient::registerPermission()` recibe bearer token explícito, corta en primer 401/403. `init.sh` actualizado para pedir JWT de setup. | ✅ |
| DOM-106 | README y README.es.md reescritos (~170 líneas). `docs/README.md` corregido. 12 docs de features del hub eliminados (stale clones). `docs/tech/jwt-auth.md` y `docs/architecture/AUTHENTICATION.md` reescritos como punteros al hub. | ✅ |

---

## ✅ Consumir base classes desde ci4-api-core (CORE-005, 2026-05-07)

24 archivos base eliminados de `app/`, 75 `use App\…` migrados a `dcardenasl\Ci4ApiCore\` vía sed batch. 3 architecture tests pure-core eliminados. PHPStan L8 + 202 tests verdes + CS-Fixer limpio. Smoke `make-crud Widget Demo` + `module:check` pasan.

---

## ✅ Consumo ci4-api-core v0.2.0 (2026-05-07)

Sin ID de tarea — trabajo derivado del runtime decoupling de ci4-api-core:
- Helpers procedurales, audit, HTTP filters, logging stack, mappers, support, `BaseRepository`, exception handlers, `Filterable`/`Searchable`/`QueryBuilder` consumidos desde `dcardenasl/ci4-api-core`
- `findByIds` implementado en `BaseRepository`
- Mapper acepta `object|array` (CORE-009)
- Fixtures de tests actualizados a imports de `dcardenasl/ci4-api-core`
- `composer.lock` regenerado

---

*TASKS_ARCHIVE · ci4-website-builder · 2026-05-07*

---

## 📦 Migrado desde `TASKS.md` — 2026-07-21

### Escalabilidad de colecciones — COL-001/002 lado Domain (2026-07-22)

- **COL-001** — `CollectionResponseDTO` no exponía `collection_type` en absoluto (constructor,
  `fromArray()`, `toArray()`); el formulario de edición del admin siempre lo mostraba vacío y el
  fallback "mantener valor actual" de `updateStructure()` lo reseteaba silenciosamente a `'other'`.
  Corregido agregando el campo al DTO. Nuevo test feature (`CollectionControllerTest::testShowExposesCollectionType`).
- **COL-002** — nueva columna `entry_cta_label` en `cms_collection_translations`, agregada
  directamente en la migración canónica `CreateCmsCollections.php` (no como migración
  incremental — este repo tiene un guardarraíl, `CleanDatabaseBootstrapConventionsTest`, que
  prohíbe migraciones que no sean `Create*` puras; la primera versión como migración `Add*`
  independiente falló ese test). Cableado en `CollectionEntity`, `CollectionTranslationModel`,
  `CollectionService::enrichEntities()`/`saveTranslations()`, ambos Request DTOs,
  `TranslationResourceCatalog` y `PublicCollectionReader` (el path que consume la web pública).
  Tests nuevos en `PublicCollectionControllerTest`.
- **Bug de infraestructura de tests encontrado y corregido**: CI4 cachea `fieldExists()`/
  `getFieldNames()` por conexión, y ni `addColumn()` ni `dropColumn()` invalidan ese caché — una
  migración `Add*`/`Drop*` guardada por `fieldExists()` se rompe en una corrida larga de PHPUnit
  que la ejecuta más de una vez contra la misma conexión (ej. `DatabaseTestTrait` refrescando
  muchas clases de test). Ya no aplica tras mover la columna a `CreateCmsCollections`, pero quedó
  documentado por si se repite el patrón.
- **Bug de fuga de mocks entre tests, encontrado y corregido**: `ApiTestCase::tearDown()` nunca
  reseteaba el singleton `hubClient` (solo `request`), así que un stub de `HubClient` inyectado
  por una clase sobrevivía como singleton compartido para el resto del proceso de PHPUnit y
  autenticaba silenciosamente tests de OTRAS clases que esperaban 401. Corregido con
  `Services::resetSingle('hubClient')` en `tearDown()` — beneficia a las 9 clases que extienden
  `ApiTestCase`. Segunda fuga relacionada: `ContextHolder` (registro estático de
  `dcardenasl/ci4-api-core`) tampoco se limpiaba fuera de `ApiTestCase`; `CollectionControllerTest`
  (que no extiende `ApiTestCase`) ahora también llama `ContextHolder::flush()` en su `tearDown()`.
- **Incidente de datos, ya resuelto**: depurando el bug de caché de `fieldExists()` arriba, un
  `migrate:refresh -g tests --force` vació por error la BD **dev** en vez de la de test. A pedido
  de David: `migrate:refresh` + `db:seed SiteBootstrapSeeder` completo desde cero en dev (en vez
  de recuperación parcial), confirmado funcionando de punta a punta.
- `composer analyse`/`format:check` limpios; suite completa (Unit+Architecture+Integration+Feature,
  474 tests) en verde. Detalle completo en `../ARCHIVE.md`.

### Auditoría de traducciones y arquitectura

- **DOM-127 / TEM-010** — publicación del Wizard respetando plantillas de bloques (2026-07-22).
  Ya existía cobertura feature (`testAutoInitializeBlocksOnEntryCreation`) contra el endpoint HTTP
  real; se agregó `testAutoInitializeMultipleBlocksPreservesSortOrder` (2 bloques declarados fuera
  de orden, confirma que `sort_order` —no la posición en el array— determina el orden final) y
  logging operativo en `EntryBlockTemplateInitializer::initialize()`: info al inicializar N
  bloques, warning si algún key de `wizard_extra` no encontró campo de bloque coincidente.
  `WizardConfigValidator`/`WizardStepFieldCatalog` (soporte domain de WIZ-STEPS-EDITOR-01, admin)
  también cerrados en este ciclo. `composer analyse`/`format:check` limpios, suite completa verde.
- **DOM-126** — corrección de presets de colecciones `news` y `portfolio`, fuente única de verdad,
  repair seeder y regresiones cubiertas.
- **DOM-125** — normalizador JSON compartido, endurecimiento de introspección de schemas y
  guardrails de dependencias de Controllers.
- **TRN-008** — auditoría de bloques por propietario, endpoint owner-scoped, aislamiento de
  propietarios, hijos incluidos y estados normalizados para el admin.
- **TRN-006** — estado `outdated` disponible en la auditoría global del dominio.
- **TRN-002** — resolución de nombres reales desde las traducciones, sin placeholders técnicos.
- **ARCH-DEEP-01** — separación de `FormService`, resolvers de uso y resolvers batch, con suite de
  calidad completa.

### Hardening y PHPStan

- **DEEP-BLOCK-01** — catálogo de bloques proyectado desde la fuente persistida y paridad de schemas.
- **DEEP-TRAN-01** — inyección explícita de dependencias en `TranslationAuditService` y catálogo de
  descriptores simples.
- **PHPSTAN-01..09** — baseline expandido drenado a cero, false-safety corregida, DTOs anotados,
  guardrails ajustados deliberadamente y suites unit/feature en verde.

### CMS y mantenimiento histórico

- **CMS-001..011** — bootstrap, schema, languages/settings, file translations, pages, menus,
  blocks, collections, entries, taxonomías, redirects y publishing programado.
- **DOM-101..111** y **BFF-107** — smoke tests, ADR de separación hub/domain, onboarding, permisos,
  validaciones, diagnóstico del hub, refactor de `HubClient` y documentación de extensiones.
- **DOM-112..124** — guardrail Controller→Model y migración progresiva de lógica a Services.

El tracker local queda sin backlog propio; las decisiones de producto y tareas cross-repo se
mantienen en `../TASKS.md`.

---

## ✅ Cierres 2026-08-05..09 — saneamiento y PublicRead

- `CFG-02`, `CFG-03`, `CFG-05`, `CORE-02` (filtros), `CORE-03` y `CORE-05` se
  verificaron y se retiraron del tracker activo.
- `LAYER-01..07`, `MIG-01..03`, `HYG-01`, `DOC-01` y la deuda de tests de la
  auditoría quedaron reconciliados según la verificación cross-repo del
  2026-08-07; los residuos explícitos siguen abiertos en `TASKS.md`.
- `PUB-00`, `PUB-01/02`, `CMS-01..05`, `SHARED-01` y `CACHE-03` se completaron
  dentro del plan de entrega pública; la fase de QA continúa abierta.

Evidencia y criterios: [`../docs/plan/2026-08-05-saneamiento-arquitectonico.md`](../docs/plan/2026-08-05-saneamiento-arquitectonico.md)
 y [`../docs/plan/2026-08-09-entrega-publica-read-model-page-delivery.md`](../docs/plan/2026-08-09-entrega-publica-read-model-page-delivery.md).
