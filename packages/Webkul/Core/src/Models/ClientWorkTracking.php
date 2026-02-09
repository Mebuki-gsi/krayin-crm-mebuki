<?php

namespace Webkul\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\User\Models\UserProxy;

class ClientWorkTracking extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'client_work_tracking';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'client_cnpj',
        'client_name',
        'classification',
        'is_checked',
        'checked_at',
        'unchecked_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
        'unchecked_at' => 'datetime',
    ];

    /**
     * Get the user that owns the tracking record.
     */
    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }
}
