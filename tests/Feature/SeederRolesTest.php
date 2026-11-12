<?php

namespace Tests\Feature;

use App\Models\Rol;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coherencia entre el seeder de roles y el id de respaldo de la migración
 * 2026_11_08_100500_blindar_cuenta_super_admin_unica.
 *
 * Esa migración, cuando la tabla roles está vacía al migrar (migrate:fresh),
 * cae al id 3 para super_admin (y la columna generada de la base queda ligada a
 * ese id). El seeder crea los roles por orden de inserción, así que super_admin
 * termina en 3. Si alguien reordena o cambia el seeder y super_admin deja de
 * ser 3, la defensa de la base quedaría ligada al id equivocado: esta prueba lo
 * detecta antes de que eso llegue a producción.
 *
 * Mutación verificada (2026-11-08): en RolesSeeder se invirtió el orden de
 * vendedor y super_admin -> fallaron 3 de 4 (las que miran ids; la de
 * conteo sigue en verde, como corresponde). Restaurado -> las 4 pasan.
 */
class SeederRolesTest extends TestCase
{
    use RefreshDatabase;

    /** Los ids que el seeder DEBE producir, iguales a los que asumen la
     *  migración de blindaje y toda la suite. */
    const IDS_ESPERADOS = [
        'admin' => 1,
        'vendedor' => 2,
        'super_admin' => 3,
    ];

    public function test_el_seeder_crea_los_roles_con_los_ids_que_asume_la_migracion(): void
    {
        $this->seed(RolesSeeder::class);

        foreach (self::IDS_ESPERADOS as $nombre => $idEsperado) {
            $idReal = Rol::where('nombre_rol', $nombre)->value('id_rol');

            $this->assertSame(
                $idEsperado,
                (int) $idReal,
                "El seeder debe crear el rol '$nombre' con id $idEsperado (lo asume la migracion de blindaje del super admin). "
                .'Si cambia el orden o el id en el seeder, hay que revisar el id de respaldo de la migracion.'
            );
        }
    }

    public function test_el_id_de_respaldo_de_la_migracion_coincide_con_el_super_admin_del_seeder(): void
    {
        $this->seed(RolesSeeder::class);

        $this->assertSame(3, (int) Rol::where('nombre_rol', 'super_admin')->value('id_rol'));
    }

    public function test_el_seeder_crea_exactamente_tres_roles_sin_empleado_ni_usuarios(): void
    {
        $this->seed(RolesSeeder::class);

        $this->assertSame(3, Rol::count());
        $this->assertFalse(Rol::where('nombre_rol', 'empleado')->exists());
        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_correr_el_seeder_dos_veces_no_duplica_roles(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->assertSame(3, Rol::count());
        $this->assertSame(3, (int) Rol::where('nombre_rol', 'super_admin')->value('id_rol'));
    }
}
