import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';
import { recruitmentApi, lookupsApi, type LookupItem } from '@shared/services/api';
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
  form_id: string;
}

interface ApplicationFormOption {
  id: number;
  name: string;
  is_active?: boolean;
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
  };
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
  const [applicationForms, setApplicationForms] = useState<ApplicationFormOption[]>([]);
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
    form_id: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (!isOpen) return;

    Promise.all([
      lookupsApi.forType('work_type'),
      lookupsApi.forType('experience_level'),
      lookupsApi.forType('job_position_status'),
      recruitmentApi.forms.list({ per_page: 100 }),
    ])
      .then(([workRes, expRes, statusRes, formsRes]) => {
        setWorkTypeOptions(workRes.data.data ?? []);
        setExperienceOptions(expRes.data.data ?? []);
        setStatusOptions(statusRes.data.data ?? []);
        const formsPayload = formsRes.data.data;
        const rows: unknown[] = Array.isArray(formsPayload)
          ? formsPayload
          : formsPayload && typeof formsPayload === 'object' && Array.isArray(formsPayload.data)
            ? formsPayload.data
            : [];
        const options: ApplicationFormOption[] = [];
        for (const row of rows) {
          if (!row || typeof row !== 'object') continue;
          const id = 'id' in row ? row.id : undefined;
          const name = 'name' in row ? row.name : undefined;
          const isActive = 'is_active' in row ? row.is_active : undefined;
          if (typeof id !== 'number' || typeof name !== 'string') continue;
          if (isActive === false) continue;
          options.push({ id, name });
        }
        setApplicationForms(options);
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
        form_id: position.form_id ? String(position.form_id) : '',
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
        form_id: '',
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
        form_id: formData.form_id ? Number(formData.form_id) : null,
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
            value={formData.form_id}
            onChange={(v) => setFormData((prev) => ({ ...prev, form_id: v }))}
            options={applicationForms.map((f) => ({
              value: String(f.id),
              label: f.name,
            }))}
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
