import React, { useState, useEffect, useCallback } from 'react';
import { adminApi } from '../../services/api';
import toast from 'react-hot-toast';
import { BsPlus, BsPencil, BsTrash, BsCopy, BsCheck2Circle, BsXCircle, BsBox, BsPeople, BsGeoAlt, BsHdd, BsClock } from 'react-icons/bs';

interface Module {
  id: number;
  name: string;
  slug: string;
  is_core: boolean;
  pivot?: {
    is_included: boolean;
    additional_price: number;
  };
}

interface LicensePackage {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  base_price: number;
  annual_price: number | null;
  user_limit: number;
  location_limit: number;
  employee_limit: number;
  storage_limit_gb: number;
  duration_months: number;
  is_active: boolean;
  is_featured: boolean;
  sort_order: number;
  features: string[] | null;
  modules: Module[];
  companies_count?: number;
}

interface PackageFormData {
  name: string;
  description: string;
  base_price: number;
  annual_price: number | null;
  user_limit: number;
  location_limit: number;
  employee_limit: number;
  storage_limit_gb: number;
  duration_months: number;
  is_active: boolean;
  is_featured: boolean;
  sort_order: number;
  features: string[];
  module_ids: number[];
}

const initialFormData: PackageFormData = {
  name: '',
  description: '',
  base_price: 0,
  annual_price: null,
  user_limit: 5,
  location_limit: 1,
  employee_limit: 50,
  storage_limit_gb: 5,
  duration_months: 12,
  is_active: true,
  is_featured: false,
  sort_order: 0,
  features: [],
  module_ids: [],
};

