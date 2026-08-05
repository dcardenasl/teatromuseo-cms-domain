# Agent Quick Reference — `teatromuseo-cms-domain`

Read `CLAUDE.md` and `TASKS.md` before editing. This domain runs on port `8190`,
owns CMS content, and delegates authentication and IAM to the Hub on `8180`.

```bash
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

The normal permission sync uses this domain's `X-App-Key` and is idempotent.
`--admin-token` is only for optional mirroring or role assignment. Restart the
server after adding routes.

## Rules

- `DomainAuthFilter`/`domainauth` and `HubClient` handle protected requests;
  never issue JWTs or call Hub URLs directly from controllers.
- Public CMS routes under `/api/v1/public/*` use `webappkey`, not a user JWT.
- Keep page/block lifecycle, entries, taxonomy, translations, forms, and
  dynamic-template behavior in services.
- Use DTOs, service/repository layers, permission constants, and tests for all
  behavior changes. Permission codes use `.` rather than `:`.
- Do not commit `.env`, tokens, credentials, or business logic in views.
