<?php

namespace App\Services\Kvkk;

use App\Models\PrivacyNotice;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrivacyNoticeService
{
    public function listFor(int $companyId, ?string $audience, int $perPage = 50): LengthAwarePaginator
    {
        return PrivacyNotice::query()
            ->where('company_id', $companyId)
            ->when($audience, fn ($q) => $q->where('audience', $audience))
            ->orderByDesc('audience')
            ->orderByDesc('version')
            ->paginate($perPage);
    }

    public function activeFor(int $companyId, string $audience): ?PrivacyNotice
    {
        return PrivacyNotice::query()
            ->where('company_id', $companyId)
            ->where('audience', $audience)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(int $companyId, array $data): PrivacyNotice
    {
        $audience = (string) $data['audience'];
        $nextVersion = (int) PrivacyNotice::query()
            ->where('company_id', $companyId)
            ->where('audience', $audience)
            ->max('version') + 1;

        return PrivacyNotice::create([
            'company_id' => $companyId,
            'audience' => $audience,
            'version' => max(1, $nextVersion),
            'title' => $data['title'],
            'body' => $data['body'],
            'effective_from' => $data['effective_from'] ?? null,
            'is_active' => false,
            'published_at' => null,
            'published_by' => null,
        ]);
    }

    /**
     * Yayınlanan versiyon DEĞİŞTİRİLEMEZ — 422.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(PrivacyNotice $notice, array $data): PrivacyNotice
    {
        if ($notice->isPublished()) {
            throw ValidationException::withMessages([
                'notice' => ['Yayınlanan aydınlatma metni değiştirilemez. Yeni versiyon oluşturun.'],
            ]);
        }

        $notice->fill(collect($data)->only(['title', 'body', 'effective_from'])->all());
        $notice->save();

        return $notice->fresh();
    }

    public function publish(PrivacyNotice $notice, User $publisher): PrivacyNotice
    {
        if ($notice->isPublished()) {
            throw ValidationException::withMessages([
                'notice' => ['Bu versiyon zaten yayınlanmış.'],
            ]);
        }

        return DB::transaction(function () use ($notice, $publisher) {
            PrivacyNotice::query()
                ->where('company_id', $notice->company_id)
                ->where('audience', $notice->audience)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $notice->is_active = true;
            $notice->published_at = now();
            $notice->published_by = $publisher->id;
            if ($notice->effective_from === null) {
                $notice->effective_from = now();
            }
            $notice->save();

            return $notice->fresh(['publisher']);
        });
    }

    public function deleteDraft(PrivacyNotice $notice): void
    {
        if ($notice->isPublished()) {
            throw ValidationException::withMessages([
                'notice' => ['Yayınlanan aydınlatma silinemez (kanıt zinciri).'],
            ]);
        }
        $notice->delete();
    }
}