const AdminPackagesPage: React.FC = () => {
  const [packages, setPackages] = useState<LicensePackage[]>([]);
  const [modules, setModules] = useState<Module[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editingPackage, setEditingPackage] = useState<LicensePackage | null>(null);
  const [formData, setFormData] = useState<PackageFormData>(initialFormData);
  const [featureInput, setFeatureInput] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const loadPackages = useCallback(async () => {
    try {
      setLoading(true);
      const response = await adminApi.licensePackages.list();
      setPackages(response.data.data || []);
    } catch (error) {
      console.error('Paketler yüklenemedi:', error);
      toast.error('Paketler yüklenirken hata oluştu');
    } finally {
      setLoading(false);
    }
  }, []);

  const loadModules = async () => {
    try {
      const response = await adminApi.availableModules();
      setModules(response.data.data || []);
    } catch (error) {
      console.error('Modüller yüklenemedi:', error);
    }
  };

  useEffect(() => {
    loadPackages();
    loadModules();
  }, [loadPackages]);

  const handleAddClick = () => {
    setEditingPackage(null);
    setFormData(initialFormData);
    setShowModal(true);
  };

  const handleEditClick = (pkg: LicensePackage) => {
    setEditingPackage(pkg);
    setFormData({
      name: pkg.name,
      description: pkg.description || '',
      base_price: pkg.base_price,
      annual_price: pkg.annual_price,
      user_limit: pkg.user_limit,
      location_limit: pkg.location_limit,
      employee_limit: pkg.employee_limit,
      storage_limit_gb: pkg.storage_limit_gb,
      duration_months: pkg.duration_months,
      is_active: pkg.is_active,
      is_featured: pkg.is_featured,
      sort_order: pkg.sort_order,
      features: pkg.features || [],
      module_ids: pkg.modules?.filter(m => m.pivot?.is_included).map(m => m.id) || [],
    });
    setShowModal(true);
  };

  const handleDuplicate = async (pkg: LicensePackage) => {
    try {
      await adminApi.licensePackages.duplicate(pkg.id);
      toast.success('Paket kopyalandı');
      loadPackages();
    } catch (error) {
      toast.error('Paket kopyalanamadı');
    }
  };

  const handleDelete = async (pkg: LicensePackage) => {
    if (pkg.companies_count && pkg.companies_count > 0) {
      toast.error('Bu pakete bağlı firmalar var. Önce firmaları başka pakete taşıyın.');
      return;
    }
    
    if (!confirm(`"${pkg.name}" paketini silmek istediğinize emin misiniz?`)) return;
    
    try {
      await adminApi.licensePackages.delete(pkg.id);
      toast.success('Paket silindi');
      loadPackages();
    } catch (error) {
      toast.error('Paket silinemedi');
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);

    try {
      if (editingPackage) {
        await adminApi.licensePackages.update(editingPackage.id, formData);
        // Modülleri ayrıca güncelle
        const moduleData = modules.map(m => ({
          id: m.id,
          is_included: m.is_core || formData.module_ids.includes(m.id),
          additional_price: 0,
        }));
        await adminApi.licensePackages.syncModules(editingPackage.id, moduleData);
        toast.success('Paket güncellendi');
      } else {
        await adminApi.licensePackages.create(formData);
        toast.success('Paket oluşturuldu');
      }
      setShowModal(false);
      loadPackages();
    } catch (error) {
      toast.error('İşlem başarısız');
    } finally {
      setSubmitting(false);
    }
  };

  const addFeature = () => {
    if (featureInput.trim()) {
      setFormData({ ...formData, features: [...formData.features, featureInput.trim()] });
      setFeatureInput('');
    }
  };

  const removeFeature = (index: number) => {
    setFormData({ ...formData, features: formData.features.filter((_, i) => i !== index) });
  };

  const formatLimit = (value: number) => value === 0 ? 'Sınırsız' : value.toString();
  
  const formatPrice = (value: number) => {
    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(value);
  };

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Lisans Paketleri</h1>
          <p className="page-subtitle">Satılabilir lisans paketlerini yönetin</p>
        </div>
        <button className="btn btn-primary" onClick={handleAddClick}>
          <BsPlus size={20} />
          Yeni Paket
        </button>
      </div>

      {/* Packages Grid */}
      {loading ? (
        <div className="text-center py-8">
          <div className="spinner-border text-primary" role="status">
            <span className="visually-hidden">Yükleniyor...</span>
          </div>
        </div>
      ) : packages.length === 0 ? (
        <div className="card">
          <div className="card-body empty-state">
            <div className="empty-state-icon"><BsBox size={48} /></div>
            <h3 className="empty-state-title">Henüz paket yok</h3>
            <p className="empty-state-text">İlk lisans paketinizi oluşturun</p>
            <button className="btn btn-primary" onClick={handleAddClick}>
              <BsPlus size={20} /> Paket Oluştur
            </button>
          </div>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {packages.map((pkg) => (
            <div key={pkg.id} className={`card ${pkg.is_featured ? 'ring-2 ring-[var(--primary)]' : ''}`}>
              <div className="card-header">
                <div className="flex items-center gap-2">
                  <h3 className="card-title">{pkg.name}</h3>
                  {pkg.is_featured && <span className="badge badge-primary">Önerilen</span>}
                  {!pkg.is_active && <span className="badge badge-secondary">Pasif</span>}
                </div>
                <div className="flex gap-1">
                  <button className="btn btn-sm btn-ghost" title="Düzenle" onClick={() => handleEditClick(pkg)}>
                    <BsPencil />
                  </button>
                  <button className="btn btn-sm btn-ghost" title="Kopyala" onClick={() => handleDuplicate(pkg)}>
                    <BsCopy />
                  </button>
                  <button className="btn btn-sm btn-ghost text-danger" title="Sil" onClick={() => handleDelete(pkg)}>
                    <BsTrash />
                  </button>
                </div>
              </div>
              <div className="card-body">
                {pkg.description && (
                  <p className="text-sm text-[var(--text-secondary)] mb-4">{pkg.description}</p>
                )}
                
                {/* Fiyat */}
                <div className="mb-4">
                  <div className="text-2xl font-bold text-[var(--primary)]">
                    {formatPrice(pkg.base_price)}
                    <span className="text-sm font-normal text-[var(--text-secondary)]">/ay</span>
                  </div>
                  {pkg.annual_price && (
                    <div className="text-sm text-[var(--text-tertiary)]">
                      Yıllık: {formatPrice(pkg.annual_price)}
                    </div>
                  )}
                </div>

                {/* Limitler */}
                <div className="space-y-2 mb-4">
                  <div className="flex items-center gap-2 text-sm">
                    <BsPeople className="text-[var(--text-tertiary)]" />
                    <span>{formatLimit(pkg.user_limit)} Kullanıcı</span>
                  </div>
                  <div className="flex items-center gap-2 text-sm">
                    <BsGeoAlt className="text-[var(--text-tertiary)]" />
                    <span>{formatLimit(pkg.location_limit)} Lokasyon</span>
                  </div>
                  <div className="flex items-center gap-2 text-sm">
                    <BsPeople className="text-[var(--text-tertiary)]" />
                    <span>{formatLimit(pkg.employee_limit)} Personel</span>
                  </div>
                  <div className="flex items-center gap-2 text-sm">
                    <BsHdd className="text-[var(--text-tertiary)]" />
                    <span>{pkg.storage_limit_gb === 0 ? 'Sınırsız' : `${pkg.storage_limit_gb} GB`} Depolama</span>
                  </div>
                  <div className="flex items-center gap-2 text-sm">
                    <BsClock className="text-[var(--text-tertiary)]" />
                    <span>{pkg.duration_months} Ay</span>
                  </div>
                </div>

                {/* Modüller */}
                <div className="mb-4">
                  <div className="text-xs font-semibold text-[var(--text-tertiary)] uppercase mb-2">Dahil Modüller</div>
                  <div className="flex flex-wrap gap-1">
                    {pkg.modules?.filter(m => m.pivot?.is_included).map(m => (
                      <span key={m.id} className={`badge ${m.is_core ? 'badge-secondary' : 'badge-primary'}`}>
                        {m.name}
                      </span>
                    ))}
                  </div>
                </div>

                {/* Firma Sayısı */}
                <div className="pt-4 border-t border-[var(--border-primary)]">
                  <div className="text-sm text-[var(--text-secondary)]">
                    <strong>{pkg.companies_count || 0}</strong> firma kullanıyor
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Modal */}
      {showModal && (
        <div className="modal-backdrop show" onClick={() => setShowModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog modal-lg" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">
                    {editingPackage ? 'Paket Düzenle' : 'Yeni Paket'}
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setShowModal(false)}></button>
                </div>
                <form onSubmit={handleSubmit}>
                  <div className="modal-body" style={{ maxHeight: '70vh', overflowY: 'auto' }}>
                    <div className="row g-3">
                      {/* Temel Bilgiler */}
                      <div className="col-md-8">
                        <label className="form-label">Paket Adı *</label>
                        <input
                          type="text"
                          className="form-control"
                          value={formData.name}
                          onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                          required
                        />
                      </div>
                      <div className="col-md-4">
                        <label className="form-label">Sıralama</label>
                        <input
                          type="number"
                          className="form-control"
                          value={formData.sort_order}
                          onChange={(e) => setFormData({ ...formData, sort_order: parseInt(e.target.value) || 0 })}
                        />
                      </div>
                      <div className="col-12">
                        <label className="form-label">Açıklama</label>
                        <textarea
                          className="form-control"
                          rows={2}
                          value={formData.description}
                          onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                        />
                      </div>

                      {/* Fiyatlandırma */}
                      <div className="col-12">
                        <h6 className="text-[var(--primary)] mb-3 mt-2">Fiyatlandırma</h6>
                      </div>
                      <div className="col-md-4">
                        <label className="form-label">Aylık Fiyat (₺) *</label>
                        <input
                          type="number"
                          className="form-control"
                          step="0.01"
                          min="0"
                          value={formData.base_price}
                          onChange={(e) => setFormData({ ...formData, base_price: parseFloat(e.target.value) || 0 })}
                          required
                        />
                      </div>
                      <div className="col-md-4">
                        <label className="form-label">Yıllık Fiyat (₺)</label>
                        <input
                          type="number"
                          className="form-control"
                          step="0.01"
                          min="0"
                          value={formData.annual_price || ''}
                          onChange={(e) => setFormData({ ...formData, annual_price: e.target.value ? parseFloat(e.target.value) : null })}
                          placeholder="Boş bırakılırsa otomatik hesaplanır"
                        />
                      </div>
                      <div className="col-md-4">
                        <label className="form-label">Varsayılan Süre (Ay)</label>
                        <input
                          type="number"
                          className="form-control"
                          min="1"
                          max="60"
                          value={formData.duration_months}
                          onChange={(e) => setFormData({ ...formData, duration_months: parseInt(e.target.value) || 12 })}
                        />
                      </div>

                      {/* Limitler */}
                      <div className="col-12">
                        <h6 className="text-[var(--primary)] mb-3 mt-2">Limitler (0 = Sınırsız)</h6>
                      </div>
                      <div className="col-md-3">
                        <label className="form-label">Kullanıcı</label>
                        <input
                          type="number"
                          className="form-control"
                          min="0"
                          value={formData.user_limit}
                          onChange={(e) => setFormData({ ...formData, user_limit: parseInt(e.target.value) || 0 })}
                        />
                      </div>
                      <div className="col-md-3">
                        <label className="form-label">Lokasyon</label>
                        <input
                          type="number"
                          className="form-control"
                          min="0"
                          value={formData.location_limit}
                          onChange={(e) => setFormData({ ...formData, location_limit: parseInt(e.target.value) || 0 })}
                        />
                      </div>
                      <div className="col-md-3">
                        <label className="form-label">Personel</label>
                        <input
                          type="number"
                          className="form-control"
                          min="0"
                          value={formData.employee_limit}
                          onChange={(e) => setFormData({ ...formData, employee_limit: parseInt(e.target.value) || 0 })}
                        />
                      </div>
                      <div className="col-md-3">
                        <label className="form-label">Depolama (GB)</label>
                        <input
                          type="number"
                          className="form-control"
                          min="0"
                          value={formData.storage_limit_gb}
                          onChange={(e) => setFormData({ ...formData, storage_limit_gb: parseInt(e.target.value) || 0 })}
                        />
                      </div>

                      {/* Modüller */}
                      <div className="col-12">
                        <h6 className="text-[var(--primary)] mb-3 mt-2">Dahil Modüller</h6>
                        <div className="row g-2">
                          {modules.map((module) => (
                            <div key={module.id} className="col-md-6">
                              <label className="form-check d-flex align-items-center gap-2 p-2 border rounded" style={{ background: 'var(--surface-secondary)', borderColor: 'var(--border-primary)' }}>
                                <input
                                  type="checkbox"
                                  className="form-check-input"
                                  checked={module.is_core || formData.module_ids.includes(module.id)}
                                  disabled={module.is_core}
                                  onChange={(e) => {
                                    if (e.target.checked) {
                                      setFormData({ ...formData, module_ids: [...formData.module_ids, module.id] });
                                    } else {
                                      setFormData({ ...formData, module_ids: formData.module_ids.filter(id => id !== module.id) });
                                    }
                                  }}
                                />
                                <span className="form-check-label">
                                  {module.name}
                                  {module.is_core && <span className="badge badge-secondary ms-2">Core</span>}
                                </span>
                              </label>
                            </div>
                          ))}
                        </div>
                      </div>

                      {/* Özellikler */}
                      <div className="col-12">
                        <h6 className="text-[var(--primary)] mb-3 mt-2">Özellik Listesi (UI için)</h6>
                        <div className="input-group mb-2">
                          <input
                            type="text"
                            className="form-control"
                            placeholder="Özellik ekle..."
                            value={featureInput}
                            onChange={(e) => setFeatureInput(e.target.value)}
                            onKeyPress={(e) => e.key === 'Enter' && (e.preventDefault(), addFeature())}
                          />
                          <button type="button" className="btn btn-secondary" onClick={addFeature}>Ekle</button>
                        </div>
                        <div className="d-flex flex-wrap gap-2">
                          {formData.features.map((feature, index) => (
                            <span key={index} className="badge badge-info d-flex align-items-center gap-1">
                              <BsCheck2Circle /> {feature}
                              <button type="button" className="btn-close btn-close-white" style={{ fontSize: '0.5rem' }} onClick={() => removeFeature(index)}></button>
                            </span>
                          ))}
                        </div>
                      </div>

                      {/* Durum */}
                      <div className="col-12">
                        <div className="d-flex gap-4 mt-3">
                          <label className="form-check">
                            <input
                              type="checkbox"
                              className="form-check-input"
                              checked={formData.is_active}
                              onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                            />
                            <span className="form-check-label">Aktif</span>
                          </label>
                          <label className="form-check">
                            <input
                              type="checkbox"
                              className="form-check-input"
                              checked={formData.is_featured}
                              onChange={(e) => setFormData({ ...formData, is_featured: e.target.checked })}
                            />
                            <span className="form-check-label">Öne Çıkan</span>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div className="modal-footer">
                    <button type="button" className="btn btn-secondary" onClick={() => setShowModal(false)}>
                      İptal
                    </button>
                    <button type="submit" className="btn btn-primary" disabled={submitting}>
                      {submitting ? 'Kaydediliyor...' : 'Kaydet'}
                    </button>
                  </div>
                </form>
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
        .modal.show { position: relative; z-index: 1051; }
        .modal-dialog { margin: 0; max-height: 90vh; }
        .modal-content {
          background: var(--surface-primary);
          border: 1px solid var(--border-primary);
          border-radius: 12px;
          max-height: 90vh;
          overflow: hidden;
        }
        .modal-header { border-bottom: 1px solid var(--border-primary); padding: 1rem 1.5rem; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { border-top: 1px solid var(--border-primary); padding: 1rem 1.5rem; }
      `}</style>
    </div>
  );
};

export default AdminPackagesPage;

