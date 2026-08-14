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

### BFF de lectura directa (2026-08-13) — ver `../docs/plan/2026-08-13-plan-bff-completo.md`

No cambia el comportamiento propio de este repo (`CollectionService`/
`CategoryService`/`TagService`/`RedirectService` siguen sirviendo su CRUD/HTTP
tal cual, hasta `CMS-PR-06`).

> ⚠️ **`CMS-PR-01..05` (abajo) se completaron bajo un diseño que el plan ya no
> usa** — proponían extraer las clases a un paquete Composor compartido
> (`teatromuseo-cms-public-read` en `ci4-platform/`), consumido de vuelta por
> este mismo repo. Revisión de diseño 2026-08-13 (decisión #5 del plan): con
> un solo consumidor final (el BFF), un paquete no aporta nada — el BFF
> escribe su propia implementación en `teatromuseo-bff/app/PublicRead/Cms/`,
> usando el código de este repo como referencia de lectura, no como
> dependencia. El análisis hecho en `CMS-PR-01..05` (qué clases son limpias,
> el split de `PublicRedirectResolver`) sigue siendo válido y es justamente lo
> que informa esa implementación — no se pierde el trabajo, pero **no dejes
> ese paquete/`repositories` como si fuera parte del diseño final**; ver
> `CMS-PR-06`, que lo limpia.

- [x] **CMS-PR-01 — Crear `teatromuseo-cms-public-read` y mover lectores
  limpios.** Agregar el bloque `repositories` faltante en `composer.json`
  (hacia `ci4-api-core` y hacia los paquetes nuevos — hoy falta pese a que
  `CLAUDE.md` dice que existe). Mover `PublicReadPageReader`,
  `PublicReadNavigationReader`, `PublicReadSettingsReader` tal cual (ya son
  `BaseConnection`-only) al paquete nuevo en `ci4-platform/`, que este repo
  pasa a consumir vía path-repo.
- [x] **CMS-PR-02 — `PublicReadCollectionsReader` nuevo, desacoplar
  `PublicReadLayoutReader`.** `PublicReadLayoutReader` hoy depende de
  `CollectionService::listPublic()`, que arrastra `CacheInvalidationClient`/
  `TranslationSynchronizer` (colaboradores de escritura). Crear
  `PublicReadCollectionsReader` (`BaseConnection`-only) componiendo
  `PublicCollectionReader` directo con los repositorios, sin esos dos
  colaboradores, y usarlo en `PublicReadLayoutReader` en vez de
  `CollectionService`. `CollectionService` no se toca (sigue sirviendo CRUD).
- [x] **CMS-PR-03 — `PublicReadCategoryReader`/`PublicReadTagReader`
  nuevos.** `PublicCategoryController`/`PublicTagController` usan
  `CategoryService`/`TagService`, que llaman `model()` (service locator) — no
  son reusables tal cual por el BFF. Escribir dos lectores nuevos
  `BaseConnection`-only, mismo patrón que
  `PublicReadCollectionItemReader` de `teatromuseo-catalog-domain`.
  `CategoryService`/`TagService` no se tocan.
- [x] **CMS-PR-04 — Partir `PublicRedirectResolver::resolve()`/`recordHit()`.**
  Hoy `resolve()` escribe (`recordHit()` incrementa `hit_count`/`last_hit_at`
  en cada resolución) — incompatible con un usuario MySQL solo-`SELECT`.
  Separar en `resolve()` puro (lo que usará el BFF, vía
  `PublicReadPageBootstrapReader`) y `recordHit()` (queda invocado solo por el
  controlador HTTP propio de este dominio — el BFF nunca la llama, se pierde
  esa métrica para tráfico servido por BFF, decisión ya tomada con David).
- [x] **CMS-PR-05 — Auditar `entries`/`forms` para el paquete compartido.**
  Mismo criterio ya aplicado a pages/nav/settings: verificar si el lector
  detrás de `GET public-read/{locale}/entries/{collectionKey}` y de
  `GET public/{locale}/forms/{formKey}` ya son `BaseConnection`-only o
  necesitan el mismo tratamiento que CMS-PR-03 antes de moverse al paquete.
- [ ] **CMS-PR-06 — Retirar el HTTP público propio, una vez el BFF sea
  estable (Fase 3 del plan).** El BFF pasa a ser dueño exclusivo de la
  lectura pública migrada — no se mantiene este dominio sirviendo el mismo
  contrato en paralelo "por si acaso" (mismo criterio que ya usó este
  proyecto en los cierres `PERF-03` de hoy: cero consumidores confirmados
  fuera de Web → se borra). Bloqueada por `BFF-DB-03` (equivalente en el BFF,
  ya funcionando y verificado) y por `WEB-BFF-03` (corte de Web estable).
  **Bloqueo operativo actual:** `CMS_PREVIEW_SECRET` está vacío en el CMS y
  Admin del entorno dev (y no se simula en BFF); todavía no existe una prueba
  positiva real de `page-bootstrap?preview=1&preview_expires=&preview_sig=`.
  Configurar el mismo secreto real en BFF/CMS/Admin y ejecutar esa prueba
  antes de borrar este controlador. Al
  ejecutar: borrar `PublicReadController.php`, `PublicCategoryController.php`,
  `PublicTagController.php`, sus rutas `public-read/*`/`public/*`
  (webappkey), OpenAPI y tests de contrato específicos de esas rutas.
  **También**: `CMS-PR-01` (2026-08-13) ya agregó a este `composer.json` los
  bloques `repositories`/`require` de `ci4-public-read-core` y
  `teatromuseo-cms-public-read` — quitar ambos de este repo (ya no es parte
  del diseño, ver nota de arriba) y correr `composer update` para que
  `composer.lock`/`vendor/` queden consistentes. **No borrar todavía** las
  carpetas `ci4-platform/ci4-public-read-core/`/`ci4-platform/teatromuseo-cms-public-read/`
  — `catalog-domain`/`event-domain` (para `ci4-public-read-core`) y el BFF
  (para todas) pueden seguir declarándolas hasta que también corran su
  propia tarea de retiro; el borrado físico de esas carpetas es la tarea de
  limpieza final cross-repo en `../TASKS.md`, no esta. **Antes de borrar**,
  confirmar que el preview de Admin
  (`page-bootstrap?preview=1&preview_expires=&preview_sig=`) ya lo sirve el
  controlador nuevo del BFF — es la única capacidad no puramente pública
  anónima de este controlador. `CollectionService`/`CategoryService`/
  `TagService`/`RedirectService` (con `recordHit()`) no se tocan. Agregar al
  `CLAUDE.md` de este repo la nota de proceso: si una migración toca una
  tabla usada por `teatromuseo-bff/app/PublicRead/Cms/`, avisar/actualizar el
  BFF en la misma sesión — no hay CI cross-repo que lo detecte solo.

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
