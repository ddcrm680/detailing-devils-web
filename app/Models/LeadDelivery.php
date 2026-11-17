<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable([
    'source',
    'source_record_id',
    'checkpoint_id',
    'record_key',
    'reference',
    'status',
    'payload',
    'attempts',
    'next_attempt_at',
    'processing_token',
    'last_http_status',
    'last_error',
    'delivered_at',
])]
#[Hidden(['payload', 'processing_token'])]
class LeadDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'source_record_id' => 'integer',
            'checkpoint_id' => 'integer',
            'payload' => 'encrypted:array',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (LeadDelivery $delivery): void {
            if (
                $delivery->getOriginal('source_record_id') === 0
                || $delivery->isDirty([
                    'source',
                    'source_record_id',
                    'checkpoint_id',
                    'record_key',
                    'reference',
                    'payload',
                ])
            ) {
                throw new LogicException('Lead delivery checkpoints, source references and payload snapshots are immutable.');
            }
        });
        static::deleting(
            fn () => throw new LogicException('Keep lead delivery references to prevent historical replay.')
        );
    }
}
