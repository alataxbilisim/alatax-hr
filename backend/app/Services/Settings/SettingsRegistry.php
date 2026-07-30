<?php

namespace App\Services\Settings;

use App\Enums\SettingScopeType;
use App\Enums\SettingTier;
use App\Enums\SettingValueType;
use App\Models\User;
use InvalidArgumentException;

/**
 * Ayar tanımları kataloğu — D1a DatasetRegistry deseni.
 */
class SettingsRegistry
{
    /** @var array<string, SettingDefinition>|null */
    private ?array $definitions = null;

    /**
     * @return array<string, SettingDefinition>
     */
    public function all(): array
    {
        if ($this->definitions === null) {
            $list = $this->buildPilotDefinitions();
            $this->definitions = [];
            foreach ($list as $def) {
                $this->definitions[$def->key] = $def;
            }
        }

        return $this->definitions;
    }

    public function get(string $key): SettingDefinition
    {
        $all = $this->all();
        if (! isset($all[$key])) {
            throw new InvalidArgumentException('Bilinmeyen ayar: '.$key);
        }

        return $all[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /**
     * Kullanıcının görebileceği tanımlar (settings.values.view + ayar permission).
     *
     * @return list<SettingDefinition>
     */
    public function visibleFor(User $user, ?string $pageKey = null, bool $includeAdvanced = true): array
    {
        $out = [];
        foreach ($this->all() as $def) {
            if ($pageKey !== null && ! in_array($pageKey, $def->pageKeys, true)) {
                continue;
            }
            if (! $includeAdvanced && $def->tier === SettingTier::Advanced) {
                continue;
            }
            if ($def->tier === SettingTier::System && ! $user->isSuperAdmin()) {
                continue;
            }
            if (! $this->userCanView($user, $def)) {
                continue;
            }
            $out[] = $def;
        }

        return $out;
    }

    public function userCanView(User $user, SettingDefinition $def): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->can('settings.values.view') && $user->can($def->permission);
    }

    public function userCanEdit(User $user, SettingDefinition $def): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $editPerm = preg_replace('/\.view$/', '.edit', $def->permission) ?? $def->permission;

        return $user->can('settings.values.edit') && $user->can($editPerm);
    }

