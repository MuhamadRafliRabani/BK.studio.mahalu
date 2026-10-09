<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasGeneratedCode
{
    protected static function bootHasGeneratedCode(): void
    {
        static::creating(function ($model) {
            if (empty($model->code)) {
                $model->code = $model->generateUniqueCode();
            }
        });
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = static::codePrefix() . '-' . strtoupper(Str::random(6));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    abstract protected static function codePrefix(): string;
}