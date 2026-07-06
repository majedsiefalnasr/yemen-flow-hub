<?php

namespace App\Policies;

use App\Models\CustomsDeclaration;
use App\Models\User;
use App\Services\Customs\FxConfirmationAuthorizationService;

class CustomsDeclarationPolicy
{
    public function __construct(private readonly FxConfirmationAuthorizationService $authorization) {}

    public function download(User $user, CustomsDeclaration $declaration): bool
    {
        return $this->authorization->canDownloadArtifact($user, $declaration);
    }

    public function downloadSignedFx(User $user, CustomsDeclaration $declaration): bool
    {
        return $this->authorization->canDownloadArtifact($user, $declaration);
    }
}
