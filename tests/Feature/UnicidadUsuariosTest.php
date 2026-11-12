<?php

namespace Tests\Feature;

use App\Models\Negocio;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Defensas EN LA BASE de la tabla usuarios (se prueban insertando directo con
 * el modelo, saltándose cualquier controller o Service):
 *
 *  - unicidad_de_usuarios_solo_entre_activos: correo y usuario únicos solo
 *    entre cuentas activas; una cuenta inactiva libera los dos.
 *  - blindar_cuenta_super_admin_unica: como máximo UNA cuenta super_admin
 *    activa, y nunca con negocio.
 *
 * Corre en SQLite en memoria. La misma defensa se verificó además contra
 * MySQL 8.4 real (ver el reporte del Lote 2A).
 *
 * Mutaciones verificadas (2026-11-08) en SQLite, cada una restaurada después
 * (y la suite volvió a 23/23):
 *  1. Sin el índice usuarios_super_admin_activo_unico -> 3 fallan:
 *     segundo_super_admin, pasar_cuenta_a_super_admin, reactivar_super_admin.
 *  2. Sin la columna super_admin_sin_negocio -> 1 falla:
 *     test_un_super_admin_con_negocio_es_rechazado.
 *  3. Sin el índice usuarios_email_activo_unico_unique -> 2 fallan:
 *     mismo_correo, reactivar_usuario_cuyo_correo_ya_tomo_otro.
 *  4. Sin el índice usuarios_usuario_activo_unico_unique -> 1 falla:
 *     test_dos_usuarios_activos_con_el_mismo_usuario_son_rechazados.
 *  5. email_activo_unico = email siempre (sin el CASE por estado) -> 2 fallan:
 *     inactivo_libera_su_correo, reactivar_usuario_cuyo_correo_ya_tomo_otro.
 */
class UnicidadUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private int $idAdmin;

    private int $idVendedor;

    private int $idSuperAdmin;

    private int $negocioA;

    private int $negocioB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->idAdmin = Rol::idPorNombre('admin');
        $this->idVendedor = Rol::idPorNombre('vendedor');
        $this->idSuperAdmin = Rol::idPorNombre('super_admin');

        $this->negocioA = Negocio::create(['nombre_negocio' => 'Tienda A', 'rubro' => 'ropa', 'estado' => 1])->id_negocio;
        $this->negocioB = Negocio::create(['nombre_negocio' => 'Tienda B', 'rubro' => 'calzado', 'estado' => 1])->id_negocio;
    }

    private function crearUsuario(array $datos): Usuario
    {
        return Usuario::create(array_merge([
            'tenant_id' => $this->negocioA,
            'id_rol' => $this->idAdmin,
            'usuario' => 'usuario_'.uniqid(),
            'nombre' => 'Persona de prueba',
            'email' => uniqid().'@prueba.test',
            'clave' => Hash::make('clave-de-prueba'),
            'estado' => 1,
        ], $datos));
    }

    private function crearSuperAdmin(array $datos = []): Usuario
    {
        return $this->crearUsuario(array_merge([
            'tenant_id' => null,
            'id_rol' => $this->idSuperAdmin,
        ], $datos));
    }

    /* ================= Cuenta super admin única ================= */

    public function test_la_primera_cuenta_super_admin_se_acepta(): void
    {
        $this->crearSuperAdmin();

        $this->assertSame(1, Usuario::where('id_rol', $this->idSuperAdmin)->count());
    }

    public function test_un_segundo_super_admin_activo_es_rechazado(): void
    {
        $this->crearSuperAdmin();

        try {
            $this->crearSuperAdmin();
            $this->fail('La base aceptó una segunda cuenta super_admin activa.');
        } catch (QueryException $e) {
            $this->assertStringContainsStringIgnoringCase('unique', $e->getMessage());
        }

        $this->assertSame(1, Usuario::where('id_rol', $this->idSuperAdmin)->count());
    }

    public function test_un_super_admin_con_negocio_es_rechazado(): void
    {
        try {
            $this->crearSuperAdmin(['tenant_id' => $this->negocioA]);
            $this->fail('La base aceptó una cuenta super_admin activa con negocio.');
        } catch (QueryException $e) {
            $this->assertStringContainsStringIgnoringCase('null', $e->getMessage());
        }

        $this->assertSame(0, Usuario::where('id_rol', $this->idSuperAdmin)->count());
    }

    public function test_no_se_puede_pasar_una_cuenta_existente_a_super_admin_si_ya_hay_uno(): void
    {
        $this->crearSuperAdmin();
        $otro = $this->crearUsuario(['tenant_id' => null, 'id_rol' => $this->idAdmin]);

        $this->expectException(QueryException::class);

        Usuario::where('id_usuario', $otro->id_usuario)->update(['id_rol' => $this->idSuperAdmin]);
    }

    public function test_reactivar_un_super_admin_inactivo_con_otro_activo_es_rechazado(): void
    {
        $viejo = $this->crearSuperAdmin(['estado' => 0]);
        $this->crearSuperAdmin();

        $this->expectException(QueryException::class);

        Usuario::where('id_usuario', $viejo->id_usuario)->update(['estado' => 1]);
    }

    public function test_admin_y_vendedor_de_varios_negocios_no_compiten_con_el_super_admin(): void
    {
        $this->crearSuperAdmin();
        $this->crearUsuario(['tenant_id' => $this->negocioA, 'id_rol' => $this->idAdmin]);
        $this->crearUsuario(['tenant_id' => $this->negocioA, 'id_rol' => $this->idVendedor]);
        $this->crearUsuario(['tenant_id' => $this->negocioB, 'id_rol' => $this->idAdmin]);
        $this->crearUsuario(['tenant_id' => $this->negocioB, 'id_rol' => $this->idVendedor]);

        $this->assertSame(5, Usuario::count());
    }

    /* ================= Unicidad de correo y usuario ================= */

    public function test_dos_usuarios_activos_con_el_mismo_correo_son_rechazados(): void
    {
        $this->crearUsuario(['email' => 'repetido@prueba.test']);

        $this->expectException(QueryException::class);

        // Otro negocio: la unicidad es GLOBAL, no por tenant.
        $this->crearUsuario(['email' => 'repetido@prueba.test', 'tenant_id' => $this->negocioB]);
    }

    public function test_dos_usuarios_activos_con_el_mismo_usuario_son_rechazados(): void
    {
        $this->crearUsuario(['usuario' => 'repetido']);

        $this->expectException(QueryException::class);

        $this->crearUsuario(['usuario' => 'repetido', 'tenant_id' => $this->negocioB]);
    }

    public function test_un_usuario_inactivo_libera_su_correo_y_su_usuario(): void
    {
        $viejo = $this->crearUsuario(['usuario' => 'liberado', 'email' => 'liberado@prueba.test']);

        Usuario::where('id_usuario', $viejo->id_usuario)->update(['estado' => 0]);

        $nuevo = $this->crearUsuario([
            'usuario' => 'liberado',
            'email' => 'liberado@prueba.test',
            'tenant_id' => $this->negocioB,
        ]);

        $this->assertNotEquals($viejo->id_usuario, $nuevo->id_usuario);

        // La columna original conserva el dato real del inactivo (auditoría).
        $this->assertSame('liberado@prueba.test', Usuario::where('id_usuario', $viejo->id_usuario)->value('email'));
    }

    public function test_reactivar_un_usuario_cuyo_correo_ya_tomo_otro_activo_es_rechazado(): void
    {
        $viejo = $this->crearUsuario(['email' => 'disputado@prueba.test', 'estado' => 0]);
        $this->crearUsuario(['email' => 'disputado@prueba.test']);

        $this->expectException(QueryException::class);

        Usuario::where('id_usuario', $viejo->id_usuario)->update(['estado' => 1]);
    }

    public function test_version_sesion_arranca_en_cero(): void
    {
        $usuario = $this->crearUsuario([]);

        $this->assertSame(0, (int) Usuario::where('id_usuario', $usuario->id_usuario)->value('version_sesion'));
    }
}
