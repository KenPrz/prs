# OpenPRS

A procurement request system: purchase requisitions, purchase orders, receiving reports, and payment request forms flow through configurable approval workflows, with signed PDF documents generated at each step.

## Features

- **Purchase Requisitions (PR)** — request items, route through department-head and approval chains, allocate to orders
- **Purchase Orders (PO)** — build orders from approved requisitions with VAT-aware totals
- **Receiving Reports (RR)** — receive against orders, track ordered/received/rejected quantities
- **Payment Request Forms (PRF)** — standalone payment approvals
- **Configurable workflows** — admin-managed approval chains per document type (roles, permissions, users, department heads, SLAs)
- **Document rendering** — DOCX templates (Carbone syntax) merged and converted to PDF, with user signatures stamped into the document
- **RBAC** — roles and permissions via spatie/laravel-permission, with a full admin config area (users, roles, departments, suppliers, item units, documents, workflows, company profile, system settings)
- **System settings** — VAT rate, currency symbol, pagination, and log retention are admin-editable (spatie/laravel-settings)
- **Audit trail** — access logs with automatic pruning

## Stack

- **Backend:** Laravel 13 (PHP ≥ 8.4), Pest, Laravel Fortify + Sanctum
- **Frontend:** React 19 + Inertia 2, TypeScript, Tailwind CSS v4, shadcn/ui (Radix)
- **Packages:** spatie/laravel-permission, spatie/laravel-medialibrary, spatie/laravel-settings, spatie/laravel-activitylog, spatie/browsershot
- **Document rendering:** a self-hosted [Carbone](https://carbone.io) server (`CARBONE_BASE_URL`, see `compose.yaml`)

## Setup

### Option A — Local (Composer)

```bash
composer setup   # composer install, .env, app key, migrations (SQLite), npm install, build
composer dev     # server + queue worker + logs + Vite, all in one
```

Document rendering needs a reachable Carbone server (`CARBONE_BASE_URL` in `.env`); everything else works without it.

### Option B — Docker (Laravel Sail via Makefile)

The Sail stack (`compose.yaml`) runs the app, Postgres, Mailpit, and the Carbone renderer — document rendering works out of the box.

```bash
composer install       # installs vendor/bin/sail (host PHP needed once)
cp .env.example .env   # switch DB_CONNECTION to pgsql, DB_HOST=pgsql, set DB_DATABASE/DB_USERNAME/DB_PASSWORD
make build             # build + start containers
make key-generate
make seed-fresh        # migrate:fresh --seed
make npm-install
make npm-dev
```

`make help` lists every command (logs, queue, cache, tests, arbitrary artisan/composer/npm).

### Seeding

Reference data (roles/permissions, workflows, document templates, company profile):

```bash
php artisan db:seed    # or: make seed
```

## Testing

```bash
composer test      # Pint check + full Pest suite
composer ci:check  # everything CI runs: ESLint, Prettier, TypeScript, Pint, tests
make test          # same suite inside the Sail container
```

Tests run against a local SQLite database (the `testing` file at the repo root, created automatically in CI).

## Document templates

The default DOCX templates live in `database/seeders/data/documents/` and are bound to document types by `DocumentSeeder` (replaced on reseed when the file changes; admin-uploaded templates are left alone). They are generated — not hand-edited — by:

```bash
php database/seeders/data/documents/generator/generate-templates.php
```

Placeholder keys must match the builders in `app/Services/DocxTemplateProcessor.php`; signature slots are placeholder images at `word/media/sig_{key}.png` that `DocxSignatureStamper` overwrites at render time.

## Admin configuration

`/admin/config` (permission-gated per section): workflows, documents, departments, suppliers, item units, users, roles, access logs, company profile, and system settings (VAT rate, currency symbol, records per page, access-log retention).
