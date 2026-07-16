import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import {
  FormEngine,
  type FormDefinitionPayload,
  type FormEngineSubmitPayload,
  type SelectOption,
} from '@shared/components';
import { portalApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { BsArrowLeft, BsArrowClockwise } from 'react-icons/bs';

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isFormDefinitionPayload(value: unknown): value is FormDefinitionPayload {
  return isRecord(value) && typeof value.name === 'string' && Array.isArray(value.fields);
}

/**
 * Portal izin Form Engine pilotu — klasik LeavesPage formu varsayılan kalır.
 */
const LeaveFormEnginePage: React.FC = () => {
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

  const loadTypes = useCallback(async (): Promise<SelectOption[]> => {
    try {
      const res = await portalApi.leaves.types();
      const rows: unknown[] = Array.isArray(res.data.data) ? res.data.data : [];
      return rows
        .map((row: unknown) => {
          if (!isRecord(row) || typeof row.id !== 'number') return null;
          return {
            value: String(row.id),
            label: typeof row.name === 'string' ? row.name : String(row.id),
          };
        })
        .filter((o: SelectOption | null): o is SelectOption => o !== null);
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
          portalApi.formDefinitions.get('leave_request'),
          loadTypes(),
        ]);
        if (cancelled) return;
        const def = defRes.data.data;
        if (!isFormDefinitionPayload(def)) {
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
  }, [loadTypes, reloadKey, t]);

  const handleSubmit = async (payload: FormEngineSubmitPayload) => {
    try {
      setSaving(true);
      const formData = new FormData();
      const s = payload.system;
      if (s.leave_type_id != null) formData.append('leave_type_id', String(s.leave_type_id));
      if (typeof s.start_date === 'string') formData.append('start_date', s.start_date);
      if (typeof s.end_date === 'string') formData.append('end_date', s.end_date);
      if (typeof s.reason === 'string' && s.reason.trim() !== '') {
        formData.append('reason', s.reason);
      }
      if (s.document instanceof File) {
        formData.append('document', s.document);
      }
      if (Object.keys(payload.custom_fields).length > 0) {
        formData.append('custom_fields', JSON.stringify(payload.custom_fields));
      }
      await portalApi.leaves.create(formData);
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
      <div className="container py-4">
        <div className="text-center py-5">
          <div className="spinner-border" role="status" />
        </div>
      </div>
    );
  }

  if (loadError || !definition) {
    return (
      <div className="container py-4">
        <p className="text-danger">{loadError || t('formEngine.loadError')}</p>
        <button type="button" className="btn btn-primary btn-sm" onClick={() => setReloadKey((k) => k + 1)}>
          <BsArrowClockwise /> {t('formEngine.retry')}
        </button>
        <Link to="/leaves" className="btn btn-secondary btn-sm ms-2">
          <BsArrowLeft /> {t('formEngine.back')}
        </Link>
      </div>
    );
  }

  return (
    <div className="container py-4">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h1 className="h4 mb-1">{t('formEngine.leaveCreateTitle')}</h1>
          <p className="text-muted small mb-0">{t('formEngine.leaveBetaHint')}</p>
        </div>
        <Link to="/leaves" className="btn btn-outline-secondary btn-sm">
          {t('formEngine.useClassicLeaveForm')}
        </Link>
      </div>
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

export default LeaveFormEnginePage;
