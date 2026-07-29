import React, { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import { dashboardsApi, type DashboardPayload } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';

function isDashboardPayload(v: unknown): v is DashboardPayload {
  return typeof v === 'object' && v !== null && 'id' in v && 'name' in v;
}

/**
 * D1g — /analytics artık sistem panosunu (hr-analytics.overview) açar.
 * Eski HrAnalyticsController uçları deprecate (DUR — Faz 6 temizlik).
 * DataScope motor üzerinden uygulanır (eski sabit sorgularda yoktu).
 */
const AnalyticsPage: React.FC = () => {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [dashboard, setDashboard] = useState<DashboardPayload | null>(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        setLoading(true);
        const res = await dashboardsApi.getByKey('hr-analytics.overview');
        const raw: unknown = res.data.data;
        if (!isDashboardPayload(raw)) {
          throw new Error('invalid');
        }
        if (!cancelled) {
          setDashboard(raw);
          navigate(`/dashboards/${raw.id}`, { replace: true });
        }
      } catch (error: unknown) {
        if (!cancelled) {
          toast.error(getErrorMessage(error, t('analyticsMotor.loadError')));
          setLoading(false);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [navigate, t]);

  if (loading && !dashboard) {
    return (
      <div className="page-container">
        <div className="page-header">
          <h1>{t('analyticsMotor.title')}</h1>
          <p>{t('analyticsMotor.subtitle')}</p>
        </div>
        <p>{t('loading')}</p>
      </div>
    );
  }

  return (
    <div className="page-container">
      <div className="page-header">
        <h1>{t('analyticsMotor.title')}</h1>
        <p>{t('analyticsMotor.deprecatedNote')}</p>
      </div>
      {dashboard ? (
        <Link to={`/dashboards/${dashboard.id}`} className="btn btn--primary">
          {t('analyticsMotor.openDashboard')}
        </Link>
      ) : null}
    </div>
  );
};

export default AnalyticsPage;
