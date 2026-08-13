# TASKS — teatromuseo-cms-domain

> Trabajo abierto de este repositorio. El programa cross-repo vigente está en
> [`../TASKS.md`](../TASKS.md), y los cierres históricos en
> [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).

## ✅ Completadas

- [x] **PERF-04 — Endpoints compuestos `layout` y `page-bootstrap` en PublicRead**
  — cerrada 2026-08-13. Enmienda ADR 004 §1/§6 vía
  [`../docs/adr/006-public-read-composite-bootstrap-endpoints.md`](../docs/adr/006-public-read-composite-bootstrap-endpoints.md):
  bajo hosting sin paralelismo real confirmado, el costo dominante de una
  carga fría de Web es el número de round-trips HTTP, no el diseño de
  composición — y Web es el único consumidor confirmado de las 5 rutas
  involucradas. Se agregaron `GET public-read/{locale}/layout` (agrega
  navigation+collections+settings, mismo payload para toda página de un
  locale) y `GET public-read/{locale}/page-bootstrap/{path}` (agrega
  redirect+página), ambos como composición pura sobre los lectores/servicios
  existentes (`PublicReadNavigationReader`, `PublicReadSettingsReader`,
  `CollectionService::listPublic()`, `PublicReadPageReader`,
  `RedirectService::resolvePublic()`) — sin duplicar su lógica de query, sin
  tocar el esquema. `page-bootstrap` responde 200 con `redirect`/`page`
  explícitamente `null` en vez de 404 cuando faltan — el llamador (Web)
  necesita el resultado de ambas búsquedas incluso cuando una falta, para
  decidir su propia estrategia de resolución. Nuevas clases:
  `PublicReadLayoutReader`/`PublicReadLayoutReaderInterface`,
  `PublicReadPageBootstrapReader`/`PublicReadPageBootstrapReaderInterface`;
  2 acciones nuevas en `PublicReadController` (mismo patrón que
  `navigation()`/`settings()`); 2 rutas en `public-read.php`; 2 factories en
  `CmsDomainServices`; documentación OpenAPI + `swagger.json` regenerado;
  `PublicReadOpenApiContractTest` extendido con los 2 paths nuevos.
  Verificado: 620/620 tests (608 previos + 12 nuevos: 6 unitarios + 6 de
  feature), PHPStan 0 errores, CS-Fixer limpio, contrato OpenAPI verde.

- [x] **PERF-03 — Retirar la ruta pública duplicada `public/{lang}/entries/{collection}`**
  — cerrada 2026-08-13. Al investigar antes de borrar (siguiendo la propia
  disciplina de este proyecto) se encontró que `PublicEntryController` NO era
  un duplicado puro: tenía dos capacidades que `public-read` no tenía. (1)
  Modo preview de borradores vía firma (`?preview=1&preview_expires=&preview_sig=`)
  — resultó ser un **bug real y vivo**: `teatromuseo-web`
  (`SiteEntryService::getBySlug()`) ya enviaba esos parámetros a
  `public-read/{lang}/entries/...`, pero `PublicReadEntryShowRequestDTO` no
  los declaraba, así que se descartaban en silencio. Corregido portando el
  soporte a `PublicReadEntryShowRequestDTO` +
  `PublicReadEntryReader::show()` (mismo patrón que `PublicPageShowRequestDTO`
  ya usaba para páginas), antes de borrar nada. (2) Alias
  `cursos`→`teatroescuela` (remanente de un rename de colección de
  2026-08-02, LEGACY-MAP-031) — decisión explícita de NO portarlo: cero
  llamadores reales confirmados, es un shim transicional específico de la
  ruta legacy. Con la brecha de preview cerrada, se borró
  `PublicEntryController`, las 2 rutas en `cms.php`, `EntryService::listPublic()`/`showPublic()`
  (delegaban una línea) y su declaración en `EntryServiceInterface`
  (incluida la dependencia ahora sin uso `PublicEntryReader` del constructor
  de `EntryService`). Los 16 tests de `PublicEntryControllerTest.php` se
  migraron a `tests/Feature/Controllers/Cms/PublicReadEntryControllerTest.php`
  (renombrado — probaba `PublicReadController`, no el controlador borrado),
  ajustando la forma real del envelope (`ok` en vez de `status:'success'`,
  `search`/`per_page` en vez de `q`/`limit`) — los 16 pasaron en el primer
  intento tras el análisis previo. Verificado: 608/608 tests, PHPStan 0
  errores, CS-Fixer limpio, swagger sin cambios (el controlador borrado no
  tenía anotaciones OpenAPI propias). Evidencia:
  [`../docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md`](../docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md).

