<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * Roles de la plataforma. Equivale a la parte de roles del UsuarioDemoSeeder
 * del proyecto hermano, SIN las cuentas demo: aquí no se crea ningún usuario
 * (la cuenta super admin es única y no se siembra).
 *
 * El ORDEN importa: admin, vendedor, super_admin -> ids 1, 2, 3. La migración
 * blindar_cuenta_super_admin_unica cae al id 3 cuando migra sobre una tabla de
 * roles vacía. SeederRolesTest rompe la suite si ese orden cambia.
 *
 * firstOrCreate en vez de create: se puede correr más de una vez sin duplicar.
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', Rol::VENDEDOR, Rol::SUPER_ADMIN] as $nombreRol) {
            Rol::firstOrCreate(
                ['nombre_rol' => $nombreRol],
                ['estado' => 1]
            );
        }
    }
}
