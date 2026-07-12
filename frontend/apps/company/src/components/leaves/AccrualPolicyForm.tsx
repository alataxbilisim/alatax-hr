import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';
import { leavesApi } from '@shared/services/api';
import { Select } from '@shared/components';
import toast from 'react-hot-toast';
import { BsPlus, BsTrash } from 'react-icons/bs';

interface TenureRule {
  years: number;
  days: number;
}

interface LeaveType {
  id: number;
  name: string;
  code?: string;
}

interface AccrualPolicy {
  id?: number;
  name: string;
  description?: string;
  leave_type_id: number;
  accrual_type: 'annual' | 'monthly' | 'per_pay_period' | 'hourly' | 'custom';
  accrual_rate: number;
  max_balance?: number;
  min_balance: number;
  tenure_rules?: TenureRule[];
  allow_carryover: boolean;
  max_carryover_days?: number;
  carryover_expiry_date?: string;
  allow_encashment: boolean;
  max_encashment_days?: number;
  encashment_rate: number;
  waiting_period_days: number;
  prorate_first_year: boolean;
  is_active: boolean;
}

interface AccrualPolicyFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  policy?: AccrualPolicy;
}

const ACCRUAL_TYPE_OPTIONS = [
  { value: 'annual', label: 'Yıllık - Yılda bir kez verilir' },
  { value: 'monthly', label: 'Aylık - Her ay birikim olur' },
  { value: 'per_pay_period', label: 'Dönemsel - Maaş dönemi başına' },
  { value: 'hourly', label: 'Saatlik - Çalışılan saat başına' },
  { value: 'custom', label: 'Özel - Manuel yönetim' },
];

