<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Yemen Flow Hub API',
    description: 'Internal regulatory workflow API for CBY import financing requests.'
)]
#[OA\Server(
    url: '/',
    description: 'Current API server'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctumCookie',
    type: 'apiKey',
    name: 'laravel_session',
    in: 'cookie',
    description: 'Sanctum stateful cookie auth for SPA'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token',
    description: 'Sanctum personal access token'
)]
#[OA\Schema(
    schema: 'ApiSuccess',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'OK'),
        new OA\Property(property: 'data', type: 'object')
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ApiError',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Forbidden action'),
        new OA\Property(property: 'errors', type: 'object')
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ValidationError',
    allOf: [new OA\Schema(ref: '#/components/schemas/ApiError')]
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'email', type: 'string'),
        new OA\Property(property: 'role', type: 'string'),
        new OA\Property(property: 'bank_id', type: 'integer', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Bank',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'is_active', type: 'boolean'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ImportRequest',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'reference_number', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'current_owner_role', type: 'string'),
        new OA\Property(property: 'bank_id', type: 'integer'),
        new OA\Property(property: 'supplier_name', type: 'string'),
        new OA\Property(property: 'currency', type: 'string'),
        new OA\Property(property: 'amount', type: 'number', format: 'float'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Vote',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'request_id', type: 'integer'),
        new OA\Property(property: 'user_id', type: 'integer'),
        new OA\Property(property: 'vote', type: 'string'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Document',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'type', type: 'string'),
        new OA\Property(property: 'original_filename', type: 'string'),
        new OA\Property(property: 'mime_type', type: 'string'),
        new OA\Property(property: 'size_bytes', type: 'integer'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CustomsDeclaration',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'request_id', type: 'integer'),
        new OA\Property(property: 'declaration_number', type: 'string'),
        new OA\Property(property: 'issued_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class OpenApiSpec
{
}
