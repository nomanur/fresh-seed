# Laravel Fresh Seed

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nomanurrahman/fresh-seed.svg?style=flat-square)](https://packagist.org/packages/nomanurrahman/fresh-seed)
[![Total Downloads](https://img.shields.io/packagist/dt/nomanurrahman/fresh-seed.svg?style=flat-square)](https://packagist.org/packages/nomanurrahman/fresh-seed)
![GitHub Actions](https://github.com/nomanurrahman/fresh-seed/actions/workflows/main.yml/badge.svg)

A lightweight Laravel package that allows you to truncate and re-seed database tables surgically. This is perfect for development when you need to refresh specific data without running a full `migrate:fresh --seed` which wipes your entire database.

---

## Features

- 🎯 **Surgical Refresh**: Truncate/delete only the tables you specify.
- 🔀 **Multi-Table Support**: Refresh multiple tables at once (e.g., `users,posts`).
- 📂 **Table Presets/Groups**: Define curated groups of tables in the config to refresh together.
- 🗺️ **Interactive Selection**: Run the command without arguments to select tables from an interactive console prompt.
- 🔍 **Dry-Run Preview**: Preview what actions would be performed without actually modifying database records.
- 🔌 **Custom Database Connections**: Execute the operations against a non-default connection.
- 🛡️ **Constraint Safety & Safe Deletion**: Disables foreign key constraints during the process, with an optional `--safe` mode utilizing `DELETE` instead of `TRUNCATE` to support transactions and avoid implicit database commits.
- ⚙️ **Auto-Seeder Detection**: Automatically locates appropriate seeders using standard Laravel conventions.

---

## Installation

Install the package via composer as a development dependency:

```bash
composer require --dev nomanurrahman/fresh-seed
```

### Configuration (Optional)

To configure presets/groups or default connections, publish the configuration file:

```bash
php artisan vendor:publish --provider="Nomanurrahman\FreshSeed\FreshSeedServiceProvider" --tag="config"
```

This will create a `config/fresh-seed.php` file:

```php
return [
    /*
     * Define presets or groups of tables that can be refreshed together.
     * Groups can be an array of table names (auto-guesses seeders) or key-value pairs of table => seeder.
     */
    'groups' => [
        'auth' => [
            'users' => 'Database\\Seeders\\UserSeeder',
            'roles' => 'Database\\Seeders\\RoleSeeder',
            'permissions' => 'Database\\Seeders\\PermissionSeeder',
        ],
        'shop' => ['products', 'categories', 'orders'],
    ],

    /*
     * Default connection to use if not specified in command.
     */
    'connection' => null,
];
```

---

## Usage

### 1. Interactive Selection
Run the command without options to select which table(s) to refresh interactively:
```bash
php artisan fresh:seed
```

### 2. Basic Single-Table Refresh
Refresh a single table and let the package locate the matching seeder automatically:
```bash
php artisan fresh:seed --table=users
```

> **Note:** For safety, the command asks for confirmation if run in a production environment. You can bypass this with the `--force` flag.

### 3. Multi-Table Refresh
Refresh multiple tables sequentially using a comma-separated list:
```bash
php artisan fresh:seed --table=users,posts,comments
```

### 4. Running Table Groups
Refresh pre-configured groups defined in `config/fresh-seed.php`:
```bash
php artisan fresh:seed --group=auth
```

### 5. Specifying a Custom Seeder
Explicitly define a custom seeder class (only valid when refreshing a single table):
```bash
php artisan fresh:seed --table=users --seeder=CustomUserSeeder
```

### 6. Safe Deletions
Use `DELETE` instead of `TRUNCATE` to avoid implicit database commits (useful for maintaining database transaction integrity):
```bash
php artisan fresh:seed --table=posts --safe
```

### 7. Dry-Run Mode
Preview the execution path without modifying database records:
```bash
php artisan fresh:seed --group=auth --dry-run
```

### 8. Targeting a Custom Connection
Run the refresh against a secondary or tenant database connection:
```bash
php artisan fresh:seed --table=users --database=tenant_1
```

---

## How Seeder Detection Works

If you don't provide a `--seeder` option or group mapping, the package looks for seeders in the following order:

1. `StudlyCaseTableNameTableSeeder` (e.g., `UsersTableSeeder`)
2. `StudlyCaseSingularTableNameSeeder` (e.g., `UserSeeder`)

Checks are conducted in both the root namespace and the standard `Database\Seeders\` namespace.

---

## Testing

The package includes a comprehensive test suite. You can run the tests using:

```bash
composer test
```

## Security

If you discover any security-related issues, please email nomanurrahman@gmail.com instead of using the issue tracker.

## Credits

- [nomanur rahman](https://github.com/nomanurrahman)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