const AccrualPolicyForm: React.FC<AccrualPolicyFormProps> = ({
  isOpen,
  onClose,
  onSuccess,
  policy,
}) => {
  const isEditing = !!policy?.id;
  const [loading, setLoading] = useState(false);
  const [leaveTypes, setLeaveTypes] = useState<LeaveType[]>([]);
  const [formData, setFormData] = useState<AccrualPolicy>({
    name: '',
    description: '',
    leave_type_id: 0,
    accrual_type: 'annual',
    accrual_rate: 14,
    max_balance: undefined,
    min_balance: 0,
    tenure_rules: [],
    allow_carryover: true,
    max_carryover_days: undefined,
    carryover_expiry_date: '',
    allow_encashment: false,
    max_encashment_days: undefined,
    encashment_rate: 1,
    waiting_period_days: 0,
    prorate_first_year: true,
    is_active: true,
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (isOpen) {
      loadLeaveTypes();
      if (policy) {
        setFormData({
          name: policy.name || '',
          description: policy.description || '',
          leave_type_id: policy.leave_type_id || 0,
          accrual_type: policy.accrual_type || 'annual',
          accrual_rate: policy.accrual_rate || 14,
          max_balance: policy.max_balance,
          min_balance: policy.min_balance || 0,
          tenure_rules: policy.tenure_rules || [],
          allow_carryover: policy.allow_carryover ?? true,
          max_carryover_days: policy.max_carryover_days,
          carryover_expiry_date: policy.carryover_expiry_date || '',
          allow_encashment: policy.allow_encashment || false,
          max_encashment_days: policy.max_encashment_days,
          encashment_rate: policy.encashment_rate || 1,
          waiting_period_days: policy.waiting_period_days || 0,
          prorate_first_year: policy.prorate_first_year ?? true,
          is_active: policy.is_active ?? true,
        });
      } else {
        setFormData({
          name: '',
          description: '',
          leave_type_id: 0,
          accrual_type: 'annual',
          accrual_rate: 14,
          max_balance: undefined,
          min_balance: 0,
          tenure_rules: [],
          allow_carryover: true,
          max_carryover_days: undefined,
          carryover_expiry_date: '',
          allow_encashment: false,
          max_encashment_days: undefined,
          encashment_rate: 1,
          waiting_period_days: 0,
          prorate_first_year: true,
          is_active: true,
        });
      }
      setErrors({});
    }
  }, [isOpen, policy]);

  const loadLeaveTypes = async () => {
    try {
      const response = await leavesApi.types.list();
      const data = response.data.data;
      setLeaveTypes(Array.isArray(data) ? data : data?.data || []);
    } catch {
      console.error('İzin türleri yüklenemedi');
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value, type } = e.target;
    const checked = (e.target as HTMLInputElement).checked;

    setFormData((prev) => ({
      ...prev,
      [name]: type === 'checkbox' 
        ? checked 
        : type === 'number' 
          ? (value === '' ? undefined : Number(value))
          : value,
    }));

    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  };

  const addTenureRule = () => {
    setFormData((prev) => ({
      ...prev,
      tenure_rules: [...(prev.tenure_rules || []), { years: 0, days: 0 }],
    }));
  };

  const updateTenureRule = (index: number, field: 'years' | 'days', value: number) => {
    setFormData((prev) => {
      const rules = [...(prev.tenure_rules || [])];
      rules[index] = { ...rules[index], [field]: value };
      return { ...prev, tenure_rules: rules };
    });
  };

  const removeTenureRule = (index: number) => {
    setFormData((prev) => ({
      ...prev,
      tenure_rules: (prev.tenure_rules || []).filter((_, i) => i !== index),
    }));
  };

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.name.trim()) {
      newErrors.name = 'Politika adı gerekli';
    }

    if (!formData.leave_type_id) {
      newErrors.leave_type_id = 'İzin türü seçin';
    }

    if (formData.accrual_rate < 0) {
      newErrors.accrual_rate = 'Hakediş oranı 0 veya üzeri olmalı';
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
        ...formData,
        tenure_rules: formData.tenure_rules?.length ? formData.tenure_rules : null,
        max_balance: formData.max_balance || null,
        max_carryover_days: formData.max_carryover_days || null,
        max_encashment_days: formData.max_encashment_days || null,
        carryover_expiry_date: formData.carryover_expiry_date || null,
      };

      if (isEditing && policy?.id) {
        await leavesApi.accrualPolicies.update(policy.id, payload);
        toast.success('Politika güncellendi');
      } else {
        await leavesApi.accrualPolicies.create(payload);
        toast.success('Politika oluşturuldu');
      }

      onSuccess();
      onClose();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { errors?: Record<string, string[]> } } };
      if (err.response?.data?.errors) {
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
      title={isEditing ? 'Hakediş Politikası Düzenle' : 'Yeni Hakediş Politikası'}
      size="lg"
      footer={
        <>
          <button className="btn btn-secondary" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button className="btn btn-primary" onClick={handleSubmit} disabled={loading}>
            {loading ? 'Kaydediliyor...' : isEditing ? 'Güncelle' : 'Oluştur'}
          </button>
        </>
      }
    >
      <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
        {/* Basic Info */}
        <div className="row">
          <div className="col-md-6">
            <div className="form-group">
              <label className="form-label">Politika Adı *</label>
              <input
                type="text"
                name="name"
                className={`form-control ${errors.name ? 'is-invalid' : ''}`}
                value={formData.name}
                onChange={handleChange}
                placeholder="Örn: Standart Yıllık İzin Politikası"
              />
              {errors.name && <div className="form-error">{errors.name}</div>}
            </div>
          </div>
          <div className="col-md-6">
            <div className="form-group">
              <label className="form-label">İzin Türü *</label>
              <Select
                value={formData.leave_type_id ? String(formData.leave_type_id) : ''}
                onChange={(v) => {
                  setFormData((prev) => ({ ...prev, leave_type_id: v ? Number(v) : 0 }));
                  if (errors.leave_type_id) {
                    setErrors((prev) => ({ ...prev, leave_type_id: '' }));
                  }
                }}
                options={leaveTypes.map((type) => ({
                  value: String(type.id),
                  label: type.code ? `${type.name} (${type.code})` : type.name,
                }))}
                allowEmpty
                placeholder="Seçin..."
                error={!!errors.leave_type_id}
                aria-label="İzin türü"
              />
              {errors.leave_type_id && <div className="form-error">{errors.leave_type_id}</div>}
            </div>
          </div>
        </div>

        <div className="form-group">
          <label className="form-label">Açıklama</label>
          <textarea
            name="description"
            className="form-control"
            value={formData.description}
            onChange={handleChange}
            rows={2}
            placeholder="Politika hakkında açıklama"
          />
        </div>

        {/* Accrual Settings */}
        <div style={{ borderTop: '1px solid var(--border-color)', paddingTop: '1rem' }}>
          <h4 style={{ fontSize: '0.875rem', fontWeight: 600, marginBottom: '0.75rem' }}>Hakediş Ayarları</h4>
          <div className="row">
            <div className="col-md-6">
              <div className="form-group">
                <label className="form-label">Hakediş Tipi *</label>
                <Select
                  value={formData.accrual_type}
                  onChange={(v) =>
                    setFormData((prev) => ({
                      ...prev,
                      accrual_type: (v || 'annual') as AccrualPolicy['accrual_type'],
                    }))
                  }
                  options={ACCRUAL_TYPE_OPTIONS}
                  placeholder="Seçiniz..."
                  aria-label="Hakediş tipi"
                />
              </div>
            </div>
            <div className="col-md-6">
              <div className="form-group">
                <label className="form-label">Hakediş Miktarı (Gün) *</label>
                <input
                  type="number"
                  name="accrual_rate"
                  className={`form-control ${errors.accrual_rate ? 'is-invalid' : ''}`}
                  value={formData.accrual_rate}
                  onChange={handleChange}
                  min={0}
                  step={0.5}
                />
                {errors.accrual_rate && <div className="form-error">{errors.accrual_rate}</div>}
              </div>
            </div>
          </div>

          <div className="row">
            <div className="col-md-4">
              <div className="form-group">
                <label className="form-label">Maks. Bakiye (Gün)</label>
                <input
                  type="number"
                  name="max_balance"
                  className="form-control"
                  value={formData.max_balance ?? ''}
                  onChange={handleChange}
                  min={0}
                  placeholder="Sınırsız"
                />
              </div>
            </div>
            <div className="col-md-4">
              <div className="form-group">
                <label className="form-label">Min. Bakiye (Gün)</label>
                <input
                  type="number"
                  name="min_balance"
                  className="form-control"
                  value={formData.min_balance}
                  onChange={handleChange}
                />
                <small className="text-muted">Negatif değer eksi bakiyeye izin verir</small>
              </div>
            </div>
            <div className="col-md-4">
              <div className="form-group">
                <label className="form-label">Bekleme Süresi (Gün)</label>
                <input
                  type="number"
                  name="waiting_period_days"
                  className="form-control"
                  value={formData.waiting_period_days}
                  onChange={handleChange}
                  min={0}
                />
                <small className="text-muted">İşe başladıktan sonra</small>
              </div>
            </div>
          </div>
        </div>

        {/* Tenure Rules */}
        <div style={{ borderTop: '1px solid var(--border-color)', paddingTop: '1rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '0.75rem' }}>
            <h4 style={{ fontSize: '0.875rem', fontWeight: 600, margin: 0 }}>Kıdem Kuralları</h4>
            <button type="button" className="btn btn-secondary btn-sm" onClick={addTenureRule}>
              <BsPlus /> Kural Ekle
            </button>
          </div>
          <p style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.75rem' }}>
            Çalışanların kıdemine göre farklı hakediş miktarları tanımlayın
          </p>
          {formData.tenure_rules && formData.tenure_rules.length > 0 && (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
              {formData.tenure_rules.map((rule, index) => (
                <div key={index} style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <input
                    type="number"
                    className="form-control"
                    style={{ width: '80px' }}
                    value={rule.years}
                    onChange={(e) => updateTenureRule(index, 'years', Number(e.target.value))}
                    min={0}
                    placeholder="Yıl"
                  />
                  <span style={{ color: 'var(--text-tertiary)' }}>yıl ve üzeri →</span>
                  <input
                    type="number"
                    className="form-control"
                    style={{ width: '80px' }}
                    value={rule.days}
                    onChange={(e) => updateTenureRule(index, 'days', Number(e.target.value))}
                    min={0}
                    step={0.5}
                    placeholder="Gün"
                  />
                  <span style={{ color: 'var(--text-tertiary)' }}>gün</span>
                  <button
                    type="button"
                    className="btn btn-ghost btn-icon btn-sm"
                    onClick={() => removeTenureRule(index)}
                    style={{ color: 'var(--danger)' }}
                  >
                    <BsTrash />
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Carryover Settings */}
        <div style={{ borderTop: '1px solid var(--border-color)', paddingTop: '1rem' }}>
          <h4 style={{ fontSize: '0.875rem', fontWeight: 600, marginBottom: '0.75rem' }}>Devir Ayarları</h4>
          <div className="form-check mb-2">
            <input
              type="checkbox"
              className="form-check-input"
              id="allow_carryover"
              name="allow_carryover"
              checked={formData.allow_carryover}
              onChange={handleChange}
            />
            <label className="form-check-label" htmlFor="allow_carryover">Kullanılmayan izinlerin devrine izin ver</label>
          </div>
          {formData.allow_carryover && (
            <div className="row">
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Maks. Devir (Gün)</label>
                  <input
                    type="number"
                    name="max_carryover_days"
                    className="form-control"
                    value={formData.max_carryover_days ?? ''}
                    onChange={handleChange}
                    min={0}
                    placeholder="Sınırsız"
                  />
                </div>
              </div>
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Devir Son Kullanma Tarihi</label>
                  <input
                    type="date"
                    name="carryover_expiry_date"
                    className="form-control"
                    value={formData.carryover_expiry_date}
                    onChange={handleChange}
                  />
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Other Options */}
        <div style={{ borderTop: '1px solid var(--border-color)', paddingTop: '1rem' }}>
          <h4 style={{ fontSize: '0.875rem', fontWeight: 600, marginBottom: '0.75rem' }}>Diğer Seçenekler</h4>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
            <div className="form-check">
              <input
                type="checkbox"
                className="form-check-input"
                id="prorate_first_year"
                name="prorate_first_year"
                checked={formData.prorate_first_year}
                onChange={handleChange}
              />
              <label className="form-check-label" htmlFor="prorate_first_year">
                İlk yıl orantılı hesapla (işe başlama tarihine göre)
              </label>
            </div>
            <div className="form-check">
              <input
                type="checkbox"
                className="form-check-input"
                id="allow_encashment"
                name="allow_encashment"
                checked={formData.allow_encashment}
                onChange={handleChange}
              />
              <label className="form-check-label" htmlFor="allow_encashment">
                İzin satışına izin ver (encashment)
              </label>
            </div>
            <div className="form-check">
              <input
                type="checkbox"
                className="form-check-input"
                id="is_active"
                name="is_active"
                checked={formData.is_active}
                onChange={handleChange}
              />
              <label className="form-check-label" htmlFor="is_active">
                Aktif
              </label>
            </div>
          </div>
        </div>
      </form>
    </Modal>
  );
};

export default AccrualPolicyForm;

