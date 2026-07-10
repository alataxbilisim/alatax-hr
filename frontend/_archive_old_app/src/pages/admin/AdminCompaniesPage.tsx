import React, { useState, useEffect, useCallback } from 'react';
import { adminApi } from '../../services/api';
import toast from 'react-hot-toast';

interface LicensePackage {
  id: number;
  name: string;
  slug: string;
  base_price: number;
  user_limit: number;
  location_limit: number;
  employee_limit: number;
}

interface Company {
  id: number;
  name: string;
  legal_name: string | null;
  email: string | null;
  phone: string | null;
  city: string | null;
  sector: string | null;
  package_type: 'starter' | 'professional' | 'enterprise';
  status: 'active' | 'suspended' | 'cancelled' | 'trial';
  user_limit: number;
  users_count: number;
  trial_ends_at: string | null;
  license_end_date: string | null;
  created_at: string;
  license_package_id: number | null;
  license_package?: LicensePackage;
  current_balance: number;
}

interface Module {
  id: number;
  name: string;
  slug: string;
  is_core: boolean;
  is_active?: boolean;
}

interface CompanyFormData {
  name: string;
  legal_name: string;
  email: string;
  phone: string;
  city: string;
  sector: string;
  package_type: 'starter' | 'professional' | 'enterprise';
  status: 'active' | 'suspended' | 'cancelled' | 'trial';
  user_limit: number;
  trial_ends_at: string;
  license_start_date: string;
  license_end_date: string;
  license_package_id: number | null;
  // Admin kullanıcı bilgileri (sadece yeni firma için)
  admin_name: string;
  admin_email: string;
  admin_password: string;
  admin_phone: string;
}

const initialFormData: CompanyFormData = {
  name: '',
  legal_name: '',
  email: '',
  phone: '',
  city: '',
  sector: '',
  package_type: 'starter',
  status: 'trial',
  user_limit: 5,
  trial_ends_at: '',
  license_start_date: '',
  license_end_date: '',
  license_package_id: null,
  admin_name: '',
  admin_email: '',
  admin_password: '',
  admin_phone: '',
};

