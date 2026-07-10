import React, { useState, useEffect, useCallback } from 'react';
import Modal from './Modal';
import { adminApi } from '@shared/services/api';
import { getErrorMessage, getValidationErrors } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { BsCheckCircle, BsCircle } from 'react-icons/bs';

interface LicensePackage {
  id?: number;
  name: string;
  slug?: string;
  description: string;
  base_price: number;
  user_limit: number;
  location_limit: number;
  employee_limit: number;
  storage_limit_gb: number;
  duration_months: number | null;
  is_active: boolean;
  modules?: Array<{
    id: number;
    name: string;
    pivot: {
      is_included: boolean;
      additional_price?: number | null;
    };
  }>;
}

interface LicensePackageInput extends Omit<LicensePackage, 'description'> {
  description: string | null;
}

interface Module {
  id: number;
  name: string;
  slug: string;
  is_core: boolean;
  price_monthly: number;
  price_yearly: number;
}

interface PackageFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  packageData?: LicensePackageInput | null;
}

const PackageForm: React.FC<PackageFormProps> = ({
  isOpen,
  onClose,
  onSuccess,
  packageData,
}) => {
  const [loading, setLoading] = useState(false);
  const [modules, setModules] = useState<Module[]>([]);
  const [selectedModules, setSelectedModules] = useState<number[]>([]);
  const [formData, setFormData] = useState<LicensePackage>({
    name: '',
    description: '',
    base_price: 0,
    user_limit: 5,
    location_limit: 1,
    employee_limit: 50,
    storage_limit_gb: 10,
    duration_months: 12,
    is_active: true,
  });

  const isEditing = Boolean(packageData);

  const loadModules = useCallback(async () => {
    try {
      const response = await adminApi.availableModules();
      const moduleList = response.data.data || [];
      setModules(moduleList);
      // Select core modules by default for new packages
      if (!isEditing) {
        const coreModuleIds = moduleList
          .filter((m: Module) => m.is_core)
          .map((m: Module) => m.id);
        setSelectedModules(coreModuleIds);
      }
    } catch (error) {
      console.error('Modüller yüklenemedi:', error);
    }
  }, [isEditing]);

  useEffect(() => {
    if (isOpen) {
      loadModules();
      if (packageData) {
        setFormData({
          ...packageData,
          description: packageData.description || '', // null kontrolü
        });
        // Load package modules if editing
        if (packageData.modules && Array.isArray(packageData.modules)) {
          const moduleIds = packageData.modules
            .filter((m) => m.pivot?.is_included)
            .map((m) => m.id);
          setSelectedModules(moduleIds);
        }
      } else {
        setFormData({
          name: '',
          description: '',
          base_price: 0,
          user_limit: 5,
          location_limit: 1,
          employee_limit: 50,
          storage_limit_gb: 10,
          duration_months: 12,
          is_active: true,
        });
        setSelectedModules([]);
      }
    }
  }, [isOpen, packageData, loadModules]);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const { name, value, type } = e.target;
    
    if (name === 'duration_months') {
      // Süresiz seçildiğinde null yap
      setFormData((prev) => ({
        ...prev,
        [name]: value === '' ? null : parseInt(value, 10),
      }));
    } else if (type === 'number') {
      setFormData((prev) => ({
        ...prev,
        [name]: parseFloat(value) || 0,
      }));
    } else {
      setFormData((prev) => ({
        ...prev,
        [name]: value,
      }));
    }
  };

  const handleCheckboxChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, checked } = e.target;
    setFormData((prev) => ({ ...prev, [name]: checked }));
  };

  const toggleModule = (moduleId: number) => {
    setSelectedModules((prev) =>
      prev.includes(moduleId)
        ? prev.filter((id) => id !== moduleId)
        : [...prev, moduleId]
    );
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const dataToSend: Record<string, unknown> = {
        name: formData.name,
        description: formData.description || null,
        base_price: Number(formData.base_price),
        user_limit: Number(formData.user_limit),
        location_limit: Number(formData.location_limit),
        employee_limit: Number(formData.employee_limit),
        storage_limit_gb: Number(formData.storage_limit_gb),
        is_active: Boolean(formData.is_active),
        is_featured: false, // Backend validation'da sometimes yok, default değer
        sort_order: 0, // Backend validation'da sometimes yok, default değer
        module_ids: selectedModules, // Backend module_ids array'i bekliyor
      };
      
      // duration_months için - null ise null gönder, undefined ise hiç gönderme
      if (formData.duration_months !== undefined) {
        dataToSend.duration_months = formData.duration_months === null ? null : Number(formData.duration_months);
      }

      console.log('Gönderilen veri:', dataToSend);

      if (packageData?.id) {
        await adminApi.licensePackages.update(packageData.id, dataToSend);
        toast.success('Paket başarıyla güncellendi');
      } else {
        await adminApi.licensePackages.create(dataToSend);
        toast.success('Paket başarıyla oluşturuldu');
      }
      onSuccess();
      onClose();
    } catch (error: unknown) {
      console.error('Paket kaydetme hatası:', error);

      const validationErrors = getValidationErrors(error);
      let errorMessage = getErrorMessage(error, 'İşlem sırasında bir hata oluştu');

      if (validationErrors) {
        errorMessage = Object.values(validationErrors).join(', ') || errorMessage;
      }

      toast.error(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('tr-TR', {
      style: 'currency',
      currency: 'TRY',
    }).format(amount);
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={packageData ? 'Paket Düzenle' : 'Yeni Paket'}
      size="lg"
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
            form="package-form"
            className="btn btn-primary"
            disabled={loading}
          >
            {loading ? 'Kaydediliyor...' : packageData ? 'Güncelle' : 'Oluştur'}
          </button>
        </>
      }
    >
      <form id="package-form" onSubmit={handleSubmit}>
        <div className="row">
          {/* Temel Bilgiler */}
          <div className="col-md-6">
            <h4 className="mb-3" style={{ fontSize: '1rem', fontWeight: 600 }}>
              Paket Bilgileri
            </h4>

            <div className="form-group">
              <label className="form-label">Paket Adı *</label>
              <input
                type="text"
                className="form-control"
                name="name"
                value={formData.name}
                onChange={handleChange}
                required
                placeholder="Örn: Başlangıç Paketi"
              />
            </div>

            <div className="form-group">
              <label className="form-label">Açıklama</label>
              <textarea
                className="form-control"
                name="description"
                value={formData.description || ''}
                onChange={handleChange}
                rows={3}
                placeholder="Paket açıklaması..."
              />
            </div>

            <div className="row">
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Fiyat (₺) *</label>
                  <input
                    type="number"
                    className="form-control"
                    name="base_price"
                    value={formData.base_price}
                    onChange={handleChange}
                    required
                    min="0"
                    step="0.01"
                  />
                </div>
              </div>
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Süre (Ay)</label>
                  <select
                    className="form-select"
                    name="duration_months"
                    value={formData.duration_months || ''}
                    onChange={handleChange}
                  >
                    <option value="">Süresiz</option>
                    <option value="1">1 Ay</option>
                    <option value="3">3 Ay</option>
                    <option value="6">6 Ay</option>
                    <option value="12">12 Ay</option>
                    <option value="24">24 Ay</option>
                  </select>
                </div>
              </div>
            </div>

            <h4 className="mb-3 mt-4" style={{ fontSize: '1rem', fontWeight: 600 }}>
              Limitler
            </h4>

            <div className="row">
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Kullanıcı Limiti</label>
                  <input
                    type="number"
                    className="form-control"
                    name="user_limit"
                    value={formData.user_limit}
                    onChange={handleChange}
                    min="1"
                  />
                </div>
              </div>
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Şube Limiti</label>
                  <input
                    type="number"
                    className="form-control"
                    name="location_limit"
                    value={formData.location_limit}
                    onChange={handleChange}
                    min="1"
                  />
                </div>
              </div>
            </div>

            <div className="row">
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Personel Limiti</label>
                  <input
                    type="number"
                    className="form-control"
                    name="employee_limit"
                    value={formData.employee_limit}
                    onChange={handleChange}
                    min="1"
                  />
                </div>
              </div>
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Depolama (GB)</label>
                  <input
                    type="number"
                    className="form-control"
                    name="storage_limit_gb"
                    value={formData.storage_limit_gb}
                    onChange={handleChange}
                    min="1"
                  />
                </div>
              </div>
            </div>

            <div className="form-check mt-3">
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
          </div>

          {/* Modüller */}
          <div className="col-md-6">
            <h4 className="mb-3" style={{ fontSize: '1rem', fontWeight: 600 }}>
              Dahil Modüller
            </h4>

            <div className="list-group">
              {modules.map((module) => (
                <div
                  key={module.id}
                  className={`d-flex align-items-center gap-3 p-3 rounded mb-2 ${
                    selectedModules.includes(module.id)
                      ? 'border border-primary'
                      : 'border'
                  }`}
                  style={{
                    cursor: module.is_core ? 'default' : 'pointer',
                    opacity: module.is_core ? 0.8 : 1,
                    background: selectedModules.includes(module.id)
                      ? 'var(--primary-soft)'
                      : 'var(--surface-glass)',
                  }}
                  onClick={() => !module.is_core && toggleModule(module.id)}
                >
                  <div className="text-primary">
                    {selectedModules.includes(module.id) ? (
                      <BsCheckCircle size={20} />
                    ) : (
                      <BsCircle size={20} />
                    )}
                  </div>
                  <div className="flex-1">
                    <div className="fw-semibold">{module.name}</div>
                    <div className="small text-muted">
                      {module.is_core ? 'Çekirdek Modül' : module.price_monthly > 0 ? `${formatCurrency(module.price_monthly)}/ay` : 'Ücretsiz'}
                    </div>
                  </div>
                  {module.is_core && (
                    <span className="status-badge active smaller">Zorunlu</span>
                  )}
                </div>
              ))}

              {modules.length === 0 && (
                <div className="text-center text-muted py-4">
                  Modül bulunamadı
                </div>
              )}
            </div>
          </div>
        </div>
      </form>
    </Modal>
  );
};

export default PackageForm;

