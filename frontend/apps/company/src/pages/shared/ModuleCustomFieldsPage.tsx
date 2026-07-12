import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { customFieldsApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import {
  BsPlus,
  BsTrash,
  BsPencil,
  BsGripVertical,
  BsToggleOn,
  BsToggleOff,
  BsArrowLeft,
  BsShieldLock,
} from 'react-icons/bs';
import { Modal, ConfirmDialog } from '../../components/ui';
import { useSelector } from 'react-redux';
import { RootState } from '../../store';

interface FieldOption {
  value: string;
  label: string;
}

// Özel alan tanımı — BE field_options: [{value, label}]
interface CustomField {
  id: number;
  entity_type: string;
  field_key: string;
  field_label: string;
  field_type: string;
  is_required: boolean;
  is_active: boolean;
  is_unique: boolean;
  show_in_list: boolean;
  show_in_filter: boolean;
  field_options?: FieldOption[] | null;
  placeholder?: string;
  default_value?: string;
  help_text?: string;
  sort_order: number;
}

interface FieldFormData {
  entity_type: string;
  field_key: string;
  field_label: string;
  field_type: string;
  is_required: boolean;
  is_active: boolean;
  is_unique: boolean;
  show_in_list: boolean;
  show_in_filter: boolean;
  field_options?: FieldOption[];
  placeholder?: string;
  default_value?: string;
  help_text?: string;
}

interface ModuleCustomFieldsPageProps {
  entityType: string;
  moduleLabel: string;
  backPath: string;
  moduleColor?: string;
}

/** BE getFieldTypes ile hizalı; multiselect/color FE'den çıkarıldı (BE desteklemiyor). */
const fieldTypes = [
  { value: 'text', label: 'Metin' },
  { value: 'textarea', label: 'Uzun Metin' },
  { value: 'number', label: 'Sayı' },
  { value: 'date', label: 'Tarih' },
  { value: 'datetime', label: 'Tarih ve Saat' },
  { value: 'select', label: 'Seçim Listesi' },
  { value: 'checkbox', label: 'Onay Kutusu' },
  { value: 'radio', label: 'Radyo Butonları' },
  { value: 'email', label: 'E-posta' },
  { value: 'phone', label: 'Telefon' },
  { value: 'url', label: 'URL' },
  { value: 'file', label: 'Dosya' },
];

const ModuleCustomFieldsPage: React.FC<ModuleCustomFieldsPageProps> = ({
  entityType,
  moduleLabel,
  backPath,
}) => {
  const navigate = useNavigate();
  const { user } = useSelector((state: RootState) => state.auth);
  
  const [fields, setFields] = useState<CustomField[]>([]);
  const [loading, setLoading] = useState(true);
  
  // Modal durumları
  const [formModalOpen, setFormModalOpen] = useState(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [selectedField, setSelectedField] = useState<CustomField | null>(null);
  const [saving, setSaving] = useState(false);
  
  // Form durumu
  const [formData, setFormData] = useState<FieldFormData>({
    entity_type: entityType,
    field_key: '',
    field_label: '',
    field_type: 'text',
    is_required: false,
    is_active: true,
    is_unique: false,
    show_in_list: false,
    show_in_filter: false,
    field_options: [],
    placeholder: '',
    default_value: '',
    help_text: '',
  });
  
  // Options yönetimi
  const [newOption, setNewOption] = useState('');
  
  // Drag durumu
  const [draggedItem, setDraggedItem] = useState<number | null>(null);
  const [dragOverItem, setDragOverItem] = useState<number | null>(null);

  // Yetki kontrolü - özel alan yetkisi kontrol et
  const hasPermission = (action: 'create' | 'update' | 'delete' | 'view') => {
    if (!user) return false;
    
    // Company Admin ve Super Admin her şeyi yapabilir
    if (user.type === 'company_admin' || user.type === 'super_admin') return true;
    
    // Role bazlı yetki kontrolü
    const permissions = user.permissions || [];
    
    // Hiyerarşik yetki formatı: {module}.custom_fields.{action}
    // Örnek: employees.custom_fields.create
    const moduleKey = entityType === 'employee' ? 'employees' : entityType;
    
    const permissionMap: Record<string, string[]> = {
      create: [
        `${moduleKey}.custom_fields.create`,
        `${moduleKey}.custom_fields.*`,
        `${moduleKey}.*`,
        // Geriye uyumluluk
        `custom_fields.create`,
        `${entityType}_custom_fields.create`,
      ],
      update: [
        `${moduleKey}.custom_fields.edit`,
        `${moduleKey}.custom_fields.*`,
        `${moduleKey}.*`,
        // Geriye uyumluluk
        `custom_fields.update`,
        `${entityType}_custom_fields.update`,
      ],
      delete: [
        `${moduleKey}.custom_fields.delete`,
        `${moduleKey}.custom_fields.*`,
        `${moduleKey}.*`,
        // Geriye uyumluluk
        `custom_fields.delete`,
        `${entityType}_custom_fields.delete`,
      ],
      view: [
        `${moduleKey}.custom_fields.view`,
        `${moduleKey}.custom_fields.*`,
        `${moduleKey}.*`,
        // Geriye uyumluluk
        `custom_fields.view`,
        `${entityType}_custom_fields.view`,
        `custom_fields.list`,
      ],
    };
    
    return permissionMap[action].some(p => permissions.includes(p));
  };

  const canCreate = hasPermission('create');
  const canUpdate = hasPermission('update');
  const canDelete = hasPermission('delete');

  const loadFields = useCallback(async () => {
    try {
      setLoading(true);
      const response = await customFieldsApi.getAll(entityType);
      setFields(response.data.data || []);
    } catch {
      toast.error('Özel alanlar yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [entityType]);

  useEffect(() => {
    loadFields();
  }, [loadFields]);

  

  const generateFieldKey = (label: string) => {
    return label
      .toLowerCase()
      .replace(/ğ/g, 'g')
      .replace(/ü/g, 'u')
      .replace(/ş/g, 's')
      .replace(/ı/g, 'i')
      .replace(/ö/g, 'o')
      .replace(/ç/g, 'c')
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_|_$/g, '');
  };

  const handleOpenForm = (field?: CustomField) => {
    if (field) {
      if (!canUpdate) {
        toast.error('Bu işlem için yetkiniz yok');
        return;
      }
      setSelectedField(field);
      setFormData({
        entity_type: field.entity_type,
        field_key: field.field_key,
        field_label: field.field_label,
        field_type: field.field_type,
        is_required: field.is_required,
        is_active: field.is_active,
        is_unique: field.is_unique,
        show_in_list: field.show_in_list,
        show_in_filter: field.show_in_filter,
        field_options: field.field_options || [],
        placeholder: field.placeholder || '',
        default_value: field.default_value || '',
        help_text: field.help_text || '',
      });
    } else {
      if (!canCreate) {
        toast.error('Bu işlem için yetkiniz yok');
        return;
      }
      setSelectedField(null);
      setFormData({
        entity_type: entityType,
        field_key: '',
        field_label: '',
        field_type: 'text',
        is_required: false,
        is_active: true,
        is_unique: false,
        show_in_list: false,
        show_in_filter: false,
        field_options: [],
        placeholder: '',
        default_value: '',
        help_text: '',
      });
    }
    setFormModalOpen(true);
  };

  const handleSave = async () => {
    if (!formData.field_label) {
      toast.error('Alan adı gereklidir');
      return;
    }

    const fieldKey = formData.field_key || generateFieldKey(formData.field_label);
    
    if (!fieldKey) {
      toast.error('Geçerli bir alan adı giriniz');
      return;
    }

    try {
      setSaving(true);
      const payload = {
        ...formData,
        field_key: fieldKey,
      };

      if (selectedField) {
        await customFieldsApi.update(selectedField.id, payload);
        toast.success('Özel alan güncellendi');
      } else {
        await customFieldsApi.create(payload);
        toast.success('Özel alan oluşturuldu');
      }
      
      setFormModalOpen(false);
      loadFields();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'İşlem başarısız'));
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!selectedField || !canDelete) {
      toast.error('Bu işlem için yetkiniz yok');
      return;
    }

    try {
      await customFieldsApi.delete(selectedField.id);
      toast.success('Özel alan silindi');
      setDeleteDialogOpen(false);
      setSelectedField(null);
      loadFields();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'Silme işlemi başarısız'));
    }
  };

  const handleToggleActive = async (field: CustomField) => {
    if (!canUpdate) {
      toast.error('Bu işlem için yetkiniz yok');
      return;
    }

    try {
      await customFieldsApi.update(field.id, { is_active: !field.is_active });
      toast.success(field.is_active ? 'Alan pasif yapıldı' : 'Alan aktif yapıldı');
      loadFields();
    } catch {
      toast.error('Durum güncellenemedi');
    }
  };

  const addOption = () => {
    const label = newOption.trim();
    if (!label) return;
    setFormData({
      ...formData,
      field_options: [...(formData.field_options || []), { value: label, label }],
    });
    setNewOption('');
  };

  const removeOption = (index: number) => {
    const field_options = [...(formData.field_options || [])];
    field_options.splice(index, 1);
    setFormData({ ...formData, field_options });
  };

  // Drag & Drop
  const handleDragStart = (index: number) => {
    setDraggedItem(index);
  };

  const handleDragOver = (e: React.DragEvent, index: number) => {
    e.preventDefault();
    setDragOverItem(index);
  };

  const handleDrop = async () => {
    if (draggedItem === null || dragOverItem === null || draggedItem === dragOverItem) {
      setDraggedItem(null);
      setDragOverItem(null);
      return;
    }

    const reordered = [...fields];
    const [movedItem] = reordered.splice(draggedItem, 1);
    reordered.splice(dragOverItem, 0, movedItem);
    
    setFields(reordered);
    setDraggedItem(null);
    setDragOverItem(null);

    try {
      await customFieldsApi.reorder(
        reordered.map((field, index) => ({
          id: field.id,
          sort_order: index,
        }))
      );
      toast.success('Sıralama güncellendi');
    } catch {
      toast.error('Sıralama güncellenemedi');
      loadFields();
    }
  };

  const needsOptions = ['select', 'radio'].includes(formData.field_type);

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <button
            className="btn btn-ghost btn-sm"
            onClick={() => navigate(backPath)}
            style={{ marginBottom: '0.5rem' }}
          >
            <BsArrowLeft /> Geri
          </button>
          <h1 className="page-title">{moduleLabel} - Özel Alanlar</h1>
          <p className="page-subtitle">
            {moduleLabel} modülü için özel alanları yönetin
          </p>
        </div>
        <div className="page-header-actions">
          {canCreate && (
            <button className="btn btn-primary" onClick={() => handleOpenForm()}>
              <BsPlus /> Yeni Özel Alan
            </button>
          )}
        </div>
      </div>

      {/* Yetki uyarısı */}
      {!canCreate && !canUpdate && !canDelete && (
        <div className="alert alert-warning mb-3" style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
          <BsShieldLock size={20} />
          <span>Bu sayfada özel alanları yönetmek için yetkiniz bulunmamaktadır. Sadece görüntüleyebilirsiniz.</span>
        </div>
      )}

      {/* Tablo */}
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
                <th>Listede</th>
                <th>Durum</th>
                <th className="text-end">İşlemler</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={8} className="text-center py-4">
                    <div className="spinner-border spinner-border-sm" role="status" />
                  </td>
                </tr>
              ) : fields.length === 0 ? (
                <tr>
                  <td colSpan={8} className="text-center py-4">
                    <p className="text-muted mb-2">Henüz özel alan tanımlanmamış</p>
                    {canCreate && (
                      <button className="btn btn-primary btn-sm" onClick={() => handleOpenForm()}>
                        <BsPlus /> İlk Özel Alanı Ekle
                      </button>
                    )}
                  </td>
                </tr>
              ) : (
                fields.map((field, index) => (
                  <tr
                    key={field.id}
                    draggable={canUpdate}
                    onDragStart={() => handleDragStart(index)}
                    onDragOver={(e) => handleDragOver(e, index)}
                    onDrop={handleDrop}
                    style={{
                      opacity: draggedItem === index ? 0.5 : 1,
                      background: dragOverItem === index ? 'var(--primary-soft)' : undefined,
                    }}
                  >
                    <td>
                      {canUpdate && (
                        <span style={{ cursor: 'grab', color: 'var(--text-tertiary)' }}>
                          <BsGripVertical />
                        </span>
                      )}
                    </td>
                    <td>
                      <strong>{field.field_label}</strong>
                      {field.help_text && (
                        <p style={{ margin: 0, fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                          {field.help_text}
                        </p>
                      )}
                    </td>
                    <td>
                      <code style={{ fontSize: '0.8rem' }}>{field.field_key}</code>
                    </td>
                    <td>
                      <span className="badge badge-secondary">
                        {fieldTypes.find(t => t.value === field.field_type)?.label || field.field_type}
                      </span>
                    </td>
                    <td>
                      {field.is_required ? (
                        <span className="badge badge-warning">Zorunlu</span>
                      ) : (
                        <span className="badge badge-secondary">Opsiyonel</span>
                      )}
                    </td>
                    <td>
                      {field.show_in_list ? (
                        <span className="badge badge-info">Görünür</span>
                      ) : (
                        <span className="badge badge-secondary">Gizli</span>
                      )}
                    </td>
                    <td>
                      <button
                        className={`btn btn-sm ${field.is_active ? 'btn-success' : 'btn-secondary'}`}
                        onClick={() => handleToggleActive(field)}
                        disabled={!canUpdate}
                        title={field.is_active ? 'Pasif Yap' : 'Aktif Yap'}
                      >
                        {field.is_active ? <BsToggleOn /> : <BsToggleOff />}
                      </button>
                    </td>
                    <td>
                      <div className="btn-group btn-group-sm">
                        {canUpdate && (
                          <button
                            className="btn btn-primary"
                            onClick={() => handleOpenForm(field)}
                            title="Düzenle"
                          >
                            <BsPencil />
                          </button>
                        )}
                        {canDelete && (
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
                        )}
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
        onClose={() => setFormModalOpen(false)}
        title={selectedField ? 'Özel Alanı Düzenle' : 'Yeni Özel Alan'}
        size="lg"
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <div className="row">
            <div className="col-md-6">
              <div className="form-group">
                <label className="form-label">Alan Adı *</label>
                <input
                  type="text"
                  className="form-control"
                  value={formData.field_label}
                  onChange={(e) => {
                    setFormData({
                      ...formData,
                      field_label: e.target.value,
                      field_key: selectedField ? formData.field_key : generateFieldKey(e.target.value),
                    });
                  }}
                  placeholder="Örn: Ehliyet Sınıfı"
                />
              </div>
            </div>
            <div className="col-md-6">
              <div className="form-group">
                <label className="form-label">Alan Anahtarı</label>
                <input
                  type="text"
                  className="form-control"
                  value={formData.field_key}
                  onChange={(e) => setFormData({ ...formData, field_key: e.target.value })}
                  placeholder="ehliyet_sinifi"
                  disabled={!!selectedField}
                />
                <small className="text-muted">Bu anahtar sistem tarafından kullanılır ve değiştirilemez.</small>
              </div>
            </div>
          </div>

          <div className="row">
            <div className="col-md-6">
              <div className="form-group">
                <label className="form-label">Alan Tipi *</label>
                <select
                  className="form-select"
                  value={formData.field_type}
                  onChange={(e) => setFormData({ ...formData, field_type: e.target.value })}
                >
                  {fieldTypes.map((type) => (
                    <option key={type.value} value={type.value}>
                      {type.label}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="col-md-6">
              <div className="form-group">
                <label className="form-label">Varsayılan Değer</label>
                <input
                  type="text"
                  className="form-control"
                  value={formData.default_value}
                  onChange={(e) => setFormData({ ...formData, default_value: e.target.value })}
                  placeholder="Boş bırakılabilir"
                />
              </div>
            </div>
          </div>

          <div className="form-group">
            <label className="form-label">Placeholder</label>
            <input
              type="text"
              className="form-control"
              value={formData.placeholder}
              onChange={(e) => setFormData({ ...formData, placeholder: e.target.value })}
              placeholder="Örn: Ehliyet sınıfınızı seçin"
            />
          </div>

          <div className="form-group">
            <label className="form-label">Yardım Metni</label>
            <input
              type="text"
              className="form-control"
              value={formData.help_text}
              onChange={(e) => setFormData({ ...formData, help_text: e.target.value })}
              placeholder="Bu alan hakkında kısa bir açıklama"
            />
          </div>

          {/* Seçenekler */}
          {needsOptions && (
            <div className="form-group">
              <label className="form-label">Seçenekler</label>
              <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '0.5rem' }}>
                <input
                  type="text"
                  className="form-control"
                  value={newOption}
                  onChange={(e) => setNewOption(e.target.value)}
                  onKeyPress={(e) => e.key === 'Enter' && (e.preventDefault(), addOption())}
                  placeholder="Yeni seçenek ekle"
                />
                <button type="button" className="btn btn-secondary" onClick={addOption}>
                  <BsPlus />
                </button>
              </div>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem' }}>
                {(formData.field_options || []).map((option, index) => (
                  <span
                    key={`${option.value}-${index}`}
                    className="badge badge-secondary"
                    style={{ display: 'flex', alignItems: 'center', gap: '0.25rem', padding: '0.25rem 0.5rem' }}
                  >
                    {option.label}
                    <button
                      type="button"
                      style={{ background: 'none', border: 'none', padding: 0, cursor: 'pointer', marginLeft: '0.25rem' }}
                      onClick={() => removeOption(index)}
                    >
                      ×
                    </button>
                  </span>
                ))}
              </div>
            </div>
          )}

          {/* Ayarlar */}
          <div className="row">
            <div className="col-md-6">
              <div className="form-check mb-2">
                <input
                  type="checkbox"
                  className="form-check-input"
                  id="is_required"
                  checked={formData.is_required}
                  onChange={(e) => setFormData({ ...formData, is_required: e.target.checked })}
                />
                <label className="form-check-label" htmlFor="is_required">Zorunlu Alan</label>
              </div>
              <div className="form-check mb-2">
                <input
                  type="checkbox"
                  className="form-check-input"
                  id="is_unique"
                  checked={formData.is_unique}
                  onChange={(e) => setFormData({ ...formData, is_unique: e.target.checked })}
                />
                <label className="form-check-label" htmlFor="is_unique">Benzersiz Değer</label>
              </div>
              <div className="form-check mb-2">
                <input
                  type="checkbox"
                  className="form-check-input"
                  id="is_active"
                  checked={formData.is_active}
                  onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                />
                <label className="form-check-label" htmlFor="is_active">Aktif</label>
              </div>
            </div>
            <div className="col-md-6">
              <div className="form-check mb-2">
                <input
                  type="checkbox"
                  className="form-check-input"
                  id="show_in_list"
                  checked={formData.show_in_list}
                  onChange={(e) => setFormData({ ...formData, show_in_list: e.target.checked })}
                />
                <label className="form-check-label" htmlFor="show_in_list">Listede Göster</label>
              </div>
              <div className="form-check mb-2">
                <input
                  type="checkbox"
                  className="form-check-input"
                  id="show_in_filter"
                  checked={formData.show_in_filter}
                  onChange={(e) => setFormData({ ...formData, show_in_filter: e.target.checked })}
                />
                <label className="form-check-label" htmlFor="show_in_filter">Filtrede Göster</label>
              </div>
            </div>
          </div>

          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1rem' }}>
            <button className="btn btn-secondary" onClick={() => setFormModalOpen(false)}>
              İptal
            </button>
            <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
              {saving ? 'Kaydediliyor...' : selectedField ? 'Güncelle' : 'Oluştur'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Silme Onay Dialog */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        onConfirm={handleDelete}
        title="Özel Alanı Sil"
        message={`"${selectedField?.field_label}" adlı özel alanı silmek istediğinizden emin misiniz? Bu alana ait tüm veriler kaybolacaktır.`}
        confirmText="Sil"
        variant="danger"
      />
    </div>
  );
};

export default ModuleCustomFieldsPage;

