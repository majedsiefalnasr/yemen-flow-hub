<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestDocument extends Model
{
    protected $fillable = [
        'request_id',
        'uploaded_by',
        'type',
        'document_type_id',
        'original_filename',
        'stored_path',
        'mime_type',
        'size_bytes',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ImportRequest::class, 'request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
