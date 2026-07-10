import React, { useState, useEffect } from 'react';
import { companyApi } from '../../services/api';
import toast from 'react-hot-toast';

interface CompanyData {
  id: number;
  name: string;
  legal_name: string | null;
  tax_office: string | null;
  tax_number: string | null;
  phone: string | null;
  email: string | null;
  website: string | null;
  address: string | null;
  city: string | null;
  district: string | null;
  sector: string | null;
  employee_count: string | null;
  package_type: string;
  user_limit: number;
  current_users: number;
  license_end_date: string | null;
  status: string;
}

interface Module {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  icon: string | null;
  is_core: boolean;
  is_active: boolean;
}

const CompanySettingsPage: React.FC = () => {
  const [company, setCompany] = useState<CompanyData | null>(null);
  const [modules, setModules] = useState<Module[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [activeTab, setActiveTab] = useState<'general' | 'modules' | 'subscription'>('general');
  
  const [formData, setFormData] = useState({
    name: '',
    legal_name: '',
    tax_office: '',
    tax_number: '',
    phone: '',
    email: '',
    website: '',
    address: '',
    city: '',
    district: '',
    sector: '',
    employee_count: '',
  });

  useEffect(() => {
    loadCompany();
    loadModules();
  }, []);

  const loadCompany = async () => {
    try {
      setLoading(true);
      const response = await companyApi.get();
      const data = response.data.data;
      setCompany(data);
      setFormData({
        name: data.name || '',
        legal_name: data.legal_name || '',
        tax_office: data.tax_office || '',
        tax_number: data.tax_number || '',
        phone: data.phone || '',
        email: data.email || '',
        website: data.website || '',
        address: data.address || '',
        city: data.city || '',
        district: data.district || '',
        sector: data.sector || '',
        employee_count: data.employee_count || '',
      });
    } catch (error) {
      console.error('Firma bilgileri yüklenemedi:', error);
      toast.error('Firma bilgileri yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const loadModules = async () => {
    try {
      const response = await companyApi.modules();
      setModules(response.data.data || []);
    } catch (error) {
      console.error('Modüller yüklenemedi:', error);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await companyApi.update(formData);
      toast.success('Firma bilgileri güncellendi');
      loadCompany();
    } catch (error) {
      console.error('Güncelleme hatası:', error);
      toast.error('Güncelleme sırasında bir hata oluştu');
    } finally {
      setSaving(false);
    }
  };

  const getPackageLabel = (pkg: string) => {
    const labels: Record<string, string> = {
      starter: 'Starter',
      professional: 'Professional',
      enterprise: 'Enterprise',
    };
    return labels[pkg] || pkg;
  };

  const getStatusBadge = (status: string) => {
    const badges: Record<string, { class: string; text: string }> = {
      active: { class: 'badge-success', text: 'Aktif' },
      trial: { class: 'badge-warning', text: 'Deneme' },
      suspended: { class: 'badge-danger', text: 'Askıda' },
      cancelled: { class: 'badge-secondary', text: 'İptal' },
    };
    return badges[status] || { class: 'badge-secondary', text: status };
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="spinner-border text-primary" role="status">
          <span className="visually-hidden">Yükleniyor...</span>
        </div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Firma Ayarları</h1>
          <p className="page-subtitle">Firma bilgilerini ve ayarlarını yönetin</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="card mb-4">
        <div className="card-body p-0">
          <div className="flex border-b border-[var(--border-primary)]">
            <button
              className={`px-6 py-3 font-medium transition-colors ${
                activeTab === 'general'
                  ? 'text-[var(--primary)] border-b-2 border-[var(--primary)]'
                  : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
              }`}
              onClick={() => setActiveTab('general')}
            >
              <i className="bi bi-building me-2"></i>
              Genel Bilgiler
            </button>
            <button
              className={`px-6 py-3 font-medium transition-colors ${
                activeTab === 'modules'
                  ? 'text-[var(--primary)] border-b-2 border-[var(--primary)]'
                  : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
              }`}
              onClick={() => setActiveTab('modules')}
            >
              <i className="bi bi-grid-3x3-gap me-2"></i>
              Modüller
            </button>
            <button
              className={`px-6 py-3 font-medium transition-colors ${
                activeTab === 'subscription'
                  ? 'text-[var(--primary)] border-b-2 border-[var(--primary)]'
                  : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
              }`}
              onClick={() => setActiveTab('subscription')}
            >
              <i className="bi bi-credit-card me-2"></i>
              Abonelik
            </button>
          </div>
        </div>
      </div>

      {/* Genel Bilgiler Tab */}
      {activeTab === 'general' && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Firma Bilgileri</h3>
          </div>
          <div className="card-body">
            <form onSubmit={handleSubmit}>
              <div className="row g-4">
                <div className="col-md-6">
                  <label className="form-label">Firma Adı *</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    required
                  />
                </div>
                <div className="col-md-6">
                  <label className="form-label">Resmi Ünvan</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.legal_name}
                    onChange={(e) => setFormData({ ...formData, legal_name: e.target.value })}
                  />
                </div>
                <div className="col-md-6">
                  <label className="form-label">Vergi Dairesi</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.tax_office}
                    onChange={(e) => setFormData({ ...formData, tax_office: e.target.value })}
                  />
                </div>
                <div className="col-md-6">
                  <label className="form-label">Vergi Numarası</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.tax_number}
                    onChange={(e) => setFormData({ ...formData, tax_number: e.target.value })}
                  />
                </div>
                <div className="col-md-6">
                  <label className="form-label">E-posta</label>
                  <input
                    type="email"
                    className="form-control"
                    value={formData.email}
                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  />
                </div>
                <div className="col-md-6">
                  <label className="form-label">Telefon</label>
                  <input
                    type="tel"
                    className="form-control"
                    value={formData.phone}
                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                  />
                </div>
                <div className="col-md-6">
                  <label className="form-label">Web Sitesi</label>
                  <input
                    type="url"
                    className="form-control"
                    placeholder="https://"
                    value={formData.website}
                    onChange={(e) => setFormData({ ...formData, website: e.target.value })}
                  />
                </div>
                <div className="col-md-6">
                  <label className="form-label">Sektör</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.sector}
                    onChange={(e) => setFormData({ ...formData, sector: e.target.value })}
                  />
                </div>
                <div className="col-md-6">
                  <label className="form-label">Şehir</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.city}
                    onChange={(e) => setFormData({ ...formData, city: e.target.value })}
                  />
                </div>
                <div className="col-md-6">
                  <label className="form-label">İlçe</label>
                  <input
                    type="text"
                    className="form-control"
                    value={formData.district}
                    onChange={(e) => setFormData({ ...formData, district: e.target.value })}
                  />
                </div>
                <div className="col-12">
                  <label className="form-label">Adres</label>
                  <textarea
                    className="form-control"
                    rows={3}
                    value={formData.address}
                    onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                  />
                </div>
                <div className="col-md-6">
                  <label className="form-label">Çalışan Sayısı</label>
                  <select
                    className="form-select"
                    value={formData.employee_count}
                    onChange={(e) => setFormData({ ...formData, employee_count: e.target.value })}
                  >
                    <option value="">Seçiniz</option>
                    <option value="1-10">1-10</option>
                    <option value="11-50">11-50</option>
                    <option value="51-200">51-200</option>
                    <option value="201-500">201-500</option>
                    <option value="500+">500+</option>
                  </select>
                </div>
              </div>
              <div className="mt-4 pt-4 border-t border-[var(--border-primary)]">
                <button type="submit" className="btn btn-primary" disabled={saving}>
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
            </form>
          </div>
        </div>
      )}

      {/* Modüller Tab */}
      {activeTab === 'modules' && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Aktif Modüller</h3>
          </div>
          <div className="card-body">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {modules.map((module) => (
                <div
                  key={module.id}
                  className={`p-4 rounded-lg border ${
                    module.is_active
                      ? 'border-[var(--primary)] bg-[var(--primary)]/10'
                      : 'border-[var(--border-primary)] opacity-50'
                  }`}
                >
                  <div className="flex items-center gap-3 mb-2">
                    <div className="w-10 h-10 rounded-lg bg-[var(--primary)] flex items-center justify-center text-white">
                      <i className={`bi bi-${module.icon || 'box'}`}></i>
                    </div>
                    <div>
                      <h4 className="font-semibold">{module.name}</h4>
                      <span className={`badge ${module.is_active ? 'badge-success' : 'badge-secondary'}`}>
                        {module.is_active ? 'Aktif' : 'Pasif'}
                      </span>
                    </div>
                  </div>
                  {module.description && (
                    <p className="text-sm text-[var(--text-secondary)]">{module.description}</p>
                  )}
                  {module.is_core && (
                    <span className="text-xs text-[var(--text-muted)]">
                      <i className="bi bi-lock me-1"></i>
                      Temel modül
                    </span>
                  )}
                </div>
              ))}
            </div>
            {modules.length === 0 && (
              <div className="text-center py-8 text-[var(--text-secondary)]">
                <i className="bi bi-grid-3x3-gap text-4xl mb-2"></i>
                <p>Henüz modül yok</p>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Abonelik Tab */}
      {activeTab === 'subscription' && company && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Mevcut Paket</h3>
            </div>
            <div className="card-body">
              <div className="flex items-center gap-4 mb-4">
                <div className="w-16 h-16 rounded-xl bg-gradient-to-br from-[var(--primary)] to-purple-600 flex items-center justify-center text-white text-2xl">
                  <i className="bi bi-gem"></i>
                </div>
                <div>
                  <h2 className="text-2xl font-bold">{getPackageLabel(company.package_type)}</h2>
                  <span className={`badge ${getStatusBadge(company.status).class}`}>
                    {getStatusBadge(company.status).text}
                  </span>
                </div>
              </div>
              <div className="space-y-3">
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">Kullanıcı Limiti</span>
                  <span className="font-semibold">{company.current_users} / {company.user_limit}</span>
                </div>
                <div className="w-full bg-[var(--surface-secondary)] rounded-full h-2">
                  <div
                    className="bg-[var(--primary)] h-2 rounded-full transition-all"
                    style={{ width: `${(company.current_users / company.user_limit) * 100}%` }}
                  />
                </div>
                {company.license_end_date && (
                  <div className="flex justify-between pt-2">
                    <span className="text-[var(--text-secondary)]">Lisans Bitiş</span>
                    <span className="font-semibold">
                      {new Date(company.license_end_date).toLocaleDateString('tr-TR')}
                    </span>
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Paket Yükselt</h3>
            </div>
            <div className="card-body">
              <p className="text-[var(--text-secondary)] mb-4">
                Daha fazla özellik ve kullanıcı limiti için paketinizi yükseltin.
              </p>
              <div className="space-y-3">
                <div className="p-3 rounded-lg border border-[var(--border-primary)] flex items-center justify-between">
                  <div>
                    <div className="font-semibold">Professional</div>
                    <div className="text-sm text-[var(--text-secondary)]">25 kullanıcı, tüm modüller</div>
                  </div>
                  <button className="btn btn-outline-primary btn-sm">
                    Yükselt
                  </button>
                </div>
                <div className="p-3 rounded-lg border border-[var(--border-primary)] flex items-center justify-between">
                  <div>
                    <div className="font-semibold">Enterprise</div>
                    <div className="text-sm text-[var(--text-secondary)]">Sınırsız kullanıcı, özel destek</div>
                  </div>
                  <button className="btn btn-outline-primary btn-sm">
                    Yükselt
                  </button>
                </div>
              </div>
              <div className="mt-4 pt-4 border-t border-[var(--border-primary)]">
                <p className="text-sm text-[var(--text-muted)]">
                  <i className="bi bi-info-circle me-1"></i>
                  Paket değişikliği için bizimle iletişime geçin.
                </p>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default CompanySettingsPage;

