<?php

namespace App\Services\Notification;

use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Firma şablon override + güvenli render (XSS escape).
 */
class NotificationTemplateService
{
    /**
     * Katalog olayları + firma override özeti.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogForCompany(int $companyId): array
    {
        $events = config('notifications.events', []);
        if (! is_array($events)) {
            return [];
        }

        $overrides = NotificationTemplate::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('event_key');

        $rows = [];
        foreach ($events as $eventKey => $meta) {
            if (! is_array($meta)) {
                continue;
            }
            $override = $overrides->get($eventKey);
            $defaults = $this->defaultTexts($eventKey, $meta);
            $rows[] = [
                'event_key' => $eventKey,
                'group' => $meta['group'] ?? 'approvals',
                'variables' => array_values($meta['variables'] ?? []),
                'force' => (bool) ($meta['force'] ?? false),
                'default_subject' => $defaults['subject'],
                'default_body' => $defaults['body'],
                'override' => $override ? [
                    'id' => $override->id,
                    'subject' => $override->subject,
                    'body' => $override->body,
                    'updated_at' => $override->updated_at?->toIso8601String(),
                ] : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array{subject: string, body: string}  $data
     */
    public function upsert(int $companyId, string $eventKey, array $data, ?int $actorId): NotificationTemplate
    {
        $this->assertEventKey($eventKey);

        $subject = $this->sanitizePlain((string) ($data['subject'] ?? ''));
        $body = $this->sanitizePlain((string) ($data['body'] ?? ''));

        if ($subject === '' || $body === '') {
            throw new InvalidArgumentException('subject ve body zorunludur');
        }

        $existing = NotificationTemplate::query()
            ->where('company_id', $companyId)
            ->where('event_key', $eventKey)
            ->first();

        if ($existing !== null) {
            $existing->update([
                'subject' => $subject,
                'body' => $body,
                'updated_by' => $actorId,
            ]);

            return $existing->fresh() ?? $existing;
        }

        return NotificationTemplate::query()->create([
            'company_id' => $companyId,
            'event_key' => $eventKey,
            'subject' => $subject,
            'body' => $body,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    public function clear(int $companyId, string $eventKey): bool
    {
        $this->assertEventKey($eventKey);

        $row = NotificationTemplate::query()
            ->where('company_id', $companyId)
            ->where('event_key', $eventKey)
            ->first();

        if ($row === null) {
            return false;
        }

        return (bool) $row->delete();
    }

    /**
     * @param  array<string, string>  $replacements  ham (escape edilmemiş) değerler
     * @return array{title: string, body: string}
     */
    public function render(string $eventKey, int $companyId, array $replacements, array $catalogMeta): array
    {
        $safe = [];
        foreach ($replacements as $key => $value) {
            $safe[$key] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $override = NotificationTemplate::query()
            ->where('company_id', $companyId)
            ->where('event_key', $eventKey)
            ->first();

        if ($override !== null) {
            return [
                'title' => $this->applyPlaceholders($override->subject, $replacements),
                'body' => $this->applyPlaceholders($override->body, $replacements),
            ];
        }

        $defaults = $this->defaultTexts($eventKey, $catalogMeta, $safe);

        return [
            'title' => $defaults['subject'],
            'body' => $defaults['body'],
        ];
    }

    /**
     * @param  array<string, string>  $replacements
     * @return array{subject: string, body: string}
     */
    private function defaultTexts(string $eventKey, array $meta, array $replacements = []): array
    {
        $langReplace = [];
        foreach ($replacements as $key => $value) {
            $langReplace[$key] = $value;
        }

        // Önizleme için boş yer tutucular
        if ($langReplace === []) {
            foreach (($meta['variables'] ?? []) as $var) {
                if (is_string($var)) {
                    $langReplace[$var] = '{{'.$var.'}}';
                }
            }
        }

        $titleKey = (string) ($meta['title_key'] ?? '');
        $bodyKey = (string) ($meta['body_key'] ?? '');

        return [
            'subject' => $titleKey !== '' ? (string) __($titleKey, $langReplace) : $eventKey,
            'body' => $bodyKey !== '' ? (string) __($bodyKey, $langReplace) : '',
        ];
    }

    /**
     * {{var}} yer tutucuları — değerler HTML escape.
     *
     * @param  array<string, string>  $replacements
     */
    public function applyPlaceholders(string $template, array $replacements): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            function (array $m) use ($replacements): string {
                $key = $m[1];
                $raw = (string) ($replacements[$key] ?? '');

                return htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            },
            $template
        );
    }

    private function sanitizePlain(string $value): string
    {
        // HTML etiketlerini düz metne indir (şablon düz metin)
        $stripped = strip_tags($value);

        return trim($stripped);
    }

    private function assertEventKey(string $eventKey): void
    {
        $events = config('notifications.events', []);
        if (! is_array($events) || ! isset($events[$eventKey])) {
            Log::warning('notification_template.unknown_event', ['event' => $eventKey]);
            throw new InvalidArgumentException("Bilinmeyen bildirim olayı: {$eventKey}");
        }
    }
}
