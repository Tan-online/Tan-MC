# Tan-MC Muster Compliance System

Tan-MC is an enterprise Laravel application for managing muster roll compliance, client structure, operational dispatch, executive mapping, bulk data imports, and reporting in a corporate ERP-style workflow.

## Tech Stack

- Laravel 12
- PHP 8.2+
- MySQL
- Bootstrap 5
- Laravel Breeze
- `maatwebsite/excel` for bulk Excel import and export
- `barryvdh/laravel-dompdf` for PDF report export

## Core Modules

- Dashboard with role-based workspaces
- Departments, States, Operation Areas, Teams
- Users and role-based access control
- Clients, Locations, Contracts, Service Orders
- Executive Mapping and Executive Replacement
- Bulk Excel imports with templates and row validation
- Muster cycle engine and compliance tracking
- Bulk location receive and review workflow
- Compliance dashboard charts and exportable reports

## Supported Dashboard Roles

- `super_admin`
- `admin`
- `operations`
- `reviewer`
- `viewer`

The dashboard layout and sidebar visibility adjust based on the logged-in user's effective role.

## Local Setup

1. Install PHP and Composer dependencies.
2. Install Node dependencies.
3. Copy the environment file and update database settings.
4. Generate the app key.
5. Run migrations.
6. Start the local development services.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run dev
php artisan serve
```

## Environment Notes

Update these values in `.env` before first use:

- `APP_NAME="Tan-MC"`
- `APP_URL`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

## Common Commands

```bash
php artisan migrate
php artisan optimize:clear
php artisan view:clear
php artisan route:list
php artisan muster:generate
php artisan test
```

## Bulk Import Support

Excel import is available for:

- Clients
- Locations
- Contracts
- Service Orders

Each module provides:

- Template download
- Row validation
- Error reporting
- Valid-row-only inserts

## Reporting

The reporting module includes:

- Client Compliance Report
- State Compliance Report
- Executive Performance Report
- Excel export
- PDF export

## Scheduler

The muster cycle generator is scheduled in `routes/console.php`.

For production, make sure the Laravel scheduler is running:

```bash
php artisan schedule:work
```

## Authentication

- The app opens directly on the login page.
- Login uses `employee_code` and password.
- User access is controlled through roles and middleware.

## Repository

GitHub: `https://github.com/Tan-online/Tan-MC`