- [x] **PERF-01 — Materializar facetas/orden de listados públicos, eliminar
  findAll() sin límite** — cerrada 2026-08-13. Tabla `cms_entry_facet_values`
  + `EntryFacetValueSynchronizer` enganchado en `BlockInstanceService` y
  `EntryBlockTemplateInitializer` (ambos write-paths de block_data), backfill
  idempotente (`cms:backfill-entry-facet-values`, corrido contra dev: 61.208
  filas / 80 field_keys / 875 entries). `PublicEntryReader::listPublic()`
  reescrito: los dos `findAll()` sin límite (facet filter y
  `order_by=field:...`, este segundo no documentado en la parte 1 del
  informe) reemplazados por WHERE/ORDER BY/LIMIT reales vía subquery
  derivada con fallback de idioma; `SELECT cms_entries.*` reemplazado por
  proyección explícita sin `wizard_extra`; `include=listing_content.<sub>`
  soporta selección parcial del blob. De paso se corrigió un bug preexistente
  en `BlockInstanceService::blockSchemaDefinition()` (no usaba
  `JsonCastNormalizer::toArray()`, devolvía `[]` siempre para schema real —
  bloqueaba también `blockReferenceValidator`/`fileUrlResolver`). Verificado:
  608/608 tests (2 nuevos de corrección+EXPLAIN, 1 de integración del write
  path), PHPStan 0 errores, CS-Fixer limpio. Evidencia:
  [`../docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md`](../docs/audits/2026-08-12-auditoria-parte2-rendimiento-listados-publicos.md).

- [x] **ADM-DASH-02 — Proyección CMS alineada al esquema real** — cerrada
  2026-08-11. La consulta de traducciones usa únicamente title y slug,
  recibe permisos antes de contar/leer y cuenta con prueba de integración
  contra cms_page_translations.

- [x] **QA-01 — Contract tests y OpenAPI** — cerrada 2026-08-10. Contrato
  PublicRead CMS, OpenAPI, auth, envelope, fallback, regresión CRUD y estados
  verificados. Evidencia en [`../docs/audits/2026-08-10-qa-01-contractos-openapi.md`](../docs/audits/2026-08-10-qa-01-contractos-openapi.md).
- [x] **QA-02 — EXPLAIN, índices y budgets SQL** — cerrada 2026-08-10. Listing
  de páginas medido con 2.000 fixtures, máximo 4 queries/500 ms SQL y
  `idx_page_status`; sin N+1. Evidencia en
  [`../docs/audits/2026-08-10-qa-02-explain-indexes.md`](../docs/audits/2026-08-10-qa-02-explain-indexes.md).
- [x] **QA-03 — Carga fría/caliente/degradada y single-flight** — cerrada
  2026-08-10 como tarea raíz cross-repo; evidencia en
  [`../docs/audits/2026-08-10-qa-03-cache-concurrency.md`](../docs/audits/2026-08-10-qa-03-cache-concurrency.md).
- [x] **QA-04 — Paridad y shadow comparison** — cerrada 2026-08-10 como tarea
  raíz cross-repo; evidencia en
  [`../docs/audits/2026-08-10-qa-04-paridad-shadow.md`](../docs/audits/2026-08-10-qa-04-paridad-shadow.md).
- [x] **ADM-DASH-01 — Resumen agregado del dashboard administrativo** — cerrada
  2026-08-11. Lectura autenticada y permission-aware para datos propiedad del
  CMS, con contrato y smoke de autenticación.

## 🔴 En progreso

- [ ] **REL-01** — Activación controlada; pendiente de ventana de cutover y
  baseline/shadow del runtime anterior.

## 🟡 Próximo

### Plan vigente — PublicRead/PageDelivery/Snapshots (2026-08-09)

`PUB-00`, `PUB-01/02`, `CMS-01..05`, `SHARED-01` y `CACHE-03` están cerradas
y archivadas. Este repo participa ahora en:


`QA-01..04` se mantienen aquí solo como espejo local de la tarea raíz; no crear
una implementación paralela ni trabajar casillas separadas del tracker
cross-repo.

### Saneamiento arquitectónico heredado (prioridad 2)

Estas tareas no deben competir con QA/cutover:

- [ ] **CFG-08** — Alinear versiones de CI4 y `guzzlehttp/psr7`.
- [ ] **CORE-02 residual** — Reconciliar migraciones de infraestructura y
  decidir el límite local de `AuditRepository`, `MetricModel`, `RequestLogModel`
  y `AuditLogModel`.
- [ ] **CORE-06** — Unificar permisos solo con ventana de mantenimiento y
  migración explícita de `permissions`/`role_permissions`; no ejecutar por
  `domain:sync-permissions` solamente.
- [ ] **TEST-01** — Resolver la flakiness de la suite completa y de
  `FileReferenceSynchronizer`.

### Dependencias y conflictos

- `QA-02` quedó cerrada; sus cambios de esquema están respaldados por EXPLAIN y
  sus regresiones deben mantenerse al modificar los readers públicos.
- `CORE-02`/`CORE-06` pueden cambiar infraestructura o auth; ejecutarlos después
  de `QA-04`, con contratos regenerados y sin tocar el camino público durante el
  cutover.
- Las tareas de seeders, limpieza y documentación cerradas en el saneamiento del
  2026-08-05/07 fueron retiradas de este tracker y están en el archivo.

## 🏗️ Contratos de arquitectura

- Controladores delgados con `ApiController::handleRequest()` y DTOs.
- Lecturas públicas separadas del CRUD administrativo.
- Autenticación delegada al Hub; no decodificar JWT localmente.
- Permisos con `.` y no con `:`.
- Ninguna vista o servicio de render inicia I/O; los datos públicos se preparan
  antes del render y se entregan mediante snapshots/caché.
