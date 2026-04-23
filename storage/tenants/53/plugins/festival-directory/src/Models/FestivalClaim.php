<?php

namespace FestivalDirectory\Models;

use Screenart\Musedock\Database\Model;

class FestivalClaim extends Model
{
    protected static string $table = 'festival_claims';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'festival_id',
        'user_name',
        'user_email',
        'user_role',
        'verification_details',
        'status',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected array $casts = [
        'id'          => 'int',
        'tenant_id'   => 'int',
        'festival_id' => 'int',
        'resolved_by' => 'int',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
