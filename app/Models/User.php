<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_admin',
        'role',
        'permissions',
        'is_active',
    ];

    public const ROLES = [
        'admin'   => 'Administrateur',
        'manager' => 'Manager',
        'staff'   => 'Employé',
    ];

    /** All grantable back-office sections (RBAC permission keys). */
    public const PERMISSIONS = [
        'orders'     => '🧾 Commandes',
        'clients'    => '💳 Clients & dettes',
        'products'   => '📦 Produits',
        'categories' => '🗂️ Catégories',
        'purchasing' => '📥 Fournisseurs & stock',
        'incidents'  => '🧯 Pertes & casses',
        'social'     => '📣 Réseaux sociaux',
        'pixels'     => '🎯 Pixels',
        'wilayas'    => '🚚 Livraison',
        'users'      => '👥 Équipe',
        'settings'   => '⚙️ Paramètres',
    ];

    /** Default permission preset applied when a role is picked. */
    public const ROLE_PRESETS = [
        'admin'   => ['*'],
        'manager' => ['orders', 'clients', 'products', 'categories', 'purchasing', 'incidents', 'social', 'pixels', 'wilayas'],
        'staff'   => ['orders', 'products'],
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst((string) $this->role);
    }

    /** Full administrators may manage staff and settings and reset data. */
    public function isFullAdmin(): bool
    {
        return $this->is_admin && $this->role === 'admin';
    }

    /**
     * Does this user hold a given back-office permission?
     * Admin role → everything. Otherwise the stored `permissions` list is
     * authoritative; if it's null (legacy user) we fall back to the role preset.
     */
    public function hasPermission(string $key): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        $granted = $this->permissions;
        if ($granted === null) {
            $granted = self::ROLE_PRESETS[$this->role] ?? [];
        }

        return in_array('*', $granted, true) || in_array($key, $granted, true);
    }

    /** The effective list of granted permission keys (for display). */
    public function grantedPermissions(): array
    {
        if ($this->role === 'admin') {
            return array_keys(self::PERMISSIONS);
        }

        $granted = $this->permissions ?? (self::ROLE_PRESETS[$this->role] ?? []);

        return in_array('*', $granted, true) ? array_keys(self::PERMISSIONS) : $granted;
    }
}