    /**
     * Pilot: İzin + Rapor. Diğer modüller Faz 6.
     *
     * @return list<SettingDefinition>
     */
    private function buildPilotDefinitions(): array
    {
        $companyScopes = [
            SettingScopeType::Company,
            SettingScopeType::Branch,
            SettingScopeType::Department,
            SettingScopeType::User,
        ];

        return [
            // —— Yasal taban (sistem; SuperAdmin) ——
            new SettingDefinition(
                key: 'legal.leaves.retention.months.min',
                moduleKey: 'settings',
                pageKeys: ['settings.registry'],
                labelKey: 'settingsRegistry.legal.leavesRetentionMin',
                descriptionKey: 'settingsRegistry.legal.leavesRetentionMinHelp',
                type: SettingValueType::Int,
                default: 12,
                scopeLevels: [SettingScopeType::System],
                permission: 'settings.reports.view',
                tier: SettingTier::System,
                affectsKey: 'settingsRegistry.affects.legalFloor',
                validation: ['min' => 1, 'max' => 1200, 'required' => true],
            ),
            new SettingDefinition(
                key: 'legal.leaves.min_days_notice.min',
                moduleKey: 'settings',
                pageKeys: ['settings.registry'],
                labelKey: 'settingsRegistry.legal.minDaysNoticeMin',
                descriptionKey: 'settingsRegistry.legal.minDaysNoticeMinHelp',
                type: SettingValueType::Int,
                default: 0,
                scopeLevels: [SettingScopeType::System],
                permission: 'settings.leaves.view',
                tier: SettingTier::System,
                affectsKey: 'settingsRegistry.affects.legalFloor',
                validation: ['min' => 0, 'max' => 365, 'required' => true],
            ),

            // —— İzin (pilot) ——
            new SettingDefinition(
                key: 'leaves.balance.allow_carryover',
                moduleKey: 'leaves',
                pageKeys: ['leaves.balances.list', 'leaves.requests.list', 'settings.registry'],
                labelKey: 'settingsRegistry.leaves.allowCarryover',
                descriptionKey: 'settingsRegistry.leaves.allowCarryoverHelp',
                type: SettingValueType::Bool,
                default: true,
                scopeLevels: $companyScopes,
                permission: 'settings.leaves.view',
                tier: SettingTier::Basic,
                affectsKey: 'settingsRegistry.affects.leaveCarryover',
            ),
            new SettingDefinition(
                key: 'leaves.balance.allow_negative',
                moduleKey: 'leaves',
                pageKeys: ['leaves.balances.list', 'leaves.requests.list', 'settings.registry'],
                labelKey: 'settingsRegistry.leaves.allowNegative',
                descriptionKey: 'settingsRegistry.leaves.allowNegativeHelp',
                type: SettingValueType::Bool,
                default: false,
                scopeLevels: $companyScopes,
                permission: 'settings.leaves.view',
                tier: SettingTier::Basic,
                affectsKey: 'settingsRegistry.affects.leaveNegative',
            ),
            new SettingDefinition(
                key: 'leaves.request.min_days_notice',
                moduleKey: 'leaves',
                pageKeys: ['leaves.requests.list', 'settings.registry'],
                labelKey: 'settingsRegistry.leaves.minDaysNotice',
                descriptionKey: 'settingsRegistry.leaves.minDaysNoticeHelp',
                type: SettingValueType::Int,
                default: 0,
                scopeLevels: $companyScopes,
                permission: 'settings.leaves.view',
                tier: SettingTier::Basic,
                affectsKey: 'settingsRegistry.affects.leaveNotice',
                validation: ['min' => 0, 'max' => 365, 'required' => true],
                legalMinKey: 'legal.leaves.min_days_notice.min',
            ),
            new SettingDefinition(
                key: 'leaves.retention.months',
                moduleKey: 'leaves',
                pageKeys: ['leaves.requests.list', 'settings.registry'],
                labelKey: 'settingsRegistry.leaves.retentionMonths',
                descriptionKey: 'settingsRegistry.leaves.retentionMonthsHelp',
                type: SettingValueType::Int,
                default: 24,
                scopeLevels: [SettingScopeType::Company],
                permission: 'settings.leaves.view',
                tier: SettingTier::Advanced,
                affectsKey: 'settingsRegistry.affects.leaveRetention',
                validation: ['min' => 1, 'max' => 1200, 'required' => true],
                legalMinKey: 'legal.leaves.retention.months.min',
            ),

            // —— Rapor (pilot; legacy company.settings.report_privacy) ——
            new SettingDefinition(
                key: 'reports.privacy.min_cell_enabled',
                moduleKey: 'reports',
                pageKeys: ['analytics.reports.builder', 'settings.report_privacy', 'settings.registry'],
                labelKey: 'settingsRegistry.reports.minCellEnabled',
                descriptionKey: 'settingsRegistry.reports.minCellEnabledHelp',
                type: SettingValueType::Bool,
                default: true,
                scopeLevels: [SettingScopeType::Company],
                permission: 'settings.reports.view',
                tier: SettingTier::Basic,
                affectsKey: 'settingsRegistry.affects.reportPrivacy',
                legacyCompanyPath: 'report_privacy.min_cell_enabled',
            ),
            new SettingDefinition(
                key: 'reports.privacy.min_cell_threshold',
                moduleKey: 'reports',
                pageKeys: ['analytics.reports.builder', 'settings.report_privacy', 'settings.registry'],
                labelKey: 'settingsRegistry.reports.minCellThreshold',
                descriptionKey: 'settingsRegistry.reports.minCellThresholdHelp',
                type: SettingValueType::Int,
                default: 5,
                scopeLevels: [SettingScopeType::Company],
                permission: 'settings.reports.view',
                tier: SettingTier::Basic,
                affectsKey: 'settingsRegistry.affects.reportPrivacy',
                validation: ['min' => 1, 'max' => 1000, 'required' => true],
                legacyCompanyPath: 'report_privacy.min_cell_threshold',
            ),
            new SettingDefinition(
                key: 'reports.cache.default_ttl_seconds',
                moduleKey: 'reports',
                pageKeys: ['analytics.reports.builder', 'settings.registry'],
                labelKey: 'settingsRegistry.reports.cacheTtl',
                descriptionKey: 'settingsRegistry.reports.cacheTtlHelp',
                type: SettingValueType::Int,
                default: 300,
                scopeLevels: [SettingScopeType::Company],
                permission: 'settings.reports.view',
                tier: SettingTier::Advanced,
                affectsKey: 'settingsRegistry.affects.reportCache',
                validation: ['min' => 0, 'max' => 86400, 'required' => true],
            ),

            // —— KVKK D2b ——
            new SettingDefinition(
                key: 'legal.kvkk.response_days.max',
                moduleKey: 'settings',
                pageKeys: ['settings.registry'],
                labelKey: 'settingsRegistry.legal.kvkkResponseDaysMax',
                descriptionKey: 'settingsRegistry.legal.kvkkResponseDaysMaxHelp',
                type: SettingValueType::Int,
                default: 30,
                scopeLevels: [SettingScopeType::System],
                permission: 'settings.kvkk.view',
                tier: SettingTier::System,
                affectsKey: 'settingsRegistry.affects.legalFloor',
                validation: ['min' => 1, 'max' => 30, 'required' => true],
            ),
            new SettingDefinition(
                key: 'kvkk.data_subject.response_days',
                moduleKey: 'kvkk',
                pageKeys: ['kvkk.requests', 'settings.registry'],
                labelKey: 'settingsRegistry.kvkk.responseDays',
                descriptionKey: 'settingsRegistry.kvkk.responseDaysHelp',
                type: SettingValueType::Int,
                default: 30,
                scopeLevels: [SettingScopeType::Company],
                permission: 'settings.kvkk.view',
                tier: SettingTier::Basic,
                affectsKey: 'settingsRegistry.affects.kvkkDue',
                validation: ['min' => 1, 'max' => 365, 'required' => true],
                legalMaxKey: 'legal.kvkk.response_days.max',
            ),
            new SettingDefinition(
                key: 'kvkk.data_subject.export_link_days',
                moduleKey: 'kvkk',
                pageKeys: ['kvkk.requests', 'settings.registry'],
                labelKey: 'settingsRegistry.kvkk.exportLinkDays',
                descriptionKey: 'settingsRegistry.kvkk.exportLinkDaysHelp',
                type: SettingValueType::Int,
                default: 7,
                scopeLevels: [SettingScopeType::Company],
                permission: 'settings.kvkk.view',
                tier: SettingTier::Advanced,
                affectsKey: 'settingsRegistry.affects.kvkkExport',
                validation: ['min' => 1, 'max' => 30, 'required' => true],
            ),

            // —— KVKK D2c ——
            new SettingDefinition(
                key: 'legal.kvkk.retention.months.min',
                moduleKey: 'settings',
                pageKeys: ['settings.registry'],
                labelKey: 'settingsRegistry.legal.kvkkRetentionMonthsMin',
                descriptionKey: 'settingsRegistry.legal.kvkkRetentionMonthsMinHelp',
                type: SettingValueType::Int,
                default: 6,
                scopeLevels: [SettingScopeType::System],
                permission: 'settings.kvkk.view',
                tier: SettingTier::System,
                affectsKey: 'settingsRegistry.affects.legalFloor',
                validation: ['min' => 1, 'max' => 600, 'required' => true],
            ),
            new SettingDefinition(
                key: 'legal.kvkk.breach_notify_hours.max',
                moduleKey: 'settings',
                pageKeys: ['settings.registry'],
                labelKey: 'settingsRegistry.legal.kvkkBreachHoursMax',
                descriptionKey: 'settingsRegistry.legal.kvkkBreachHoursMaxHelp',
                type: SettingValueType::Int,
                default: 72,
                scopeLevels: [SettingScopeType::System],
                permission: 'settings.kvkk.view',
                tier: SettingTier::System,
                affectsKey: 'settingsRegistry.affects.legalFloor',
                validation: ['min' => 1, 'max' => 72, 'required' => true],
            ),
            new SettingDefinition(
                key: 'kvkk.destruction.review_period_months',
                moduleKey: 'kvkk',
                pageKeys: ['kvkk.destruction', 'settings.registry'],
                labelKey: 'settingsRegistry.kvkk.reviewPeriodMonths',
                descriptionKey: 'settingsRegistry.kvkk.reviewPeriodMonthsHelp',
                type: SettingValueType::Int,
                default: 6,
                scopeLevels: [SettingScopeType::Company],
                permission: 'settings.kvkk.view',
                tier: SettingTier::Basic,
                affectsKey: 'settingsRegistry.affects.kvkkDestruction',
                validation: ['min' => 1, 'max' => 36, 'required' => true],
            ),
        ];
    }
}
