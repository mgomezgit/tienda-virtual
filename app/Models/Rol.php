<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $primaryKey = 'id_rol';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_rol',
        'nombre_rol',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];

    /** Nombre del rol de la cuenta de plataforma (única, sin negocio). */
    const SUPER_ADMIN = 'super_admin';

    /** Nombre del rol de acceso limitado dentro de un negocio. */
    const VENDEDOR = 'vendedor';

    /**
     * Roles que se pueden asignar a una cuenta de negocio, desde cualquier
     * camino (pantalla de Usuarios, carga masiva, registro). super_admin NO
     * está aquí a propósito: esa cuenta es única y nadie la crea ni la asigna.
     */
    const ROLES_DE_NEGOCIO = ['admin', self::VENDEDOR];

    /** Nombre del rol con ese id, o null si no existe. */
    public static function nombrePorId($idRol): ?string
    {
        if (empty($idRol) || ! is_numeric($idRol)) {
            return null;
        }

        return self::where('id_rol', (int) $idRol)->value('nombre_rol');
    }

    /** Id del rol con ese nombre, o null si no existe. */
    public static function idPorNombre(string $nombreRol): ?int
    {
        $id = self::where('nombre_rol', $nombreRol)->value('id_rol');

        return $id !== null ? (int) $id : null;
    }

    /** ¿Ese id corresponde a uno de los roles asignables a una cuenta de negocio? */
    public static function esRolDeNegocio($idRol): bool
    {
        return in_array(self::nombrePorId($idRol), self::ROLES_DE_NEGOCIO, true);
    }

    /**
     * Indica si el rol recibido corresponde al rol "vendedor". Centraliza la
     * comparación para no repartir comparaciones de id_rol por los controllers.
     */
    public static function esRolVendedor($idRol): bool
    {
        if (empty($idRol)) {
            return false;
        }

        return self::where('id_rol', $idRol)->value('nombre_rol') === self::VENDEDOR;
    }

    /**
     * Indica si el rol recibido corresponde al rol "super_admin".
     *
     * El super admin se identifica SIEMPRE por su rol, nunca por tener
     * tenant_id null: una petición anónima también tiene tenant_id null.
     */
    public static function esRolSuperAdmin($idRol): bool
    {
        if (empty($idRol)) {
            return false;
        }

        return self::where('id_rol', $idRol)->value('nombre_rol') === self::SUPER_ADMIN;
    }
}
