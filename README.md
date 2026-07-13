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

- **Backend:** Laravel 13 (PHP ≥ 8.3), Pest, Laravel Fortify + Sanctum
- **Frontend:** React 19 + Inertia 2, TypeScript, Tailwind CSS v4, shadcn/ui (Radix)
- **Packages:** spatie/laravel-permission, spatie/laravel-medialibrary, spatie/laravel-settings, spatie/laravel-activitylog, spatie/browsershot
- **Document rendering:** a self-hosted [Carbone](https://carbone.io) server (`CARBONE_BASE_URL`, see `compose.yaml`)

## Setup

```bash
composer setup   # composer install, .env, app key, migrations, npm install, build
```

Then start everything (server, queue worker, logs, Vite):

```bash
composer dev
```

Seed reference data (roles/permissions, workflows, document templates, company profile):

```bash
php artisan db:seed
```

## Testing

```bash
composer test      # Pint check + full Pest suite
composer ci:check  # everything CI runs: ESLint, Prettier, TypeScript, Pint, tests
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
