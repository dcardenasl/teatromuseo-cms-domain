# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Catalog and event block presets** — page structure seeders now wire richer block sets for catalog and event detail pages, including fallback media and new block types.
- **Public listing page types & menu link targets** — added `events`, `catalog_listing`, `template_catalog_item`, `template_event_item` page types (folded into the base `CreateCmsPages` migration) in `CmsEnums`. Added `target_blank` link target support in `MenuItemModel`, DTOs, and services.
- **Public page reader API** — implemented `PublicPageReader` service and `/api/v1/cms/public/pages/*` endpoints (`PublicPageController`) for resolving public pages by slug/type and handling site redirects.
- **Public listing & navigation seeders** — added `CmsTeatroMuseoPublicListingPagesSeeder` and `CmsTeatroMuseoRedirectSeeder` to bootstrap CMS page structures, listing blocks, and redirect rules.
- **`entry_reference` / `entry_reference_list` block field types** — blocks can now reference
  other published entries (`BlockReferenceValidator`, `EntryReferenceResolver`,
  `EntryRelationSynchronizer`), with semantic relations tracked in `cms_entry_related`.
- **TeatroMuseo editorial collections** — companies, people, works, videos, festivals,
  exhibitions, courses and publications, seeded with their own block templates and pilot content.
- **French and Portuguese** — added as active CMS languages alongside Spanish/English; bootstrap
  content (settings, forms, menus, institutional and legal pages) is now seeded in all four.
- **`CmsTeatroMuseoInstitutionalPagesSeeder` / `CmsTeatroMuseoLegalPagesSeeder`** — real
  TeatroMuseo About/History pages and 7 legal pages (privacy, cookies, data rights, terms,
  transparency, accessibility, legal notice), replacing the generic starter-kit demo content.
- **Grouped site navigation** — `CmsTeatroMuseoNavigationSeeder` now seeds the main menu as 7
  entries behind 4 dropdowns (Nosotros, Programación, Museo, Prensa y Medios) instead of 10 flat
  items, and the footer as 3 labeled columns (Explora, Institución, Prensa y Medios) instead of a
  flat 11-item list, replacing `SiteMenuSeeder`/`SiteLegalMenuSeeder`.
- **`internal/files/*` endpoints** — `HubSignatureFilter` + `InternalFileController` let the Hub
  check whether a file is referenced by CMS content before deleting it, and invalidate this
  domain's cached file metadata after a replace, via HMAC-signed requests.
- **`compania_ficha.director` / `.director_ref`** — companies now have a raw-text `director`
  field (populated by the legacy migration) plus an optional `director_ref` `entry_reference` to
  `personas` for manual editorial curation.

### Changed

- **Default public locale** — now Spanish (`es`), matching the primary audience.

### Fixed

- **Detail page templates** — `catalog_item`/`event_item` template pages now consistently ship 4
  blocks (header, details, content, gallery); seeder contract tests were out of sync with the
  actual block set.
- **`BlockTypeUpdateRequestDTO`, `BlockInstanceUpdateRequestDTO`, `EntryUpdateRequestDTO`, `TagUpdateRequestDTO`, `CategoryUpdateRequestDTO`, `CollectionUpdateRequestDTO`, `RedirectUpdateRequestDTO`, `MenuUpdateRequestDTO`** — update requests can now explicitly clear a nullable field to `null` instead of silently dropping it.
- **`EntryBlockTemplateInitializer`** — pre-filling `block_data` from `wizard_extra` never
  worked for any block in any TeatroMuseo domain: it checked `is_array()` on a `schema_definition`
  field that CI4 casts to `stdClass`, which is always false. Fixed with `JsonCastNormalizer`.
- **`EntryService`, `PageService`** — deleting an entry or page left its `cms_block_instances`
  behind as orphans (that table has no soft-delete column), which kept counting as "in use" for
  any Hub file they referenced and blocked `DELETE /files/{id}` with a 409 indefinitely. Both
  services now purge their owned block instances on delete via the new `BlockInstancePurger`.
- **`PublicEntryControllerTest`** — two featured-image tests made a real HTTP call to `HubClient`
  instead of mocking it, so they only passed by accident (whenever the real Hub had no file at
  the hardcoded test `file_id`).
- **`EntryService::afterStore()`** — wrote entry translations before an internal `wizard_extra`
  cleanup update, so `cms_entries.updated_at` could land a second after
  `cms_entry_translations.updated_at` and make the translation audit flag a complete
  translation as "outdated". Fixed by writing translations after that housekeeping write.
