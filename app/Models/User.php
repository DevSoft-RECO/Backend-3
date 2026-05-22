<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'username',
        'name',
        'email',
        'telefono',
        'agencia_id',
        'puesto',
        'roles_list',
        'permissions_list',
        'jti',
        'avatar',
        'updated_at'
    ];

    protected $appends = ['idagencia', 'roles', 'permissions', 'permisos'];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'roles_list' => 'array',
            'permissions_list' => 'array',
        ];
    }

    // --- Accesores de Compatibilidad Histórica ---

    public function getIdagenciaAttribute()
    {
        return $this->agencia_id;
    }

    public function getRolesAttribute()
    {
        return $this->roles_list ?? [];
    }

    public function getPermissionsAttribute()
    {
        return $this->permissions_list ?? [];
    }

    public function getPermisosAttribute()
    {
        return $this->permissions_list ?? [];
    }

    // --- Helpers de Autorización Rápidos ---

    public function hasRole($role) {
        if (!is_array($this->roles_list)) return false;
        return in_array($role, $this->roles_list);
    }

    public function hasPermissionTo($permission) {
        if ($this->hasRole('Super Admin')) return true;
        if (!is_array($this->permissions_list)) return false;
        return in_array($permission, $this->permissions_list);
    }

    // --- Compatibilidad con Laravel Auth ---

    public function tokenCan($ability)
    {
        return $this->hasPermissionTo($ability);
    }

    public function currentAccessToken()
    {
        return null;
    }
}
