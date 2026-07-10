import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { branchesApi, usersApi } from '@shared/services/api';
import { Branch, User, Employee } from '@shared/types/modules';
import toast from 'react-hot-toast';
import {
  BsBuilding,
  BsPlus,
  BsPencil,
  BsTrash,
  BsStarFill,
  BsStar,
  BsCheck,
  BsX,
  BsSearch,
  BsPeople,
  BsEye,
} from 'react-icons/bs';
import BranchForm from './BranchForm';
import DeleteDialog from '../../components/ui/DeleteDialog';
import { Modal, Skeleton } from '../../components/ui';

interface PaginatedResponse {
  data: Branch[];
  meta?: {
    current_page: number;
    last_page: number;
    total: number;
  };
  current_page?: number;
  last_page?: number;
  total?: number;
}

const BranchesPage: React.FC = () => {
  const navigate = useNavigate();
  const [branches, setBranches] = useState<Branch[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [isActiveFilter, setIsActiveFilter] = useState<boolean | null>(null);

  // Modal states
  const [formOpen, setFormOpen] = useState(false);
  const [selectedBranch, setSelectedBranch] = useState<Branch | undefined>();
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [branchToDelete, setBranchToDelete] = useState<Branch | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  // Users for manager selection
  const [users, setUsers] = useState<User[]>([]);

  // Employees modal
  const [employeesModalOpen, setEmployeesModalOpen] = useState(false);
  const [selectedBranchForEmployees, setSelectedBranchForEmployees] = useState<Branch | null>(null);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [employeesLoading, setEmployeesLoading] = useState(false);
  const [employeesPage, setEmployeesPage] = useState(1);
  const [employeesTotalPages, setEmployeesTotalPages] = useState(1);

  const loadBranches = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = { page, per_page: 15 };
      if (search) params.search = search;
      if (isActiveFilter !== null) params.is_active = isActiveFilter;

      const response = await branchesApi.list(params);
      const resData = response.data.data as PaginatedResponse;

      if (Array.isArray(resData)) {
        setBranches(resData);
        setTotalPages(1);
        setTotal(resData.length);
      } else if (resData.data) {
        setBranches(resData.data);
        setTotalPages(resData.meta?.last_page || resData.last_page || 1);
        setTotal(resData.meta?.total || resData.total || 0);
      } else {
        setBranches([]);
      }
    } catch {
      toast.error('Şubeler yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [page, search, isActiveFilter]);

  const loadUsers = useCallback(async () => {
    try {
      const response = await usersApi.list({ per_page: 100 });
      setUsers(response.data.data?.data || response.data.data || []);
    } catch {
      // Silent fail
    }
  }, []);

  const loadBranchEmployees = useCallback(async (branchId: number, page: number = 1) => {
    try {
      setEmployeesLoading(true);
      const response = await branchesApi.employees(branchId, { page, per_page: 15 });
      const data = response.data.data;
      setEmployees(data.data || []);
      setEmployeesTotalPages(data.last_page || 1);
    } catch {
      toast.error('Çalışanlar yüklenemedi');
    } finally {
      setEmployeesLoading(false);
    }
  }, []);

  useEffect(() => {
    loadBranches();
    loadUsers();
  }, [loadBranches, loadUsers]);

  

  

  const handleEdit = (branch: Branch) => {
    setSelectedBranch(branch);
    setFormOpen(true);
  };

  const handleDelete = (branch: Branch) => {
    if (branch.is_headquarters) {
      toast.error('Merkez şube silinemez');
      return;
    }
    setBranchToDelete(branch);
    setDeleteDialogOpen(true);
  };

  const confirmDelete = async () => {
    if (!branchToDelete) return;

    try {
      setDeleteLoading(true);
      await branchesApi.delete(branchToDelete.id);
      toast.success('Şube silindi');
      setDeleteDialogOpen(false);
      setBranchToDelete(null);
      loadBranches();
    } catch {
      toast.error('Şube silinemedi');
    } finally {
      setDeleteLoading(false);
    }
  };

  const handleSetHeadquarters = async (branch: Branch) => {
    try {
      setActionLoading(branch.id);
      await branchesApi.setHeadquarters(branch.id);
      toast.success('Merkez şube güncellendi');
      loadBranches();
    } catch {
      toast.error('Merkez şube güncellenemedi');
    } finally {
      setActionLoading(null);
    }
  };

  const handleToggleStatus = async (branch: Branch) => {
    try {
      setActionLoading(branch.id);
      await branchesApi.update(branch.id, { is_active: !branch.is_active });
      toast.success(branch.is_active ? 'Şube pasif yapıldı' : 'Şube aktif yapıldı');
      loadBranches();
    } catch {
      toast.error('Durum güncellenemedi');
    } finally {
      setActionLoading(null);
    }
  };

  const handleFormClose = () => {
    setFormOpen(false);
    setSelectedBranch(undefined);
  };

  const handleFormSubmit = () => {
    handleFormClose();
    loadBranches();
  };

  const handleViewEmployees = async (branch: Branch) => {
    setSelectedBranchForEmployees(branch);
    setEmployeesModalOpen(true);
    setEmployeesPage(1);
    loadBranchEmployees(branch.id, 1);
  };

  

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1>Şubeler</h1>
          <p>Şube ve lokasyon yönetimi</p>
        </div>
        <button className="btn btn-primary" onClick={() => setFormOpen(true)}>
          <BsPlus /> Yeni Şube
        </button>
      </div>

      {/* Filters */}
      <div className="card mb-4">
        <div className="card-body">
          <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', alignItems: 'center' }}>
            <div style={{ flex: 1, minWidth: 250 }}>
              <div className="form-group" style={{ marginBottom: 0 }}>
                <div className="input-with-icon">
                  <BsSearch />
                  <input
                    type="text"
                    className="form-control"
                    placeholder="Şube ara..."
                    value={search}
                    onChange={(e) => {
                      setSearch(e.target.value);
                      setPage(1);
                    }}
                  />
                </div>
              </div>
            </div>
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <button
                className={`btn btn-ghost ${isActiveFilter === null ? 'active' : ''}`}
                onClick={() => setIsActiveFilter(null)}
              >
                Tümü
              </button>
              <button
                className={`btn btn-ghost ${isActiveFilter === true ? 'active' : ''}`}
                onClick={() => setIsActiveFilter(true)}
              >
                Aktif
              </button>
              <button
                className={`btn btn-ghost ${isActiveFilter === false ? 'active' : ''}`}
                onClick={() => setIsActiveFilter(false)}
              >
                Pasif
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Branches List */}
      <div className="card">
        <div className="card-body">
          {loading ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              {[...Array(3)].map((_, i) => (
                <div key={i} style={{ padding: '1rem', background: 'var(--surface-glass)', borderRadius: 'var(--radius-md)' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                    <Skeleton width={200} height={20} />
                    <Skeleton width={60} height={20} />
                    <div style={{ flex: 1 }} />
                    <Skeleton width={24} height={24} circle />
                    <Skeleton width={24} height={24} circle />
                    <Skeleton width={24} height={24} circle />
                  </div>
                  <div style={{ marginTop: '0.5rem', display: 'flex', gap: '1rem' }}>
                    <Skeleton width={150} height={14} />
                    <Skeleton width={120} height={14} />
                  </div>
                </div>
              ))}
            </div>
          ) : branches.length === 0 ? (
            <div style={{ textAlign: 'center', padding: '3rem', color: 'var(--text-tertiary)' }}>
              <BsBuilding size={48} style={{ marginBottom: '1rem', opacity: 0.5 }} />
              <p>Henüz şube eklenmemiş</p>
              <button className="btn btn-primary btn-sm" onClick={() => setFormOpen(true)}>
                <BsPlus /> İlk Şubeyi Ekle
              </button>
            </div>
          ) : (
            <>
              <div style={{ display: 'grid', gap: '1rem' }}>
                {branches.map((branch) => (
                  <div
                    key={branch.id}
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                      padding: '1rem',
                      background: branch.is_active ? 'var(--surface-primary)' : 'var(--surface-glass)',
                      border: `1px solid ${branch.is_headquarters ? 'var(--primary)' : 'var(--border-primary)'}`,
                      borderRadius: 'var(--radius-md)',
                      opacity: branch.is_active ? 1 : 0.7,
                    }}
                  >
                    <div style={{ flex: 1 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.5rem' }}>
                        <h4 style={{ margin: 0, fontSize: '1rem', fontWeight: 600 }}>
                          {branch.name}
                        </h4>
                        {branch.is_headquarters && (
                          <span className="badge badge-primary" style={{ fontSize: '0.75rem' }}>
                            <BsStarFill size={12} /> Merkez
                          </span>
                        )}
                        {branch.code && (
                          <span className="badge badge-secondary" style={{ fontSize: '0.75rem' }}>
                            {branch.code}
                          </span>
                        )}
                        <span className={`badge ${branch.is_active ? 'badge-success' : 'badge-secondary'}`}>
                          {branch.is_active ? 'Aktif' : 'Pasif'}
                        </span>
                      </div>
                      <div style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', display: 'flex', flexWrap: 'wrap', gap: '1rem' }}>
                        {branch.city && (
                          <span>
                            <BsBuilding size={14} style={{ marginRight: '0.25rem' }} />
                            {branch.city}
                            {branch.district && `, ${branch.district}`}
                          </span>
                        )}
                        {branch.phone && <span>📞 {branch.phone}</span>}
                        {branch.email && <span>✉️ {branch.email}</span>}
                        {branch.manager && (
                          <span>👤 {branch.manager.name}</span>
                        )}
                      </div>
                      {branch.address && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginTop: '0.5rem' }}>
                          {branch.address}
                        </div>
                      )}
                    </div>
                    <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
                      {!branch.is_headquarters && (
                        <button
                          className="btn btn-ghost btn-icon btn-sm"
                          onClick={() => handleSetHeadquarters(branch)}
                          disabled={actionLoading === branch.id || !branch.is_active}
                          title="Merkez şube yap"
                        >
                          <BsStar />
                        </button>
                      )}
                      <button
                        className="btn btn-ghost btn-icon btn-sm"
                        onClick={() => handleToggleStatus(branch)}
                        disabled={actionLoading === branch.id}
                        title={branch.is_active ? 'Pasif yap' : 'Aktif yap'}
                      >
                        {branch.is_active ? <BsCheck /> : <BsX />}
                      </button>
                      <button
                        className="btn btn-ghost btn-icon btn-sm"
                        onClick={() => handleViewEmployees(branch)}
                        title="Çalışanları Görüntüle"
                      >
                        <BsPeople />
                      </button>
                      <button
                        className="btn btn-ghost btn-icon btn-sm"
                        onClick={() => navigate(`/branches/${branch.id}`)}
                        title="Detay"
                      >
                        <BsEye />
                      </button>
                      <button
                        className="btn btn-ghost btn-icon btn-sm"
                        onClick={() => handleEdit(branch)}
                        title="Düzenle"
                      >
                        <BsPencil />
                      </button>
                      {!branch.is_headquarters && (
                        <button
                          className="btn btn-ghost btn-icon btn-sm"
                          onClick={() => handleDelete(branch)}
                          title="Sil"
                          style={{ color: 'var(--danger)' }}
                        >
                          <BsTrash />
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>

              {/* Pagination */}
              {totalPages > 1 && (
                <div style={{ marginTop: '1.5rem', display: 'flex', justifyContent: 'center', gap: '0.5rem' }}>
                  <button
                    className="btn btn-ghost btn-sm"
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                    disabled={page === 1}
                  >
                    Önceki
                  </button>
                  <span style={{ display: 'flex', alignItems: 'center', padding: '0 1rem' }}>
                    Sayfa {page} / {totalPages} (Toplam: {total})
                  </span>
                  <button
                    className="btn btn-ghost btn-sm"
                    onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                    disabled={page === totalPages}
                  >
                    Sonraki
                  </button>
                </div>
              )}
            </>
          )}
        </div>
      </div>

      {/* Branch Form Modal */}
      {formOpen && (
        <BranchForm
          branch={selectedBranch}
          users={users}
          onClose={handleFormClose}
          onSuccess={handleFormSubmit}
        />
      )}

      {/* Delete Dialog */}
      <DeleteDialog
        open={deleteDialogOpen}
        onClose={() => {
          setDeleteDialogOpen(false);
          setBranchToDelete(null);
        }}
        onConfirm={confirmDelete}
        loading={deleteLoading}
        title="Şubeyi Sil"
        message={`"${branchToDelete?.name}" şubesini silmek istediğinize emin misiniz? Bu işlem geri alınamaz.`}
      />

      {/* Employees Modal */}
      <Modal
        isOpen={employeesModalOpen}
        onClose={() => {
          setEmployeesModalOpen(false);
          setSelectedBranchForEmployees(null);
          setEmployees([]);
        }}
        title={`${selectedBranchForEmployees?.name} - Çalışanlar`}
        size="lg"
      >
        {employeesLoading ? (
          <div className="page-loading">
            <div className="loading-spinner" />
          </div>
        ) : employees.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
            <BsPeople size={48} style={{ marginBottom: '1rem', opacity: 0.5 }} />
            <p>Bu şubede henüz çalışan bulunmuyor</p>
          </div>
        ) : (
          <>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              {employees.map((employee) => (
                <div
                  key={employee.id}
                  style={{
                    padding: '1rem',
                    background: 'var(--surface-glass)',
                    border: '1px solid var(--border-primary)',
                    borderRadius: 'var(--radius-md)',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '1rem',
                  }}
                >
                  <div
                    style={{
                      width: 40,
                      height: 40,
                      borderRadius: 'var(--radius-md)',
                      background: 'var(--gradient-primary)',
                      color: 'white',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      fontSize: '0.875rem',
                      fontWeight: 600,
                      flexShrink: 0,
                    }}
                  >
                    {employee.user?.name?.charAt(0).toUpperCase() || '?'}
                  </div>
                  <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 500, color: 'var(--text-primary)' }}>
                      {employee.user?.name || 'İsimsiz'}
                    </div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                      {employee.user?.email || '-'}
                    </div>
                    {employee.position && (
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', marginTop: '0.25rem' }}>
                        {employee.position}
                      </div>
                    )}
                  </div>
                  {employee.department && (
                    <span className="badge badge-secondary" style={{ fontSize: '0.75rem' }}>
                      {employee.department.name}
                    </span>
                  )}
                  <span className={`badge ${employee.status === 'active' ? 'badge-success' : 'badge-secondary'}`}>
                    {employee.status === 'active' ? 'Aktif' : 'Pasif'}
                  </span>
                </div>
              ))}
            </div>

            {/* Pagination */}
            {employeesTotalPages > 1 && (
              <div style={{ marginTop: '1.5rem', display: 'flex', justifyContent: 'center', gap: '0.5rem' }}>
                <button
                  className="btn btn-ghost btn-sm"
                  onClick={() => {
                    const newPage = employeesPage - 1;
                    setEmployeesPage(newPage);
                    if (selectedBranchForEmployees) {
                      loadBranchEmployees(selectedBranchForEmployees.id, newPage);
                    }
                  }}
                  disabled={employeesPage === 1}
                >
                  Önceki
                </button>
                <span style={{ display: 'flex', alignItems: 'center', padding: '0 1rem' }}>
                  Sayfa {employeesPage} / {employeesTotalPages}
                </span>
                <button
                  className="btn btn-ghost btn-sm"
                  onClick={() => {
                    const newPage = employeesPage + 1;
                    setEmployeesPage(newPage);
                    if (selectedBranchForEmployees) {
                      loadBranchEmployees(selectedBranchForEmployees.id, newPage);
                    }
                  }}
                  disabled={employeesPage === employeesTotalPages}
                >
                  Sonraki
                </button>
              </div>
            )}
          </>
        )}
      </Modal>
    </div>
  );
};

export default BranchesPage;

