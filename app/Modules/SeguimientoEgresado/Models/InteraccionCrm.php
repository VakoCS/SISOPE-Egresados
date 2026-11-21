<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class InteraccionCrm extends ModuleModel
{
    protected $table = 'seguimiento_egresado.interaccion_crm';

    public $timestamps = false;

    protected $fillable = [
        'id_egresado',
        'id_campania',
        'id_usuario_registro',
        'canal',
        'estado',
        'resumen',
        'observacion',
        'fecha_interaccion',
        'proximo_contacto_en',
        'creado_en',
    ];

    protected $casts = [
        'fecha_interaccion' => 'datetime',
        'proximo_contacto_en' => 'date',
        'creado_en' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id_usuario_registro)) {
                $usuarioId = auth()->id();
                if ($usuarioId) {
                    $model->id_usuario_registro = UsuarioInterno::where('correo_institucional', auth()->user()?->email)->value('id');
                }
                if (empty($model->id_usuario_registro)) {
                    $model->id_usuario_registro = UsuarioInterno::value('id') ?? 1;
                }
            }
        });
    }

    public function egresado()
    {
        return $this->belongsTo(Egresado::class, 'id_egresado');
    }

    public function campania()
    {
        return $this->belongsTo(CampaniaSeguimiento::class, 'id_campania');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(UsuarioInterno::class, 'id_usuario_registro');
    }

    public function scopePorEstado($query, string $estado)
    {
        return $query->where('estado', strtoupper($estado));
    }

    public function scopePorCanal($query, string $canal)
    {
        return $query->where('canal', strtoupper($canal));
    }

    public function scopeRecientes($query)
    {
        return $query->orderByDesc('fecha_interaccion');
    }
}
