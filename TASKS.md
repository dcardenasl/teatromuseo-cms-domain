# TASKS — teatromuseo-cms-domain

> Trabajo abierto de este repositorio. El programa cross-repo vigente está en
> [`../TASKS.md`](../TASKS.md), y los cierres históricos en
> [`TASKS_ARCHIVE.md`](TASKS_ARCHIVE.md).

## ✅ Completadas

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
