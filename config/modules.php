<?php

$module = static fn (
    string $name,
    string $schema,
    string $directory,
    bool $migrationsEnabled = false,
): array => [
    'name' => $name,
    'schema' => $schema,
    'migration_path' => "app/Modules/{$directory}/Database/Migrations",
    'migrations_enabled' => $migrationsEnabled,
    'dependencies' => $migrationsEnabled ? ['shared'] : [],
];

return [
    'seguimiento-egresado' => $module('Seguimiento al Egresado', 'seguimiento_egresado', 'SeguimientoEgresado', true),
];
