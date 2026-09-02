# Auditoría de `cms_entry_facet_values`

## 1. Objetivo

Verificar la proyección materializada usada por los listados públicos para
filtrar y ordenar campos guardados dentro de `block_data`, y confirmar cómo se
crea y se puebla en un servidor.

## 2. Entorno

- Repositorio: `teatromuseo-cms-domain`.
- Rama: `dev`.
- Fecha: 2026-08-13.
- Estado inicial: working tree limpio y alineado con `origin/dev`.

## 3. Registro del proceso

### 2026-08-13 — Identificación

- Se encontró la migración `2026-08-12-100001_CreateCmsEntryFacetValues`.
- Se encontraron `EntryFacetValueSynchronizer`, el comando
  `cms:backfill-entry-facet-values` y los hooks de escritura.
- Se ejecutaron los quality gates y comprobaciones sobre la base local sin
  modificar datos mediante el dry-run.

## 4. Hallazgos

- La migración es DDL: crea tabla, índices, clave única y claves foráneas; no
  inserta datos.
- Los datos iniciales se generan con el comando de backfill, no con un seeder.
- Las escrituras normales de bloques de entradas sincronizan la proyección.
- Los seeders escriben bloques directamente y no invocan por sí mismos el
  sincronizador; un bootstrap o carga de contenido requiere un backfill
  posterior si crea o modifica entradas.

## 5. Correcciones aplicadas

Ninguna en el código de aplicación. Esta auditoría es de revisión. Se agregaron
únicamente estas bitácoras documental en inglés y español.

## 6. Evidencia

- La migración está aplicada en la base local, en el batch 43.
- La base local contiene 61.208 filas, 875 entradas y 80 claves de campo.
- El dry-run actual encontró 8.480 traducciones de bloques de entradas.
- `BlockInstanceServiceTest`: 4 tests y 17 assertions, todos OK.
- `PublicReadQueryBudgetTest`: 3 tests y 26 assertions, todos OK.
- `composer quality`: PHP CS Fixer, PHPStan, Swagger, arquitectura, i18n y
  fixture policy, 593 tests (1 omitido) y 15 tests de seed contracts pasaron.

## 7. Trabajo pendiente

- Ejecutar el procedimiento de backfill en cada servidor con contenido editorial
  existente después de aplicar la migración.
- Incluir el dry-run y el conteo resultante en el checklist del release.

## 8. Oportunidades de automatización

- Integrar el backfill como paso explícito y observable del release, después de
  `php spark migrate`.
- Añadir un chequeo de cobertura que compare traducciones de bloques de entrada
  con filas materializadas.

## 9. Resumen final

La implementación está integrada y cubierta por pruebas. La tabla no se puebla
con `migrate`; para contenido existente debe ejecutarse el comando de backfill
con `--confirm`. Las ediciones posteriores hechas por los servicios normales se
sincronizan automáticamente.
