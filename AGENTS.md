# AGENTS.md — `teatromuseo-cms-domain`

## Purpose and boundaries

This is the Editorial CMS domain application, served locally on port `8190`.
It owns pages, blocks, entries, collections, menus, taxonomy, forms, and
editorial settings in its own database.

The central Hub is `teatromuseo-api` on port `8180`:

- This application never issues JWTs and never stores users or IAM tables.
- `DomainAuthFilter` (`domainauth`) delegates bearer-token introspection to
  `App\Libraries\Hub\HubClient`.
- `HubClient` is the only application class allowed to call Hub URLs.
- `/api/v1/public/*` routes are for the public Web application and use the
  `webappkey` filter; they do not require a user JWT.

Read this repository's `CLAUDE.md` and `TASKS.md` before editing. Check the
repository status first and keep unrelated work intact.

## Important entry points

- `app/Filters/DomainAuthFilter.php` — `domainauth` filter.
- `app/Filters/WebAppKeyRequiredFilter.php` — public Web app-key filter.
- `app/Libraries/Hub/HubClient.php` — Hub introspection and service calls.
- `app/Config/DomainPermissions.php` — CMS permission catalog.
- `app/Commands/SyncPermissions.php` — `php spark domain:sync-permissions`.
- `app/Config/Scaffolding.php` — CRUD generator configuration.
- `app/Config/Routes/v1/cms.php` — CMS and public CMS routes.

Permission codes use a dot separator, for example `cms.pages.read`. Never use
`:` inside a permission code because CodeIgniter parses colons as filter
arguments.

## Commands

Run these from this repository root:

```bash
composer install
php spark serve --port 8190

php spark migrate
php spark domain:sync-permissions

bash vendor/bin/make-crud.sh ResourceName Cms 'field:type:rules,...' yes [route]
php spark module:check ResourceName --domain Cms
php spark swagger:generate

composer test:unit
composer test:integration
composer test:feature
composer quality
composer cs-fix
```

The primary permission sync uses this domain's `X-App-Key` and is idempotent;
it does not require a superadmin JWT. `--admin-token` is only needed for the
optional `--mirror-to-self` or `--assign-to-role` operations.

After scaffolding a resource, run its migration, validate the generated module,
regenerate Swagger, and restart `php spark serve`; route files are not
hot-reloaded.

## Architecture rules

- Controllers orchestrate HTTP and DTOs; business decisions belong in services.
- Request DTOs are validated by their constructors before reaching services.
- Services remain HTTP-agnostic and use the repository/model layer for data.
- Use the generated per-resource auth and permission filters; do not bypass
  `DomainAuthFilter` for protected routes.
- Keep public routes app-key-gated and free of user-session assumptions.
- Keep content lifecycle, block validation, taxonomy, and translation behavior
  in CMS services, not in controllers or views.
- Add tests for every behavior change, including public endpoint contracts,
  editor/admin permission boundaries, and dynamic-template behavior.

## Anti-patterns

- Do not issue JWTs or implement a local JWT secret.
- Do not call Hub endpoints directly from controllers or services outside
  `HubClient`.
- Do not use `Services::userModel()` or copy Hub IAM services into this app.
- Do not hardcode permission strings when a permission catalog constant exists.
- Do not put business logic in views or commit `.env` files and credentials.
