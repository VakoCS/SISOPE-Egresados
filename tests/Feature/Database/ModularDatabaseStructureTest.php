<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ModularDatabaseStructureTest extends TestCase
{
    private const EXPECTED_COUNTS = [
        'seguimiento_egresado' => 45,
    ];

    private const SHARED_COUNTS = [
        'core' => 22,
        'seguridad' => 10,
        'comun' => 7,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL migrado mediante module:migrate all.');
        }
    }

    public function test_enabled_modules_have_the_expected_table_counts(): void
    {
        foreach (self::EXPECTED_COUNTS as $schema => $expected) {
            $actual = DB::table('information_schema.tables')
                ->where('table_schema', $schema)
                ->where('table_type', 'BASE TABLE')
                ->count();

            $this->assertSame($expected, $actual, "Conteo incorrecto en {$schema}");
        }
    }

    public function test_shared_schemas_have_the_expected_table_counts(): void
    {
        foreach (self::SHARED_COUNTS as $schema => $expected) {
            $actual = DB::table('information_schema.tables')
                ->where('table_schema', $schema)
                ->where('table_type', 'BASE TABLE')
                ->count();

            $this->assertSame($expected, $actual, "Conteo incorrecto en {$schema}");
        }
    }

    public function test_module_foreign_keys_never_target_another_module(): void
    {
        $moduleSchemas = array_keys(self::EXPECTED_COUNTS);
        $placeholders = implode(', ', array_fill(0, count($moduleSchemas), '?'));

        $invalid = DB::select(<<<SQL
            SELECT child_ns.nspname AS child_schema,
                   child.relname AS child_table,
                   constraint_definition.conname,
                   parent_ns.nspname AS parent_schema,
                   parent.relname AS parent_table
            FROM pg_constraint constraint_definition
            JOIN pg_class child ON child.oid = constraint_definition.conrelid
            JOIN pg_namespace child_ns ON child_ns.oid = child.relnamespace
            JOIN pg_class parent ON parent.oid = constraint_definition.confrelid
            JOIN pg_namespace parent_ns ON parent_ns.oid = parent.relnamespace
            WHERE constraint_definition.contype = 'f'
              AND child_ns.nspname IN ({$placeholders})
              AND parent_ns.nspname <> child_ns.nspname
              AND parent_ns.nspname NOT IN ('core', 'seguridad', 'comun', 'public')
            SQL, $moduleSchemas);

        $this->assertSame([], $invalid);
    }
}
