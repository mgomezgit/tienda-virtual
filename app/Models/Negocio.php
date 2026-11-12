<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Negocio extends Model
{
    use HasFactory;

    protected $table = 'negocios';

    protected $primaryKey = 'id_negocio';

    const CREATED_AT = null;

    const UPDATED_AT = null;

    /**
     * Solo columnas que existen hoy en la tabla. Las del hermano (slug,
     * contacto, tema, horario, frase, IA, chatbot, onboarding) se agregan aquí
     * en el mismo lote que agregue su migración.
     */
    protected $fillable = [
        'id_negocio',
        'nombre_negocio',
        'rubro',
        'usuario_registra',
        'fecha_registro',
        'estado',
    ];
}
