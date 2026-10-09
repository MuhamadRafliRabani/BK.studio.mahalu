<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;

trait HasAuditColumns
{
    protected static function bootHasAuditColumns(): void
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->creby = Auth::id();
                $model->modby = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->modby = Auth::id();
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'creby');
    }

    public function modifier()
    {
        return $this->belongsTo(\App\Models\User::class, 'modby');
    }
}