import React, { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import { formDefinitionsApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import type { FormDefinitionPayload, FormFieldMeta, FormLayout } from '@shared/components';
import toast from 'react-hot-toast';
import {
  BsArrowDown,
  BsArrowUp,
  BsEye,
  BsEyeSlash,
  BsLock,
  BsSave,
  BsShieldLock,
} from 'react-icons/bs';

/**
 * Ayarlar Stüdyosu — Personel form layout / alan metadata editörü.
 * Sistem alan silinemez; yalnız gizle / rename / zorunlu / sıra.
 */
const FormLayoutEditorPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [definition, setDefinition] = useState<FormDefinitionPayload | null>(null);
  const [fields, setFields] = useState<FormFieldMeta[]>([]);
  const [layout, setLayout] = useState<FormLayout | null>(null);
  const [formName, setFormName] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const res = await formDefinitionsApi.get('employee');
      const data = res.data.data as FormDefinitionPayload;
      setDefinition(data);
      setFields(data.fields ?? []);
      setLayout(data.layout);
      setFormName(data.name);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('formEngine.editorLoadError')));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  const updateField = (index: number, patch: Partial<FormFieldMeta>) => {
    setFields((prev) => {
      const next = [...prev];
      next[index] = { ...next[index], ...patch };
      if (patch.label_override !== undefined) {
        next[index].effective_label =
          patch.label_override || next[index].field_label;
      }
      if (patch.is_required_override !== undefined) {
        next[index].effective_required =
          patch.is_required_override !== null
            ? Boolean(patch.is_required_override)
            : next[index].is_required;
      }
      return next;
    });
  };

  const moveField = (index: number, direction: -1 | 1) => {
    setFields((prev) => {
      const target = index + direction;
      if (target < 0 || target >= prev.length) return prev;
      const next = [...prev];
      const tmp = next[index];
      next[index] = next[target];
      next[target] = tmp;
      return next.map((f, i) => ({ ...f, sort_order: (i + 1) * 10 }));
    });
  };

  const handleSave = async () => {
    if (!layout) return;
    try {
      setSaving(true);
      const payload = {
        name: formName,
        layout,
        fields: fields.map((f) => {
          if (f.is_system) {
            return {
              system_key: f.system_key ?? f.field_key,
              label_override: f.label_override,
              is_hidden: f.is_hidden,
              is_required_override: f.is_required_override,
              sort_order: f.sort_order,
              field_permission: f.field_permission,
            };
          }
          return {
            id: f.id,
            field_key: f.field_key,
            label_override: f.label_override,
            field_label: f.field_label,
            is_hidden: f.is_hidden,
            is_required: f.is_required,
            is_required_override: f.is_required_override,
            sort_order: f.sort_order,
            field_permission: f.field_permission,
          };
        }),
      };

      const res = await formDefinitionsApi.update('employee', payload);
      const data = res.data.data as FormDefinitionPayload;
      setDefinition(data);
      setFields(data.fields ?? []);
      setLayout(data.layout);
      setFormName(data.name);
      toast.success(t('formEngine.editorSaved'));
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('formEngine.editorSaveError')));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="animate-fade-in">
        <div className="card">
          <div className="card-body text-center py-5">
            <div className="spinner-border" role="status" />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">{t('formEngine.editorTitle')}</h1>
          <p className="page-subtitle">{t('formEngine.editorSubtitle')}</p>
        </div>
        <div className="page-header-actions" style={{ display: 'flex', gap: '0.5rem' }}>
          <Link to="/employees/form-engine/new" className="btn btn-secondary btn-sm">
            {t('formEngine.previewPilot')}
          </Link>
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={() => void handleSave()}
            disabled={saving}
          >
            <BsSave /> {t('formEngine.save')}
          </button>
        </div>
      </div>

      <div className="card" style={{ marginBottom: 'var(--space-4)' }}>
        <div className="card-body">
          <div className="form-group">
            <label className="form-label">{t('formEngine.formName')}</label>
            <input
              className="form-control"
              value={formName}
              onChange={(e) => setFormName(e.target.value)}
            />
          </div>
          {definition?.is_system_default && (
            <p style={{ color: 'var(--text-tertiary)', margin: 0, fontSize: '0.875rem' }}>
              {t('formEngine.usingSystemDefault')}
            </p>
          )}
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <h3 className="card-title">{t('formEngine.fieldsHeading')}</h3>
        </div>
        <div className="card-body" style={{ padding: 0 }}>
          <div style={{ overflowX: 'auto' }}>
            <table className="table" style={{ margin: 0 }}>
              <thead>
                <tr>
                  <th style={{ width: 72 }}>{t('formEngine.colOrder')}</th>
                  <th>{t('formEngine.colField')}</th>
                  <th>{t('formEngine.colLabel')}</th>
                  <th style={{ width: 100 }}>{t('formEngine.colHidden')}</th>
                  <th style={{ width: 120 }}>{t('formEngine.colRequired')}</th>
                  <th style={{ width: 140 }}>{t('formEngine.colPermission')}</th>
                  <th style={{ width: 80 }}>{t('formEngine.colSystem')}</th>
                </tr>
              </thead>
              <tbody>
                {fields.map((field, index) => (
                  <tr key={`${field.id}-${field.field_key}`}>
                    <td>
                      <div style={{ display: 'flex', gap: 4 }}>
                        <button
                          type="button"
                          className="btn btn-sm btn-secondary"
                          onClick={() => moveField(index, -1)}
                          disabled={index === 0}
                          aria-label={t('formEngine.moveUp')}
                        >
                          <BsArrowUp />
                        </button>
                        <button
                          type="button"
                          className="btn btn-sm btn-secondary"
                          onClick={() => moveField(index, 1)}
                          disabled={index === fields.length - 1}
                          aria-label={t('formEngine.moveDown')}
                        >
                          <BsArrowDown />
                        </button>
                      </div>
                    </td>
                    <td>
                      <code style={{ fontSize: '0.8rem' }}>{field.field_key}</code>
                    </td>
                    <td>
                      <input
                        className="form-control form-control-sm"
                        value={field.label_override ?? ''}
                        placeholder={field.field_label}
                        onChange={(e) =>
                          updateField(index, {
                            label_override: e.target.value || null,
                          })
                        }
                      />
                      <small style={{ color: 'var(--text-tertiary)' }}>
                        {t('formEngine.originalLabel')}: {field.field_label}
                      </small>
                    </td>
                    <td>
                      <button
                        type="button"
                        className="btn btn-sm btn-secondary"
                        onClick={() => updateField(index, { is_hidden: !field.is_hidden })}
                        title={
                          field.is_system
                            ? t('formEngine.hideSystemHint')
                            : t('formEngine.toggleHidden')
                        }
                      >
                        {field.is_hidden ? <BsEyeSlash /> : <BsEye />}
                      </button>
                    </td>
                    <td>
                      <select
                        className="form-control form-control-sm"
                        value={
                          field.is_required_override === null ||
                          field.is_required_override === undefined
                            ? ''
                            : field.is_required_override
                              ? '1'
                              : '0'
                        }
                        onChange={(e) => {
                          const v = e.target.value;
                          updateField(index, {
                            is_required_override: v === '' ? null : v === '1',
                          });
                        }}
                      >
                        <option value="">{t('formEngine.requiredDefault')}</option>
                        <option value="1">{t('formEngine.requiredYes')}</option>
                        <option value="0">{t('formEngine.requiredNo')}</option>
                      </select>
                    </td>
                    <td>
                      <select
                        className="form-control form-control-sm"
                        value={field.field_permission ?? ''}
                        onChange={(e) => {
                          const v = e.target.value;
                          updateField(index, {
                            field_permission:
                              v === '' ? null : (v as 'readonly' | 'hidden'),
                          });
                        }}
                      >
                        <option value="">{t('formEngine.permNormal')}</option>
                        <option value="readonly">{t('formEngine.permReadonly')}</option>
                        <option value="hidden">{t('formEngine.permHidden')}</option>
                      </select>
                    </td>
                    <td>
                      {field.is_system ? (
                        <span title={t('formEngine.systemNoDelete')}>
                          <BsShieldLock /> <BsLock />
                        </span>
                      ) : (
                        '—'
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <p style={{ marginTop: 'var(--space-3)', color: 'var(--text-tertiary)', fontSize: '0.875rem' }}>
        {t('formEngine.systemNoDeleteHelp')}
      </p>
    </div>
  );
};

export default FormLayoutEditorPage;
