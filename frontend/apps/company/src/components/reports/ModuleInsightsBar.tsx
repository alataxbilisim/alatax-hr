import React from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import { usePermission } from '@shared/hooks';

interface ModuleInsightsBarProps {
  /** ModuleSeeder slug — örn. leave-management, timesheet */
  moduleKey: string;
  /** Modülün kendi view izni: module + page */
  modulePermission: { module: string; page: string };
}

/**
 * D1g — Ortak modül [Pano] / [Raporlar] şeridi.
 * Modül view + reports.* birlikte gerekir; kopya sayfa yok.
 */
const ModuleInsightsBar: React.FC<ModuleInsightsBarProps> = ({
  moduleKey,
  modulePermission,
}) => {
  const { t } = useTranslation('common');
  const { hasPermission } = usePermission();

  const canModule = hasPermission(modulePermission.module, modulePermission.page, 'view');
  const canReports = hasPermission('reports', 'definitions', 'view');
  const canDashboards = hasPermission('reports', 'dashboards', 'view');

  if (!canModule) {
    return null;
  }
  if (!canReports && !canDashboards) {
    return null;
  }

  return (
    <div
      className="module-insights-bar"
      style={{
        display: 'flex',
        gap: 'var(--sp-2)',
        marginBottom: 'var(--sp-3)',
        flexWrap: 'wrap',
      }}
    >
      {canDashboards ? (
        <Link
          to={`/dashboards?module_key=${encodeURIComponent(moduleKey)}`}
          className="btn btn--sm btn--ghost"
        >
          {t('moduleInsights.dashboards')}
        </Link>
      ) : null}
      {canReports ? (
        <Link
          to={`/reports?module_key=${encodeURIComponent(moduleKey)}`}
          className="btn btn--sm btn--ghost"
        >
          {t('moduleInsights.reports')}
        </Link>
      ) : null}
    </div>
  );
};

export default ModuleInsightsBar;
