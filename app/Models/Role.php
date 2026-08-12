<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $primaryKey = 'nama_role';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nama_role'
    ];

    public function users() {
        return $this->belongsToMany(
            Users::class,
            'user_roles',
            'nama_role',
            'id_user',
            'nama_role',
            'id_user'
        );
    }
}
