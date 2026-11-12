<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    use HasFactory;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    protected $fillable = [
        'id_usuario',
        'tenant_id',
        'id_rol',
        'usuario',
        'nombre',
        'email',
        'clave',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];
}
