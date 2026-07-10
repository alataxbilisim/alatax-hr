import React, { useState, useEffect, useRef } from 'react';
import { useParams } from 'react-router-dom';
import api from '../../services/api';

interface FormField {
  id: string;
  type: 'text' | 'email' | 'phone' | 'textarea' | 'select' | 'checkbox' | 'radio' | 'file' | 'date';
  label: string;
  placeholder?: string;
  required: boolean;
  options?: string[];
}

interface JobPosition {
  id: number;
  title: string;
  description: string | null;
  department: string | null;
  location: string | null;
  employment_type: string | null;
  company: {
    name: string;
    logo: string | null;
  };
  form: {
    id: number;
    name: string;
    fields: FormField[];
  } | null;
}

const PublicApplicationPage: React.FC = () => {
  const { slug } = useParams<{ slug: string }>();
  const [position, setPosition] = useState<JobPosition | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [formData, setFormData] = useState<Record<string, unknown>>({});
  const [kvkkAccepted, setKvkkAccepted] = useState(false);
  const fileInputRefs = useRef<Record<string, HTMLInputElement | null>>({});

  useEffect(() => {
    loadPosition();
  }, [slug]);

  const loadPosition = async () => {
    try {
      setLoading(true);
      const response = await api.get(`/api/v1/public/jobs/${slug}`);
      setPosition(response.data.data);
      
      // Initialize form data
      const initialData: Record<string, unknown> = {};
      response.data.data.form?.fields?.forEach((field: FormField) => {
        if (field.type === 'checkbox') {
          initialData[field.id] = [];
        } else {
          initialData[field.id] = '';
        }
      });
      setFormData(initialData);
    } catch (err) {
      setError('Bu pozisyon bulunamadı veya başvurular kapatılmış.');
    } finally {
      setLoading(false);
    }
  };

  const handleFieldChange = (fieldId: string, value: unknown) => {
    setFormData({ ...formData, [fieldId]: value });
  };

  const handleCheckboxChange = (fieldId: string, option: string, checked: boolean) => {
    const current = (formData[fieldId] as string[]) || [];
    if (checked) {
      handleFieldChange(fieldId, [...current, option]);
    } else {
      handleFieldChange(fieldId, current.filter((o) => o !== option));
    }
  };

  const handleFileChange = (fieldId: string, file: File | null) => {
    handleFieldChange(fieldId, file);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!kvkkAccepted) {
      alert('KVKK metnini onaylamanız gerekmektedir.');
      return;
    }

    setSubmitting(true);
    try {
      const submitData = new FormData();
      
      Object.entries(formData).forEach(([key, value]) => {
        if (value instanceof File) {
          submitData.append(key, value);
        } else if (Array.isArray(value)) {
          submitData.append(key, JSON.stringify(value));
        } else {
          submitData.append(key, String(value || ''));
        }
      });

      await api.post(`/api/v1/public/jobs/${slug}/apply`, submitData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      
      setSubmitted(true);
    } catch (err) {
      alert('Başvurunuz gönderilemedi. Lütfen tekrar deneyin.');
    } finally {
      setSubmitting(false);
    }
  };

  const renderField = (field: FormField) => {
    const value = formData[field.id];

    switch (field.type) {
      case 'text':
      case 'email':
      case 'phone':
        return (
          <input
            type={field.type === 'phone' ? 'tel' : field.type}
            className="form-control"
            placeholder={field.placeholder}
            value={String(value || '')}
            onChange={(e) => handleFieldChange(field.id, e.target.value)}
            required={field.required}
          />
        );

      case 'textarea':
        return (
          <textarea
            className="form-control"
            rows={4}
            placeholder={field.placeholder}
            value={String(value || '')}
            onChange={(e) => handleFieldChange(field.id, e.target.value)}
            required={field.required}
          />
        );

      case 'select':
        return (
          <select
            className="form-select"
            value={String(value || '')}
            onChange={(e) => handleFieldChange(field.id, e.target.value)}
            required={field.required}
          >
            <option value="">Seçiniz</option>
            {field.options?.map((option, i) => (
              <option key={i} value={option}>
                {option}
              </option>
            ))}
          </select>
        );

      case 'radio':
        return (
          <div className="space-y-2">
            {field.options?.map((option, i) => (
              <label key={i} className="flex items-center gap-2 cursor-pointer">
                <input
                  type="radio"
                  name={field.id}
                  value={option}
                  checked={value === option}
                  onChange={(e) => handleFieldChange(field.id, e.target.value)}
                  required={field.required}
                  className="form-check-input"
                />
                <span>{option}</span>
              </label>
            ))}
          </div>
        );

      case 'checkbox':
        return (
          <div className="space-y-2">
            {field.options?.map((option, i) => (
              <label key={i} className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={(value as string[])?.includes(option)}
                  onChange={(e) => handleCheckboxChange(field.id, option, e.target.checked)}
                  className="form-check-input"
                />
                <span>{option}</span>
              </label>
            ))}
          </div>
        );

      case 'date':
        return (
          <input
            type="date"
            className="form-control"
            value={String(value || '')}
            onChange={(e) => handleFieldChange(field.id, e.target.value)}
            required={field.required}
          />
        );

      case 'file':
        return (
          <div>
            <input
              type="file"
              ref={(el) => (fileInputRefs.current[field.id] = el)}
              className="hidden"
              onChange={(e) => handleFileChange(field.id, e.target.files?.[0] || null)}
              accept=".pdf,.doc,.docx"
            />
            <div
              className="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:bg-gray-50 transition-colors"
              onClick={() => fileInputRefs.current[field.id]?.click()}
            >
              {value instanceof File ? (
                <div>
                  <i className="bi bi-file-check text-2xl text-green-500"></i>
                  <p className="mt-1 font-medium">{value.name}</p>
                </div>
              ) : (
                <div>
                  <i className="bi bi-cloud-upload text-2xl text-gray-400"></i>
                  <p className="mt-1 text-gray-500">Dosya seçmek için tıklayın</p>
                  <p className="text-xs text-gray-400">PDF, DOC, DOCX</p>
                </div>
              )}
            </div>
          </div>
        );

      default:
        return null;
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="spinner-border text-primary mb-4" role="status">
            <span className="visually-hidden">Yükleniyor...</span>
          </div>
          <p className="text-gray-600">Yükleniyor...</p>
        </div>
      </div>
    );
  }

  if (error || !position) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="text-center">
          <div className="text-6xl mb-4">😕</div>
          <h1 className="text-2xl font-bold text-gray-800 mb-2">Sayfa Bulunamadı</h1>
          <p className="text-gray-600">{error || 'Bu sayfa mevcut değil.'}</p>
        </div>
      </div>
    );
  }

  if (submitted) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-green-50 to-blue-50 flex items-center justify-center p-4">
        <div className="text-center max-w-md">
          <div className="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <i className="bi bi-check-lg text-5xl text-green-600"></i>
          </div>
          <h1 className="text-3xl font-bold text-gray-800 mb-4">Başvurunuz Alındı!</h1>
          <p className="text-gray-600 mb-6">
            {position.title} pozisyonu için başvurunuz başarıyla alındı. 
            En kısa sürede sizinle iletişime geçeceğiz.
          </p>
          <p className="text-sm text-gray-500">
            Teşekkür ederiz,<br />
            <strong>{position.company.name}</strong>
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-purple-50 py-8 px-4">
      <div className="max-w-2xl mx-auto">
        {/* Header */}
        <div className="text-center mb-8">
          {position.company.logo ? (
            <img
              src={position.company.logo}
              alt={position.company.name}
              className="h-16 mx-auto mb-4"
            />
          ) : (
            <div className="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4">
              {position.company.name.charAt(0)}
            </div>
          )}
          <h1 className="text-3xl font-bold text-gray-800">{position.title}</h1>
          <p className="text-gray-600 mt-1">{position.company.name}</p>
          <div className="flex items-center justify-center gap-4 mt-3 text-sm text-gray-500">
            {position.location && (
              <span>
                <i className="bi bi-geo-alt me-1"></i>
                {position.location}
              </span>
            )}
            {position.department && (
              <span>
                <i className="bi bi-building me-1"></i>
                {position.department}
              </span>
            )}
            {position.employment_type && (
              <span>
                <i className="bi bi-briefcase me-1"></i>
                {position.employment_type}
              </span>
            )}
          </div>
        </div>

        {/* Description */}
        {position.description && (
          <div className="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 className="font-semibold text-gray-800 mb-3">Pozisyon Hakkında</h2>
            <div className="text-gray-600 whitespace-pre-line">{position.description}</div>
          </div>
        )}

        {/* Application Form */}
        <div className="bg-white rounded-xl shadow-sm p-6">
          <h2 className="font-semibold text-gray-800 mb-6">Başvuru Formu</h2>
          
          {position.form?.fields && position.form.fields.length > 0 ? (
            <form onSubmit={handleSubmit} className="space-y-5">
              {position.form.fields.map((field) => (
                <div key={field.id}>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    {field.label}
                    {field.required && <span className="text-red-500 ml-1">*</span>}
                  </label>
                  {renderField(field)}
                </div>
              ))}

              {/* KVKK */}
              <div className="border-t pt-4">
                <label className="flex items-start gap-3 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={kvkkAccepted}
                    onChange={(e) => setKvkkAccepted(e.target.checked)}
                    className="form-check-input mt-1"
                    required
                  />
                  <span className="text-sm text-gray-600">
                    <strong>KVKK Aydınlatma Metni</strong>ni okudum ve kişisel verilerimin 
                    işlenmesini kabul ediyorum. <span className="text-red-500">*</span>
                  </span>
                </label>
              </div>

              <button
                type="submit"
                className="w-full py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white font-semibold rounded-lg hover:opacity-90 transition-opacity disabled:opacity-50"
                disabled={submitting}
              >
                {submitting ? (
                  <>
                    <span className="spinner-border spinner-border-sm me-2"></span>
                    Gönderiliyor...
                  </>
                ) : (
                  <>
                    <i className="bi bi-send me-2"></i>
                    Başvuruyu Gönder
                  </>
                )}
              </button>
            </form>
          ) : (
            <div className="text-center py-8 text-gray-500">
              <i className="bi bi-exclamation-circle text-4xl mb-2"></i>
              <p>Bu pozisyon için başvuru formu henüz hazırlanmamış.</p>
            </div>
          )}
        </div>

        {/* Footer */}
        <p className="text-center text-xs text-gray-400 mt-6">
          Powered by <strong>Alatax HR</strong>
        </p>
      </div>
    </div>
  );
};

export default PublicApplicationPage;

