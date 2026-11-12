<?php

namespace Tests\Feature;

use App\Models\Rol;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Helpers del modelo Rol: son los que usarán los middlewares y controllers
 * para decidir permisos, así que un falso positivo aquí abre una puerta.
 *
 * Mutaciones verificadas (2026-11-08), cada una restaurada después:
 *  1. esRolVendedor() comparando con 'admin' en vez de 'vendedor' -> falla
 *     test_es_rol_vendedor_solo_para_vendedor (5 de 6 siguen en verde).
 *  2. super_admin agregado a ROLES_DE_NEGOCIO -> falla
 *     test_roles_de_negocio_son_admin_y_vendedor_nunca_super_admin.
 * Restaurado -> las 6 pasan.
 */
class RolModeloTest extends TestCase
{
    use RefreshDatabase;

    private int $idAdmin;

    private int $idVendedor;

    private int $idSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->idAdmin = Rol::idPorNombre('admin');
        $this->idVendedor = Rol::idPorNombre('vendedor');
        $this->idSuperAdmin = Rol::idPorNombre('super_admin');
    }

    public function test_existen_los_tres_roles_de_la_plataforma(): void
    {
        $this->assertSame(
            ['admin', 'super_admin', 'vendedor'],
            Rol::orderBy('nombre_rol')->pluck('nombre_rol')->all()
        );
    }

    public function test_es_rol_vendedor_solo_para_vendedor(): void
    {
        $this->assertTrue(Rol::esRolVendedor($this->idVendedor));
        $this->assertTrue(Rol::esRolVendedor((string) $this->idVendedor));
        $this->assertFalse(Rol::esRolVendedor($this->idAdmin));
        $this->assertFalse(Rol::esRolVendedor($this->idSuperAdmin));
    }

    public function test_es_rol_vendedor_rechaza_ids_vacios_o_inexistentes(): void
    {
        $this->assertFalse(Rol::esRolVendedor(null));
        $this->assertFalse(Rol::esRolVendedor(''));
        $this->assertFalse(Rol::esRolVendedor(0));
        $this->assertFalse(Rol::esRolVendedor(999));
    }

    public function test_es_rol_super_admin_solo_para_super_admin(): void
    {
        $this->assertTrue(Rol::esRolSuperAdmin($this->idSuperAdmin));
        $this->assertFalse(Rol::esRolSuperAdmin($this->idAdmin));
        $this->assertFalse(Rol::esRolSuperAdmin($this->idVendedor));
        $this->assertFalse(Rol::esRolSuperAdmin(null));
        $this->assertFalse(Rol::esRolSuperAdmin(999));
    }

    public function test_roles_de_negocio_son_admin_y_vendedor_nunca_super_admin(): void
    {
        $this->assertSame(['admin', 'vendedor'], Rol::ROLES_DE_NEGOCIO);
        $this->assertTrue(Rol::esRolDeNegocio($this->idAdmin));
        $this->assertTrue(Rol::esRolDeNegocio($this->idVendedor));
        $this->assertFalse(Rol::esRolDeNegocio($this->idSuperAdmin));
        $this->assertFalse(Rol::esRolDeNegocio('abc'));
        $this->assertFalse(Rol::esRolDeNegocio(999));
    }

    public function test_nombre_por_id_e_id_por_nombre(): void
    {
        $this->assertSame('vendedor', Rol::nombrePorId($this->idVendedor));
        $this->assertNull(Rol::nombrePorId('1 OR 1=1'));
        $this->assertNull(Rol::nombrePorId(999));
        $this->assertNull(Rol::idPorNombre('empleado'));
    }
}
