import React, { useState, useEffect, useCallback } from 'react';
import toast from 'react-hot-toast';
import api from '../../services/api';

interface FormField {
  id: string;
  type: 'text' | 'email' | 'phone' | 'textarea' | 'select' | 'checkbox' | 'radio' | 'file' | 'date';
  label: string;
  placeholder?: string;
  required: boolean;
  options?: string[];
  validation?: string;
}

interface ApplicationForm {
  id: number;
  name: string;
  description: string | null;
  fields: FormField[];
  is_active: boolean;
  created_at: string;
}

const fieldTypes = [
  { type: 'text', label: 'Metin', icon: 'bi-fonts' },
  { type: 'email', label: 'E-posta', icon: 'bi-envelope' },
  { type: 'phone', label: 'Telefon', icon: 'bi-telephone' },
  { type: 'textarea', label: 'Uzun Metin', icon: 'bi-text-paragraph' },
  { type: 'select', label: 'Seçim Listesi', icon: 'bi-list-ul' },
  { type: 'checkbox', label: 'Çoklu Seçim', icon: 'bi-check2-square' },
  { type: 'radio', label: 'Tekli Seçim', icon: 'bi-circle' },
  { type: 'file', label: 'Dosya Yükleme', icon: 'bi-paperclip' },
  { type: 'date', label: 'Tarih', icon: 'bi-calendar' },
];

