import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';
import { recruitmentApi, lookupsApi, formDefinitionsApi, type LookupItem } from '@shared/services/api';
import { Select } from '@shared/components';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';

interface JobPositionFormValues {
  id?: number;
  title: string;
  department: string;
  location: string;
  employment_type: string;
  experience_level: string;
  description: string;
  requirements: string;
  salary_min?: number;
  salary_max?: number;
  status: string;
  /** unified: "" | "legacy:{id}" | "definition:{id}" */
  form_binding: string;
}

interface FormBindingOption {
  value: string;
  label: string;
}

interface JobPositionFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  position?: {
    id?: number;
    title?: string;
    department?: string;
    location?: string;
    employment_type?: string;
    experience_level?: string;
    description?: string;
    requirements?: string;
    salary_min?: number;
    salary_max?: number;
    status?: string;
    form_id?: number | null;
    form_definition_id?: number | null;
  };
}

function bindingFromPosition(position?: JobPositionFormProps['position']): string {
  if (position?.form_definition_id) {
    return `definition:${position.form_definition_id}`;
  }
  if (position?.form_id) {
    return `legacy:${position.form_id}`;
  }
  return '';
}

const JobPositionForm: React.FC<JobPositionFormProps> = ({
  isOpen,
  onClose,
  onSuccess,
  position,
}) => {
  const { t } = useTranslation('common');
  const isEditing = !!position?.id;
  const [loading, setLoading] = useState(false);
  const [workTypeOptions, setWorkTypeOptions] = useState<LookupItem[]>([]);
  const [experienceOptions, setExperienceOptions] = useState<LookupItem[]>([]);
  const [statusOptions, setStatusOptions] = useState<LookupItem[]>([]);
  const [formBindingOptions, setFormBindingOptions] = useState<FormBindingOption[]>([]);
  const [formData, setFormData] = useState<JobPositionFormValues>({
    title: '',
    department: '',
    location: '',
    employment_type: 'full_time',
    experience_level: 'mid',
    description: '',
    requirements: '',
    salary_min: undefined,
    salary_max: undefined,
    status: 'draft',
    form_binding: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (!isOpen) return;

    Promise.all([
      lookupsApi.forType('work_type'),
      lookupsApi.forType('experience_level'),
      lookupsApi.forType('job_position_status'),
      recruitmentApi.forms.list({ per_page: 100 }),
      formDefinitionsApi.get('job_application'),
    ])
      .then(([workRes, expRes, statusRes, formsRes, defRes]) => {
        setWorkTypeOptions(workRes.data.data ?? []);
        setExperienceOptions(expRes.data.data ?? []);
        setStatusOptions(statusRes.data.data ?? []);

        const options: FormBindingOption[] = [];
        const def = defRes.data.data;
        if (def && typeof def === 'object' && typeof def.id === 'number') {
          const name = typeof def.name === 'string' ? def.name : t('recruitment.formEngineDefinition');
          options.push({
            value: `definition:${def.id}`,
            label: `${t('recruitment.formEnginePrefix')} ${name}`,
          });
        }

        const formsPayload = formsRes.data.data;
        const rows: unknown[] = Array.isArray(formsPayload)
          ? formsPayload
          : formsPayload && typeof formsPayload === 'object' && Array.isArray(formsPayload.data)
            ? formsPayload.data
            : [];
        for (const row of rows) {
          if (!row || typeof row !== 'object') continue;
          const id = 'id' in row ? row.id : undefined;
          const name = 'name' in row ? row.name : undefined;
          const isActive = 'is_active' in row ? row.is_active : undefined;
          if (typeof id !== 'number' || typeof name !== 'string') continue;
          if (isActive === false) continue;
          options.push({
            value: `legacy:${id}`,
            label: `${t('recruitment.legacyFormPrefix')} ${name}`,
          });
        }
        setFormBindingOptions(options);
      })
      .catch(() => toast.error(t('recruitment.lookupsLoadFailed')));

    if (position) {
      setFormData({
        title: position.title || '',
        department: position.department || '',
        location: position.location || '',
        employment_type: position.employment_type || 'full_time',
        experience_level: position.experience_level || 'mid',
        description: position.description || '',
        requirements: position.requirements || '',
        salary_min: position.salary_min,
        salary_max: position.salary_max,
        status: position.status || 'draft',
        form_binding: bindingFromPosition(position),
      });
    } else {
      setFormData({
        title: '',
        department: '',
        location: '',
        employment_type: 'full_time',
        experience_level: 'mid',
        description: '',
        requirements: '',
        salary_min: undefined,
        salary_max: undefined,
        status: 'draft',
        form_binding: '',
      });
    }
    setErrors({});
  }, [isOpen, position, t]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value, type } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: type === 'number' ? (value ? Number(value) : undefined) : value,
    }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  };

  const setSelectField = (name: keyof Pick<JobPositionFormValues, 'employment_type' | 'experience_level' | 'status'>, value: string) => {
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  };

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.title.trim()) {
      newErrors.title = 'Pozisyon adı gerekli';
    }

    if (!formData.department.trim()) {
      newErrors.department = 'Departman gerekli';
    }

    if (!formData.location.trim()) {
      newErrors.location = 'Lokasyon gerekli';
    }

    if (!formData.description.trim()) {
      newErrors.description = 'Açıklama gerekli';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    setLoading(true);
    try {
      const binding = formData.form_binding;
      let form_id: number | null = null;
      let form_definition_id: number | null = null;
      if (binding.startsWith('definition:')) {
        form_definition_id = Number(binding.slice('definition:'.length));
      } else if (binding.startsWith('legacy:')) {
        form_id = Number(binding.slice('legacy:'.length));
      }

      const payload = {
        title: formData.title,
        department: formData.department,
        location: formData.location,
        employment_type: formData.employment_type,
        experience_level: formData.experience_level,
        description: formData.description,
        requirements: formData.requirements,
        salary_min: formData.salary_min,
        salary_max: formData.salary_max,
        status: formData.status,
        form_id,
        form_definition_id,
      };
      if (isEditing && position?.id) {
        await recruitmentApi.positions.update(position.id, payload);
        toast.success('Pozisyon güncellendi');
      } else {
        await recruitmentApi.positions.create(payload);
        toast.success('Pozisyon oluşturuldu');
      }
      onSuccess();
      onClose();
    } catch (error: unknown) {
      const err =
        error && typeof error === 'object' && 'response' in error
          ? (error as { response?: { data?: { errors?: Record<string, string[]> } } })
          : null;
      if (err?.response?.data?.errors) {
        const backendErrors: Record<string, string> = {};
        Object.entries(err.response.data.errors).forEach(([key, msgs]) => {
          backendErrors[key] = msgs[0];
        });
        setErrors(backendErrors);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? 'Pozisyon Düzenle' : 'Yeni Pozisyon'}
      size="lg"
      footer={
        <>
          <button type="button" className="btn btn-secondary" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button type="button" className="btn btn-primary" onClick={handleSubmit} disabled={loading}>
            {loading ? 'Kaydediliyor...' : isEditing ? 'Güncelle' : 'Oluştur'}
          </button>
        </>
      }
    >
      <form onSubmit={handleSubmit}>
        {/* Title */}
        <div className="form-group">
          <label className="form-label">Pozisyon Adı *</label>
          <input
            type="text"
            name="title"
            className={`form-control ${errors.title ? 'is-invalid' : ''}`}
            value={formData.title}
            onChange={handleChange}
            placeholder="Örn: Frontend Developer"
          />
          {errors.title && <div className="form-error">{errors.title}</div>}
        </div>

        {/* Department & Location */}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
          <div className="form-group">
            <label className="form-label">Departman *</label>
            <input
              type="text"
              name="department"
              className={`form-control ${errors.department ? 'is-invalid' : ''}`}
              value={formData.department}
              onChange={handleChange}
              placeholder="Örn: Yazılım"
            />
            {errors.department && <div className="form-error">{errors.department}</div>}
          </div>

          <div className="form-group">
            <label className="form-label">Lokasyon *</label>
            <input
              type="text"
              name="location"
              className={`form-control ${errors.location ? 'is-invalid' : ''}`}
              value={formData.location}
              onChange={handleChange}
              placeholder="Örn: İstanbul / Remote"
            />
            {errors.location && <div className="form-error">{errors.location}</div>}
          </div>
        </div>

        {/* Employment Type & Experience Level */}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
          <div className="form-group">
            <label className="form-label">Çalışma Şekli</label>
            <Select
              value={formData.employment_type}
              onChange={(v) => setSelectField('employment_type', v)}
              options={workTypeOptions.map((opt) => ({
                value: opt.value,
                label: opt.label,
                color: opt.color,
              }))}
              placeholder="Seçiniz..."
              aria-label="Çalışma şekli"
            />
          </div>

          <div className="form-group">
            <label className="form-label">Deneyim Seviyesi</label>
            <Select
              value={formData.experience_level}
              onChange={(v) => setSelectField('experience_level', v)}
              options={experienceOptions.map((opt) => ({
                value: opt.value,
                label: opt.label,
                color: opt.color,
              }))}
              placeholder="Seçiniz..."
              aria-label="Deneyim seviyesi"
            />
          </div>
        </div>

        {/* Salary Range */}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
          <div className="form-group">
            <label className="form-label">Min. Maaş (₺)</label>
            <input
              type="number"
              name="salary_min"
              className="form-control"
              value={formData.salary_min || ''}
              onChange={handleChange}
              placeholder="Örn: 30000"
            />
          </div>

          <div className="form-group">
            <label className="form-label">Maks. Maaş (₺)</label>
            <input
              type="number"
              name="salary_max"
              className="form-control"
              value={formData.salary_max || ''}
              onChange={handleChange}
              placeholder="Örn: 50000"
            />
          </div>
        </div>

        {/* Description */}
        <div className="form-group">
          <label className="form-label">Açıklama *</label>
          <textarea
            name="description"
            className={`form-control ${errors.description ? 'is-invalid' : ''}`}
            value={formData.description}
            onChange={handleChange}
            rows={3}
            placeholder="Pozisyon hakkında detaylı açıklama..."
          />
          {errors.description && <div className="form-error">{errors.description}</div>}
        </div>

        {/* Requirements */}
        <div className="form-group">
          <label className="form-label">Gereksinimler</label>
          <textarea
            name="requirements"
            className="form-control"
            value={formData.requirements}
            onChange={handleChange}
            rows={3}
            placeholder="Aranan nitelikler..."
          />
        </div>

        {/* Status */}
        <div className="form-group">
          <label className="form-label">Durum</label>
          <Select
            value={formData.status}
            onChange={(v) => setSelectField('status', v)}
            options={statusOptions.map((opt) => ({
              value: opt.value,
              label: opt.label,
              color: opt.color,
            }))}
            placeholder="Seçiniz..."
            aria-label="Pozisyon durumu"
          />
        </div>

        <div className="form-group">
          <label className="form-label">{t('recruitment.applicationForm')}</label>
          <Select
            value={formData.form_binding}
            onChange={(v) => setFormData((prev) => ({ ...prev, form_binding: v }))}
            options={formBindingOptions}
            allowEmpty
            emptyLabel={t('recruitment.noApplicationForm')}
            clearable
            placeholder={t('recruitment.selectApplicationForm')}
            aria-label={t('recruitment.applicationForm')}
          />
          <p style={{ marginTop: '0.35rem', color: 'var(--text-secondary)', fontSize: 'var(--fs-caption)' }}>
            {t('recruitment.applicationFormHint')}
          </p>
        </div>
      </form>
    </Modal>
  );
};

export default JobPositionForm;
