import React, { useState, useEffect } from 'react';
import { Modal } from '../ui';
import { leavesApi, customFieldsApi } from '@shared/services/api';
import { Select } from '@shared/components';
import toast from 'react-hot-toast';
import { BsUpload } from 'react-icons/bs';

interface LeaveType {
  id: number;
  name: string;
  default_days: number;
  requires_document: boolean;
  is_active: boolean;
}

interface CustomField {
  id: number;
  name: string;
  label: string;
  type: 'text' | 'number' | 'date' | 'select' | 'checkbox' | 'textarea' | 'email' | 'phone' | 'url';
  options?: string[];
  is_required: boolean;
  placeholder?: string;
  default_value?: string;
}

interface LeaveRequestFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const LeaveRequestForm: React.FC<LeaveRequestFormProps> = ({
  isOpen,
  onClose,
  onSuccess,
}) => {
  const [loading, setLoading] = useState(false);
  const [leaveTypes, setLeaveTypes] = useState<LeaveType[]>([]);
  const [customFields, setCustomFields] = useState<CustomField[]>([]);
  const [formData, setFormData] = useState({
    leave_type_id: '',
    start_date: '',
    end_date: '',
    reason: '',
  });
  const [customFieldValues, setCustomFieldValues] = useState<Record<string, unknown>>({});
  const [file, setFile] = useState<File | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (isOpen) {
      loadLeaveTypes();
      loadCustomFields();
      setFormData({
        leave_type_id: '',
        start_date: '',
        end_date: '',
        reason: '',
      });
      setCustomFieldValues({});
      setFile(null);
      setErrors({});
    }
  }, [isOpen]);

  const loadLeaveTypes = async () => {
    try {
      const response = await leavesApi.types.list({ is_active: true });
      const data = response.data.data;
      setLeaveTypes(Array.isArray(data) ? data : data?.data || []);
    } catch {
      toast.error('İzin türleri yüklenemedi');
    }
  };

  const loadCustomFields = async () => {
    try {
      const response = await customFieldsApi.getAll('leave_request');
      const fields = response.data.data || [];
      setCustomFields(fields.filter((f: CustomField & { is_active?: boolean }) => f.is_active !== false));
      
      // Set default values
      const defaults: Record<string, unknown> = {};
      fields.forEach((field: CustomField) => {
        if (field.default_value) {
          defaults[field.name] = field.default_value;
        }
      });
      setCustomFieldValues(defaults);
    } catch {
      console.error('Özel alanlar yüklenemedi');
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  };

  const handleCustomFieldChange = (field: CustomField, value: unknown) => {
    setCustomFieldValues((prev) => ({ ...prev, [field.name]: value }));
    if (errors[`custom_${field.name}`]) {
      setErrors((prev) => ({ ...prev, [`custom_${field.name}`]: '' }));
    }
  };

  const handleDocumentChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selected = e.target.files?.[0] || null;
    setFile(selected);
  };

  const calculateDays = () => {
    if (!formData.start_date || !formData.end_date) return 0;
    const start = new Date(formData.start_date);
    const end = new Date(formData.end_date);
    const diff = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1;
    return diff > 0 ? diff : 0;
  };

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.leave_type_id) {
      newErrors.leave_type_id = 'İzin türü seçin';
    }

    if (!formData.start_date) {
      newErrors.start_date = 'Başlangıç tarihi gerekli';
    }

    if (!formData.end_date) {
      newErrors.end_date = 'Bitiş tarihi gerekli';
    }

    if (formData.start_date && formData.end_date) {
      const start = new Date(formData.start_date);
      const end = new Date(formData.end_date);
      if (end < start) {
        newErrors.end_date = 'Bitiş tarihi başlangıçtan önce olamaz';
      }
    }

    // Check document requirement
    const selectedType = leaveTypes.find(t => t.id === Number(formData.leave_type_id));
    if (selectedType?.requires_document && !file) {
      newErrors.document = 'Bu izin türü için belge gerekli';
    }

    // Validate required custom fields
    customFields.forEach((field) => {
      if (field.is_required) {
        const value = customFieldValues[field.name];
        if (value === undefined || value === null || value === '') {
          newErrors[`custom_${field.name}`] = `${field.label} gerekli`;
        }
      }
    });

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validate()) return;

    setLoading(true);
    try {
      const formDataToSend = new FormData();
      formDataToSend.append('leave_type_id', formData.leave_type_id.toString());
      formDataToSend.append('start_date', formData.start_date);
      formDataToSend.append('end_date', formData.end_date);
      formDataToSend.append('reason', formData.reason || '');

      if (file) {
        formDataToSend.append('document', file);
      }

      // Add custom field values
      if (Object.keys(customFieldValues).length > 0) {
        formDataToSend.append('custom_fields', JSON.stringify(customFieldValues));
      }

      await leavesApi.requests.create(formDataToSend);
      toast.success('İzin talebi oluşturuldu');
      onSuccess();
      onClose();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } };
      if (err.response?.data?.errors) {
        const backendErrors: Record<string, string> = {};
        Object.entries(err.response.data.errors).forEach(([key, msgs]) => {
          backendErrors[key] = msgs[0];
        });
        setErrors(backendErrors);
      } else if (err.response?.data?.message) {
        toast.error(err.response.data.message);
      }
    } finally {
      setLoading(false);
    }
  };

  const renderCustomField = (field: CustomField) => {
    const value = customFieldValues[field.name];
    const error = errors[`custom_${field.name}`];

    switch (field.type) {
      case 'text':
      case 'email':
      case 'phone':
      case 'url':
        return (
          <input
            type={field.type === 'phone' ? 'tel' : field.type}
            className={`form-control ${error ? 'is-invalid' : ''}`}
            value={(value as string) || ''}
            onChange={(e) => handleCustomFieldChange(field, e.target.value)}
            placeholder={field.placeholder}
          />
        );
      
      case 'number':
        return (
          <input
            type="number"
            className={`form-control ${error ? 'is-invalid' : ''}`}
            value={(value as number) ?? ''}
            onChange={(e) => handleCustomFieldChange(field, e.target.value ? Number(e.target.value) : '')}
            placeholder={field.placeholder}
          />
        );
      
      case 'date':
        return (
          <input
            type="date"
            className={`form-control ${error ? 'is-invalid' : ''}`}
            value={(value as string) || ''}
            onChange={(e) => handleCustomFieldChange(field, e.target.value)}
          />
        );
      
      case 'select':
        return (
          <Select
            value={(value as string) || ''}
            onChange={(v) => handleCustomFieldChange(field, v)}
            options={(field.options || []).map((opt) => ({ value: opt, label: opt }))}
            allowEmpty
            placeholder="Seçin..."
            error={!!error}
            aria-label={field.label}
          />
        );
      
      case 'checkbox':
        return (
          <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
            <input
              type="checkbox"
              checked={!!value}
              onChange={(e) => handleCustomFieldChange(field, e.target.checked)}
              style={{ width: 16, height: 16, accentColor: 'var(--primary)' }}
            />
            <span style={{ fontSize: '0.8125rem' }}>{field.label}</span>
          </label>
        );
      
      case 'textarea':
        return (
          <textarea
            className={`form-control ${error ? 'is-invalid' : ''}`}
            value={(value as string) || ''}
            onChange={(e) => handleCustomFieldChange(field, e.target.value)}
            placeholder={field.placeholder}
            rows={2}
          />
        );
      
      default:
        return (
          <input
            type="text"
            className={`form-control ${error ? 'is-invalid' : ''}`}
            value={(value as string) || ''}
            onChange={(e) => handleCustomFieldChange(field, e.target.value)}
            placeholder={field.placeholder}
          />
        );
    }
  };

  const days = calculateDays();
  const selectedType = leaveTypes.find(t => t.id === Number(formData.leave_type_id));

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Yeni İzin Talebi"
      size="md"
      footer={
        <>
          <button className="btn btn-secondary" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button className="btn btn-primary" onClick={handleSubmit} disabled={loading}>
            {loading ? 'Gönderiliyor...' : 'Talep Oluştur'}
          </button>
        </>
      }
    >
      <form onSubmit={handleSubmit}>
        {/* Leave Type */}
        <div className="form-group">
          <label className="form-label">İzin Türü *</label>
          <Select
            value={formData.leave_type_id}
            onChange={(v) => {
              setFormData((prev) => ({ ...prev, leave_type_id: v }));
              if (errors.leave_type_id) {
                setErrors((prev) => ({ ...prev, leave_type_id: '' }));
              }
            }}
            options={leaveTypes
              .filter((t) => t.is_active)
              .map((type) => ({
                value: String(type.id),
                label: `${type.name} (${type.default_days} gün)`,
              }))}
            allowEmpty
            placeholder="Seçin..."
            error={!!errors.leave_type_id}
            aria-label="İzin türü"
          />
          {errors.leave_type_id && <div className="form-error">{errors.leave_type_id}</div>}
        </div>

        {/* Dates */}
        <div className="form-grid form-grid-2">
          <div className="form-group">
            <label className="form-label">Başlangıç Tarihi *</label>
            <input
              type="date"
              name="start_date"
              className={`form-control ${errors.start_date ? 'is-invalid' : ''}`}
              value={formData.start_date}
              onChange={handleChange}
            />
            {errors.start_date && <div className="form-error">{errors.start_date}</div>}
          </div>

          <div className="form-group">
            <label className="form-label">Bitiş Tarihi *</label>
            <input
              type="date"
              name="end_date"
              className={`form-control ${errors.end_date ? 'is-invalid' : ''}`}
              value={formData.end_date}
              onChange={handleChange}
            />
            {errors.end_date && <div className="form-error">{errors.end_date}</div>}
          </div>
        </div>

        {/* Days info */}
        {days > 0 && (
          <div
            style={{
              background: 'var(--primary-soft)',
              border: '1px solid var(--primary)',
              borderRadius: 'var(--radius-md)',
              padding: '0.625rem 0.875rem',
              marginBottom: '1rem',
              fontSize: '0.8125rem',
              color: 'var(--primary)',
            }}
          >
            Toplam <strong>{days} gün</strong> izin talep ediyorsunuz.
          </div>
        )}

        {/* Reason */}
        <div className="form-group">
          <label className="form-label">Açıklama</label>
          <textarea
            name="reason"
            className={`form-control ${errors.reason ? 'is-invalid' : ''}`}
            value={formData.reason}
            onChange={handleChange}
            rows={2}
            placeholder="İzin talebinizin nedenini yazın..."
          />
          {errors.reason && <div className="form-error">{errors.reason}</div>}
        </div>

        {/* Document Upload */}
        {selectedType?.requires_document && (
          <div className="form-group">
            <label className="form-label">Belge *</label>
            <div
              style={{
                border: `1px dashed ${errors.document ? 'var(--danger)' : 'var(--border-color)'}`,
                borderRadius: 'var(--radius-md)',
                padding: '1rem',
                textAlign: 'center',
                cursor: 'pointer',
                background: 'var(--bg-secondary)',
              }}
              onClick={() => document.getElementById('leave-document-input')?.click()}
            >
              <input
                id="leave-document-input"
                type="file"
                style={{ display: 'none' }}
                onChange={handleDocumentChange}
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
              />
              <BsUpload size={24} style={{ color: 'var(--text-tertiary)', marginBottom: '0.5rem' }} />
              <p style={{ margin: 0, fontSize: '0.8125rem', color: 'var(--text-secondary)' }}>
                {file ? file.name : 'Belge yüklemek için tıklayın'}
              </p>
            </div>
            {errors.document && <div className="form-error">{errors.document}</div>}
          </div>
        )}

        {/* Custom Fields */}
        {customFields.length > 0 && (
          <div style={{ borderTop: '1px solid var(--border-color)', marginTop: '1rem', paddingTop: '1rem' }}>
            <h4 style={{ fontSize: '0.875rem', fontWeight: 600, marginBottom: '0.75rem', color: 'var(--text-secondary)' }}>
              Ek Bilgiler
            </h4>
            {customFields.map((field) => (
              <div key={field.id} className="form-group">
                {field.type !== 'checkbox' && (
                  <label className="form-label">
                    {field.label} {field.is_required && '*'}
                  </label>
                )}
                {renderCustomField(field)}
                {errors[`custom_${field.name}`] && (
                  <div className="form-error">{errors[`custom_${field.name}`]}</div>
                )}
              </div>
            ))}
          </div>
        )}
      </form>
    </Modal>
  );
};

export default LeaveRequestForm;
