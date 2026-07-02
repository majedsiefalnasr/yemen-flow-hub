<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomsDeclaration extends Model
{
    protected $fillable = [
        'engine_request_id',
        'declaration_number',
        'issued_by',
        'issued_at',
        'pdf_path',
        'signed_fx_doc_path',
        'signed_fx_doc_uploaded_at',
        'signed_fx_doc_uploaded_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'signed_fx_doc_uploaded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function hasSigned(): bool
    {
        return $this->signed_fx_doc_path !== null;
    }

    public function engineRequest(): BelongsTo
    {
        return $this->belongsTo(EngineRequest::class, 'engine_request_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function signedFxUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_fx_doc_uploaded_by');
    }

    protected static function booted(): void
    {
        static::updating(function (self $declaration): void {
            if (str_starts_with((string) $declaration->getOriginal('declaration_number'), 'PENDING-FX-')) {
                return;
            }

            throw new \LogicException('Customs declarations are immutable once issued.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Customs declarations are immutable once issued.');
        });
    }
}
