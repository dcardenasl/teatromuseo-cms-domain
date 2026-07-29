# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Catalog and event block presets** — page structure seeders now wire richer block sets for catalog and event detail pages, including fallback media and new block types.
- **Public listing page types & menu link targets** — added `events`, `catalog_listing`, `template_catalog_item`, `template_event_item` page types in `CmsEnums` and schema migration `2026-07-28-000002_AlterCmsPagesForPublicListingPageTypes`. Added `target_blank` link target support in `MenuItemModel`, DTOs, and services.
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

### Changed

- **Default public locale** — now Spanish (`es`), matching the primary audience.
