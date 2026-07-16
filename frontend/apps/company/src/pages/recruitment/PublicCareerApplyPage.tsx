import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import {
  FormEngine,
  type FormDefinitionPayload,
  type FormEngineSubmitPayload,
} from '@shared/components';
import { publicApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';

const SYSTEM_COLUMN_KEYS = new Set([
  'first_name',
  'last_name',
  'email',
  'phone',
  'cv',
  'consent_kvkk',
]);

interface PublicFormPayload {
  has_custom_form: boolean;
  company: { slug: string; name: string };
  position: { slug: string; title: string; description?: string | null; location?: string | null };
  definition: FormDefinitionPayload;
}

function isPublicFormPayload(value: unknown): value is PublicFormPayload {
  if (!value || typeof value !== 'object') return false;
  if (!('has_custom_form' in value) || !('definition' in value) || !('company' in value) || !('position' in value)) {
    return false;
  }
  const def = value.definition;
  if (!def || typeof def !== 'object' || !('fields' in def)) return false;
  return Array.isArray(def.fields);
}

/**
 * Public kariyer başvurusu — FormEngine (form bağlıysa) veya B-2 sabit form.
 * Auth yok; submit B-2 public apply ucuna gider.
 */
const PublicCareerApplyPage: React.FC = () => {
  const { t } = useTranslation(['common', 'validation']);
  const { companySlug = '', positionSlug = '' } = useParams<{
    companySlug: string;
    positionSlug: string;
  }>();

  const [payload, setPayload] = useState<PublicFormPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [done, setDone] = useState(false);

  // Klasik B-2 form state (form bağlı değilse)
  const [classic, setClassic] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    consent_kvkk: false,
  });
  const [cvFile, setCvFile] = useState<File | null>(null);

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

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setLoadError(null);
      const res = await publicApi.jobs.form(companySlug, positionSlug);
      const dataUnknown: unknown = res.data.data;
      if (!isPublicFormPayload(dataUnknown)) {
        throw new Error(t('recruitment.careerLoadError'));
      }
      setPayload(dataUnknown);
    } catch (error: unknown) {
      setPayload(null);
      setLoadError(getErrorMessage(error, t('recruitment.careerLoadError')));
    } finally {
      setLoading(false);
    }
  }, [companySlug, positionSlug, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const appendApplyFormData = (fd: FormData, values: Record<string, unknown>) => {
    fd.append('first_name', String(values.first_name ?? ''));
    fd.append('last_name', String(values.last_name ?? ''));
    fd.append('email', String(values.email ?? ''));
    if (values.phone) fd.append('phone', String(values.phone));
    fd.append('consent_kvkk', values.consent_kvkk ? '1' : '0');
    if (values.cv instanceof File) {
      fd.append('cv', values.cv);
    }

    const formData: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(values)) {
      if (SYSTEM_COLUMN_KEYS.has(key)) continue;
      if (value instanceof File) continue;
      if (key === 'cover_letter' || !SYSTEM_COLUMN_KEYS.has(key)) {
        formData[key] = value;
      }
    }
    if (Object.keys(formData).length > 0) {
      fd.append('form_data', JSON.stringify(formData));
    }
  };

  const handleEngineSubmit = async (payload: FormEngineSubmitPayload) => {
    const merged: Record<string, unknown> = {
      ...payload.custom_fields,
      ...payload.system,
    };
    if (!merged.consent_kvkk) {
      toast.error(t('recruitment.consentRequired'));
      return;
    }
    setSaving(true);
    try {
      const fd = new FormData();
      appendApplyFormData(fd, merged);
      await publicApi.apply(companySlug, positionSlug, fd);
      toast.success(t('recruitment.careerSuccess'));
      setDone(true);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('recruitment.candidateAddFailed')));
    } finally {
      setSaving(false);
    }
  };

  const handleClassicSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!classic.consent_kvkk) {
      toast.error(t('recruitment.consentRequired'));
      return;
    }
    setSaving(true);
    try {
      const fd = new FormData();
      appendApplyFormData(fd, { ...classic, cv: cvFile });
      await publicApi.apply(companySlug, positionSlug, fd);
      toast.success(t('recruitment.careerSuccess'));
      setDone(true);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('recruitment.candidateAddFailed')));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="loading-screen">
        <div className="loading-spinner" style={{ width: 40, height: 40 }} />
      </div>
    );
  }

  if (loadError || !payload) {
    return (
      <div style={{ maxWidth: 560, margin: '3rem auto', padding: 'var(--sp-4)' }}>
        <h1 className="page-title">{t('recruitment.careerTitle')}</h1>
        <p style={{ color: 'var(--danger)' }}>{loadError ?? t('recruitment.careerInactive')}</p>
      </div>
    );
  }

  if (done) {
    return (
      <div style={{ maxWidth: 560, margin: '3rem auto', padding: 'var(--sp-4)' }}>
        <h1 className="page-title">{t('recruitment.careerSuccess')}</h1>
        <p>{payload.position.title}</p>
      </div>
    );
  }

  return (
    <div style={{ maxWidth: 720, margin: '2rem auto', padding: 'var(--sp-4)' }}>
      <p style={{ color: 'var(--text-secondary)', marginBottom: '0.25rem' }}>{payload.company.name}</p>
      <h1 className="page-title">{payload.position.title}</h1>
      {payload.position.location ? (
        <p style={{ color: 'var(--text-secondary)' }}>{payload.position.location}</p>
      ) : null}

      {payload.has_custom_form ? (
        <div style={{ marginTop: 'var(--sp-4)' }}>
          <FormEngine
            definition={payload.definition}
            messages={messages}
            onSubmit={handleEngineSubmit}
            submitLabel={saving ? t('formEngine.saving') : t('recruitment.careerSubmit')}
            loading={saving}
          />
        </div>
      ) : (
        <form onSubmit={(e) => void handleClassicSubmit(e)} style={{ marginTop: 'var(--sp-4)', display: 'flex', flexDirection: 'column', gap: 'var(--sp-3)' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--sp-3)' }}>
            <div>
              <label className="form-label">{t('recruitment.firstName')} *</label>
              <input
                className="form-input"
                value={classic.first_name}
                onChange={(e) => setClassic({ ...classic, first_name: e.target.value })}
                required
              />
            </div>
            <div>
              <label className="form-label">{t('recruitment.lastName')} *</label>
              <input
                className="form-input"
                value={classic.last_name}
                onChange={(e) => setClassic({ ...classic, last_name: e.target.value })}
                required
              />
            </div>
          </div>
          <div>
            <label className="form-label">{t('recruitment.email')} *</label>
            <input
              type="email"
              className="form-input"
              value={classic.email}
              onChange={(e) => setClassic({ ...classic, email: e.target.value })}
              required
            />
          </div>
          <div>
            <label className="form-label">{t('recruitment.phone')}</label>
            <input
              className="form-input"
              value={classic.phone}
              onChange={(e) => setClassic({ ...classic, phone: e.target.value })}
            />
          </div>
          <div>
            <label className="form-label">CV</label>
            <input
              type="file"
              accept=".pdf,.doc,.docx,application/pdf"
              onChange={(e) => setCvFile(e.target.files?.[0] ?? null)}
            />
          </div>
          <label style={{ display: 'flex', gap: 'var(--sp-2)', alignItems: 'flex-start' }}>
            <input
              type="checkbox"
              checked={classic.consent_kvkk}
              onChange={(e) => setClassic({ ...classic, consent_kvkk: e.target.checked })}
              required
            />
            <span>{t('recruitment.kvkkConsent')}</span>
          </label>
          <button type="submit" className="btn btn-primary" disabled={saving}>
            {saving ? t('formEngine.saving') : t('recruitment.careerSubmit')}
          </button>
        </form>
      )}
    </div>
  );
};

export default PublicCareerApplyPage;
