<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'paterno',
        'materno',
        'ci',
        'ci_extension',
        'sexo',
        'email',
        'celular',
        'cargo',
        'area',
        'oficina_regional',
        'password',
        'id_rol',
        'activo',
        'otp_code',
        'otp_expires_at',
        'face_photo',
        'face_descriptor',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
        'face_descriptor',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'activo' => 'boolean',
        'password' => 'hashed',
        'face_descriptor' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'id_rol');
    }

    public function inspecciones()
    {
        return $this->hasMany(Inspeccion::class, 'id_usuario');
    }

    public function facturas()
    {
        return $this->hasMany(Factura::class, 'id_usuario');
    }

    public function movimientos()
    {
        return $this->hasMany(Movimiento::class, 'id_usuario');
    }

    public function alertasRecibidas()
    {
        return $this->hasMany(Alerta::class, 'id_usuario_destino');
    }

    public function almacenesResponsable()
    {
        return $this->hasMany(Almacen::class, 'id_responsable');
    }

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    public function tieneRol(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return in_array($this->rol?->nombre, $roles, true);
    }

    /*
    |--------------------------------------------------------------------------
    | Permisos
    |--------------------------------------------------------------------------
    */

    public function permisos(): array
    {
        if (! $this->rol) {
            return [];
        }

        if (isset($this->rol->activo) && ! $this->rol->activo) {
            return [];
        }

        $permisos = $this->rol->permisos;

        if (is_string($permisos)) {
            $permisos = json_decode($permisos, true);
        }

        if (! is_array($permisos)) {
            return [];
        }

        return $permisos;
    }

    public function tienePermiso(string $permiso): bool
    {
        if ($this->tieneRol('Superadmin')) {
            return true;
        }

        return in_array($permiso, $this->permisos(), true);
    }

    public function tieneAlgunoDeEstosPermisos(array $permisos): bool
    {
        if ($this->tieneRol('Superadmin')) {
            return true;
        }

        foreach ($permisos as $permiso) {
            if ($this->tienePermiso($permiso)) {
                return true;
            }
        }

        return false;
    }
}
