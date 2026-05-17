<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RequestDocument;
use App\Models\User;

class RequestDocumentPolicy
{
    public function download(User $user, RequestDocument $document): bool
    {
        $document->loadMissing('request');
        $requestBankId = $document->request->bank_id;

        return match ($document->type) {
            'REQUEST_DOC' => $this->canDownloadRequestDoc($user, $requestBankId),
            'SWIFT' => $this->canDownloadSwift($user, $requestBankId),
            default => false,
        };
    }

    private function canDownloadRequestDoc(User $user, ?int $requestBankId): bool
    {
        return match ($user->role) {
            UserRole::DATA_ENTRY,
            UserRole::BANK_REVIEWER,
            UserRole::SWIFT_OFFICER => $user->bank_id === $requestBankId,
            UserRole::SUPPORT_COMMITTEE,
            UserRole::EXECUTIVE_MEMBER,
            UserRole::COMMITTEE_DIRECTOR,
            UserRole::CBY_ADMIN => true,
            default => false,
        };
    }

    private function canDownloadSwift(User $user, ?int $requestBankId): bool
    {
        return match ($user->role) {
            UserRole::DATA_ENTRY,
            UserRole::SUPPORT_COMMITTEE => false,
            UserRole::BANK_REVIEWER,
            UserRole::SWIFT_OFFICER => $user->bank_id === $requestBankId,
            UserRole::EXECUTIVE_MEMBER,
            UserRole::COMMITTEE_DIRECTOR,
            UserRole::CBY_ADMIN => true,
            default => false,
        };
    }
}
