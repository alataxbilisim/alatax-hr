import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import {
  FormEngine,
  type FormDefinitionPayload,
  type FormEngineSubmitPayload,
  type FormEngineValues,
} from '@shared/components';
import {
  branchesApi,
  employeesApi,
  formDefinitionsApi,
  lookupsApi,
  positionsApi,
  type LookupItem,
} from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import type { SelectOption } from '@shared/components';
import toast from 'react-hot-toast';
import { BsArrowLeft, BsArrowClockwise } from 'react-icons/bs';

const LOOKUP_KEY_MAP: Record<string, string> = {
  status: 'employee_status',
  gender: 'gender',
  marital_status: 'marital_status',
  blood_type: 'blood_type',
  education_level: 'education_level',
  emergency_contact_relation: 'emergency_relation',
  contract_type: 'contract_type',
  work_type: 'work_type',
  currency: 'currency',
};

function asOptionList(data: unknown): Array<Record<string, unknown>> {
  if (Array.isArray(data)) {
    return data as Array<Record<string, unknown>>;
  }
  if (data && typeof data === 'object' && Array.isArray((data as { data?: unknown }).data)) {
    return (data as { data: Array<Record<string, unknown>> }).data;
  }
  return [];
}

/**
 * Personel Form Engine pilotu — eski EmployeeForm'dan AYRI route.
 * /employees/form-engine/new | /employees/form-engine/:id/edit
 */
