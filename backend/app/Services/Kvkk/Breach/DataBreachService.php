<?php

namespace App\Services\Kvkk\Breach;

use App\Enums\DataBreachSeverity;
use App\Models\DataBreach;
use App\Models\User;
use App\Services\Settings\Settings;

class DataBreachService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $companyId, User $actor, array $data): DataBreach
    {
        return DataBreach::query()->create([
            'company_id' => $companyId,
            'detected_at' => $data['detected_at'],
            'occurred_at' => $data['occurred_at'] ?? null,
            'description' => $data['description'],
            'affected_categories' => $data['affected_categories'] ?? [],
            'affected_subject_count' => (int) ($data['affected_subject_count'] ?? 0),
            'severity' => DataBreachSeverity::from($data['severity'] ?? 'medium'),
            'root_cause' => $data['root_cause'] ?? null,
            'containment_actions' => $data['containment_actions'] ?? null,
            'status' => $data['status'] ?? 'open',
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DataBreach $breach, User $actor, array $data): DataBreach
    {
        $fill = collect($data)->only([
            'occurred_at', 'description', 'affected_categories', 'affected_subject_count',
            'severity', 'root_cause', 'containment_actions', 'status',
            'notified_kvkk', 'notified_kvkk_at', 'notified_subjects',
            'notified_subjects_at', 'notified_subjects_method', 'closed_at',
        ])->all();

        if (isset($fill['severity'])) {
            $fill['severity'] = DataBreachSeverity::from($fill['severity']);
        }
        if (! empty($fill['notified_kvkk']) && empty($fill['notified_kvkk_at'])) {
            $fill['notified_kvkk_at'] = now();
        }
        if (($fill['status'] ?? null) === 'closed' && empty($fill['closed_at'])) {
            $fill['closed_at'] = now();
        }
        $fill['updated_by'] = $actor->id;
        $breach->forceFill($fill)->save();

        return $breach->fresh();
    }

    public function notifyDeadlineHours(): int
    {
        $h = (int) Settings::get('legal.kvkk.breach_notify_hours.max', []);

        return $h > 0 ? $h : 72;
    }

    /** @return array{overdue: int, open: int, hours_max: int} */
    public function summary(int $companyId): array
    {
        $hours = $this->notifyDeadlineHours();
        $open = DataBreach::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['closed'])
            ->get();

        $overdue = $open->filter(fn (DataBreach $b) => $b->isKvkkDeadlineOverdue())->count();

        return [
            'overdue' => $overdue,
            'open' => $open->count(),
            'hours_max' => $hours,
        ];
    }

    public function reportHtml(DataBreach $breach): string
    {
        $deadline = $breach->kvkkDeadlineAt()->toIso8601String();
        $cats = implode(', ', $breach->affected_categories ?? []);

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Veri İhlali Raporu</title></head><body>'
            .'<h1>Kişisel Veri İhlali Bildirim Taslağı</h1>'
            .'<p><strong>Firma ID:</strong> '.$breach->company_id.'</p>'
            .'<p><strong>Tespit:</strong> '.$breach->detected_at?->toIso8601String().'</p>'
            .'<p><strong>KVKK bildirim son tarihi ('.$this->notifyDeadlineHours().'s):</strong> '.$deadline.'</p>'
            .'<p><strong>Şiddet:</strong> '.($breach->severity?->value ?? '').'</p>'
            .'<p><strong>Etkilenen kategori:</strong> '.e($cats).'</p>'
            .'<p><strong>Etkilenen kişi sayısı:</strong> '.$breach->affected_subject_count.'</p>'
            .'<p><strong>Açıklama:</strong> '.e((string) $breach->description).'</p>'
            .'<p><strong>Kök neden:</strong> '.e((string) ($breach->root_cause ?? '')).'</p>'
            .'<p><strong>Alınan önlemler:</strong> '.e((string) ($breach->containment_actions ?? '')).'</p>'
            .'<p><em>Bu taslak kurum içi kullanıma yöneliktir; resmi bildirim formatı ayrı doğrulanmalıdır.</em></p>'
            .'</body></html>';
    }
}
