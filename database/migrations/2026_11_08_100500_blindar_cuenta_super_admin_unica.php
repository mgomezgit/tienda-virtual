<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Defensa EN LA BASE de la cuenta de plataforma (rol super_admin), con el mismo
 * patrón de columna generada STORED + índice único condicional que ya se usó
 * para la unicidad de correo y usuario entre cuentas activas. Si alguien inserta
 * saltándose el controller y el Service, la base rechaza igual.
 *
 * Se imponen DOS reglas:
 *   A. Como MÁXIMO una cuenta super_admin ACTIVA en toda la plataforma.
 *   B. Una cuenta super_admin ACTIVA no puede tener negocio (tenant_id null).
 *
 * Cómo se resuelve el id del rol (sin escribirlo fijo a mano):
 * El rol super_admin se identifica por su NOMBRE en la tabla roles, pero una
 * columna generada no puede llevar una subconsulta, así que su expresión
 * necesita el id como literal. Ese id se LEE de la tabla roles en el momento de
 * migrar. En una base ya poblada (producción) la lectura devuelve el id real.
 * En un esquema recién creado (la suite de pruebas corre migrate:fresh y las
 * migraciones NUNCA siembran roles) la tabla está vacía al migrar, así que se
 * cae al id con el que el seeder del proyecto y TODAS las pruebas fijan el rol
 * super_admin. En ambos entornos el literal que queda en la expresión es el id
 * verdadero del rol; no hay un número suelto escrito a mano en el SQL.
 */
return new class extends Migration
{
    /**
     * Id del rol super_admin: leído de la base; si el esquema aún no tiene roles
     * (migrate:fresh de las pruebas), se usa el id convencional del proyecto.
     */
    private function idSuperAdmin(): int
    {
        $id = DB::table('roles')->where('nombre_rol', 'super_admin')->value('id_rol');

        // El seeder (RolesSeeder) crea los roles en el orden admin,
        // vendedor, super_admin -> 1, 2, 3; y cada prueba fija super_admin en 3.
        // SeederRolesTest rompe la suite si ese orden cambia.
        return $id !== null ? (int) $id : 3;
    }

    public function up(): void
    {
        $idSuper = $this->idSuperAdmin();

        // Regla A: vale 1 solo para una cuenta super_admin activa, NULL para el
        // resto. Un índice único admite varios NULL, así que solo puede existir
        // una fila con el valor 1: una sola cuenta super_admin activa.
        DB::statement(
            'ALTER TABLE usuarios ADD COLUMN super_admin_activo_unico TINYINT'
            ." GENERATED ALWAYS AS (CASE WHEN id_rol = $idSuper AND estado = 1 THEN 1 ELSE NULL END) STORED"
        );

        DB::statement('CREATE UNIQUE INDEX usuarios_super_admin_activo_unico ON usuarios (super_admin_activo_unico)');

        // Regla B: vale NULL justo cuando una cuenta super_admin activa trae
        // negocio (el caso prohibido) y 1 en cualquier otro caso. Declarada NOT
        // NULL, la base rechaza ese insert: la expresión no puede dar NULL.
        // Esta columna VA por VIRTUAL, no STORED, y a propósito: su expresión
        // depende de tenant_id, y la llave foránea de tenant_id es ON DELETE SET
        // NULL. MySQL 8.4 prohíbe que una columna generada STORED se base en una
        // columna con esa acción referencial (error 1215), pero sí lo permite en
        // una VIRTUAL. SQLite acepta ambas. Una columna virtual también evalúa y
        // comprueba su NOT NULL al insertar, que es lo que hace cumplir la regla.
        // El NOT NULL va DESPUÉS de la cláusula generada (MySQL exige ese orden).
        DB::statement(
            'ALTER TABLE usuarios ADD COLUMN super_admin_sin_negocio TINYINT'
            ." GENERATED ALWAYS AS (CASE WHEN id_rol = $idSuper AND estado = 1 AND tenant_id IS NOT NULL THEN NULL ELSE 1 END) VIRTUAL NOT NULL"
        );
    }

    public function down(): void
    {
        Schema::table('usuarios', function ($table) {
            $table->dropUnique('usuarios_super_admin_activo_unico');
        });

        Schema::table('usuarios', function ($table) {
            $table->dropColumn(['super_admin_activo_unico', 'super_admin_sin_negocio']);
        });
    }
};