const EmployeeFormEnginePage: React.FC = () => {
  const { t } = useTranslation(['common', 'validation']);
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const isEdit = Boolean(id);

  const [definition, setDefinition] = useState<FormDefinitionPayload | null>(null);
  const [defaultValues, setDefaultValues] = useState<FormEngineValues>({});
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

  const loadRelationOptions = useCallback(async () => {
    const settled = await Promise.allSettled([
      employeesApi.getDepartments(),
      branchesApi.list({ per_page: 100, is_active: true }),
      employeesApi.getManagers(),
      positionsApi.getAll({ active_only: true, per_page: 100 }),
    ]);

    const map: Record<string, SelectOption[]> = {};

    if (settled[0].status === 'fulfilled') {
      const deptList = asOptionList(settled[0].value.data.data);
      map.department_id = deptList.map((d) => ({
        value: String(d.id),
        label: String(d.name ?? d.id),
      }));
    }

    if (settled[1].status === 'fulfilled') {
      const branchList = asOptionList(settled[1].value.data.data);
      map.branch_id = branchList.map((b) => ({
        value: String(b.id),
        label: String(b.name ?? b.id),
      }));
    }

    if (settled[2].status === 'fulfilled') {
      const managerList = asOptionList(settled[2].value.data.data);
      map.manager_id = managerList.map((m) => {
        const user = m.user as { name?: string } | undefined;
        const labelBase = user?.name || String(m.employee_code ?? m.id);
        const position = m.position ? ` — ${String(m.position)}` : '';
        return { value: String(m.id), label: `${labelBase}${position}` };
      });
    }

    if (settled[3].status === 'fulfilled') {
      const posList = asOptionList(settled[3].value.data.data);
      map.position = posList.map((p) => {
        const code = String(p.code ?? '');
        const name = String(p.name ?? code);
        const sgk = p.sgk_occupation_code ? ` (${String(p.sgk_occupation_code)})` : '';
        return { value: code, label: `${code} — ${name}${sgk}` };
      });
    }

    return map;
  }, []);

  const loadLookups = useCallback(async () => {
    const entries = Object.entries(LOOKUP_KEY_MAP);
    const results = await Promise.allSettled(entries.map(([, type]) => lookupsApi.forType(type)));
    const map: Record<string, SelectOption[]> = {};
    entries.forEach(([fieldKey], i) => {
      const result = results[i];
      if (result.status !== 'fulfilled') return;
      const items = asOptionList(result.value.data.data) as unknown as LookupItem[];
      map[fieldKey] = items.map((item) => ({ value: item.value, label: item.label }));
    });
    return map;
  }, []);

  useEffect(() => {
    let cancelled = false;

    const boot = async () => {
      try {
        setLoading(true);
        setLoadError(null);

        const defRes = await formDefinitionsApi.get('employee');
        if (cancelled) return;

        const def = defRes.data.data as FormDefinitionPayload;
        if (!def?.fields || !Array.isArray(def.fields)) {
          throw new Error(t('formEngine.loadError'));
        }

        const [lookupMap, relationMap] = await Promise.all([loadLookups(), loadRelationOptions()]);
        if (cancelled) return;

        setDefinition(def);
        setSelectOptionsByKey({ ...lookupMap, ...relationMap });

        if (isEdit && id) {
          const empRes = await employeesApi.getById(Number(id));
          const data = empRes.data.data;
          const employee = (data.employee || data) as Record<string, unknown> & {
            user?: { name?: string };
            custom_fields?: Record<string, unknown>;
          };

          const values: FormEngineValues = {};
          for (const field of def.fields) {
            if (field.is_system) {
              if (field.field_key === 'name') {
                values.name = employee.user?.name ?? '';
              } else {
                const v = employee[field.field_key];
                values[field.field_key] =
                  v === null || v === undefined ? '' : v;
              }
            } else {
              const cf = employee.custom_fields ?? {};
              const v = cf[field.field_key];
              values[field.field_key] =
                v === null || v === undefined ? '' : v;
            }
          }
          setDefaultValues(values);
        } else {
          setDefaultValues({ status: 'active' });
        }
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
  }, [id, isEdit, loadLookups, loadRelationOptions, reloadKey, t]);

  const handleSubmit = async (payload: FormEngineSubmitPayload) => {
    try {
      setSaving(true);
      const body = {
        ...payload.system,
        custom_fields: payload.custom_fields,
      };

      if (isEdit && id) {
        await employeesApi.update(Number(id), body);
        toast.success(t('formEngine.updateSuccess'));
      } else {
        await employeesApi.create(body);
        toast.success(t('formEngine.createSuccess'));
      }
      navigate('/employees');
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
            <h1 className="page-title">
              {isEdit ? t('formEngine.editTitle') : t('formEngine.createTitle')}
            </h1>
            <p className="page-subtitle">{t('formEngine.betaHint')}</p>
          </div>
          <div className="page-header-actions" style={{ display: 'flex', gap: '0.5rem' }}>
            <button type="button" className="btn btn-secondary btn-sm" onClick={() => navigate('/employees')}>
              <BsArrowLeft /> {t('formEngine.back')}
            </button>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center py-5">
            <p className="text-danger mb-3">{loadError || t('formEngine.loadError')}</p>
            <div style={{ display: 'flex', gap: '0.5rem', justifyContent: 'center', flexWrap: 'wrap' }}>
              <button
                type="button"
                className="btn btn-primary btn-sm"
                onClick={() => setReloadKey((k) => k + 1)}
              >
                <BsArrowClockwise /> {t('formEngine.retry')}
              </button>
              <Link to="/employees/new" className="btn btn-secondary btn-sm">
                {t('formEngine.useClassicForm')}
              </Link>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in form-page">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">
            {isEdit ? t('formEngine.editTitle') : t('formEngine.createTitle')}
          </h1>
          <p className="page-subtitle">{t('formEngine.betaHint')}</p>
        </div>
        <div className="page-header-actions" style={{ display: 'flex', gap: '0.5rem' }}>
          <Link to={isEdit && id ? `/employees/${id}/edit` : '/employees/new'} className="btn btn-secondary btn-sm">
            {t('formEngine.useClassicForm')}
          </Link>
          <button type="button" className="btn btn-secondary btn-sm" onClick={() => navigate('/employees')}>
            <BsArrowLeft /> {t('formEngine.back')}
          </button>
        </div>
      </div>

      <FormEngine
        definition={definition}
        defaultValues={defaultValues}
        selectOptionsByKey={selectOptionsByKey}
        onSubmit={handleSubmit}
        onCancel={() => navigate('/employees')}
        loading={saving}
        submitLabel={t('formEngine.save')}
        cancelLabel={t('formEngine.cancel')}
        savingLabel={t('formEngine.saving')}
        messages={messages}
      />
    </div>
  );
};

export default EmployeeFormEnginePage;
