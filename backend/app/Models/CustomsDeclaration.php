<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomsDeclaration extends Model
{
    /**
     * Columns allowed to be mutated on an already-issued declaration.
     * All other columns are immutable.
     */
    private const MUTABLE_SIGNED_DOC_COLUMNS = [
        'signed_fx_doc_path',
        'signed_fx_doc_uploaded_at',
        'signed_fx_doc_uploaded_by',
        'signed_uploaded_by',
        'metadata',
    ];

    protected $fillable = [
        'engine_request_id',
        'declaration_number',
        'issued_by',
        'generated_by',
        'issued_at',
        'pdf_path',
        'signed_fx_doc_path',
        'signed_fx_doc_uploaded_at',
        'signed_fx_doc_uploaded_by',
        'signed_uploaded_by',
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

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function signedFxUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_fx_doc_uploaded_by');
    }

    public function signedUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_uploaded_by');
    }

    protected static function booted(): void
    {
        static::updating(function (self $declaration): void {
            $dirty = array_keys($declaration->getDirty());

            foreach ($dirty as $attribute) {
                if (! in_array($attribute, self::MUTABLE_SIGNED_DOC_COLUMNS, true)) {
                    throw new \LogicException(
                        "Customs declaration column '{$attribute}' is immutable once issued. "
                        .'Only signed-doc and metadata columns may be updated.',
                    );
                }
            }
        });

        static::deleting(function (): void {
            throw new \LogicException('Customs declarations are immutable once issued.');
        });
    }
}
