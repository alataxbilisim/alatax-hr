import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';
import { leavesApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { Select } from '@shared/components';
import toast from 'react-hot-toast';

interface LeaveType {
  id?: number;
  name: string;
  code?: string;
  description?: string;
  is_paid: boolean;
  default_days: number;
  requires_document: boolean;
  gender_restriction: string;
  max_days_at_once?: number;
  min_days_notice: number;
  is_active: boolean;
}

interface LeaveTypeFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  leaveType?: LeaveType;
}

const LeaveTypeForm: React.FC<LeaveTypeFormProps> = ({
  isOpen,
  onClose,
  onSuccess,
  leaveType,
}) => {
  const isEditing = !!leaveType?.id;
  const [loading, setLoading] = useState(false);
  const [genderOptions, setGenderOptions] = useState<LookupItem[]>([]);
  const [formData, setFormData] = useState<LeaveType>({
    name: '',
    code: '',
    description: '',
    is_paid: true,
    default_days: 14,
    requires_document: false,
    gender_restriction: 'all',
    max_days_at_once: undefined,
    min_days_notice: 0,
    is_active: true,
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (isOpen) {
      loadGenderLookups();
      if (leaveType) {
        setFormData({
          name: leaveType.name || '',
          code: leaveType.code || '',
          description: leaveType.description || '',
          is_paid: leaveType.is_paid ?? true,
          default_days: leaveType.default_days || 14,
          requires_document: leaveType.requires_document || false,
          gender_restriction: leaveType.gender_restriction || 'all',
          max_days_at_once: leaveType.max_days_at_once,
          min_days_notice: leaveType.min_days_notice || 0,
          is_active: leaveType.is_active ?? true,
        });
      } else {
        setFormData({
          name: '',
          code: '',
          description: '',
          is_paid: true,
          default_days: 14,
          requires_document: false,
          gender_restriction: 'all',
          max_days_at_once: undefined,
          min_days_notice: 0,
          is_active: true,
        });
      }
      setErrors({});
    }
  }, [isOpen, leaveType]);

  const loadGenderLookups = async () => {
    try {
      const response = await lookupsApi.forType('leave_gender_restriction');
      setGenderOptions(response.data.data ?? []);
    } catch {
      console.error('Cinsiyet kısıtlaması lookup yüklenemedi');
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

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.name.trim()) {
      newErrors.name = 'İzin türü adı gerekli';
    }

    if (formData.default_days < 0) {
      newErrors.default_days = 'Varsayılan gün 0 veya üzeri olmalı';
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
        code: formData.code || null,
        max_days_at_once: formData.max_days_at_once || null,
      };

      if (isEditing && leaveType?.id) {
        await leavesApi.types.update(leaveType.id, payload);
        toast.success('İzin türü güncellendi');
      } else {
        await leavesApi.types.create(payload);
        toast.success('İzin türü oluşturuldu');
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
      title={isEditing ? 'İzin Türü Düzenle' : 'Yeni İzin Türü'}
      size="md"
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
      <form onSubmit={handleSubmit}>
        {/* Name & Code */}
        <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '1rem' }}>
          <div className="form-group">
            <label className="form-label">İzin Türü Adı *</label>
            <input
              type="text"
              name="name"
              className={`form-control ${errors.name ? 'is-invalid' : ''}`}
              value={formData.name}
              onChange={handleChange}
              placeholder="Örn: Yıllık İzin"
            />
            {errors.name && <div className="form-error">{errors.name}</div>}
          </div>

          <div className="form-group">
            <label className="form-label">Kod</label>
            <input
              type="text"
              name="code"
              className="form-control"
              value={formData.code}
              onChange={handleChange}
              placeholder="Örn: YI"
              maxLength={10}
            />
          </div>
        </div>

        {/* Days */}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '1rem' }}>
          <div className="form-group">
            <label className="form-label">Varsayılan Gün *</label>
            <input
              type="number"
              name="default_days"
              className={`form-control ${errors.default_days ? 'is-invalid' : ''}`}
              value={formData.default_days}
              onChange={handleChange}
              min={0}
            />
            {errors.default_days && <div className="form-error">{errors.default_days}</div>}
          </div>

          <div className="form-group">
            <label className="form-label">Maks. Art Arda Gün</label>
            <input
              type="number"
              name="max_days_at_once"
              className="form-control"
              value={formData.max_days_at_once ?? ''}
              onChange={handleChange}
              min={1}
              placeholder="Sınırsız"
            />
          </div>

          <div className="form-group">
            <label className="form-label">Min. Ön Bildirim (Gün)</label>
            <input
              type="number"
              name="min_days_notice"
              className="form-control"
              value={formData.min_days_notice}
              onChange={handleChange}
              min={0}
            />
          </div>
        </div>

        {/* Gender Restriction */}
        <div className="form-group">
          <label className="form-label">Cinsiyet Kısıtlaması</label>
          <Select
            value={formData.gender_restriction}
            onChange={(v) => setFormData((prev) => ({ ...prev, gender_restriction: v || 'all' }))}
            options={genderOptions.map((opt) => ({
              value: opt.value,
              label: opt.label,
              color: opt.color,
            }))}
            placeholder="Seçiniz..."
            aria-label="Cinsiyet kısıtlaması"
          />
        </div>

        {/* Options */}
        <div className="form-group">
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
              <input
                type="checkbox"
                name="is_paid"
                checked={formData.is_paid}
                onChange={handleChange}
                style={{ width: 16, height: 16, accentColor: 'var(--primary)' }}
              />
              <span style={{ fontSize: '0.8125rem', color: 'var(--text-primary)' }}>Ücretli İzin</span>
            </label>

            <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
              <input
                type="checkbox"
                name="requires_document"
                checked={formData.requires_document}
                onChange={handleChange}
                style={{ width: 16, height: 16, accentColor: 'var(--primary)' }}
              />
              <span style={{ fontSize: '0.8125rem', color: 'var(--text-primary)' }}>Belge Gerektirir</span>
            </label>

            <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
              <input
                type="checkbox"
                name="is_active"
                checked={formData.is_active}
                onChange={handleChange}
                style={{ width: 16, height: 16, accentColor: 'var(--primary)' }}
              />
              <span style={{ fontSize: '0.8125rem', color: 'var(--text-primary)' }}>Aktif</span>
            </label>
          </div>
        </div>

        {/* Description */}
        <div className="form-group">
          <label className="form-label">Açıklama</label>
          <textarea
            name="description"
            className="form-control"
            value={formData.description}
            onChange={handleChange}
            rows={2}
            placeholder="İzin türü hakkında açıklama (isteğe bağlı)"
          />
        </div>
      </form>
    </Modal>
  );
};

export default LeaveTypeForm;
