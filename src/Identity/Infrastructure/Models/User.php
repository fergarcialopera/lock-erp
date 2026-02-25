<?php

namespace Src\Identity\Infrastructure\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable, HasUlids;

    protected $guarded = [];

    protected $hidden = [
        'password',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'clinic_id' => $this->clinic_id,
            'role' => $this->role,
        ];
    }
}
