import React, { useEffect, useState, useCallback } from 'react';
import { adminApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import {
  BsPlus,
  BsSearch,
  BsPencil,
  BsTrash,
  BsWallet,
  BsKey,
  BsBuilding,
  BsArrowClockwise,
} from 'react-icons/bs';
import { CompanyForm, ConfirmDialog, LedgerModal } from '../components';

interface Company {
  id: number;
  name: string;
  slug: string;
  email: string;
  phone: string;
  status: string;
  license_package?: {
    id: number;
    name: string;
  };
  license_end_date: string | null;
  current_balance: number;
  users_count: number; // Backend withCount('users') kullanıyor
  created_at: string;
}

interface PaginatedResponse {
  data: Company[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

const CompaniesPage: React.FC = () => {
  const [companies, setCompanies] = useState<PaginatedResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);

  // Modal states
  const [showForm, setShowForm] = useState(false);
  const [editingCompany, setEditingCompany] = useState<Company | null>(null);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [deletingCompany, setDeletingCompany] = useState<Company | null>(null);
  const [showLedgerModal, setShowLedgerModal] = useState(false);
  const [ledgerCompany, setLedgerCompany] = useState<Company | null>(null);
  const [ledgerType, setLedgerType] = useState<'debit' | 'credit'>('credit');
  const [deleting, setDeleting] = useState(false);

  const loadCompanies = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = { page, per_page: 15 };
      if (search) params.search = search;
      if (statusFilter) params.status = statusFilter;

      const response = await adminApi.companies.list(params);
      // Backend response formatı: { success, message, data: [...], meta: {...} }
      setCompanies({
        data: response.data.data || [],
        meta: response.data.meta || {
          current_page: 1,
          last_page: 1,
          per_page: 15,
          total: 0,
        },
      });
    } catch {
      toast.error('Firmalar yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [page, search, statusFilter]);

  useEffect(() => {
    loadCompanies();
  }, [loadCompanies]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
    loadCompanies();
  };

  const handleEdit = (company: Company) => {
    setEditingCompany(company);
    setShowForm(true);
  };

  const handleDelete = (company: Company) => {
    setDeletingCompany(company);
    setShowDeleteConfirm(true);
  };

  const confirmDelete = async () => {
    if (!deletingCompany) return;
    setDeleting(true);
    try {
      await adminApi.companies.delete(deletingCompany.id);
      toast.success('Firma silindi');
      loadCompanies();
    } catch {
      toast.error('Firma silinemedi');
    } finally {
      setDeleting(false);
      setShowDeleteConfirm(false);
      setDeletingCompany(null);
    }
  };

  const handleAddDebit = (company: Company) => {
    setLedgerCompany(company);
    setLedgerType('debit');
    setShowLedgerModal(true);
  };

  const handleAddCredit = (company: Company) => {
    setLedgerCompany(company);
    setLedgerType('credit');
    setShowLedgerModal(true);
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('tr-TR', {
      style: 'currency',
      currency: 'TRY',
    }).format(amount);
  };

  const getStatusBadge = (status: string) => {
    const statusMap: Record<string, { label: string; class: string }> = {
      active: { label: 'Aktif', class: 'active' },
      trial: { label: 'Deneme', class: 'trial' },
      suspended: { label: 'Askıda', class: 'suspended' },
      inactive: { label: 'Pasif', class: 'inactive' },
    };
    const s = statusMap[status] || { label: status, class: '' };
    return <span className={`status-badge ${s.class}`}>{s.label}</span>;
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Firmalar</h1>
          <p className="page-subtitle">Tüm müşteri firmalarını yönetin</p>
        </div>
        <div className="page-header-actions">
          <button
            className="btn btn-secondary"
            onClick={loadCompanies}
            disabled={loading}
          >
            <BsArrowClockwise className={loading ? 'animate-spin' : ''} />
          </button>
          <button
            className="btn btn-primary"
            onClick={() => {
              setEditingCompany(null);
              setShowForm(true);
            }}
          >
            <BsPlus /> Yeni Firma
          </button>
        </div>
      </div>

      {/* Filters */}
      <div className="card mb-4">
        <div className="card-body">
          <form onSubmit={handleSearch} className="d-flex gap-3 flex-wrap">
            <div className="input-group" style={{ maxWidth: '300px' }}>
              <span className="input-icon">
                <BsSearch />
              </span>
              <input
                type="text"
                className="form-control"
                placeholder="Firma ara..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
            <select
              className="form-select"
              style={{ maxWidth: '200px' }}
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setPage(1);
              }}
            >
              <option value="">Tüm Durumlar</option>
              <option value="active">Aktif</option>
              <option value="trial">Deneme</option>
              <option value="suspended">Askıda</option>
              <option value="inactive">Pasif</option>
            </select>
            <button type="submit" className="btn btn-secondary">
              Filtrele
            </button>
          </form>
        </div>
      </div>

      {/* Companies Table */}
      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="page-loading">
              <div className="loading-spinner"></div>
              <p>Yükleniyor...</p>
            </div>
          ) : companies?.data && companies.data.length > 0 ? (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Firma</th>
                    <th>Paket</th>
                    <th>Lisans Bitiş</th>
                    <th>Kullanıcı</th>
                    <th>Bakiye</th>
                    <th>Durum</th>
                    <th>İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  {companies.data.map((company) => (
                    <tr key={company.id}>
                      <td>
                        <div className="d-flex align-items-center gap-2">
                          <div className="avatar avatar-sm">
                            {company.name.charAt(0)}
                          </div>
                          <div>
                            <div className="fw-semibold">{company.name}</div>
                            <div className="text-muted small">{company.email}</div>
                          </div>
                        </div>
                      </td>
                      <td>{company.license_package?.name || '-'}</td>
                      <td>
                        {company.license_end_date
                          ? new Date(company.license_end_date).toLocaleDateString('tr-TR')
                          : 'Süresiz'}
                      </td>
                      <td>{company.users_count || 0}</td>
                      <td>
                        <span
                          className={
                            company.current_balance > 0
                              ? 'ledger-debit'
                              : 'ledger-credit'
                          }
                        >
                          {formatCurrency(Math.abs(company.current_balance))}
                          {company.current_balance > 0 ? ' (B)' : company.current_balance < 0 ? ' (A)' : ''}
                        </span>
                      </td>
                      <td>{getStatusBadge(company.status)}</td>
                      <td>
                        <div className="btn-group">
                          <button
                            className="btn btn-sm btn-ghost"
                            title="Düzenle"
                            onClick={() => handleEdit(company)}
                          >
                            <BsPencil />
                          </button>
                          <button
                            className="btn btn-sm btn-ghost"
                            title="Borç Ekle"
                            onClick={() => handleAddDebit(company)}
                          >
                            <BsWallet />
                          </button>
                          <button
                            className="btn btn-sm btn-ghost text-success"
                            title="Ödeme Kaydet"
                            onClick={() => handleAddCredit(company)}
                          >
                            <BsKey />
                          </button>
                          <button
                            className="btn btn-sm btn-ghost text-danger"
                            title="Sil"
                            onClick={() => handleDelete(company)}
                          >
                            <BsTrash />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="empty-state">
              <BsBuilding size={48} />
              <h3>Firma bulunamadı</h3>
              <p>Arama kriterlerinize uygun firma yok veya henüz firma eklenmemiş.</p>
              <button
                className="btn btn-primary"
                onClick={() => {
                  setEditingCompany(null);
                  setShowForm(true);
                }}
              >
                <BsPlus /> İlk Firmayı Ekle
              </button>
            </div>
          )}
        </div>

        {/* Pagination */}
        {companies && companies.meta.last_page > 1 && (
          <div className="card-footer d-flex justify-content-between align-items-center">
            <span className="text-muted">Toplam {companies.meta.total} firma</span>
            <div className="btn-group">
              <button
                className="btn btn-sm btn-secondary"
                disabled={page === 1}
                onClick={() => setPage(page - 1)}
              >
                Önceki
              </button>
              <span className="btn btn-sm btn-ghost" style={{ pointerEvents: 'none' }}>
                {page} / {companies.meta.last_page}
              </span>
              <button
                className="btn btn-sm btn-secondary"
                disabled={page === companies.meta.last_page}
                onClick={() => setPage(page + 1)}
              >
                Sonraki
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Modals */}
      <CompanyForm
        isOpen={showForm}
        onClose={() => {
          setShowForm(false);
          setEditingCompany(null);
        }}
        onSuccess={loadCompanies}
        company={editingCompany}
      />

      <ConfirmDialog
        isOpen={showDeleteConfirm}
        onClose={() => {
          setShowDeleteConfirm(false);
          setDeletingCompany(null);
        }}
        onConfirm={confirmDelete}
        title="Firma Sil"
        message={`"${deletingCompany?.name}" firmasını silmek istediğinize emin misiniz? Bu işlem geri alınamaz.`}
        confirmText="Sil"
        type="danger"
        loading={deleting}
      />

      {ledgerCompany && (
        <LedgerModal
          isOpen={showLedgerModal}
          onClose={() => {
            setShowLedgerModal(false);
            setLedgerCompany(null);
          }}
          onSuccess={loadCompanies}
          companyId={ledgerCompany.id}
          companyName={ledgerCompany.name}
          type={ledgerType}
        />
      )}
    </div>
  );
};

export default CompaniesPage;
