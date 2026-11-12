<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versión de sesión de cada usuario, para poder cerrar TODAS sus sesiones.
 *
 * El login es manual (no usa Auth de Laravel), así que la tabla sessions no
 * sabe a qué usuario pertenece cada sesión y no hay forma de buscarlas para
 * borrarlas. En su lugar: la sesión guarda la versión vigente al entrar, y
 * cambiar la clave la incrementa. VerificarSesion, que ya consulta al usuario
 * en cada petición, corta cualquier sesión con una versión vieja.
 *
 * Es un entero y no una fecha para no depender de la precisión del reloj
 * (un login en el mismo segundo del cambio quedaría ambiguo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->unsignedInteger('version_sesion')->default(0)->after('clave');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('version_sesion');
        });
    }
};