const AdminCompaniesPage: React.FC = () => {
  const [companies, setCompanies] = useState<Company[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [showModulesModal, setShowModulesModal] = useState(false);
  const [showLedgerModal, setShowLedgerModal] = useState(false);
  const [editingCompany, setEditingCompany] = useState<Company | null>(null);
  const [selectedCompany, setSelectedCompany] = useState<Company | null>(null);
  const [formData, setFormData] = useState<CompanyFormData>(initialFormData);
  const [modules, setModules] = useState<Module[]>([]);
  const [companyModules, setCompanyModules] = useState<number[]>([]);
  const [licensePackages, setLicensePackages] = useState<LicensePackage[]>([]);
  const [ledgerData, setLedgerData] = useState<{ transactions: unknown[]; summary: Record<string, number> } | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });

  // Firmaları yükle
  const loadCompanies = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = {};
      if (searchQuery) params.search = searchQuery;
      if (statusFilter) params.status = statusFilter;
      
      const response = await adminApi.companies.list(params);
      setCompanies(response.data.data || []);
      if (response.data.meta) {
        setMeta(response.data.meta);
      }
    } catch (error) {
      console.error('Firmalar yüklenemedi:', error);
      toast.error('Firmalar yüklenirken bir hata oluştu');
    } finally {
      setLoading(false);
    }
  }, [searchQuery, statusFilter]);

  // Modülleri yükle
  const loadModules = async () => {
    try {
      const response = await adminApi.modules.list();
      setModules(response.data.data || []);
    } catch (error) {
      console.error('Modüller yüklenemedi:', error);
    }
  };

  // Lisans paketlerini yükle
  const loadLicensePackages = async () => {
    try {
      const response = await adminApi.licensePackages.list();
      setLicensePackages(response.data.data || []);
    } catch (error) {
      console.error('Paketler yüklenemedi:', error);
    }
  };

  // Cari hesap modalı aç
  const openLedgerModal = async (company: Company) => {
    setSelectedCompany(company);
    try {
      const response = await adminApi.companies.getLedger(company.id);
      setLedgerData(response.data.data);
      setShowLedgerModal(true);
    } catch (error) {
      toast.error('Cari hesap yüklenemedi');
    }
  };

  // Cari borç ekle
  const handleAddDebit = async (amount: number, description: string) => {
    if (!selectedCompany) return;
    try {
      await adminApi.companies.addDebit(selectedCompany.id, { amount, description });
      toast.success('Borç kaydı eklendi');
      const response = await adminApi.companies.getLedger(selectedCompany.id);
      setLedgerData(response.data.data);
      loadCompanies();
    } catch (error) {
      toast.error('Borç eklenemedi');
    }
  };

  // Cari alacak/ödeme ekle
  const handleAddCredit = async (amount: number, description: string, paymentMethod: string) => {
    if (!selectedCompany) return;
    try {
      await adminApi.companies.addCredit(selectedCompany.id, { amount, description, payment_method: paymentMethod });
      toast.success('Ödeme kaydı eklendi');
      const response = await adminApi.companies.getLedger(selectedCompany.id);
      setLedgerData(response.data.data);
      loadCompanies();
    } catch (error) {
      toast.error('Ödeme eklenemedi');
    }
  };

  useEffect(() => {
    loadCompanies();
    loadModules();
    loadLicensePackages();
  }, [loadCompanies]);

  // Modal aç - Yeni firma
  const handleAddClick = () => {
    setEditingCompany(null);
    setFormData(initialFormData);
    setShowModal(true);
  };

  // Modal aç - Düzenle
  const handleEditClick = (company: Company) => {
    setEditingCompany(company);
    setFormData({
      name: company.name || '',
      legal_name: company.legal_name || '',
      email: company.email || '',
      phone: company.phone || '',
      city: company.city || '',
      sector: company.sector || '',
      package_type: company.package_type || 'starter',
      status: company.status || 'trial',
      user_limit: company.user_limit || 5,
      trial_ends_at: company.trial_ends_at?.split('T')[0] || '',
      license_start_date: '',
      license_end_date: company.license_end_date?.split('T')[0] || '',
    });
    setShowModal(true);
  };

  // Modül yönetimi modalı
  const handleModulesClick = async (company: Company) => {
    setSelectedCompany(company);
    try {
      const response = await adminApi.companies.get(company.id);
      const companyData = response.data.data;
      const activeModuleIds = companyData.modules
        ?.filter((m: Module & { pivot?: { is_active: boolean } }) => m.pivot?.is_active)
        .map((m: Module) => m.id) || [];
      setCompanyModules(activeModuleIds);
      setShowModulesModal(true);
    } catch (error) {
      toast.error('Firma modülleri yüklenemedi');
    }
  };

  // Form gönder
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);

    try {
      if (editingCompany) {
        // Düzenlerken admin bilgilerini gönderme
        const updateData = {
          name: formData.name,
          legal_name: formData.legal_name,
          email: formData.email,
          phone: formData.phone,
          city: formData.city,
          sector: formData.sector,
          package_type: formData.package_type,
          status: formData.status,
          user_limit: formData.user_limit,
          trial_ends_at: formData.trial_ends_at,
          license_start_date: formData.license_start_date,
          license_end_date: formData.license_end_date,
        };
        await adminApi.companies.update(editingCompany.id, updateData);
        toast.success('Firma güncellendi');
      } else {
        // Yeni firma oluştururken admin bilgilerini de gönder
        const response = await adminApi.companies.create(formData);
        const adminData = response.data?.data?.admin;
        if (adminData) {
          toast.success(
            `Firma ve admin oluşturuldu!\n\nAdmin: ${adminData.email}`,
            { duration: 5000 }
          );
        } else {
          toast.success('Firma oluşturuldu');
        }
      }
      setShowModal(false);
      loadCompanies();
    } catch (error: unknown) {
      console.error('Firma kaydedilemedi:', error);
      const axiosError = error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      if (axiosError.response?.data?.errors) {
        const errors = axiosError.response.data.errors;
        const firstError = Object.values(errors)[0]?.[0];
        toast.error(firstError || 'Kayıt sırasında bir hata oluştu');
      } else {
        toast.error(axiosError.response?.data?.message || 'Kayıt sırasında bir hata oluştu');
      }
    } finally {
      setSubmitting(false);
    }
  };

  // Modülleri kaydet
  const handleSaveModules = async () => {
    if (!selectedCompany) return;
    
    try {
      const moduleData = modules.map(m => ({
        id: m.id,
        is_active: m.is_core || companyModules.includes(m.id),
      }));
      
      await adminApi.companies.syncModules(selectedCompany.id, moduleData);
      toast.success('Modüller güncellendi');
      setShowModulesModal(false);
      loadCompanies();
    } catch (error) {
      toast.error('Modüller güncellenemedi');
    }
  };

  // Durum değiştir
  const handleStatusChange = async (company: Company, newStatus: string) => {
    try {
      await adminApi.companies.toggleStatus(company.id, newStatus);
      toast.success('Firma durumu güncellendi');
      loadCompanies();
    } catch (error) {
      toast.error('Durum güncellenemedi');
    }
  };

  // Firma sil
  const handleDelete = async (company: Company) => {
    if (!confirm(`"${company.name}" firmasını silmek istediğinize emin misiniz?`)) return;
    
    try {
      await adminApi.companies.delete(company.id);
      toast.success('Firma silindi');
      loadCompanies();
    } catch (error) {
      console.error('Firma silinemedi:', error);
    }
  };

  // Durum badge
  const getStatusBadge = (status: string) => {
    const badges: Record<string, { class: string; text: string }> = {
      active: { class: 'badge-success', text: 'Aktif' },
      trial: { class: 'badge-warning', text: 'Deneme' },
      suspended: { class: 'badge-danger', text: 'Askıda' },
      cancelled: { class: 'badge-secondary', text: 'İptal' },
    };
    return badges[status] || { class: 'badge-secondary', text: status };
  };

  // Paket badge
  const getPackageBadge = (pkg: string) => {
    const badges: Record<string, { class: string; text: string }> = {
      starter: { class: 'badge-info', text: 'Starter' },
      professional: { class: 'badge-primary', text: 'Professional' },
      enterprise: { class: 'badge-accent', text: 'Enterprise' },
    };
    return badges[pkg] || { class: 'badge-secondary', text: pkg };
  };

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Firmalar</h1>
          <p className="page-subtitle">
            {meta.total} firma kayıtlı
          </p>
        </div>
        <button className="btn btn-primary" onClick={handleAddClick}>
          <i className="bi bi-plus-lg me-2"></i>
          Firma Ekle
        </button>
      </div>

      {/* Filters */}
      <div className="card mb-4">
        <div className="card-body">
          <div className="row g-3">
            <div className="col-md-6">
              <div className="input-icon">
                <i className="bi bi-search"></i>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Firma adı, email veya vergi no ile ara..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
            </div>
            <div className="col-md-3">
              <select
                className="form-select"
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
              >
                <option value="">Tüm Durumlar</option>
                <option value="active">Aktif</option>
                <option value="trial">Deneme</option>
                <option value="suspended">Askıda</option>
                <option value="cancelled">İptal</option>
              </select>
            </div>
            <div className="col-md-3">
              <button 
                className="btn btn-outline-secondary w-100"
                onClick={loadCompanies}
              >
                <i className="bi bi-arrow-clockwise me-2"></i>
                Yenile
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Table */}
      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="text-center py-5">
              <div className="spinner-border text-primary" role="status">
                <span className="visually-hidden">Yükleniyor...</span>
              </div>
            </div>
          ) : companies.length === 0 ? (
            <div className="empty-state py-5">
              <div className="empty-state-icon">🏢</div>
              <h3 className="empty-state-title">Henüz firma yok</h3>
              <p className="empty-state-text">İlk firmayı eklemek için butona tıklayın</p>
              <button className="btn btn-primary" onClick={handleAddClick}>
                <i className="bi bi-plus-lg me-2"></i>
                Firma Ekle
              </button>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Firma</th>
                    <th>İletişim</th>
                    <th>Paket</th>
                    <th>Kullanıcı</th>
                    <th>Cari Bakiye</th>
                    <th>Durum</th>
                    <th>Kayıt Tarihi</th>
                    <th className="text-end">İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  {companies.map((company) => {
                    const statusBadge = getStatusBadge(company.status);
                    const packageBadge = getPackageBadge(company.package_type);
                    
                    return (
                      <tr key={company.id}>
                        <td>
                          <div className="d-flex align-items-center gap-3">
                            <div className="avatar avatar-md" style={{ 
                              background: 'var(--primary)', 
                              color: 'white',
                              display: 'flex',
                              alignItems: 'center',
                              justifyContent: 'center',
                              width: '40px',
                              height: '40px',
                              borderRadius: '8px',
                              fontSize: '14px',
                              fontWeight: '600'
                            }}>
                              {company.name.substring(0, 2).toUpperCase()}
                            </div>
                            <div>
                              <div className="fw-semibold">{company.name}</div>
                              {company.city && (
                                <small className="text-muted">{company.city}</small>
                              )}
                            </div>
                          </div>
                        </td>
                        <td>
                          <div>{company.email || '-'}</div>
                          <small className="text-muted">{company.phone || '-'}</small>
                        </td>
                        <td>
                          <span className={`badge ${packageBadge.class}`}>
                            {packageBadge.text}
                          </span>
                        </td>
                        <td>
                          <span className="fw-semibold">{company.users_count || 0}</span>
                          <span className="text-muted">/{company.user_limit}</span>
                        </td>
                        <td>
                          <span 
                            className={`fw-semibold cursor-pointer ${company.current_balance > 0 ? 'text-danger' : company.current_balance < 0 ? 'text-success' : ''}`}
                            style={{ cursor: 'pointer' }}
                            onClick={() => openLedgerModal(company)}
                            title="Cari hesabı görüntüle"
                          >
                            {company.current_balance > 0 
                              ? `${company.current_balance.toLocaleString('tr-TR')} ₺ Borç`
                              : company.current_balance < 0 
                                ? `${Math.abs(company.current_balance).toLocaleString('tr-TR')} ₺ Alacak`
                                : '0 ₺'}
                          </span>
                        </td>
                        <td>
                          <span className={`badge ${statusBadge.class}`}>
                            {statusBadge.text}
                          </span>
                        </td>
                        <td>
                          {new Date(company.created_at).toLocaleDateString('tr-TR')}
                        </td>
                        <td>
                          <div className="d-flex gap-1 justify-content-end">
                            <button
                              className="btn btn-sm btn-outline-secondary"
                              title="Modüller"
                              onClick={() => handleModulesClick(company)}
                            >
                              <i className="bi bi-grid-3x3-gap"></i>
                            </button>
                            <button
                              className="btn btn-sm btn-outline-secondary"
                              title="Düzenle"
                              onClick={() => handleEditClick(company)}
                            >
                              <i className="bi bi-pencil"></i>
                            </button>
                            <div className="dropdown">
                              <button
                                className="btn btn-sm btn-outline-secondary dropdown-toggle"
                                type="button"
                                data-bs-toggle="dropdown"
                              >
                                <i className="bi bi-three-dots-vertical"></i>
                              </button>
                              <ul className="dropdown-menu dropdown-menu-end">
                                <li>
                                  <button
                                    className="dropdown-item"
                                    onClick={() => openLedgerModal(company)}
                                  >
                                    <i className="bi bi-wallet2 text-primary me-2"></i>
                                    Cari Hesap
                                  </button>
                                </li>
                                <li><hr className="dropdown-divider" /></li>
                                <li>
                                  <button
                                    className="dropdown-item"
                                    onClick={() => handleStatusChange(company, 'active')}
                                  >
                                    <i className="bi bi-check-circle text-success me-2"></i>
                                    Aktifleştir
                                  </button>
                                </li>
                                <li>
                                  <button
                                    className="dropdown-item"
                                    onClick={() => handleStatusChange(company, 'suspended')}
                                  >
                                    <i className="bi bi-pause-circle text-warning me-2"></i>
                                    Askıya Al
                                  </button>
                                </li>
                                <li><hr className="dropdown-divider" /></li>
                                <li>
                                  <button
                                    className="dropdown-item text-danger"
                                    onClick={() => handleDelete(company)}
                                  >
                                    <i className="bi bi-trash me-2"></i>
                                    Sil
                                  </button>
                                </li>
                              </ul>
                            </div>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* Firma Modal */}
      {showModal && (
        <div className="modal-backdrop show" onClick={() => setShowModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog modal-lg" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">
                    {editingCompany ? 'Firma Düzenle' : 'Yeni Firma'}
                  </h5>
                  <button
                    type="button"
                    className="btn-close"
                    onClick={() => setShowModal(false)}
                  ></button>
                </div>
                <form onSubmit={handleSubmit}>
                  <div className="modal-body">
                    <div className="row g-3">
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
                        <label className="form-label">Şehir</label>
                        <input
                          type="text"
                          className="form-control"
                          value={formData.city}
                          onChange={(e) => setFormData({ ...formData, city: e.target.value })}
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
                      <div className="col-md-4">
                        <label className="form-label">Paket *</label>
                        <select
                          className="form-select"
                          value={formData.package_type}
                          onChange={(e) => setFormData({ ...formData, package_type: e.target.value as CompanyFormData['package_type'] })}
                          required
                        >
                          <option value="starter">Starter</option>
                          <option value="professional">Professional</option>
                          <option value="enterprise">Enterprise</option>
                        </select>
                      </div>
                      <div className="col-md-4">
                        <label className="form-label">Durum *</label>
                        <select
                          className="form-select"
                          value={formData.status}
                          onChange={(e) => setFormData({ ...formData, status: e.target.value as CompanyFormData['status'] })}
                          required
                        >
                          <option value="trial">Deneme</option>
                          <option value="active">Aktif</option>
                          <option value="suspended">Askıda</option>
                          <option value="cancelled">İptal</option>
                        </select>
                      </div>
                      <div className="col-md-4">
                        <label className="form-label">Kullanıcı Limiti *</label>
                        <input
                          type="number"
                          className="form-control"
                          min="1"
                          value={formData.user_limit}
                          onChange={(e) => setFormData({ ...formData, user_limit: parseInt(e.target.value) })}
                          required
                        />
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">Deneme Bitiş Tarihi</label>
                        <input
                          type="date"
                          className="form-control"
                          value={formData.trial_ends_at}
                          onChange={(e) => setFormData({ ...formData, trial_ends_at: e.target.value })}
                        />
                      </div>
                      <div className="col-md-6">
                        <label className="form-label">Lisans Bitiş Tarihi</label>
                        <input
                          type="date"
                          className="form-control"
                          value={formData.license_end_date}
                          onChange={(e) => setFormData({ ...formData, license_end_date: e.target.value })}
                        />
                      </div>

                      {/* Admin Kullanıcı Bilgileri - Sadece yeni firma eklerken göster */}
                      {!editingCompany && (
                        <>
                          <div className="col-12">
                            <hr className="my-3" />
                            <h6 className="text-primary mb-3">
                              <i className="bi bi-person-badge me-2"></i>
                              Firma Admin Kullanıcısı
                            </h6>
                            <p className="text-muted small mb-3">
                              Bu kullanıcı firma paneline giriş yapacak ve tüm yönetim yetkilerine sahip olacak.
                            </p>
                          </div>
                          <div className="col-md-6">
                            <label className="form-label">Admin Adı Soyadı *</label>
                            <input
                              type="text"
                              className="form-control"
                              placeholder="Örn: Ahmet Yılmaz"
                              value={formData.admin_name}
                              onChange={(e) => setFormData({ ...formData, admin_name: e.target.value })}
                              required
                            />
                          </div>
                          <div className="col-md-6">
                            <label className="form-label">Admin E-posta *</label>
                            <input
                              type="email"
                              className="form-control"
                              placeholder="Örn: admin@firma.com"
                              value={formData.admin_email}
                              onChange={(e) => setFormData({ ...formData, admin_email: e.target.value })}
                              required
                            />
                          </div>
                          <div className="col-md-6">
                            <label className="form-label">Admin Şifre *</label>
                            <input
                              type="password"
                              className="form-control"
                              placeholder="En az 8 karakter"
                              value={formData.admin_password}
                              onChange={(e) => setFormData({ ...formData, admin_password: e.target.value })}
                              minLength={8}
                              required
                            />
                            <small className="text-muted">
                              En az 8 karakter olmalıdır
                            </small>
                          </div>
                          <div className="col-md-6">
                            <label className="form-label">Admin Telefon</label>
                            <input
                              type="tel"
                              className="form-control"
                              placeholder="Örn: 0532 123 4567"
                              value={formData.admin_phone}
                              onChange={(e) => setFormData({ ...formData, admin_phone: e.target.value })}
                            />
                          </div>
                        </>
                      )}
                    </div>
                  </div>
                  <div className="modal-footer">
                    <button
                      type="button"
                      className="btn btn-secondary"
                      onClick={() => setShowModal(false)}
                    >
                      İptal
                    </button>
                    <button
                      type="submit"
                      className="btn btn-primary"
                      disabled={submitting}
                    >
                      {submitting ? (
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
          </div>
        </div>
      )}

      {/* Modüller Modal */}
      {showModulesModal && selectedCompany && (
        <div className="modal-backdrop show" onClick={() => setShowModulesModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">
                    {selectedCompany.name} - Modüller
                  </h5>
                  <button
                    type="button"
                    className="btn-close"
                    onClick={() => setShowModulesModal(false)}
                  ></button>
                </div>
                <div className="modal-body">
                  <div className="list-group">
                    {modules.map((module) => (
                      <label
                        key={module.id}
                        className="list-group-item d-flex justify-content-between align-items-center"
                      >
                        <div>
                          <div className="fw-semibold">{module.name}</div>
                          <small className="text-muted">{module.slug}</small>
                        </div>
                        <div className="form-check form-switch">
                          <input
                            className="form-check-input"
                            type="checkbox"
                            checked={module.is_core || companyModules.includes(module.id)}
                            disabled={module.is_core}
                            onChange={(e) => {
                              if (e.target.checked) {
                                setCompanyModules([...companyModules, module.id]);
                              } else {
                                setCompanyModules(companyModules.filter(id => id !== module.id));
                              }
                            }}
                          />
                        </div>
                      </label>
                    ))}
                  </div>
                </div>
                <div className="modal-footer">
                  <button
                    type="button"
                    className="btn btn-secondary"
                    onClick={() => setShowModulesModal(false)}
                  >
                    İptal
                  </button>
                  <button
                    type="button"
                    className="btn btn-primary"
                    onClick={handleSaveModules}
                  >
                    <i className="bi bi-check-lg me-2"></i>
                    Kaydet
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Cari Hesap Modal */}
      {showLedgerModal && selectedCompany && ledgerData && (
        <div className="modal-backdrop show" onClick={() => setShowLedgerModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog modal-lg" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">
                    {selectedCompany.name} - Cari Hesap
                  </h5>
                  <button
                    type="button"
                    className="btn-close"
                    onClick={() => setShowLedgerModal(false)}
                  ></button>
                </div>
                <div className="modal-body">
                  {/* Özet Kartları */}
                  <div className="row g-3 mb-4">
                    <div className="col-md-4">
                      <div className="card bg-danger bg-opacity-10">
                        <div className="card-body text-center">
                          <div className="text-danger small">Toplam Borç</div>
                          <div className="h4 mb-0 text-danger">
                            {(ledgerData.summary?.total_debit || 0).toLocaleString('tr-TR')} ₺
                          </div>
                        </div>
                      </div>
                    </div>
                    <div className="col-md-4">
                      <div className="card bg-success bg-opacity-10">
                        <div className="card-body text-center">
                          <div className="text-success small">Toplam Alacak</div>
                          <div className="h4 mb-0 text-success">
                            {(ledgerData.summary?.total_credit || 0).toLocaleString('tr-TR')} ₺
                          </div>
                        </div>
                      </div>
                    </div>
                    <div className="col-md-4">
                      <div className={`card ${selectedCompany.current_balance > 0 ? 'bg-warning' : 'bg-info'} bg-opacity-10`}>
                        <div className="card-body text-center">
                          <div className={selectedCompany.current_balance > 0 ? 'text-warning' : 'text-info'} style={{ fontSize: '0.75rem' }}>Bakiye</div>
                          <div className={`h4 mb-0 ${selectedCompany.current_balance > 0 ? 'text-warning' : 'text-info'}`}>
                            {selectedCompany.current_balance > 0 ? '+' : ''}{selectedCompany.current_balance.toLocaleString('tr-TR')} ₺
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Hızlı İşlem Butonları */}
                  <div className="d-flex gap-2 mb-4">
                    <button 
                      className="btn btn-danger btn-sm"
                      onClick={() => {
                        const amount = prompt('Borç tutarı (₺):');
                        const desc = prompt('Açıklama:');
                        if (amount && desc) handleAddDebit(parseFloat(amount), desc);
                      }}
                    >
                      <i className="bi bi-plus-circle me-1"></i> Borç Ekle
                    </button>
                    <button 
                      className="btn btn-success btn-sm"
                      onClick={() => {
                        const amount = prompt('Ödeme tutarı (₺):');
                        const desc = prompt('Açıklama:');
                        if (amount && desc) handleAddCredit(parseFloat(amount), desc, 'bank_transfer');
                      }}
                    >
                      <i className="bi bi-plus-circle me-1"></i> Ödeme Ekle
                    </button>
                  </div>

                  {/* İşlem Listesi */}
                  <div className="table-responsive" style={{ maxHeight: '300px', overflowY: 'auto' }}>
                    <table className="table table-sm">
                      <thead style={{ position: 'sticky', top: 0, background: 'var(--surface-primary)' }}>
                        <tr>
                          <th>Tarih</th>
                          <th>Açıklama</th>
                          <th className="text-end">Borç</th>
                          <th className="text-end">Alacak</th>
                          <th className="text-end">Bakiye</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(ledgerData.transactions as Array<{id: number; created_at: string; description: string; type: string; amount: number; balance_after: number}>).map((tx) => (
                          <tr key={tx.id}>
                            <td>{new Date(tx.created_at).toLocaleDateString('tr-TR')}</td>
                            <td>{tx.description}</td>
                            <td className="text-end text-danger">
                              {tx.type === 'debit' ? tx.amount.toLocaleString('tr-TR') + ' ₺' : '-'}
                            </td>
                            <td className="text-end text-success">
                              {tx.type === 'credit' ? tx.amount.toLocaleString('tr-TR') + ' ₺' : '-'}
                            </td>
                            <td className="text-end fw-semibold">{tx.balance_after.toLocaleString('tr-TR')} ₺</td>
                          </tr>
                        ))}
                        {(ledgerData.transactions as Array<unknown>).length === 0 && (
                          <tr>
                            <td colSpan={5} className="text-center text-muted py-4">
                              Henüz işlem kaydı yok
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>
                <div className="modal-footer">
                  <button
                    type="button"
                    className="btn btn-secondary"
                    onClick={() => setShowLedgerModal(false)}
                  >
                    Kapat
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
          max-height: 90vh;
        }
        .modal-content {
          background: var(--surface-primary);
          border: 1px solid var(--border-primary);
          border-radius: 12px;
          max-height: 90vh;
          overflow: hidden;
        }
        .modal-header {
          border-bottom: 1px solid var(--border-primary);
          padding: 1rem 1.5rem;
        }
        .modal-body {
          padding: 1.5rem;
          overflow-y: auto;
        }
        .modal-footer {
          border-top: 1px solid var(--border-primary);
          padding: 1rem 1.5rem;
        }
        .list-group-item {
          background: var(--surface-secondary);
          border-color: var(--border-primary);
          color: var(--text-primary);
        }
        .dropdown-menu {
          background: var(--surface-primary);
          border-color: var(--border-primary);
        }
        .dropdown-item {
          color: var(--text-primary);
        }
        .dropdown-item:hover {
          background: var(--surface-secondary);
        }
      `}</style>
    </div>
  );
};

export default AdminCompaniesPage;

