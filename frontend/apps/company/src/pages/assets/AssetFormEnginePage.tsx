import React, { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import {
  FormEngine,
  type FormDefinitionPayload,
  type FormEngineSubmitPayload,
  type SelectOption,
} from '@shared/components';
import { assetsApi, formDefinitionsApi, lookupsApi } from '@shared/services/api';
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
 * Varlık Form Engine pilotu — klasik AssetForm modalından AYRI route.
 */
const AssetFormEnginePage: React.FC = () => {
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

  useEffect(() => {
    let cancelled = false;
    const boot = async () => {
      try {
        setLoading(true);
        setLoadError(null);
        const [defRes, catRes, condRes] = await Promise.all([
          formDefinitionsApi.get('asset'),
          assetsApi.categories.list(),
          lookupsApi.forType('asset_condition'),
        ]);
        if (cancelled) return;
        const def = defRes.data.data;
        if (!isFormDefinitionPayload(def)) {
          throw new Error(t('formEngine.loadError'));
        }
        setDefinition(def);

        const catsRaw = catRes.data.data;
        const catRows = Array.isArray(catsRaw)
          ? catsRaw
          : isRecord(catsRaw) && Array.isArray(catsRaw.data)
            ? catsRaw.data
            : [];
        const categoryOptions: SelectOption[] = catRows
          .map((row) => {
            if (!isRecord(row) || typeof row.id !== 'number') return null;
            return {
              value: String(row.id),
              label: typeof row.name === 'string' ? row.name : String(row.id),
            };
          })
          .filter((o): o is SelectOption => o !== null);

        const condRaw = condRes.data.data;
        const condRows = Array.isArray(condRaw) ? condRaw : [];
        const conditionOptions: SelectOption[] = condRows
          .map((row) => {
            if (!isRecord(row)) return null;
            const value = typeof row.value === 'string' ? row.value : null;
            const label = typeof row.label === 'string' ? row.label : value;
            if (!value || !label) return null;
            return { value, label };
          })
          .filter((o): o is SelectOption => o !== null);

        setSelectOptionsByKey({
          category_id: categoryOptions,
          condition: conditionOptions,
        });
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
  }, [reloadKey, t]);

  const handleSubmit = async (payload: FormEngineSubmitPayload) => {
    try {
      setSaving(true);
      const s = payload.system;
      const body: Record<string, unknown> = {
        name: s.name,
        category_id: s.category_id != null ? Number(s.category_id) : null,
        description: s.description ?? null,
        asset_code: s.asset_code ?? null,
        serial_number: s.serial_number ?? null,
        brand: s.brand ?? null,
        model: s.model ?? null,
        purchase_date: s.purchase_date ?? null,
        purchase_price: s.purchase_price ?? null,
        warranty_end_date: s.warranty_end_date ?? null,
        condition: s.condition ?? null,
        location: s.location ?? null,
        custom_fields: payload.custom_fields,
      };
      await assetsApi.items.create(body);
      toast.success(t('formEngine.assetCreateSuccess'));
      navigate('/assets');
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
          <h1 className="page-title">{t('formEngine.assetCreateTitle')}</h1>
          <button type="button" className="btn btn-secondary btn-sm" onClick={() => navigate('/assets')}>
            <BsArrowLeft /> {t('formEngine.back')}
          </button>
        </div>
        <div className="card">
          <div className="card-body text-center py-5">
            <p className="text-danger mb-3">{loadError || t('formEngine.loadError')}</p>
            <button type="button" className="btn btn-primary btn-sm" onClick={() => setReloadKey((k) => k + 1)}>
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
          <h1 className="page-title">{t('formEngine.assetCreateTitle')}</h1>
          <p className="page-subtitle">{t('formEngine.assetBetaHint')}</p>
        </div>
        <div className="page-header-actions" style={{ display: 'flex', gap: '0.5rem' }}>
          <Link to="/assets" className="btn btn-secondary btn-sm">
            {t('formEngine.useClassicAssetForm')}
          </Link>
          <button type="button" className="btn btn-secondary btn-sm" onClick={() => navigate('/assets')}>
            <BsArrowLeft /> {t('formEngine.back')}
          </button>
        </div>
      </div>
      <FormEngine
        definition={definition}
        selectOptionsByKey={selectOptionsByKey}
        onSubmit={handleSubmit}
        onCancel={() => navigate('/assets')}
        loading={saving}
        submitLabel={t('formEngine.save')}
        cancelLabel={t('formEngine.cancel')}
        savingLabel={t('formEngine.saving')}
        messages={messages}
      />
    </div>
  );
};

export default AssetFormEnginePage;