const FormBuilderPage: React.FC = () => {
  const [forms, setForms] = useState<ApplicationForm[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editingForm, setEditingForm] = useState<ApplicationForm | null>(null);
  const [formName, setFormName] = useState('');
  const [formDescription, setFormDescription] = useState('');
  const [fields, setFields] = useState<FormField[]>([]);
  const [saving, setSaving] = useState(false);

  const loadForms = useCallback(async () => {
    try {
      setLoading(true);
      const response = await api.get('/api/v1/recruitment/forms');
      setForms(response.data.data || []);
    } catch (error) {
      console.error('Formlar yüklenemedi:', error);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadForms();
  }, [loadForms]);

  const generateId = () => Math.random().toString(36).substr(2, 9);

  const handleAddField = (type: FormField['type']) => {
    const newField: FormField = {
      id: generateId(),
      type,
      label: fieldTypes.find(f => f.type === type)?.label || 'Yeni Alan',
      placeholder: '',
      required: false,
      options: type === 'select' || type === 'checkbox' || type === 'radio' ? ['Seçenek 1'] : undefined,
    };
    setFields([...fields, newField]);
  };

  const handleUpdateField = (id: string, updates: Partial<FormField>) => {
    setFields(fields.map(f => f.id === id ? { ...f, ...updates } : f));
  };

  const handleRemoveField = (id: string) => {
    setFields(fields.filter(f => f.id !== id));
  };

  const handleMoveField = (index: number, direction: 'up' | 'down') => {
    const newFields = [...fields];
    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= fields.length) return;
    [newFields[index], newFields[targetIndex]] = [newFields[targetIndex], newFields[index]];
    setFields(newFields);
  };

  const handleAddOption = (fieldId: string) => {
    const field = fields.find(f => f.id === fieldId);
    if (field && field.options) {
      handleUpdateField(fieldId, {
        options: [...field.options, `Seçenek ${field.options.length + 1}`]
      });
    }
  };

  const handleUpdateOption = (fieldId: string, optionIndex: number, value: string) => {
    const field = fields.find(f => f.id === fieldId);
    if (field && field.options) {
      const newOptions = [...field.options];
      newOptions[optionIndex] = value;
      handleUpdateField(fieldId, { options: newOptions });
    }
  };

  const handleRemoveOption = (fieldId: string, optionIndex: number) => {
    const field = fields.find(f => f.id === fieldId);
    if (field && field.options && field.options.length > 1) {
      handleUpdateField(fieldId, {
        options: field.options.filter((_, i) => i !== optionIndex)
      });
    }
  };

  const handleNewForm = () => {
    setEditingForm(null);
    setFormName('');
    setFormDescription('');
    setFields([
      { id: generateId(), type: 'text', label: 'Ad Soyad', placeholder: 'Adınızı ve soyadınızı girin', required: true },
      { id: generateId(), type: 'email', label: 'E-posta', placeholder: 'ornek@email.com', required: true },
      { id: generateId(), type: 'phone', label: 'Telefon', placeholder: '0532 123 4567', required: true },
      { id: generateId(), type: 'file', label: 'CV Yükle', required: true },
    ]);
    setShowModal(true);
  };

  const handleEditForm = (form: ApplicationForm) => {
    setEditingForm(form);
    setFormName(form.name);
    setFormDescription(form.description || '');
    setFields(form.fields || []);
    setShowModal(true);
  };

  const handleSaveForm = async () => {
    if (!formName.trim()) {
      toast.error('Form adı zorunludur');
      return;
    }
    if (fields.length === 0) {
      toast.error('En az bir alan ekleyin');
      return;
    }

    setSaving(true);
    try {
      const data = {
        name: formName,
        description: formDescription,
        fields: fields,
        is_active: true,
      };

      if (editingForm) {
        await api.put(`/api/v1/recruitment/forms/${editingForm.id}`, data);
        toast.success('Form güncellendi');
      } else {
        await api.post('/api/v1/recruitment/forms', data);
        toast.success('Form oluşturuldu');
      }
      setShowModal(false);
      loadForms();
    } catch (error) {
      console.error('Form kaydedilemedi:', error);
      toast.error('Form kaydedilemedi');
    } finally {
      setSaving(false);
    }
  };

  const handleDeleteForm = async (form: ApplicationForm) => {
    if (!confirm(`"${form.name}" formunu silmek istediğinize emin misiniz?`)) return;
    
    try {
      await api.delete(`/api/v1/recruitment/forms/${form.id}`);
      toast.success('Form silindi');
      loadForms();
    } catch (error) {
      toast.error('Form silinemedi');
    }
  };

  const handleToggleActive = async (form: ApplicationForm) => {
    try {
      await api.put(`/api/v1/recruitment/forms/${form.id}`, {
        ...form,
        is_active: !form.is_active,
      });
      toast.success(form.is_active ? 'Form pasifleştirildi' : 'Form aktifleştirildi');
      loadForms();
    } catch (error) {
      toast.error('Durum değiştirilemedi');
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Form Builder</h1>
          <p className="page-subtitle">Başvuru formları oluşturun ve yönetin</p>
        </div>
        <button className="btn btn-primary" onClick={handleNewForm}>
          <i className="bi bi-plus-lg me-2"></i>
          Yeni Form
        </button>
      </div>

      {/* Form Listesi */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {loading ? (
          <div className="col-span-full text-center py-8">
            <div className="spinner-border text-primary" role="status">
              <span className="visually-hidden">Yükleniyor...</span>
            </div>
          </div>
        ) : forms.length === 0 ? (
          <div className="col-span-full">
            <div className="card">
              <div className="card-body text-center py-12">
                <div className="text-6xl mb-4">📝</div>
                <h3 className="text-xl font-semibold mb-2">Henüz form yok</h3>
                <p className="text-[var(--text-secondary)] mb-4">
                  İş başvuruları için özel formlar oluşturun
                </p>
                <button className="btn btn-primary" onClick={handleNewForm}>
                  <i className="bi bi-plus-lg me-2"></i>
                  İlk Formu Oluştur
                </button>
              </div>
            </div>
          </div>
        ) : (
          forms.map((form) => (
            <div key={form.id} className="card">
              <div className="card-body">
                <div className="flex items-start justify-between mb-3">
                  <div>
                    <h3 className="font-semibold text-lg">{form.name}</h3>
                    <span className={`badge ${form.is_active ? 'badge-success' : 'badge-secondary'}`}>
                      {form.is_active ? 'Aktif' : 'Pasif'}
                    </span>
                  </div>
                  <div className="dropdown">
                    <button className="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                      <i className="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul className="dropdown-menu dropdown-menu-end">
                      <li>
                        <button className="dropdown-item" onClick={() => handleEditForm(form)}>
                          <i className="bi bi-pencil me-2"></i>Düzenle
                        </button>
                      </li>
                      <li>
                        <button className="dropdown-item" onClick={() => handleToggleActive(form)}>
                          <i className={`bi bi-${form.is_active ? 'pause' : 'play'} me-2`}></i>
                          {form.is_active ? 'Pasifleştir' : 'Aktifleştir'}
                        </button>
                      </li>
                      <li><hr className="dropdown-divider" /></li>
                      <li>
                        <button className="dropdown-item text-danger" onClick={() => handleDeleteForm(form)}>
                          <i className="bi bi-trash me-2"></i>Sil
                        </button>
                      </li>
                    </ul>
                  </div>
                </div>
                {form.description && (
                  <p className="text-sm text-[var(--text-secondary)] mb-3">{form.description}</p>
                )}
                <div className="flex items-center gap-4 text-sm text-[var(--text-secondary)]">
                  <span>
                    <i className="bi bi-list-ul me-1"></i>
                    {form.fields?.length || 0} alan
                  </span>
                  <span>
                    <i className="bi bi-calendar me-1"></i>
                    {new Date(form.created_at).toLocaleDateString('tr-TR')}
                  </span>
                </div>
              </div>
            </div>
          ))
        )}
      </div>

      {/* Form Builder Modal */}
      {showModal && (
        <div className="modal-backdrop show" onClick={() => setShowModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog modal-xl" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content" style={{ maxHeight: '90vh', overflow: 'hidden' }}>
                <div className="modal-header">
                  <h5 className="modal-title">
                    {editingForm ? 'Formu Düzenle' : 'Yeni Form Oluştur'}
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setShowModal(false)}></button>
                </div>
                <div className="modal-body" style={{ overflow: 'auto', maxHeight: 'calc(90vh - 130px)' }}>
                  <div className="row">
                    {/* Sol Panel - Alan Tipleri */}
                    <div className="col-md-3 border-r border-[var(--border-primary)]">
                      <h6 className="font-semibold mb-3">Alan Ekle</h6>
                      <div className="space-y-2">
                        {fieldTypes.map((ft) => (
                          <button
                            key={ft.type}
                            className="w-full p-2 text-left rounded-lg border border-[var(--border-primary)] hover:bg-[var(--surface-secondary)] transition-colors flex items-center gap-2"
                            onClick={() => handleAddField(ft.type as FormField['type'])}
                          >
                            <i className={`bi ${ft.icon} text-[var(--primary)]`}></i>
                            <span>{ft.label}</span>
                          </button>
                        ))}
                      </div>
                    </div>

                    {/* Sağ Panel - Form Alanları */}
                    <div className="col-md-9">
                      <div className="mb-4">
                        <label className="form-label">Form Adı *</label>
                        <input
                          type="text"
                          className="form-control"
                          value={formName}
                          onChange={(e) => setFormName(e.target.value)}
                          placeholder="Örn: Yazılımcı Başvuru Formu"
                        />
                      </div>
                      <div className="mb-4">
                        <label className="form-label">Açıklama</label>
                        <input
                          type="text"
                          className="form-control"
                          value={formDescription}
                          onChange={(e) => setFormDescription(e.target.value)}
                          placeholder="Form hakkında kısa açıklama"
                        />
                      </div>

                      <h6 className="font-semibold mb-3">Form Alanları</h6>
                      {fields.length === 0 ? (
                        <div className="text-center py-8 border-2 border-dashed border-[var(--border-primary)] rounded-lg">
                          <i className="bi bi-arrow-left text-4xl text-[var(--text-secondary)]"></i>
                          <p className="text-[var(--text-secondary)] mt-2">
                            Soldan alan ekleyin
                          </p>
                        </div>
                      ) : (
                        <div className="space-y-3">
                          {fields.map((field, index) => (
                            <div
                              key={field.id}
                              className="p-3 border border-[var(--border-primary)] rounded-lg bg-[var(--surface-secondary)]"
                            >
                              <div className="flex items-center gap-2 mb-2">
                                <div className="flex gap-1">
                                  <button
                                    className="btn btn-sm btn-outline-secondary"
                                    onClick={() => handleMoveField(index, 'up')}
                                    disabled={index === 0}
                                  >
                                    <i className="bi bi-chevron-up"></i>
                                  </button>
                                  <button
                                    className="btn btn-sm btn-outline-secondary"
                                    onClick={() => handleMoveField(index, 'down')}
                                    disabled={index === fields.length - 1}
                                  >
                                    <i className="bi bi-chevron-down"></i>
                                  </button>
                                </div>
                                <span className="badge badge-info">
                                  {fieldTypes.find(f => f.type === field.type)?.label}
                                </span>
                                <div className="flex-1">
                                  <input
                                    type="text"
                                    className="form-control form-control-sm"
                                    value={field.label}
                                    onChange={(e) => handleUpdateField(field.id, { label: e.target.value })}
                                    placeholder="Alan adı"
                                  />
                                </div>
                                <label className="flex items-center gap-1 text-sm">
                                  <input
                                    type="checkbox"
                                    checked={field.required}
                                    onChange={(e) => handleUpdateField(field.id, { required: e.target.checked })}
                                  />
                                  Zorunlu
                                </label>
                                <button
                                  className="btn btn-sm btn-outline-danger"
                                  onClick={() => handleRemoveField(field.id)}
                                >
                                  <i className="bi bi-trash"></i>
                                </button>
                              </div>
                              
                              {(field.type === 'text' || field.type === 'email' || field.type === 'phone' || field.type === 'textarea') && (
                                <input
                                  type="text"
                                  className="form-control form-control-sm"
                                  value={field.placeholder || ''}
                                  onChange={(e) => handleUpdateField(field.id, { placeholder: e.target.value })}
                                  placeholder="Placeholder metni"
                                />
                              )}

                              {(field.type === 'select' || field.type === 'checkbox' || field.type === 'radio') && field.options && (
                                <div className="mt-2 space-y-1">
                                  {field.options.map((option, optIndex) => (
                                    <div key={optIndex} className="flex gap-2">
                                      <input
                                        type="text"
                                        className="form-control form-control-sm flex-1"
                                        value={option}
                                        onChange={(e) => handleUpdateOption(field.id, optIndex, e.target.value)}
                                      />
                                      <button
                                        className="btn btn-sm btn-outline-danger"
                                        onClick={() => handleRemoveOption(field.id, optIndex)}
                                        disabled={field.options!.length <= 1}
                                      >
                                        <i className="bi bi-x"></i>
                                      </button>
                                    </div>
                                  ))}
                                  <button
                                    className="btn btn-sm btn-outline-primary"
                                    onClick={() => handleAddOption(field.id)}
                                  >
                                    <i className="bi bi-plus me-1"></i>
                                    Seçenek Ekle
                                  </button>
                                </div>
                              )}
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowModal(false)}>
                    İptal
                  </button>
                  <button type="button" className="btn btn-primary" onClick={handleSaveForm} disabled={saving}>
                    {saving ? (
                      <>
                        <span className="spinner-border spinner-border-sm me-2"></span>
                        Kaydediliyor...
                      </>
                    ) : (
                      <>
                        <i className="bi bi-check-lg me-2"></i>
                        Kaydet
                      </>
                    )}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      <style>{`
        .modal-backdrop.show {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.5);
          backdrop-filter: blur(4px);
          z-index: 1050;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .modal.show {
          position: relative;
          z-index: 1051;
        }
        .modal-dialog {
          margin: 0;
        }
        .modal-content {
          background: var(--surface-primary);
          border: 1px solid var(--border-primary);
          border-radius: 12px;
        }
        .modal-header {
          border-bottom: 1px solid var(--border-primary);
        }
        .modal-footer {
          border-top: 1px solid var(--border-primary);
        }
      `}</style>
    </div>
  );
};

export default FormBuilderPage;

