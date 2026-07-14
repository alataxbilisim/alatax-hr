import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import {
  FormEngine,
  type FormDefinitionPayload,
  type FormEngineSubmitPayload,
  type SelectOption,
} from '@shared/components';
import { formDefinitionsApi, leavesApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { BsArrowLeft, BsArrowClockwise } from 'react-icons/bs';

function asRecordList(data: unknown): Array<Record<string, unknown>> {
  if (Array.isArray(data)) {
    return data as Array<Record<string, unknown>>;
  }
  if (data && typeof data === 'object' && Array.isArray((data as { data?: unknown }).data)) {
    return (data as { data: Array<Record<string, unknown>> }).data;
  }
  return [];
}

/**
 * İzin talebi Form Engine pilotu — klasik LeaveRequestForm modal’dan AYRI route.
 * Submit mevcut POST /leaves/requests uçuna gider (bakiye/workflow BE’de kalır).
 */
const LeaveRequestFormEnginePage: React.FC = () => {
  const { t } = useTranslation(['common', 'validation']);
  const navigate = useNavigate();

  const [definition, setDefinition] = useState<FormDefinitionPayload | null>(null);
  const [selectOptionsByKey, setSelectOptionsByKey] = useState<Record<string, SelectOption[]>>({});
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);
  const [saving, setSaving] = useState(false);

  const messages = useMemo(
    () => ({
      required: t('validation:required'),
      email: t('validation:email'),
      tckn: t('formEngine.invalidTckn'),
      number: t('validation:pattern'),
      readonlyBadge: t('formEngine.readonlyBadge'),
      selectPlaceholder: t('formEngine.selectPlaceholder'),
    }),
    [t]
  );

  const loadLeaveTypes = useCallback(async (): Promise<SelectOption[]> => {
    try {
      const response = await leavesApi.types.list({ is_active: true });
      const rows = asRecordList(response.data.data);
      return rows.map((row) => ({
        value: String(row.id),
        label: String(row.name ?? row.id),
      }));
    } catch {
      return [];
    }
  }, []);

  useEffect(() => {
    let cancelled = false;

    const boot = async () => {
      try {
        setLoading(true);
        setLoadError(null);
        const [defRes, typeOptions] = await Promise.all([
          formDefinitionsApi.get('leave_request'),
          loadLeaveTypes(),
        ]);
        if (cancelled) return;

        const def = defRes.data.data as FormDefinitionPayload;
        if (!def?.fields || !Array.isArray(def.fields)) {
          throw new Error(t('formEngine.loadError'));
        }

        setDefinition(def);
        setSelectOptionsByKey({ leave_type_id: typeOptions });
      } catch (error: unknown) {
        if (cancelled) return;
        const message = getErrorMessage(error, t('formEngine.loadError'));
        setLoadError(message);
        setDefinition(null);
        toast.error(message);
      } finally {
        if (!cancelled) setLoading(false);
      }
    };

    void boot();
    return () => {
      cancelled = true;
    };
  }, [loadLeaveTypes, reloadKey, t]);

  const handleSubmit = async (payload: FormEngineSubmitPayload) => {
    try {
      setSaving(true);
      const system = payload.system;
      const formData = new FormData();

      const leaveTypeId = system.leave_type_id;
      const startDate = system.start_date;
      const endDate = system.end_date;
      const reason = system.reason;

      if (leaveTypeId !== null && leaveTypeId !== undefined) {
        formData.append('leave_type_id', String(leaveTypeId));
      }
      if (typeof startDate === 'string') {
        formData.append('start_date', startDate);
      }
      if (typeof endDate === 'string') {
        formData.append('end_date', endDate);
      }
      if (typeof reason === 'string' && reason.trim() !== '') {
        formData.append('reason', reason);
      }

      const documentValue = system.document;
      if (documentValue instanceof File) {
        formData.append('document', documentValue);
      }

      await leavesApi.requests.create(formData);
      toast.success(t('formEngine.leaveCreateSuccess'));
      navigate('/leaves');
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('formEngine.saveError')));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="animate-fade-in form-page">
        <div className="card">
          <div className="card-body text-center py-5">
            <div className="spinner-border" role="status" />
          </div>
        </div>
      </div>
    );
  }

  if (loadError || !definition) {
    return (
      <div className="animate-fade-in form-page">
        <div className="page-header">
          <div className="page-header-content">
            <h1 className="page-title">{t('formEngine.leaveCreateTitle')}</h1>
          </div>
          <div className="page-header-actions">
            <button type="button" className="btn btn-secondary btn-sm" onClick={() => navigate('/leaves')}>
              <BsArrowLeft /> {t('formEngine.back')}
            </button>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center py-5">
            <p className="text-danger mb-3">{loadError || t('formEngine.loadError')}</p>
            <button
              type="button"
              className="btn btn-primary btn-sm"
              onClick={() => setReloadKey((k) => k + 1)}
            >
              <BsArrowClockwise /> {t('formEngine.retry')}
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in form-page">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">{t('formEngine.leaveCreateTitle')}</h1>
          <p className="page-subtitle">{t('formEngine.leaveBetaHint')}</p>
        </div>
        <div className="page-header-actions" style={{ display: 'flex', gap: '0.5rem' }}>
          <Link to="/leaves" className="btn btn-secondary btn-sm">
            {t('formEngine.useClassicLeaveForm')}
          </Link>
          <button type="button" className="btn btn-secondary btn-sm" onClick={() => navigate('/leaves')}>
            <BsArrowLeft /> {t('formEngine.back')}
          </button>
        </div>
      </div>

      <p style={{ color: 'var(--text-tertiary)', marginBottom: 'var(--space-3)', fontSize: '0.875rem' }}>
        {t('formEngine.leaveTotalDaysHint')}
      </p>

      <FormEngine
        definition={definition}
        selectOptionsByKey={selectOptionsByKey}
        onSubmit={handleSubmit}
        onCancel={() => navigate('/leaves')}
        loading={saving}
        submitLabel={t('formEngine.save')}
        cancelLabel={t('formEngine.cancel')}
        savingLabel={t('formEngine.saving')}
        messages={messages}
      />
    </div>
  );
};

export default LeaveRequestFormEnginePage;
