import React, { useEffect, useMemo, useState } from 'react';
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
 * Portal masraf Form Engine pilotu — header FormEngine, kalemler klasik (zorunlu).
 */
const ExpenseFormEnginePage: React.FC = () => {
  const { t } = useTranslation(['common', 'validation']);
  const navigate = useNavigate();
  const [definition, setDefinition] = useState<FormDefinitionPayload | null>(null);
  const [selectOptionsByKey, setSelectOptionsByKey] = useState<Record<string, SelectOption[]>>({});
  const [categories, setCategories] = useState<SelectOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);
  const [saving, setSaving] = useState(false);
  const [itemCategoryId, setItemCategoryId] = useState('');
  const [itemDescription, setItemDescription] = useState('');
  const [itemAmount, setItemAmount] = useState('');
  const [itemDate, setItemDate] = useState(() => new Date().toISOString().slice(0, 10));

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
        const [defRes, catRes] = await Promise.all([
          portalApi.formDefinitions.get('expense'),
          portalApi.expenses.categories(),
        ]);
        if (cancelled) return;
        const def = defRes.data.data;
        if (!isFormDefinitionPayload(def)) {
          throw new Error(t('formEngine.loadError'));
        }
        setDefinition(def);

        const catRows: unknown[] = Array.isArray(catRes.data.data) ? catRes.data.data : [];
        const opts: SelectOption[] = catRows
          .map((row: unknown) => {
            if (!isRecord(row) || typeof row.id !== 'number') return null;
            return {
              value: String(row.id),
              label: typeof row.name === 'string' ? row.name : String(row.id),
            };
          })
          .filter((o: SelectOption | null): o is SelectOption => o !== null);
        setCategories(opts);
        setSelectOptionsByKey({
          currency: [
            { value: 'TRY', label: 'TRY' },
            { value: 'USD', label: 'USD' },
            { value: 'EUR', label: 'EUR' },
          ],
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
    if (!itemCategoryId || !itemDescription.trim() || !itemAmount) {
      toast.error(t('formEngine.expenseItemRequired'));
      return;
    }
    try {
      setSaving(true);
      const s = payload.system;
      await portalApi.expenses.create({
        title: s.title,
        description: s.description ?? null,
        expense_date: s.expense_date,
        currency: s.currency ?? 'TRY',
        custom_fields: payload.custom_fields,
        items: [
          {
            expense_category_id: Number(itemCategoryId),
            description: itemDescription.trim(),
            item_date: itemDate,
            amount: Number(itemAmount),
          },
        ],
      });
      toast.success(t('formEngine.expenseCreateSuccess'));
      navigate('/expenses');
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
        <Link to="/expenses" className="btn btn-secondary btn-sm ms-2">
          <BsArrowLeft /> {t('formEngine.back')}
        </Link>
      </div>
    );
  }

  return (
    <div className="container py-4">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h1 className="h4 mb-1">{t('formEngine.expenseCreateTitle')}</h1>
          <p className="text-muted small mb-0">{t('formEngine.expenseBetaHint')}</p>
        </div>
        <Link to="/expenses" className="btn btn-outline-secondary btn-sm">
          {t('formEngine.useClassicExpenseForm')}
        </Link>
      </div>

      <div className="card mb-3">
        <div className="card-body">
          <h2 className="h6">{t('formEngine.expenseItemsHeading')}</h2>
          <div className="row g-2">
            <div className="col-md-3">
              <label className="form-label">{t('formEngine.expenseItemCategory')}</label>
              <select
                className="form-select"
                value={itemCategoryId}
                onChange={(e) => setItemCategoryId(e.target.value)}
              >
                <option value="">{t('formEngine.selectPlaceholder')}</option>
                {categories.map((c) => (
                  <option key={c.value} value={c.value}>
                    {c.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-4">
              <label className="form-label">{t('formEngine.expenseItemDescription')}</label>
              <input
                className="form-control"
                value={itemDescription}
                onChange={(e) => setItemDescription(e.target.value)}
              />
            </div>
            <div className="col-md-2">
              <label className="form-label">{t('formEngine.expenseItemAmount')}</label>
              <input
                className="form-control"
                type="number"
                min={0.01}
                step="0.01"
                value={itemAmount}
                onChange={(e) => setItemAmount(e.target.value)}
              />
            </div>
            <div className="col-md-3">
              <label className="form-label">{t('formEngine.expenseItemDate')}</label>
              <input
                className="form-control"
                type="date"
                value={itemDate}
                onChange={(e) => setItemDate(e.target.value)}
              />
            </div>
          </div>
        </div>
      </div>

      <FormEngine
        definition={definition}
        selectOptionsByKey={selectOptionsByKey}
        onSubmit={handleSubmit}
        onCancel={() => navigate('/expenses')}
        loading={saving}
        submitLabel={t('formEngine.save')}
        cancelLabel={t('formEngine.cancel')}
        savingLabel={t('formEngine.saving')}
        messages={messages}
      />
    </div>
  );
};

export default ExpenseFormEnginePage;
