import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from '@shared/i18n';
import { PageSettingsButton } from '@shared/components';
import { companyApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';

const ReportPrivacySettingsPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [enabled, setEnabled] = useState(true);
  const [threshold, setThreshold] = useState(5);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await companyApi.getSettings();
      const data: unknown = res.data.data;
      if (typeof data === 'object' && data !== null && 'report_privacy' in data) {
        const rp = data.report_privacy;
        if (typeof rp === 'object' && rp !== null) {
          if ('min_cell_enabled' in rp && typeof rp.min_cell_enabled === 'boolean') {
            setEnabled(rp.min_cell_enabled);
          }
          if ('min_cell_threshold' in rp && typeof rp.min_cell_threshold === 'number') {
            setThreshold(rp.min_cell_threshold);
          }
        }
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportPrivacy.loadError')));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  const save = async () => {
    try {
      setSaving(true);
      await companyApi.updateSettings({
        report_privacy: {
          min_cell_enabled: enabled,
          min_cell_threshold: threshold,
        },
      });
      toast.success(t('reportPrivacy.saveSuccess'));
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('reportPrivacy.saveError')));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <div className="page-container">{t('loading')}</div>;
  }

  return (
    <div className="page-container animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">{t('reportPrivacy.title')}</h1>
          <p className="page-subtitle">{t('reportPrivacy.subtitle')}</p>
        </div>
        <div className="page-header-actions">
          <PageSettingsButton pageKey="settings-report-privacy" />
        </div>
      </div>
      <div className="card">
        <div className="card-body" style={{ display: 'flex', flexDirection: 'column', gap: 'var(--sp-3)' }}>
          <label style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-2)' }}>
            <input
              type="checkbox"
              checked={enabled}
              onChange={(e) => setEnabled(e.target.checked)}
            />
            {t('reportPrivacy.minCellEnabled')}
          </label>
          <label style={{ display: 'flex', flexDirection: 'column', gap: 'var(--sp-1)' }}>
            {t('reportPrivacy.minCellThreshold')}
            <input
              type="number"
              min={1}
              max={100}
              value={threshold}
              onChange={(e) => setThreshold(Number(e.target.value) || 5)}
              style={{ maxWidth: 120 }}
            />
          </label>
          <button type="button" className="btn btn-primary" disabled={saving} onClick={() => void save()}>
            {t('reportPrivacy.save')}
          </button>
        </div>
      </div>
    </div>
  );
};

export default ReportPrivacySettingsPage;
