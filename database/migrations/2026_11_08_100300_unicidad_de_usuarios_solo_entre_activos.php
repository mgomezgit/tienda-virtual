<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El correo y el nombre de usuario pasan a ser únicos solo entre cuentas
 * ACTIVAS. Al desactivar a alguien, sus credenciales quedan libres para
 * reutilizarse, incluso en otro negocio.
 *
 * La restricción sigue viviendo en la base, no solo en la validación de la
 * aplicación: si alguien inserta saltándose el controller, la base rechaza igual.
 *
 * El truco son dos columnas generadas STORED que valen NULL cuando la cuenta
 * está inactiva. Un índice único admite varios NULL, así que las cuentas
 * inactivas dejan de competir por el valor, y las activas siguen protegidas.
 *
 * Las columnas originales "email" y "usuario" NO se tocan: conservan el dato
 * real completo para siempre (auditoría y soporte), esté la cuenta activa o no.
 * Lo único que cambia es CUÁNDO ese valor cuenta para la restricción.
 */
return new class extends Migration
{
    /**
     * La sintaxis de columna generada es la misma en MySQL 8.4 (producción) y en
     * SQLite (las pruebas), así que una sola sentencia sirve para ambos.
     */
    public function up(): void
    {
        Schema::table('usuarios', function ($table) {
            $table->dropUnique('usuarios_email_unique');
            $table->dropUnique('usuarios_usuario_unique');
        });

        DB::statement(
            'ALTER TABLE usuarios ADD COLUMN email_activo_unico VARCHAR(150)'
            .' GENERATED ALWAYS AS (CASE WHEN estado = 1 THEN email ELSE NULL END) STORED'
        );

        DB::statement(
            'ALTER TABLE usuarios ADD COLUMN usuario_activo_unico VARCHAR(50)'
            .' GENERATED ALWAYS AS (CASE WHEN estado = 1 THEN usuario ELSE NULL END) STORED'
        );

        DB::statement('CREATE UNIQUE INDEX usuarios_email_activo_unico_unique ON usuarios (email_activo_unico)');
        DB::statement('CREATE UNIQUE INDEX usuarios_usuario_activo_unico_unique ON usuarios (usuario_activo_unico)');
    }

    /**
     * Revertir restaura la unicidad global sobre las columnas originales.
     *
     * OJO: si para entonces ya existen credenciales repetidas entre cuentas
     * inactivas —justo lo que esta migración permite—, la creación del índice
     * único global fallará. Eso es esperado, no un fallo de la migración: son
     * datos que la política anterior no admitía. Para revertir habría que
     * resolver primero esos duplicados a mano.
     */
    public function down(): void
    {
        // Se usa el constructor de esquema y no SQL crudo: la sentencia para
        // eliminar un índice difiere entre MySQL y SQLite.
        Schema::table('usuarios', function ($table) {
            $table->dropUnique('usuarios_email_activo_unico_unique');
            $table->dropUnique('usuarios_usuario_activo_unico_unique');
        });

        Schema::table('usuarios', function ($table) {
            $table->dropColumn(['email_activo_unico', 'usuario_activo_unico']);
        });

        Schema::table('usuarios', function ($table) {
            $table->unique('email', 'usuarios_email_unique');
            $table->unique('usuario', 'usuarios_usuario_unique');
        });
    }
};
