import React, { useState, useEffect, useCallback } from 'react';
import { customFieldsApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { Modal, ConfirmDialog } from '../../components/ui';
import {
  BsPlus,
  BsTrash,
  BsPencil,
  BsGripVertical,
  BsToggleOn,
  BsToggleOff,
  BsArrowUp,
  BsArrowDown,
} from 'react-icons/bs';

interface FieldOption {
  value: string;
  label: string;
}

interface CustomField {
  id: number;
  entity_type: string;
  field_key: string;
  field_label: string;
  field_type: string;
  field_options?: FieldOption[] | null;
  placeholder?: string;
  default_value?: string;
  is_required: boolean;
  is_active: boolean;
  is_unique: boolean;
  show_in_list: boolean;
  show_in_filter: boolean;
  sort_order: number;
  help_text?: string;
}

interface FieldFormData {
  entity_type: string;
  field_key: string;
  field_label: string;
  field_type: string;
  /** Form textarea: satır başına bir etiket; kayıtta [{value,label}]'e çevrilir */
  field_options?: string;
  placeholder?: string;
  default_value?: string;
  is_required: boolean;
  is_active: boolean;
  is_unique: boolean;
  show_in_list: boolean;
  show_in_filter: boolean;
  help_text?: string;
}

const entityTypes = [
  { value: 'employee', label: 'Personel' },
  { value: 'leave_request', label: 'İzin Talebi' },
  { value: 'training', label: 'Eğitim' },
  { value: 'performance', label: 'Performans' },
  { value: 'document', label: 'Belge' },
  { value: 'expense', label: 'Masraf' },
];

/** BE getFieldTypes ile hizalı; multiselect FE'den çıkarıldı. datetime BE'de string olarak kabul. */
const fieldTypes = [
  { value: 'text', label: 'Metin' },
  { value: 'textarea', label: 'Uzun Metin' },
  { value: 'number', label: 'Sayı' },
  { value: 'date', label: 'Tarih' },
  { value: 'datetime', label: 'Tarih ve Saat' },
  { value: 'select', label: 'Seçim Kutusu' },
  { value: 'radio', label: 'Radyo Butonları' },
  { value: 'checkbox', label: 'Onay Kutusu' },
  { value: 'email', label: 'Email' },
  { value: 'phone', label: 'Telefon' },
  { value: 'url', label: 'URL' },
  { value: 'file', label: 'Dosya' },
];

const initialFormData: FieldFormData = {
  entity_type: 'employee',
  field_key: '',
  field_label: '',
  field_type: 'text',
  field_options: '',
  placeholder: '',
  default_value: '',
  is_required: false,
  is_active: true,
  is_unique: false,
  show_in_list: false,
  show_in_filter: false,
  help_text: '',
};

const CustomFieldsPage: React.FC = () => {
  const [fields, setFields] = useState<CustomField[]>([]);
  const [loading, setLoading] = useState(true);
  const [entityFilter, setEntityFilter] = useState('employee');
  
  // Modal states
  const [formModalOpen, setFormModalOpen] = useState(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [selectedField, setSelectedField] = useState<CustomField | null>(null);
  const [formData, setFormData] = useState<FieldFormData>(initialFormData);
  const [saving, setSaving] = useState(false);
  
  // Drag state
  const [draggedItem, setDraggedItem] = useState<number | null>(null);
  const [dragOverItem, setDragOverItem] = useState<number | null>(null);

  const loadFields = useCallback(async () => {
    try {
      setLoading(true);
      const response = await customFieldsApi.getAll(entityFilter);
      setFields(response.data.data || []);
    } catch {
      toast.error('Özel alanlar yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [entityFilter]);

  useEffect(() => {
    loadFields();
  }, [loadFields]);

  

  const handleOpenForm = (field?: CustomField) => {
    if (field) {
      setSelectedField(field);
      setFormData({
        entity_type: field.entity_type,
        field_key: field.field_key,
        field_label: field.field_label,
        field_type: field.field_type,
        field_options: (field.field_options ?? [])
          .map((o) => o.label || o.value)
          .join('\n'),
        placeholder: field.placeholder || '',
        default_value: field.default_value || '',
        is_required: field.is_required,
        is_active: field.is_active,
        is_unique: field.is_unique,
        show_in_list: field.show_in_list,
        show_in_filter: field.show_in_filter,
        help_text: field.help_text || '',
      });
    } else {
      setSelectedField(null);
      setFormData({
        ...initialFormData,
        entity_type: entityFilter,
      });
    }
    setFormModalOpen(true);
  };

  const handleCloseForm = () => {
    setFormModalOpen(false);
    setSelectedField(null);
    setFormData(initialFormData);
  };

  const generateFieldKey = (label: string) => {
    return label
      .toLowerCase()
      .replace(/[^a-z0-9\s]/g, '')
      .replace(/\s+/g, '_')
      .substring(0, 50);
  };

  const handleFieldLabelChange = (value: string) => {
    setFormData({
      ...formData,
      field_label: value,
      field_key: selectedField ? formData.field_key : generateFieldKey(value),
    });
  };

  const handleSave = async () => {
    if (!formData.field_label || !formData.field_key) {
      toast.error('Alan adı ve anahtar zorunludur');
      return;
    }

    try {
      setSaving(true);
      
      const data = {
        ...formData,
        field_options: formData.field_options
          ? formData.field_options
              .split('\n')
              .map((o) => o.trim())
              .filter(Boolean)
              .map((label) => ({ value: label, label }))
          : null,
      };

      if (selectedField) {
        await customFieldsApi.update(selectedField.id, data);
        toast.success('Özel alan güncellendi');
      } else {
        await customFieldsApi.create(data);
        toast.success('Özel alan oluşturuldu');
      }

      handleCloseForm();
      loadFields();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'İşlem başarısız'));
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!selectedField) return;

    try {
      await customFieldsApi.delete(selectedField.id);
      toast.success('Özel alan silindi');
      setDeleteDialogOpen(false);
      setSelectedField(null);
      loadFields();
    } catch {
      toast.error('Özel alan silinemedi');
    }
  };

  const handleToggleActive = async (field: CustomField) => {
    try {
      await customFieldsApi.update(field.id, { is_active: !field.is_active });
      toast.success(field.is_active ? 'Alan pasif yapıldı' : 'Alan aktif yapıldı');
      loadFields();
    } catch {
      toast.error('Durum güncellenemedi');
    }
  };

  const handleMoveUp = async (index: number) => {
    if (index === 0) return;
    
    const newFields = [...fields];
    const temp = newFields[index].sort_order;
    newFields[index].sort_order = newFields[index - 1].sort_order;
    newFields[index - 1].sort_order = temp;
    
    try {
      await customFieldsApi.reorder(
        newFields.map((f, i) => ({ id: f.id, sort_order: i }))
      );
      loadFields();
    } catch {
      toast.error('Sıralama güncellenemedi');
    }
  };

  const handleMoveDown = async (index: number) => {
    if (index === fields.length - 1) return;
    
    const newFields = [...fields];
    const temp = newFields[index].sort_order;
    newFields[index].sort_order = newFields[index + 1].sort_order;
    newFields[index + 1].sort_order = temp;
    
    try {
      await customFieldsApi.reorder(
        newFields.map((f, i) => ({ id: f.id, sort_order: i }))
      );
      loadFields();
    } catch {
      toast.error('Sıralama güncellenemedi');
    }
  };

  const handleDragStart = (index: number) => {
    setDraggedItem(index);
  };

  const handleDragOver = (e: React.DragEvent, index: number) => {
    e.preventDefault();
    setDragOverItem(index);
  };

  const handleDrop = async (e: React.DragEvent, dropIndex: number) => {
    e.preventDefault();
    
    if (draggedItem === null || draggedItem === dropIndex) {
      setDraggedItem(null);
      setDragOverItem(null);
      return;
    }

    const newFields = [...fields];
    const [draggedField] = newFields.splice(draggedItem, 1);
    newFields.splice(dropIndex, 0, draggedField);

    setDraggedItem(null);
    setDragOverItem(null);

    try {
      await customFieldsApi.reorder(
        newFields.map((f, i) => ({ id: f.id, sort_order: i }))
      );
      setFields(newFields);
      toast.success('Sıralama güncellendi');
    } catch {
      toast.error('Sıralama güncellenemedi');
      loadFields();
    }
  };

  const getFieldTypeLabel = (type: string) => {
    return fieldTypes.find(t => t.value === type)?.label || type;
  };

  const needsOptions = (type: string) => ['select', 'radio'].includes(type);

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Özel Alanlar</h1>
          <p className="page-subtitle">Personel ve diğer modüller için özel alanlar tanımlayın</p>
        </div>
        <div className="page-header-actions">
          <button className="btn btn-primary" onClick={() => handleOpenForm()}>
            <BsPlus /> Yeni Özel Alan
          </button>
        </div>
      </div>

      {/* Filter */}
      <div className="card mb-3">
        <div className="card-body">
          <div className="row align-items-end">
            <div className="col-md-4">
              <label className="form-label">Modül</label>
              <select
                className="form-select"
                value={entityFilter}
                onChange={(e) => setEntityFilter(e.target.value)}
              >
                {entityTypes.map((et) => (
                  <option key={et.value} value={et.value}>{et.label}</option>
                ))}
              </select>
            </div>
            <div className="col-md-8">
              <p style={{ margin: 0, fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
                <strong>{fields.length}</strong> özel alan tanımlı • Sürükleyerek veya ok tuşlarıyla sıralayabilirsiniz
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Table */}
      <div className="card">
        <div className="table-responsive">
          <table className="table">
            <thead>
              <tr>
                <th style={{ width: 40 }}></th>
                <th>Alan Adı</th>
                <th>Alan Anahtarı</th>
                <th>Tip</th>
                <th>Zorunlu</th>
                <th>Durum</th>
                <th style={{ width: 50 }}>Sıra</th>
                <th className="text-end">İşlemler</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={8} className="text-center py-4">
                    <div className="spinner-border spinner-border-sm" />
                  </td>
                </tr>
              ) : fields.length === 0 ? (
                <tr>
                  <td colSpan={8} className="text-center py-4">
                    <div style={{ color: 'var(--text-tertiary)' }}>
                      <p>Henüz özel alan tanımlanmamış</p>
                      <button className="btn btn-primary btn-sm" onClick={() => handleOpenForm()}>
                        <BsPlus /> İlk Alanı Oluştur
                      </button>
                    </div>
                  </td>
                </tr>
              ) : (
                fields.map((field, index) => (
                  <tr
                    key={field.id}
                    draggable
                    onDragStart={() => handleDragStart(index)}
                    onDragOver={(e) => handleDragOver(e, index)}
                    onDrop={(e) => handleDrop(e, index)}
                    style={{
                      cursor: 'grab',
                      background: dragOverItem === index ? 'var(--primary-soft)' : undefined,
                      opacity: draggedItem === index ? 0.5 : 1,
                    }}
                  >
                    <td>
                      <BsGripVertical style={{ color: 'var(--text-tertiary)' }} />
                    </td>
                    <td>
                      <div style={{ fontWeight: 500 }}>{field.field_label}</div>
                      {field.help_text && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                          {field.help_text}
                        </div>
                      )}
                    </td>
                    <td>
                      <code style={{ fontSize: '0.75rem', background: 'var(--bg-tertiary)', padding: '0.125rem 0.375rem', borderRadius: 'var(--radius-sm)' }}>
                        {field.field_key}
                      </code>
                    </td>
                    <td>
                      <span className="badge badge-info">{getFieldTypeLabel(field.field_type)}</span>
                    </td>
                    <td>
                      {field.is_required ? (
                        <span className="badge badge-warning">Zorunlu</span>
                      ) : (
                        <span className="badge badge-secondary">Opsiyonel</span>
                      )}
                    </td>
                    <td>
                      <button
                        className="btn btn-ghost btn-sm"
                        onClick={() => handleToggleActive(field)}
                        title={field.is_active ? 'Pasif Yap' : 'Aktif Yap'}
                      >
                        {field.is_active ? (
                          <BsToggleOn size={20} style={{ color: 'var(--success)' }} />
                        ) : (
                          <BsToggleOff size={20} style={{ color: 'var(--text-tertiary)' }} />
                        )}
                      </button>
                    </td>
                    <td>
                      <div style={{ display: 'flex', gap: '0.25rem' }}>
                        <button
                          className="btn btn-ghost btn-icon btn-sm"
                          onClick={() => handleMoveUp(index)}
                          disabled={index === 0}
                          title="Yukarı Taşı"
                        >
                          <BsArrowUp />
                        </button>
                        <button
                          className="btn btn-ghost btn-icon btn-sm"
                          onClick={() => handleMoveDown(index)}
                          disabled={index === fields.length - 1}
                          title="Aşağı Taşı"
                        >
                          <BsArrowDown />
                        </button>
                      </div>
                    </td>
                    <td>
                      <div className="btn-group btn-group-sm">
                        <button
                          className="btn btn-primary"
                          onClick={() => handleOpenForm(field)}
                          title="Düzenle"
                        >
                          <BsPencil />
                        </button>
                        <button
                          className="btn btn-danger"
                          onClick={() => {
                            setSelectedField(field);
                            setDeleteDialogOpen(true);
                          }}
                          title="Sil"
                        >
                          <BsTrash />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Form Modal */}
      <Modal
        isOpen={formModalOpen}
        onClose={handleCloseForm}
        title={selectedField ? 'Özel Alanı Düzenle' : 'Yeni Özel Alan'}
        size="lg"
      >
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '1rem' }}>
          <div className="form-group">
            <label className="form-label">Modül <span style={{ color: 'var(--danger)' }}>*</span></label>
            <select
              className="form-select"
              value={formData.entity_type}
              onChange={(e) => setFormData({ ...formData, entity_type: e.target.value })}
              disabled={!!selectedField}
            >
              {entityTypes.map((et) => (
                <option key={et.value} value={et.value}>{et.label}</option>
              ))}
            </select>
          </div>

          <div className="form-group">
            <label className="form-label">Alan Tipi <span style={{ color: 'var(--danger)' }}>*</span></label>
            <select
              className="form-select"
              value={formData.field_type}
              onChange={(e) => setFormData({ ...formData, field_type: e.target.value })}
            >
              {fieldTypes.map((ft) => (
                <option key={ft.value} value={ft.value}>{ft.label}</option>
              ))}
            </select>
          </div>

          <div className="form-group">
            <label className="form-label">Alan Adı <span style={{ color: 'var(--danger)' }}>*</span></label>
            <input
              type="text"
              className="form-control"
              value={formData.field_label}
              onChange={(e) => handleFieldLabelChange(e.target.value)}
              placeholder="Kullanıcıların göreceği ad"
            />
          </div>

          <div className="form-group">
            <label className="form-label">Alan Anahtarı <span style={{ color: 'var(--danger)' }}>*</span></label>
            <input
              type="text"
              className="form-control"
              value={formData.field_key}
              onChange={(e) => setFormData({ ...formData, field_key: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '') })}
              placeholder="benzersiz_anahtar"
              disabled={!!selectedField}
            />
            <small style={{ color: 'var(--text-tertiary)' }}>Sadece küçük harf, rakam ve alt çizgi</small>
          </div>

          {needsOptions(formData.field_type) && (
            <div className="form-group" style={{ gridColumn: '1 / -1' }}>
              <label className="form-label">Seçenekler <span style={{ color: 'var(--danger)' }}>*</span></label>
              <textarea
                className="form-control"
                value={formData.field_options}
                onChange={(e) => setFormData({ ...formData, field_options: e.target.value })}
                rows={4}
                placeholder="Her satıra bir seçenek yazın"
              />
            </div>
          )}

          <div className="form-group">
            <label className="form-label">Placeholder</label>
            <input
              type="text"
              className="form-control"
              value={formData.placeholder}
              onChange={(e) => setFormData({ ...formData, placeholder: e.target.value })}
              placeholder="Örnek değer göster"
            />
          </div>

          <div className="form-group">
            <label className="form-label">Varsayılan Değer</label>
            <input
              type="text"
              className="form-control"
              value={formData.default_value}
              onChange={(e) => setFormData({ ...formData, default_value: e.target.value })}
            />
          </div>

          <div className="form-group" style={{ gridColumn: '1 / -1' }}>
            <label className="form-label">Yardım Metni</label>
            <input
              type="text"
              className="form-control"
              value={formData.help_text}
              onChange={(e) => setFormData({ ...formData, help_text: e.target.value })}
              placeholder="Kullanıcıya yardımcı olacak açıklama"
            />
          </div>

          <div style={{ gridColumn: '1 / -1', display: 'flex', flexWrap: 'wrap', gap: '1.5rem', padding: '1rem', background: 'var(--bg-tertiary)', borderRadius: 'var(--radius-md)' }}>
            <div className="form-check">
              <input
                type="checkbox"
                className="form-check-input"
                id="is_required"
                checked={formData.is_required}
                onChange={(e) => setFormData({ ...formData, is_required: e.target.checked })}
              />
              <label className="form-check-label" htmlFor="is_required">
                Zorunlu Alan
              </label>
            </div>

            <div className="form-check">
              <input
                type="checkbox"
                className="form-check-input"
                id="is_active"
                checked={formData.is_active}
                onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
              />
              <label className="form-check-label" htmlFor="is_active">
                Aktif
              </label>
            </div>

            <div className="form-check">
              <input
                type="checkbox"
                className="form-check-input"
                id="is_unique"
                checked={formData.is_unique}
                onChange={(e) => setFormData({ ...formData, is_unique: e.target.checked })}
              />
              <label className="form-check-label" htmlFor="is_unique">
                Benzersiz Değer
              </label>
            </div>

            <div className="form-check">
              <input
                type="checkbox"
                className="form-check-input"
                id="show_in_list"
                checked={formData.show_in_list}
                onChange={(e) => setFormData({ ...formData, show_in_list: e.target.checked })}
              />
              <label className="form-check-label" htmlFor="show_in_list">
                Listede Göster
              </label>
            </div>

            <div className="form-check">
              <input
                type="checkbox"
                className="form-check-input"
                id="show_in_filter"
                checked={formData.show_in_filter}
                onChange={(e) => setFormData({ ...formData, show_in_filter: e.target.checked })}
              />
              <label className="form-check-label" htmlFor="show_in_filter">
                Filtrede Göster
              </label>
            </div>
          </div>
        </div>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.5rem' }}>
          <button className="btn btn-secondary" onClick={handleCloseForm}>
            İptal
          </button>
          <button
            className="btn btn-primary"
            onClick={handleSave}
            disabled={saving || !formData.field_label || !formData.field_key}
          >
            {saving ? 'Kaydediliyor...' : selectedField ? 'Güncelle' : 'Oluştur'}
          </button>
        </div>
      </Modal>

      {/* Delete Dialog */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => { setDeleteDialogOpen(false); setSelectedField(null); }}
        onConfirm={handleDelete}
        title="Özel Alanı Sil"
        message={`"${selectedField?.field_label}" alanını silmek istediğinizden emin misiniz? Bu alana ait tüm veriler kaybolacaktır.`}
        confirmText="Sil"
        variant="danger"
      />
    </div>
  );
};

export default CustomFieldsPage;
