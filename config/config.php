<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    /*
     * Define presets or groups of tables that can be refreshed together.
     * Groups can be an array of table names (auto-guesses seeders) or key-value pairs of table => seeder.
     * 
     * Example:
     * 'groups' => [
     *     'auth' => [
     *         'users' => 'Database\\Seeders\\UserSeeder',
     *         'roles' => 'Database\\Seeders\\RoleSeeder',
     *         'permissions' => 'Database\\Seeders\\PermissionSeeder',
     *     ],
     *     'shop' => ['products', 'categories', 'orders'],
     * ]
     */
    'groups' => [
        // 'auth' => ['users', 'roles', 'permissions'],
    ],

    /*
     * Default connection to use if not specified in command.
     * If null, the default database connection of Laravel will be used.
     */
    'connection' => null,
];