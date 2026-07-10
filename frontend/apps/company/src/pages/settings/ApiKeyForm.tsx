import React, { useState, useEffect } from 'react';
import { apiKeysApi } from '@shared/services/api';
import type { ApiKey } from '@shared/types';
import toast from 'react-hot-toast';
import { BsSave } from 'react-icons/bs';
import { useFormValidation } from '@shared/hooks/useFormValidation';
import { required } from '@shared/utils/validation';

interface ApiKeyFormProps {
  apiKey?: ApiKey | null;
  onSuccess: (newKey?: string) => void;
  onClose: () => void;
}

const ApiKeyForm: React.FC<ApiKeyFormProps> = ({ apiKey, onSuccess, onClose }) => {
  const [saving, setSaving] = useState(false);

  const {
    values,
    errors,
    touched,
    handleChange,
    handleBlur,
    setValues,
    setFieldValue,
    validate,
  } = useFormValidation({
    initialValues: {
      name: '',
      description: '',
      expires_at: '',
      is_active: true,
    },
    schema: {
      name: [required()],
    },
  });

  useEffect(() => {
    if (apiKey) {
      setValues({
        name: apiKey.name || '',
        description: apiKey.description || '',
        expires_at: apiKey.expires_at ? new Date(apiKey.expires_at).toISOString().split('T')[0] : '',
        is_active: apiKey.is_active ?? true,
      });
    }
  }, [apiKey, setValues]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validate()) {
      return;
    }

    setSaving(true);
    try {
      const data: Record<string, unknown> = {
        name: values.name,
        description: values.description || null,
        is_active: values.is_active,
      };

      if (values.expires_at) {
        data.expires_at = values.expires_at;
      }

      if (apiKey) {
        await apiKeysApi.update(apiKey.id, data);
        toast.success('API anahtarı güncellendi');
        onSuccess();
      } else {
        const response = await apiKeysApi.create(data);
        toast.success('API anahtarı oluşturuldu');
        onSuccess(response.data.data.key);
      }
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err?.response?.data?.message || 'İşlem başarısız');
    } finally {
      setSaving(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
        <div className="form-group">
          <label className="form-label">
            Ad <span style={{ color: 'var(--danger)' }}>*</span>
          </label>
          <input
            type="text"
            name="name"
            className={`form-control ${errors.name && touched.name ? 'error' : ''}`}
            value={values.name}
            onChange={handleChange}
            onBlur={handleBlur}
            placeholder="Örn: Production API Key"
          />
          {errors.name && touched.name && (
            <div className="form-error">{errors.name}</div>
          )}
        </div>

        <div className="form-group">
          <label className="form-label">Açıklama</label>
          <textarea
            name="description"
            className="form-control"
            value={values.description}
            onChange={handleChange}
            rows={3}
            placeholder="Bu API anahtarının kullanım amacını açıklayın"
          />
        </div>

        <div className="form-group">
          <label className="form-label">Son Kullanma Tarihi (Opsiyonel)</label>
          <input
            type="date"
            name="expires_at"
            className="form-control"
            value={values.expires_at}
            onChange={handleChange}
            min={new Date().toISOString().split('T')[0]}
          />
        </div>

        <div className="form-group">
          <label className="form-checkbox">
            <input
              type="checkbox"
              name="is_active"
              checked={values.is_active}
              onChange={(e) => setFieldValue('is_active', e.target.checked)}
            />
            <span>Aktif</span>
          </label>
        </div>
      </div>

      <div style={{ marginTop: '1.5rem', display: 'flex', justifyContent: 'flex-end', gap: '0.5rem' }}>
        <button type="button" className="btn btn-ghost" onClick={onClose} disabled={saving}>
          İptal
        </button>
        <button type="submit" className="btn btn-primary" disabled={saving}>
          {saving ? (
            <>
              <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
              Kaydediliyor...
            </>
          ) : (
            <>
              <BsSave /> Kaydet
            </>
          )}
        </button>
      </div>
    </form>
  );
};

export default ApiKeyForm;

