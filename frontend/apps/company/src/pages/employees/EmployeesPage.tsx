import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  BsPlus, 
  BsSearch, 
  BsPersonBadge, 
  BsDownload,
  BsUpload,
  BsFilter,
  BsPencil,
  BsTrash,
  BsEye,
  BsKey,
  BsKeyFill,
  BsCheckSquare,
  BsSquare,
} from 'react-icons/bs';
import { employeesApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { ConfirmDialog } from '../../components/ui';
import EmployeeImportModal from '../../components/employees/EmployeeImportModal';

interface Department {
  id: number;
  name: string;
}

interface Employee {
  id: number;
  employee_code: string;
  position?: string;
  title?: string;
  status: string;
  hire_date?: string;
  contract_type?: string;
  user?: {
    id: number;
    name: string;
    email: string;
  };
  department?: {
    id: number;
    name: string;
  };
  manager?: {
    id: number;
    user?: {
      name: string;
    };
  };
}

const EmployeesPage: React.FC = () => {
  const navigate = useNavigate();
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [departmentFilter, setDepartmentFilter] = useState('');
  const [contractTypeFilter, setContractTypeFilter] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalCount, setTotalCount] = useState(0);
  const [showFilters, setShowFilters] = useState(false);
  
  // Seçim durumu
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [, setShowBulkMenu] = useState(false);
  
  // Modal durumları
  const [importModalOpen, setImportModalOpen] = useState(false);
  const [bulkDeleteDialogOpen, setBulkDeleteDialogOpen] = useState(false);
  const [bulkStatusDialogOpen, setBulkStatusDialogOpen] = useState(false);
  const [newBulkStatus, setNewBulkStatus] = useState('');

  const loadDepartments = useCallback(async () => {
    try {
      const response = await employeesApi.getDepartments();
      setDepartments(response.data.data);
    } catch (error) {
      console.error('Departmanlar yüklenemedi:', error);
    }
  }, []);

  const loadEmployees = useCallback(async () => {
    try {
      setLoading(true);
      const response = await employeesApi.getAll({
        search,
        status: statusFilter,
        department_id: departmentFilter,
        contract_type: contractTypeFilter,
        page: currentPage,
        per_page: 15,
      });
      
      setEmployees(response.data.data.data);
      setTotalPages(response.data.data.last_page);
      setTotalCount(response.data.data.total);
      setSelectedIds([]);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'Personeller yüklenirken hata oluştu'));
    } finally {
      setLoading(false);
    }
  }, [search, statusFilter, departmentFilter, contractTypeFilter, currentPage]);

  useEffect(() => {
    loadDepartments();
  }, [loadDepartments]);

  useEffect(() => {
    loadEmployees();
  }, [loadEmployees]);

  

  

  const handleDelete = async (id: number) => {
    if (!confirm('Bu personeli silmek istediğinizden emin misiniz?')) return;

    try {
      await employeesApi.delete(id);
      toast.success('Personel başarıyla silindi');
      loadEmployees();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'Personel silinirken hata oluştu'));
    }
  };

  const handleExport = async () => {
    try {
      const response = await employeesApi.export({
        status: statusFilter,
        department_id: departmentFilter,
      });
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `personel_listesi_${new Date().toISOString().split('T')[0]}.csv`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      toast.success('Personel listesi indirildi');
    } catch {
      toast.error('Export işlemi başarısız oldu');
    }
  };

  const handleSelectAll = () => {
    if (selectedIds.length === employees.length) {
      setSelectedIds([]);
    } else {
      setSelectedIds(employees.map(e => e.id));
    }
  };

  const handleSelectOne = (id: number) => {
    if (selectedIds.includes(id)) {
      setSelectedIds(selectedIds.filter(i => i !== id));
    } else {
      setSelectedIds([...selectedIds, id]);
    }
  };

  const handleBulkDelete = async () => {
    try {
      await employeesApi.bulkDelete(selectedIds);
      toast.success(`${selectedIds.length} personel silindi`);
      setBulkDeleteDialogOpen(false);
      loadEmployees();
    } catch {
      toast.error('Toplu silme işlemi başarısız oldu');
    }
  };

  const handleBulkStatusUpdate = async () => {
    if (!newBulkStatus) return;
    
    try {
      await employeesApi.bulkUpdate(selectedIds, { status: newBulkStatus });
      toast.success(`${selectedIds.length} personelin durumu güncellendi`);
      setBulkStatusDialogOpen(false);
      setNewBulkStatus('');
      loadEmployees();
    } catch {
      toast.error('Toplu güncelleme işlemi başarısız oldu');
    }
  };

  const clearFilters = () => {
    setSearch('');
    setStatusFilter('');
    setDepartmentFilter('');
    setContractTypeFilter('');
    setCurrentPage(1);
  };

  const hasActiveFilters = search || statusFilter || departmentFilter || contractTypeFilter;

  const getStatusBadge = (status: string) => {
    const statusMap: Record<string, { label: string; className: string }> = {
      active: { label: 'Aktif', className: 'badge-success' },
      on_leave: { label: 'İzinli', className: 'badge-warning' },
      suspended: { label: 'Askıda', className: 'badge-danger' },
      terminated: { label: 'İşten Çıkmış', className: 'badge-secondary' },
    };
    
    const statusInfo = statusMap[status] || { label: status, className: 'badge-secondary' };
    return <span className={`badge ${statusInfo.className}`}>{statusInfo.label}</span>;
  };

  if (loading && employees.length === 0) {
    return (
      <div className="animate-fade-in">
        <div className="page-header">
          <div className="page-header-content">
            <h1 className="page-title">Personel Yönetimi</h1>
            <p className="page-subtitle">Tüm personel kayıtlarını yönetin</p>
          </div>
        </div>
        <div className="card">
          <div className="card-body">
            <div className="text-center py-4">
              <div className="spinner-border" role="status">
                <span className="visually-hidden">Yükleniyor...</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Personel Yönetimi</h1>
          <p className="page-subtitle">
            {totalCount > 0 ? `${totalCount} personel kayıtlı` : 'Tüm personel kayıtlarını yönetin'}
          </p>
        </div>
        <div className="page-header-actions">
          <button className="btn btn-secondary" onClick={() => setImportModalOpen(true)}>
            <BsUpload /> Import
          </button>
          <button className="btn btn-secondary" onClick={handleExport}>
            <BsDownload /> Export
          </button>
          <button className="btn btn-primary" onClick={() => navigate('/employees/new')}>
            <BsPlus /> Yeni Personel
          </button>
        </div>
      </div>

      {/* Toplu İşlem Barı */}
      {selectedIds.length > 0 && (
        <div
          className="card mb-3"
          style={{
            background: 'var(--primary-soft)',
            border: '1px solid var(--primary)',
          }}
        >
          <div className="card-body" style={{ padding: '0.75rem 1rem' }}>
            <div className="d-flex justify-content-between align-items-center">
              <span style={{ fontWeight: 500 }}>
                {selectedIds.length} personel seçildi
              </span>
              <div className="d-flex gap-2">
                <button
                  className="btn btn-secondary btn-sm"
                  onClick={() => {
                    setBulkStatusDialogOpen(true);
                    setShowBulkMenu(false);
                  }}
                >
                  Durumu Değiştir
                </button>
                <button
                  className="btn btn-danger btn-sm"
                  onClick={() => {
                    setBulkDeleteDialogOpen(true);
                    setShowBulkMenu(false);
                  }}
                >
                  <BsTrash /> Sil
                </button>
                <button
                  className="btn btn-ghost btn-sm"
                  onClick={() => setSelectedIds([])}
                >
                  İptal
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Filters */}
      <div className="card mb-3">
        <div className="card-body">
          <div className="d-flex gap-2 align-items-center">
            <div className="flex-grow-1">
              <div className="input-group">
                <span className="input-icon"><BsSearch /></span>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Personel ara (ad, sicil no, pozisyon...)"
                  value={search}
                  onChange={(e) => { setSearch(e.target.value); setCurrentPage(1); }}
                />
              </div>
            </div>
            <button 
              className={`btn ${showFilters || hasActiveFilters ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => setShowFilters(!showFilters)}
            >
              <BsFilter /> Filtreler {hasActiveFilters && `(${[statusFilter, departmentFilter, contractTypeFilter].filter(Boolean).length})`}
            </button>
            {hasActiveFilters && (
              <button className="btn btn-ghost" onClick={clearFilters}>
                Temizle
              </button>
            )}
          </div>

          {showFilters && (
            <div className="mt-3 pt-3 border-top">
              <div className="row g-3">
                <div className="col-md-4">
                  <label className="form-label">Durum</label>
                  <select
                    className="form-select"
                    value={statusFilter}
                    onChange={(e) => { setStatusFilter(e.target.value); setCurrentPage(1); }}
                  >
                    <option value="">Tümü</option>
                    <option value="active">Aktif</option>
                    <option value="on_leave">İzinli</option>
                    <option value="suspended">Askıda</option>
                    <option value="terminated">İşten Çıkmış</option>
                  </select>
                </div>
                <div className="col-md-4">
                  <label className="form-label">Departman</label>
                  <select
                    className="form-select"
                    value={departmentFilter}
                    onChange={(e) => { setDepartmentFilter(e.target.value); setCurrentPage(1); }}
                  >
                    <option value="">Tümü</option>
                    {departments.map((dept) => (
                      <option key={dept.id} value={dept.id}>{dept.name}</option>
                    ))}
                  </select>
                </div>
                <div className="col-md-4">
                  <label className="form-label">Sözleşme Tipi</label>
                  <select
                    className="form-select"
                    value={contractTypeFilter}
                    onChange={(e) => { setContractTypeFilter(e.target.value); setCurrentPage(1); }}
                  >
                    <option value="">Tümü</option>
                    <option value="permanent">Süresiz</option>
                    <option value="temporary">Süreli</option>
                    <option value="intern">Stajyer</option>
                    <option value="contract">Sözleşmeli</option>
                  </select>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Table */}
      <div className="card">
        <div className="table-responsive">
          <table className="table">
            <thead>
              <tr>
                <th style={{ width: 40 }}>
                  <button
                    className="btn btn-ghost btn-icon btn-sm"
                    onClick={handleSelectAll}
                    title={selectedIds.length === employees.length ? 'Seçimi Kaldır' : 'Tümünü Seç'}
                  >
                    {selectedIds.length === employees.length && employees.length > 0 ? (
                      <BsCheckSquare />
                    ) : (
                      <BsSquare />
                    )}
                  </button>
                </th>
                <th>Sicil No</th>
                <th>Ad Soyad</th>
                <th>Email</th>
                <th>Departman</th>
                <th>Pozisyon</th>
                <th>İşe Giriş</th>
                <th>Durum</th>
                <th>Portal</th>
                <th className="text-end">İşlemler</th>
              </tr>
            </thead>
            <tbody>
              {employees.length === 0 ? (
                <tr>
                  <td colSpan={10} className="text-center py-4">
                    <BsPersonBadge size={48} className="text-muted mb-2" />
                    <p className="text-muted">Henüz personel kaydı bulunmuyor</p>
                    <button 
                      className="btn btn-primary btn-sm"
                      onClick={() => navigate('/employees/new')}
                    >
                      <BsPlus /> İlk Personeli Ekle
                    </button>
                  </td>
                </tr>
              ) : (
                employees.map((employee) => (
                  <tr 
                    key={employee.id}
                    style={{
                      background: selectedIds.includes(employee.id) ? 'var(--primary-soft)' : undefined,
                    }}
                  >
                    <td>
                      <button
                        className="btn btn-ghost btn-icon btn-sm"
                        onClick={() => handleSelectOne(employee.id)}
                      >
                        {selectedIds.includes(employee.id) ? (
                          <BsCheckSquare style={{ color: 'var(--primary)' }} />
                        ) : (
                          <BsSquare />
                        )}
                      </button>
                    </td>
                    <td>
                      <span className="badge badge-secondary">{employee.employee_code}</span>
                    </td>
                    <td>
                      <strong>{employee.user?.name || '-'}</strong>
                    </td>
                    <td>{employee.user?.email || '-'}</td>
                    <td>{employee.department?.name || '-'}</td>
                    <td>{employee.position || '-'}</td>
                    <td>
                      {employee.hire_date 
                        ? new Date(employee.hire_date).toLocaleDateString('tr-TR')
                        : '-'
                      }
                    </td>
                    <td>{getStatusBadge(employee.status)}</td>
                    <td>
                      {employee.user ? (
                        <span className="badge badge-success">
                          <BsKeyFill /> Var
                        </span>
                      ) : (
                        <span className="badge badge-secondary">
                          <BsKey /> Yok
                        </span>
                      )}
                    </td>
                    <td>
                      <div className="btn-group btn-group-sm">
                        <button
                          className="btn btn-secondary"
                          onClick={() => navigate(`/employees/${employee.id}`)}
                          title="Detay"
                        >
                          <BsEye />
                        </button>
                        <button
                          className="btn btn-primary"
                          onClick={() => navigate(`/employees/${employee.id}/edit`)}
                          title="Düzenle"
                        >
                          <BsPencil />
                        </button>
                        <button
                          className="btn btn-danger"
                          onClick={() => handleDelete(employee.id)}
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

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="card-footer">
            <div className="d-flex justify-content-between align-items-center">
              <div>
                Sayfa {currentPage} / {totalPages} ({totalCount} kayıt)
              </div>
              <div className="btn-group">
                <button
                  className="btn btn-secondary btn-sm"
                  disabled={currentPage === 1}
                  onClick={() => setCurrentPage(currentPage - 1)}
                >
                  Önceki
                </button>
                <button
                  className="btn btn-secondary btn-sm"
                  disabled={currentPage === totalPages}
                  onClick={() => setCurrentPage(currentPage + 1)}
                >
                  Sonraki
                </button>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Import Modal */}
      <EmployeeImportModal
        isOpen={importModalOpen}
        onClose={() => setImportModalOpen(false)}
        onSuccess={loadEmployees}
      />

      {/* Toplu Silme Dialog */}
      <ConfirmDialog
        isOpen={bulkDeleteDialogOpen}
        onClose={() => setBulkDeleteDialogOpen(false)}
        onConfirm={handleBulkDelete}
        title="Toplu Silme"
        message={`${selectedIds.length} personeli silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.`}
        confirmText="Sil"
        variant="danger"
      />

      {/* Toplu Durum Güncelleme Modal */}
      {bulkStatusDialogOpen && (
        <div className="modal-overlay" onClick={() => setBulkStatusDialogOpen(false)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: 400 }}>
            <div className="modal-header">
              <h3>Durum Güncelle</h3>
              <button className="btn btn-ghost btn-icon" onClick={() => setBulkStatusDialogOpen(false)}>
                ×
              </button>
            </div>
            <div className="modal-body">
              <p style={{ marginBottom: '1rem', color: 'var(--text-secondary)' }}>
                {selectedIds.length} personelin durumunu güncelleyin
              </p>
              <div className="form-group">
                <label className="form-label">Yeni Durum</label>
                <select
                  className="form-select"
                  value={newBulkStatus}
                  onChange={(e) => setNewBulkStatus(e.target.value)}
                >
                  <option value="">Seçiniz...</option>
                  <option value="active">Aktif</option>
                  <option value="on_leave">İzinli</option>
                  <option value="suspended">Askıda</option>
                  <option value="terminated">İşten Çıkmış</option>
                </select>
              </div>
            </div>
            <div className="modal-footer">
              <button className="btn btn-secondary" onClick={() => setBulkStatusDialogOpen(false)}>
                İptal
              </button>
              <button
                className="btn btn-primary"
                onClick={handleBulkStatusUpdate}
                disabled={!newBulkStatus}
              >
                Güncelle
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default EmployeesPage;
