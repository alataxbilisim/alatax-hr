import React, { useState, useEffect } from 'react';
import Modal from './Modal';
import { adminApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';

interface Module {
  id?: number;
  name: string;
  slug: string;
  description: string;
  icon: string;
  is_active: boolean;
  is_core: boolean;
  price_monthly: number;
  price_yearly: number;
  sort_order: number;
}

/** Liste sayfasından gelen (nullable alanlı) modül */
interface ModuleInput {
  id?: number;
  name: string;
  slug: string;
  description?: string | null;
  icon?: string | null;
  is_active: boolean;
  is_core: boolean;
  price_monthly: number;
  price_yearly: number;
  sort_order: number;
}

interface ModuleFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  module?: ModuleInput | null;
}

const ModuleForm: React.FC<ModuleFormProps> = ({
  isOpen,
  onClose,
  onSuccess,
  module,
}) => {
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState<Module>({
    name: '',
    slug: '',
    description: '',
    icon: 'bi-puzzle',
    is_active: true,
    is_core: false,
    price_monthly: 0,
    price_yearly: 0,
    sort_order: 0,
  });

  useEffect(() => {
    if (isOpen) {
      if (module) {
        setFormData({
          ...module,
          description: module.description ?? '',
          icon: module.icon ?? 'bi-puzzle',
          slug: module.slug || '',
          price_monthly: module.price_monthly || 0,
          price_yearly: module.price_yearly || 0,
          sort_order: module.sort_order || 0,
        });
      } else {
        setFormData({
          name: '',
          slug: '',
          description: '',
          icon: 'bi-puzzle',
          is_active: true,
          is_core: false,
          price_monthly: 0,
          price_yearly: 0,
          sort_order: 0,
        });
      }
    }
  }, [isOpen, module]);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const { name, value, type } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: type === 'number' ? (value === '' ? 0 : parseFloat(value)) : value,
    }));
  };

  // Slug auto-generate from name
  const generateSlug = (name: string) => {
    return name
      .toLowerCase()
      .replace(/ğ/g, 'g')
      .replace(/ü/g, 'u')
      .replace(/ş/g, 's')
      .replace(/ı/g, 'i')
      .replace(/ö/g, 'o')
      .replace(/ç/g, 'c')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  };

  const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const name = e.target.value;
    setFormData((prev) => ({
      ...prev,
      name,
      slug: prev.id ? prev.slug : generateSlug(name), // Only auto-generate for new modules
    }));
  };

  const handleCheckboxChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, checked } = e.target;
    setFormData((prev) => ({ ...prev, [name]: checked }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      if (module?.id) {
        await adminApi.modules.update(module.id, formData);
        toast.success('Modül başarıyla güncellendi');
      } else {
        await adminApi.modules.create(formData);
        toast.success('Modül başarıyla oluşturuldu');
      }
      onSuccess();
      onClose();
    } catch (error: unknown) {
      console.error('Modül kaydetme hatası:', error);
      toast.error(getErrorMessage(error, 'İşlem sırasında bir hata oluştu'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={module ? 'Modül Düzenle' : 'Yeni Modül'}
      size="md"
      footer={
        <>
          <button
            type="button"
            className="btn btn-secondary"
            onClick={onClose}
            disabled={loading}
          >
            İptal
          </button>
          <button
            type="submit"
            form="module-form"
            className="btn btn-primary"
            disabled={loading}
          >
            {loading ? 'Kaydediliyor...' : module ? 'Güncelle' : 'Oluştur'}
          </button>
        </>
      }
    >
      <form id="module-form" onSubmit={handleSubmit}>
        <div className="row">
          <div className="col-md-6">
            <div className="form-group">
              <label className="form-label">Modül Adı *</label>
              <input
                type="text"
                className="form-control"
                name="name"
                value={formData.name}
                onChange={handleNameChange}
                required
                placeholder="Örn: İş Başvuru Yönetimi"
              />
            </div>
          </div>
          <div className="col-md-6">
            <div className="form-group">
              <label className="form-label">Slug *</label>
              <input
                type="text"
                className="form-control"
                name="slug"
                value={formData.slug}
                onChange={handleChange}
                required
                placeholder="Örn: job-applications"
              />
              <small className="text-muted">URL ve sistem tanımlayıcısı olarak kullanılır</small>
            </div>
          </div>
        </div>

        <div className="form-group">
          <label className="form-label">Açıklama</label>
          <textarea
            className="form-control"
            name="description"
            value={formData.description}
            onChange={handleChange}
            rows={3}
            placeholder="Modül açıklaması..."
          />
        </div>

        <div className="row">
          <div className="col-md-6">
            <div className="form-group">
              <label className="form-label">İkon</label>
              <select
                className="form-select"
                name="icon"
                value={formData.icon}
                onChange={handleChange}
              >
                <option value="bi-puzzle">Puzzle</option>
                <option value="bi-person-badge">Person Badge</option>
                <option value="bi-file-earmark-text">File Text</option>
                <option value="bi-calendar-check">Calendar Check</option>
                <option value="bi-people">People</option>
                <option value="bi-building">Building</option>
                <option value="bi-journal-text">Journal</option>
                <option value="bi-graph-up">Graph</option>
                <option value="bi-mortarboard">Mortarboard</option>
                <option value="bi-laptop">Laptop</option>
                <option value="bi-person-check">Person Check</option>
              </select>
            </div>
          </div>
          <div className="col-md-6">
            <div className="form-group">
              <label className="form-label">Sıralama</label>
              <input
                type="number"
                className="form-control"
                name="sort_order"
                value={formData.sort_order}
                onChange={handleChange}
                min="0"
                placeholder="0"
              />
            </div>
          </div>
        </div>

        <div className="row">
          <div className="col-md-6">
            <div className="form-group">
              <label className="form-label">Aylık Fiyat (₺)</label>
              <input
                type="number"
                className="form-control"
                name="price_monthly"
                value={formData.price_monthly}
                onChange={handleChange}
                min="0"
                step="0.01"
                placeholder="0.00"
              />
            </div>
          </div>
          <div className="col-md-6">
            <div className="form-group">
              <label className="form-label">Yıllık Fiyat (₺)</label>
              <input
                type="number"
                className="form-control"
                name="price_yearly"
                value={formData.price_yearly}
                onChange={handleChange}
                min="0"
                step="0.01"
                placeholder="0.00"
              />
              <small className="text-muted">Genellikle aylık x 10 ay (2 ay indirim)</small>
            </div>
          </div>
        </div>

        <div className="d-flex gap-4 mt-3">
          <div className="form-check">
            <input
              type="checkbox"
              className="form-check-input"
              id="is_active"
              name="is_active"
              checked={formData.is_active}
              onChange={handleCheckboxChange}
            />
            <label className="form-check-label" htmlFor="is_active">
              Aktif
            </label>
          </div>

          <div className="form-check">
            <input
              type="checkbox"
              className="form-check-input"
              id="is_core"
              name="is_core"
              checked={formData.is_core}
              onChange={handleCheckboxChange}
            />
            <label className="form-check-label" htmlFor="is_core">
              Çekirdek Modül
            </label>
          </div>
        </div>

        {formData.is_core && (
          <div className="mt-3 p-3 rounded" style={{ background: 'var(--warning-soft)' }}>
            <small className="text-warning">
              Çekirdek modüller tüm paketlerde zorunlu olarak bulunur ve devre dışı bırakılamaz.
            </small>
          </div>
        )}
      </form>
    </Modal>
  );
};

export default ModuleForm;

