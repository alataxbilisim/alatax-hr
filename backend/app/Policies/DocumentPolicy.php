<?php

namespace App\Policies;

use App\Enums\DataScopeLevel;
use App\Models\Document;
use App\Models\User;
use App\Services\DataScopeService;

/**
 * Şirket evrakları (documents tablosu).
 * Şemada personel görünürlük kolonu YOK — firma geneli; erişim permission + company.
 * Yazma: İK (company|department) veya yükleyen (uploaded_by).
 */
class DocumentPolicy
{
    public function __construct(
        protected DataScopeService $dataScope,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): bool
    {
        $scope = $this->dataScope->resolve($user);

        if (in_array($scope, [DataScopeLevel::Company, DataScopeLevel::Department], true)) {
            return true;
        }

        return (int) $document->uploaded_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->dataScope->canManageHrRecords($user);
    }

    public function update(User $user, Document $document): bool
    {
        if ($this->dataScope->canManageHrRecords($user)) {
            return true;
        }

        return (int) $document->uploaded_by === $user->id;
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }
}
