# TASKS — teatromuseo-cms-domain

> Trabajo abierto de este repositorio. El programa cross-repo vigente está en
> [`../TASKS.md`](../TASKS.md), y los cierres históricos en
> [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).

## ✅ Completadas

_(vacío — cierres hasta 2026-08-18 en [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md))_

## 🔴 En progreso

- [ ] **REL-01** — Activación controlada; pendiente de ventana de cutover y
  baseline/shadow del runtime anterior.

## 🟡 Próximo

### Autorización editorial por recurso (2026-08-20) — ver `../docs/plan/2026-08-20-plan-autorizacion-editorial-por-recurso-cms-v2.md`

Depende de `CMS-ACCESS-01` (`ci4-platform/ci4-api-core`, filtro multi-código)
y `CMS-ACCESS-03` (`teatromuseo-api`, permisos/roles) antes de empezar.

- [ ] **CMS-ACCESS-04 — Modelo de datos y seam.** Migraciones
  `cms_page_user_access`/`cms_collection_user_access`/`cms_access_policy_revision`
  (idempotentes, tablas vacías). `App\Services\Cms\CmsResourceAccessPolicy` +
  interfaz + repositorios tipados. Los asserts devuelven `NotFoundException`
  (404) para un recurso fuera de ámbito de un actor scoped — nunca
  `AuthorizationException` (403), que queda reservada para "sin ninguna
  capacidad relevante" (ver plan §6.1). Subir constraint de
  `dcardenasl/ci4-api-core` a la versión que trae el filtro multi-código.
  Pruebas unitarias de política con `EXPLAIN` documentado.
- [ ] **CMS-ACCESS-05 — Enforcement completo.** Eliminar el chequeo manual
  duplicado (`if (!$context->hasPermission(...))`) en `PageController`/
  `CollectionController`/`EntryController` — reemplazarlo por el assert de la
  política donde la ruta gana variante scoped, quitarlo sin sustituto donde
  no. Delete de cualquier recurso exige siempre capacidad admin global
  (`cms.pages.admin` se enruta por primera vez), nunca variante scoped.
  Repositorios de listado (`PageListRepository`/`CollectionListRepository`/
  `EntryListRepository`) reciben el ámbito resuelto y aplican `EXISTS` dentro
  del CTE existente, antes de `COUNT(*) OVER()`/`LIMIT`. Pruebas negativas por
  endpoint (§12.2 del plan).
- [ ] **CMS-ACCESS-06 — API de gestión de grants y auditoría.**
  `GET`/`PUT /cms/pages/{id}/access`, `.../collections/{id}/entry-access`.
  Reemplazo transaccional (`wrapInTransaction` — código nuevo, no se asume
  como convención heredada de `PageService`), `expected_revision` para
  concurrencia (409), auditoría con actor/target/resource/acción. Depende de
  `CMS-ACCESS-04`.

### BFF de lectura directa a 4 BDs — cerrado 2026-08-14

`CollectionService`/`CategoryService`/`TagService`/`RedirectService` siguen
sirviendo su CRUD/HTTP tal cual, sin cambios de este repo (el BFF lee
directo su propia copia). Detalle de cierre (`CMS-PR-01..06`) en
`TASKS_ARCHIVE.md`.

### Saneamiento arquitectónico heredado (prioridad 2)

Estas tareas no deben competir con QA/cutover:

- [ ] **CFG-08** — Alinear versiones de CI4 y `guzzlehttp/psr7`.
- [ ] **CORE-02 residual** — Reconciliar migraciones de infraestructura y
  decidir el límite local de `AuditRepository`, `MetricModel`, `RequestLogModel`
  y `AuditLogModel`.
- [ ] **CORE-06** — Unificar **convenciones de nomenclatura** de permisos entre
  los tres dominios (catalog usa `catalog.<camelCaseSingular>.<create|read|update|delete>`,
  event usa `event.<kebab-plural>.<read|write|delete>`, cms usa
  `cms.<plural>.<read|write|admin>` — ver `docs/plan/2026-08-05-saneamiento-arquitectonico.md`
  §CORE-06). Solo con ventana de mantenimiento y migración explícita de
  `permissions`/`role_permissions` porque toca roles ya asignados en la BD del
  hub; no ejecutar por `domain:sync-permissions` solamente. **No bloquea altas
  de permisos nuevos dentro de una convención ya conforme** — los códigos
  scoped de
  [`docs/plan/2026-08-20-plan-autorizacion-editorial-por-recurso-cms-v2.md`](../docs/plan/2026-08-20-plan-autorizacion-editorial-por-recurso-cms-v2.md)
  (`cms.pages.scoped-read`, etc.) siguen la convención `cms.<plural>.<acción>`
  que CORE-06 ya trata como el objetivo a converger, no como algo a renombrar.
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
