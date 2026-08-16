# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Removed

- **`/api/v1/public-read/{locale}/**`, `/api/v1/public/{locale}/categories/{key}` and
  `/api/v1/public/{locale}/tags/{key}`** — retired now that `teatromuseo-bff` reads this
  domain's database directly and serves the public-read surface exclusively; removed the
  controllers, DTOs, readers, interfaces, OpenAPI docs and their transitional shared-package
  dependencies.

### Added

- **`/api/v1/public-read/{locale}/layout` and `/page-bootstrap/{path}`** — composite
  PublicRead endpoints (ADR 006) aggregating navigation+collections+settings and
  redirect+page respectively, so `teatromuseo-web` can resolve a cold page load in 2
  HTTP round trips instead of 5, without duplicating any existing reader's query logic.
- **`/api/v1/public-read/{locale}/...` endpoints** — versioned envelope read model covering
  pages, navigation, settings, and collection entries (listing + detail), gated by a dedicated
  public-read throttle bucket and served through set-based queries with batched media resolution.
- **`HubClient::resolvePublicFileMeta()`** — chunks batches to the Hub's 200-id limit and falls
  back to a bounded stale cache when the Hub is unreachable, instead of dropping the miss set.
- **Declarative public listing projections** — public entries now expose schema-declared fields,
  date metadata, ordering, filtering, and projection values for configurable collection cards.
- **Editorial route canonicalization** — Editorial now owns the canonical collection index and
  localized routes, with legacy publication URLs redirected and obsolete navigation targets retired.
- **Editable institutional team roster** — About-page team members are now persisted as
  editable `team_member` children with profession, roles, email, primary media, and hover media.
- **Cache invalidation source tracking** — automatic CMS invalidations now identify their source
  when reporting status to the public website.

- **Course timeline listings** — public TeatroEscuela/course listings now order upcoming
  activities first and expose their real `start_date` as `display_date`.
- **Collection grid metadata and defaults** — collection listings now support source metadata,
  configurable aspect ratios, and bootstrap normalization of per-collection defaults.
- **CMS slug and translation tooling** — added slug repair, legacy translation backfill, and
  untranslated-content audit commands, plus a home hero slider recovery command.
- **Semantic block navigation** — block types can declare navigation metadata and public readers
  can resolve typed destinations for slide blocks.
- **Editorial content expansion** — added publication page types and collections, institutional
  team content, press document galleries, and public listing video projections.
- **TeatroEscuela contract** — introduced the canonical `teatroescuela` collection and
  `teatroescuela_ficha` block, with compatibility aliases for legacy `cursos` requests.

- **Institutional About-page content migration** — normalized About-page blocks and locales,
  persisted approved institutional copy, and migrated the selected legacy team roster into CMS
  content migrations.

- **`POST /api/v1/cms/submissions/import`** — authenticated bulk-import endpoint for
  `cms_form_submissions` (`FormSubmissionImportRequestDTO` + `FormSubmissionService::import()`,
  gated by `cms.submissions.write`), for backfilling historical submissions with their real
  `created_at`/`status` instead of the public endpoint's always-now/always-new behavior.
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

- **Institutional bootstrap preservation** — seeders now retain editorially managed blocks,
  consolidate duplicate team roots, restore historical hero slides, and normalize additional roles.
- **Translation audit scope and pagination** — audit reports now distinguish actionable issues
  from review-only warnings, ignore operational block values, exclude the base language from
  outdated checks, and return paginated metadata.

- **Default public locale** — now Spanish (`es`), matching the primary audience.
- **Bootstrap and route normalization** — seeders preserve existing editorial content, public
  routes use localized canonical slugs, and editorial data migrations are classified explicitly.

### Fixed

- **`PublicEntryReader` translation media** — featured/OG images are now resolved in one batched
  Hub call per request instead of one call per image, matching the N+1-safe pattern already used
  elsewhere in the read path.
- **Stored media URLs** — CMS content now persists portable, host-less `/uploads/...` paths
  instead of baking in the Hub's deployment host at save time; `FileUrlResolver` resolves the
  public delivery URL (via the new `hub.publicUrl`) only when content is served, and a
  sanitization pass normalizes previously-stored rows.
- **`GET cms/public/languages`** — moved into the `webappkey + throttle` filter group like every
  other public endpoint in this file; it previously accepted anonymous, unthrottled requests.
- **Operational translation regression fixture** — seed integration coverage now matches the
  normalized operational translation data used by the CMS bootstrap.
- **Publication type rollback** — rolling back publication page types now reclassifies affected
  pages as generic instead of aborting or deleting editorial records.

- **Duplicate TeatroEscuela collections** — identifier normalization now merges legacy and
  canonical collections, reassigns their content and translations, and removes the obsolete
  duplicate safely.
- **`EntryBlockTemplateInitializer`** — required blocks auto-created for an entry that is
  already published now start published too, instead of always defaulting to private and
  hiding the entry's own required content from public readers.
- **`CmsContentSanitizationSeeder`** — backfills the same private-block bug for existing
  published video entries by validating and publishing their `video_ficha` block data.

- **Public route and redirect consistency** — redirects survive slug changes and localized
  homepage hero destinations are normalized to the currently published routes.
- **TeatroEscuela public ordering and identifiers** — public entry, category, and tag readers,
  slug repair, collection presets, and navigation now use the canonical TeatroEscuela contract.

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
