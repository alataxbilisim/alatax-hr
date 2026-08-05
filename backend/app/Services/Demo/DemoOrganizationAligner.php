<?php

namespace App\Services\Demo;

use App\Models\Company;
use App\Models\Organization;
use App\Models\User;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

/**
 * Demo holding: demo-firma + demo-otel-b + demo-otel-c → tek organization.
 * G1 backfill 1:1 org üretmiş olabilir; bu sınıf idempotent hizalar (silmez).
 */
final class DemoOrganizationAligner
{
    public const HOLDING_ORG_SLUG = 'org-demo-holding';

    public const HOLDING_ORG_NAME = 'Demo Holding';

    /** @var list<string> */
    public const DEMO_COMPANY_SLUGS = ['demo-firma', 'demo-otel-b', 'demo-otel-c'];

    public function __construct(
        protected CompanyContextService $companyContext,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   organization_id: int|null,
     *   companies: list<array{id: int, slug: string, organization_id: int|null}>,
     *   realigned: list<string>,
     *   orphan_org_ids: list<int>,
     *   missing_slugs: list<string>,
     *   message: string
     * }
     */
    public function align(): array
    {
        $companies = Company::query()
            ->whereIn('slug', self::DEMO_COMPANY_SLUGS)
            ->get()
            ->keyBy('slug');

        $missing = [];
        foreach (self::DEMO_COMPANY_SLUGS as $slug) {
            if (! $companies->has($slug)) {
                $missing[] = $slug;
            }
        }

        if ($missing !== []) {
            return [
                'ok' => false,
                'organization_id' => null,
                'companies' => [],
                'realigned' => [],
                'orphan_org_ids' => [],
                'missing_slugs' => $missing,
                'message' => 'Eksik demo şirket: '.implode(', ', $missing),
            ];
        }

        $org = Organization::query()->firstOrCreate(
            ['slug' => self::HOLDING_ORG_SLUG],
            ['name' => self::HOLDING_ORG_NAME]
        );
        if ($org->name !== self::HOLDING_ORG_NAME) {
            $org->forceFill(['name' => self::HOLDING_ORG_NAME])->saveQuietly();
        }

        $realigned = [];
        $previousOrgIds = [];

        DB::transaction(function () use ($companies, $org, &$realigned, &$previousOrgIds): void {
            foreach (self::DEMO_COMPANY_SLUGS as $slug) {
                /** @var Company $company */
                $company = $companies->get($slug);
                $before = $company->organization_id !== null ? (int) $company->organization_id : null;
                if ($before !== null && $before !== (int) $org->id) {
                    $previousOrgIds[] = $before;
                }
                if ($before !== (int) $org->id) {
                    $company->forceFill(['organization_id' => $org->id])->saveQuietly();
                    $realigned[] = $slug;
                }
            }
        });

        $emptyPrevious = [];
        foreach (array_unique($previousOrgIds) as $oid) {
            if ($oid === (int) $org->id) {
                continue;
            }
            if (! Company::query()->where('organization_id', $oid)->exists()) {
                $emptyPrevious[] = $oid;
            }
        }

        $snapshot = [];
        foreach (self::DEMO_COMPANY_SLUGS as $slug) {
            $c = Company::query()->where('slug', $slug)->first();
            $snapshot[] = [
                'id' => (int) $c->id,
                'slug' => $slug,
                'organization_id' => $c->organization_id !== null ? (int) $c->organization_id : null,
            ];
        }

        return [
            'ok' => true,
            'organization_id' => (int) $org->id,
            'companies' => $snapshot,
            'realigned' => $realigned,
            'orphan_org_ids' => $emptyPrevious,
            'missing_slugs' => [],
            'message' => $realigned === []
                ? 'Zaten hizalı (organization_id='.$org->id.')'
                : 'Hizalandı: '.implode(', ', $realigned),
        ];
    }

    /**
     * admin@demo.test / ik@demo.test → üç demo şirket membership.
     *
     * @return list<string>
     */
    public function ensureCenterMemberships(): array
    {
        $bySlug = Company::query()
            ->whereIn('slug', self::DEMO_COMPANY_SLUGS)
            ->get()
            ->keyBy('slug');

        $ids = [];
        foreach (self::DEMO_COMPANY_SLUGS as $slug) {
            if (! $bySlug->has($slug)) {
                return [];
            }
            $ids[] = (int) $bySlug->get($slug)->id;
        }

        $touched = [];
        foreach (['admin@demo.test', 'ik@demo.test'] as $email) {
            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                continue;
            }
            foreach ($ids as $i => $companyId) {
                $this->companyContext->ensureMembership($user, $companyId, $i === 0);
            }
            $touched[] = $email;
        }

        return $touched;
    }
}
