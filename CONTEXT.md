# CMS Domain

Dominio de contenido (páginas, colecciones, entradas) del stack Teatro Museo. Posee sus propias tablas y
delega identidad y capacidades globales al Hub (`teatromuseo-api`), pero es dueño de una capa de
autorización propia sobre sus propios recursos.

## Language

**Capacidad global**:
Un permiso plano (`cms.pages.write`, `cms.entries.read`, etc.) resuelto y emitido por el Hub, que concede
acceso completo a todas las instancias de un tipo de recurso. No conoce IDs de páginas ni colecciones.
_Avoid_: permiso, rol

**Ámbito editorial**:
El subconjunto de páginas o colecciones concretas sobre las que un usuario con una capacidad *scoped*
(`cms.pages.scoped-read`, `cms.entries.scoped-write`) tiene acceso, decidido por el CMS Domain a partir de
sus **grants**. Una capacidad scoped por sí sola no concede acceso a nada — solo habilita la pregunta "¿tiene
grant sobre este recurso?".
_Avoid_: scope, alcance, permiso por recurso

**Grant**:
La asignación explícita de un **nivel de acceso** (`read` o `write`) de un usuario del Hub sobre una
**Página** o **Colección** concreta, propiedad del CMS Domain. `write` implica `read`. Un grant nunca
autoriza el tipo de recurso hermano (un grant de Página nunca autoriza una Entrada, un grant de Colección
nunca autoriza editar el esquema de la Colección — solo el acceso a sus Entradas).
_Avoid_: permiso, ACL, asignación

**Recurso editorial**:
Una **Página** o una **Colección** — los dos tipos de recurso sobre los que puede existir un grant en esta
primera versión. Una **Entrada** no es un recurso editorial propio: su autorización se hereda siempre de la
Colección a la que pertenece.
_Avoid_: recurso (a secas, cuando el contexto es ambiguo entre "tipo CMS cualquiera" y "recurso con grant")

## Relationships

- Un **Usuario** (del Hub, referenciado solo por `hub_user_id`, sin datos personales replicados) tiene cero
  o más **Grants**.
- Un **Grant** apunta a exactamente una **Página** o una **Colección**, nunca a ambas.
- Una **Entrada** pertenece a exactamente una **Colección**; su autorización efectiva es siempre la del
  grant de esa Colección — no existe grant de Entrada.
- **Borrar** un recurso editorial (Página, Colección) exige siempre capacidad global de admin
  (`cms.pages.admin` / `cms.collections.admin` / `cms.entries.admin`) — nunca hay una variante scoped para
  delete. Un ámbito editorial con `write` permite crear y editar, nunca borrar.

## Example dialogue

> **Dev:** "Si le doy a un editor un grant `write` sobre la Colección 'Exposiciones', ¿puede borrar una
> Entrada de esa colección?"
> **Domain expert:** "No. Ese grant le da **ámbito editorial** de write sobre las Entradas de esa
> Colección — puede crearlas y editarlas. Borrar sigue exigiendo `cms.entries.admin`, que es una
> **capacidad global**, no algo que un grant conceda nunca."

## Flagged ambiguities

- "acceso a la colección" se usó en el plan original de forma ambigua entre "editar el esquema de la
  colección" y "acceso a sus entradas" — resuelto: un grant de Colección siempre significa lo segundo. Editar
  el esquema de la colección sigue exigiendo la capacidad global `cms.collections.*`, sin scoping.
