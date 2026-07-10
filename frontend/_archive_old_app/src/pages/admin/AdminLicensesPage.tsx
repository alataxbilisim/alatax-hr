import React, { useState, useEffect, useCallback } from 'react';
import { adminApi } from '../../services/api';
import toast from 'react-hot-toast';

interface Company {
  id: number;
  name: string;
  email: string;
  package_type: string;
  status: string;
  user_limit: number;
  current_users: number;
  license_start_date: string | null;
  license_end_date: string | null;
  trial_ends_at: string | null;
  payment_status: string;
  last_payment_date: string | null;
  next_payment_date: string | null;
  monthly_fee: number | null;
}

interface PaymentRecord {
  id: number;
  company_id: number;
  amount: number;
  payment_date: string;
  payment_method: string;
  status: string;
  notes: string | null;
}

const AdminLicensesPage: React.FC = () => {
  const [companies, setCompanies] = useState<Company[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedCompany, setSelectedCompany] = useState<Company | null>(null);
  const [showPaymentModal, setShowPaymentModal] = useState(false);
  const [showExtendModal, setShowExtendModal] = useState(false);
  const [filter, setFilter] = useState<'all' | 'expiring' | 'expired' | 'trial'>('all');
  
  const [paymentData, setPaymentData] = useState({
    amount: '',
    payment_method: 'bank_transfer',
    notes: '',
  });
  
  const [extendData, setExtendData] = useState({
    months: 12,
    license_end_date: '',
  });

  const loadCompanies = useCallback(async () => {
    try {
      setLoading(true);
      const response = await adminApi.companies.list();
      let data = response.data.data || [];
      
      // Filter
      const today = new Date();
      const thirtyDaysLater = new Date(today.getTime() + 30 * 24 * 60 * 60 * 1000);
      
      if (filter === 'expiring') {
        data = data.filter((c: Company) => {
          if (!c.license_end_date) return false;
          const endDate = new Date(c.license_end_date);
          return endDate > today && endDate <= thirtyDaysLater;
        });
      } else if (filter === 'expired') {
        data = data.filter((c: Company) => {
          if (!c.license_end_date) return false;
          return new Date(c.license_end_date) < today;
        });
      } else if (filter === 'trial') {
        data = data.filter((c: Company) => c.status === 'trial');
      }
      
      setCompanies(data);
    } catch (error) {
      console.error('Firmalar yüklenemedi:', error);
    } finally {
      setLoading(false);
    }
  }, [filter]);

  useEffect(() => {
    loadCompanies();
  }, [loadCompanies]);

  const handleRecordPayment = async () => {
    if (!selectedCompany || !paymentData.amount) return;
    
    try {
      await adminApi.companies.update(selectedCompany.id, {
        payment_status: 'paid',
        last_payment_date: new Date().toISOString().split('T')[0],
      });
      toast.success('Ödeme kaydedildi');
      setShowPaymentModal(false);
      setPaymentData({ amount: '', payment_method: 'bank_transfer', notes: '' });
      loadCompanies();
    } catch (error) {
      toast.error('Ödeme kaydedilemedi');
    }
  };

  const handleExtendLicense = async () => {
    if (!selectedCompany || !extendData.license_end_date) return;
    
    try {
      await adminApi.companies.update(selectedCompany.id, {
        license_end_date: extendData.license_end_date,
        status: 'active',
      });
      toast.success('Lisans uzatıldı');
      setShowExtendModal(false);
      loadCompanies();
    } catch (error) {
      toast.error('Lisans uzatılamadı');
    }
  };

  const openExtendModal = (company: Company) => {
    setSelectedCompany(company);
    const startDate = company.license_end_date ? new Date(company.license_end_date) : new Date();
    startDate.setMonth(startDate.getMonth() + 12);
    setExtendData({
      months: 12,
      license_end_date: startDate.toISOString().split('T')[0],
    });
    setShowExtendModal(true);
  };

  const getPackageBadge = (pkg: string) => {
    const badges: Record<string, { class: string; label: string }> = {
      starter: { class: 'badge-info', label: 'Starter' },
      professional: { class: 'badge-primary', label: 'Professional' },
      enterprise: { class: 'badge-accent', label: 'Enterprise' },
    };
    return badges[pkg] || { class: 'badge-secondary', label: pkg };
  };

  const getStatusBadge = (status: string) => {
    const badges: Record<string, { class: string; label: string }> = {
      active: { class: 'badge-success', label: 'Aktif' },
      trial: { class: 'badge-warning', label: 'Deneme' },
      suspended: { class: 'badge-danger', label: 'Askıda' },
      cancelled: { class: 'badge-secondary', label: 'İptal' },
    };
    return badges[status] || { class: 'badge-secondary', label: status };
  };

  const getLicenseStatus = (company: Company) => {
    if (!company.license_end_date) {
      return { class: 'text-gray-500', label: 'Belirsiz', icon: 'bi-question-circle' };
    }
    
    const endDate = new Date(company.license_end_date);
    const today = new Date();
    const daysLeft = Math.ceil((endDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
    
    if (daysLeft < 0) {
      return { class: 'text-red-500', label: `${Math.abs(daysLeft)} gün geçti`, icon: 'bi-x-circle-fill' };
    } else if (daysLeft <= 7) {
      return { class: 'text-red-500', label: `${daysLeft} gün kaldı`, icon: 'bi-exclamation-triangle-fill' };
    } else if (daysLeft <= 30) {
      return { class: 'text-yellow-500', label: `${daysLeft} gün kaldı`, icon: 'bi-exclamation-circle-fill' };
    }
    return { class: 'text-green-500', label: `${daysLeft} gün kaldı`, icon: 'bi-check-circle-fill' };
  };

  const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('tr-TR');
  };

  const stats = {
    total: companies.length,
    active: companies.filter(c => c.status === 'active').length,
    trial: companies.filter(c => c.status === 'trial').length,
    expiring: companies.filter(c => {
      if (!c.license_end_date) return false;
      const endDate = new Date(c.license_end_date);
      const today = new Date();
      const thirtyDaysLater = new Date(today.getTime() + 30 * 24 * 60 * 60 * 1000);
      return endDate > today && endDate <= thirtyDaysLater;
    }).length,
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Lisans Yönetimi</h1>
          <p className="page-subtitle">Firma lisanslarını ve ödemelerini yönetin</p>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div 
          className={`card cursor-pointer transition-all ${filter === 'all' ? 'ring-2 ring-[var(--primary)]' : ''}`}
          onClick={() => setFilter('all')}
        >
          <div className="card-body text-center">
            <div className="text-3xl font-bold text-[var(--primary)]">{stats.total}</div>
            <div className="text-sm text-[var(--text-secondary)]">Toplam Firma</div>
          </div>
        </div>
        <div 
          className={`card cursor-pointer transition-all ${filter === 'trial' ? 'ring-2 ring-[var(--warning)]' : ''}`}
          onClick={() => setFilter('trial')}
        >
          <div className="card-body text-center">
            <div className="text-3xl font-bold text-[var(--warning)]">{stats.trial}</div>
            <div className="text-sm text-[var(--text-secondary)]">Deneme Süreci</div>
          </div>
        </div>
        <div 
          className={`card cursor-pointer transition-all ${filter === 'expiring' ? 'ring-2 ring-orange-500' : ''}`}
          onClick={() => setFilter('expiring')}
        >
          <div className="card-body text-center">
            <div className="text-3xl font-bold text-orange-500">{stats.expiring}</div>
            <div className="text-sm text-[var(--text-secondary)]">30 Gün İçinde Dolacak</div>
          </div>
        </div>
        <div 
          className={`card cursor-pointer transition-all ${filter === 'expired' ? 'ring-2 ring-red-500' : ''}`}
          onClick={() => setFilter('expired')}
        >
          <div className="card-body text-center">
            <div className="text-3xl font-bold text-[var(--success)]">{stats.active}</div>
            <div className="text-sm text-[var(--text-secondary)]">Aktif Lisans</div>
          </div>
        </div>
      </div>

      {/* Table */}
      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="text-center py-8">
              <div className="spinner-border text-primary" role="status">
                <span className="visually-hidden">Yükleniyor...</span>
              </div>
            </div>
          ) : companies.length === 0 ? (
            <div className="text-center py-8 text-[var(--text-secondary)]">
              <i className="bi bi-building text-4xl mb-2"></i>
              <p>Bu filtrede firma bulunamadı</p>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Firma</th>
                    <th>Paket</th>
                    <th>Durum</th>
                    <th>Lisans Durumu</th>
                    <th>Bitiş Tarihi</th>
                    <th className="text-end">İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  {companies.map((company) => {
                    const licenseStatus = getLicenseStatus(company);
                    const pkgBadge = getPackageBadge(company.package_type);
                    const statusBadge = getStatusBadge(company.status);
                    
                    return (
                      <tr key={company.id}>
                        <td>
                          <div>
                            <div className="font-semibold">{company.name}</div>
                            <small className="text-[var(--text-muted)]">{company.email}</small>
                          </div>
                        </td>
                        <td>
                          <span className={`badge ${pkgBadge.class}`}>{pkgBadge.label}</span>
                        </td>
                        <td>
                          <span className={`badge ${statusBadge.class}`}>{statusBadge.label}</span>
                        </td>
                        <td>
                          <span className={licenseStatus.class}>
                            <i className={`bi ${licenseStatus.icon} me-1`}></i>
                            {licenseStatus.label}
                          </span>
                        </td>
                        <td>{formatDate(company.license_end_date)}</td>
                        <td className="text-end">
                          <div className="flex gap-2 justify-end">
                            <button
                              className="btn btn-sm btn-outline-success"
                              onClick={() => {
                                setSelectedCompany(company);
                                setShowPaymentModal(true);
                              }}
                              title="Ödeme Kaydet"
                            >
                              <i className="bi bi-credit-card"></i>
                            </button>
                            <button
                              className="btn btn-sm btn-outline-primary"
                              onClick={() => openExtendModal(company)}
                              title="Lisans Uzat"
                            >
                              <i className="bi bi-calendar-plus"></i>
                            </button>
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

      {/* Payment Modal */}
      {showPaymentModal && selectedCompany && (
        <div className="modal-backdrop show" onClick={() => setShowPaymentModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">Ödeme Kaydet - {selectedCompany.name}</h5>
                  <button type="button" className="btn-close" onClick={() => setShowPaymentModal(false)}></button>
                </div>
                <div className="modal-body">
                  <div className="mb-3">
                    <label className="form-label">Ödeme Tutarı (TL) *</label>
                    <input
                      type="number"
                      className="form-control"
                      value={paymentData.amount}
                      onChange={(e) => setPaymentData({ ...paymentData, amount: e.target.value })}
                      placeholder="0.00"
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Ödeme Yöntemi</label>
                    <select
                      className="form-select"
                      value={paymentData.payment_method}
                      onChange={(e) => setPaymentData({ ...paymentData, payment_method: e.target.value })}
                    >
                      <option value="bank_transfer">Banka Havalesi</option>
                      <option value="credit_card">Kredi Kartı</option>
                      <option value="cash">Nakit</option>
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Notlar</label>
                    <textarea
                      className="form-control"
                      rows={2}
                      value={paymentData.notes}
                      onChange={(e) => setPaymentData({ ...paymentData, notes: e.target.value })}
                    />
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowPaymentModal(false)}>
                    İptal
                  </button>
                  <button
                    type="button"
                    className="btn btn-success"
                    onClick={handleRecordPayment}
                    disabled={!paymentData.amount}
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

      {/* Extend License Modal */}
      {showExtendModal && selectedCompany && (
        <div className="modal-backdrop show" onClick={() => setShowExtendModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">Lisans Uzat - {selectedCompany.name}</h5>
                  <button type="button" className="btn-close" onClick={() => setShowExtendModal(false)}></button>
                </div>
                <div className="modal-body">
                  <div className="mb-3">
                    <label className="form-label">Mevcut Bitiş Tarihi</label>
                    <input
                      type="text"
                      className="form-control"
                      value={formatDate(selectedCompany.license_end_date)}
                      disabled
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Yeni Bitiş Tarihi *</label>
                    <input
                      type="date"
                      className="form-control"
                      value={extendData.license_end_date}
                      onChange={(e) => setExtendData({ ...extendData, license_end_date: e.target.value })}
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Hızlı Seçim</label>
                    <div className="flex gap-2">
                      {[3, 6, 12].map((months) => (
                        <button
                          key={months}
                          type="button"
                          className="btn btn-outline-primary btn-sm"
                          onClick={() => {
                            const startDate = selectedCompany.license_end_date 
                              ? new Date(selectedCompany.license_end_date) 
                              : new Date();
                            startDate.setMonth(startDate.getMonth() + months);
                            setExtendData({
                              months,
                              license_end_date: startDate.toISOString().split('T')[0],
                            });
                          }}
                        >
                          +{months} Ay
                        </button>
                      ))}
                    </div>
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowExtendModal(false)}>
                    İptal
                  </button>
                  <button
                    type="button"
                    className="btn btn-primary"
                    onClick={handleExtendLicense}
                    disabled={!extendData.license_end_date}
                  >
                    <i className="bi bi-calendar-plus me-2"></i>
                    Uzat
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
        .modal.show { position: relative; z-index: 1051; }
        .modal-dialog { margin: 0; }
        .modal-content {
          background: var(--surface-primary);
          border: 1px solid var(--border-primary);
          border-radius: 12px;
        }
        .modal-header { border-bottom: 1px solid var(--border-primary); }
        .modal-footer { border-top: 1px solid var(--border-primary); }
      `}</style>
    </div>
  );
};

export default AdminLicensesPage;

