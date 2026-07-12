import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
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
import { employeesApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { Select } from '@shared/components';
import { toggleDensity } from '@shared/store/slices/themeSlice';
import toast from 'react-hot-toast';
import { ConfirmDialog, DataTable } from '../../components/ui';
import type { Column } from '../../components/ui/DataTable';
import EmployeeImportModal from '../../components/employees/EmployeeImportModal';
import type { RootState, AppDispatch } from '../../store';

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
  status_label?: string;
  status_color?: string;
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
  const dispatch = useDispatch<AppDispatch>();
  const density = useSelector((state: RootState) => state.theme.density);
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

  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [, setShowBulkMenu] = useState(false);

  const [importModalOpen, setImportModalOpen] = useState(false);
  const [bulkDeleteDialogOpen, setBulkDeleteDialogOpen] = useState(false);
  const [bulkStatusDialogOpen, setBulkStatusDialogOpen] = useState(false);
  const [newBulkStatus, setNewBulkStatus] = useState('');
  const [statusOptions, setStatusOptions] = useState<LookupItem[]>([]);
  const [contractOptions, setContractOptions] = useState<LookupItem[]>([]);

  const loadDepartments = useCallback(async () => {
    try {
      const response = await employeesApi.getDepartments();
      setDepartments(response.data.data);
    } catch (error) {
      console.error('Departmanlar yüklenemedi:', error);
    }
  }, []);

  const loadStatusLookups = useCallback(async () => {
    try {
      const [statusRes, contractRes] = await Promise.all([
        lookupsApi.forType('employee_status'),
        lookupsApi.forType('contract_type'),
      ]);
      setStatusOptions(statusRes.data.data ?? []);
      setContractOptions(contractRes.data.data ?? []);
    } catch (error) {
      console.error('Durum lookup yüklenemedi:', error);
    }
  }, []);

  const loadEmployees = useCallback(async () => {
    try {
      setLoading(true);
      // Boş string filtreleri gönderme — backend has() boş değerde de true döner
      const params: Record<string, string | number> = {
        page: currentPage,
        per_page: 15,
      };
      if (search.trim()) params.search = search.trim();
      if (statusFilter) params.status = statusFilter;
      if (departmentFilter) params.department_id = departmentFilter;
      if (contractTypeFilter) params.contract_type = contractTypeFilter;

      const response = await employeesApi.getAll(params);

      // ApiResponse paginated: data = items[], meta = { last_page, total, ... }
      setEmployees(Array.isArray(response.data.data) ? response.data.data : []);
      setTotalPages(response.data.meta?.last_page ?? 1);
      setTotalCount(response.data.meta?.total ?? 0);
      setSelectedIds([]);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'Personeller yüklenirken hata oluştu'));
    } finally {
      setLoading(false);
    }
  }, [search, statusFilter, departmentFilter, contractTypeFilter, currentPage]);

  useEffect(() => {
    loadDepartments();
    loadStatusLookups();
  }, [loadDepartments, loadStatusLookups]);

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
      setSelectedIds(employees.map((e) => e.id));
    }
  };

  const handleSelectOne = (id: number) => {
    if (selectedIds.includes(id)) {
      setSelectedIds(selectedIds.filter((i) => i !== id));
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

  const hasActiveFilters = Boolean(search || statusFilter || departmentFilter || contractTypeFilter);
  const advancedFilterCount = [statusFilter, departmentFilter, contractTypeFilter].filter(Boolean).length;

  const getStatusBadge = (employee: Employee) => {
    const label = employee.status_label
      || statusOptions.find((o) => o.value === employee.status)?.label
      || employee.status;
    const className =
      employee.status === 'active'
        ? 'badge-success'
        : employee.status === 'on_leave'
          ? 'badge-warning'
          : employee.status === 'suspended'
            ? 'badge-danger'
            : 'badge-secondary';

    return <span className={`badge ${className}`}>{label}</span>;
  };

  const columns: Column<Employee>[] = useMemo(
    () => [
      {
        key: 'select',
        width: '40px',
        title: (
          <button
            type="button"
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
        ),
        render: (employee) => (
          <button
            type="button"
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => handleSelectOne(employee.id)}
          >
            {selectedIds.includes(employee.id) ? (
              <BsCheckSquare style={{ color: 'var(--primary)' }} />
            ) : (
              <BsSquare />
            )}
          </button>
        ),
      },
      {
        key: 'employee_code',
        title: 'Sicil',
        width: '88px',
        render: (e) => <span className="badge badge-secondary">{e.employee_code}</span>,
      },
      {
        key: 'name',
        title: 'Ad Soyad',
        render: (e) => <strong>{e.user?.name || '-'}</strong>,
      },
      {
        key: 'email',
        title: 'E-posta',
        render: (e) => e.user?.email || '-',
      },
      {
        key: 'department',
        title: 'Departman',
        render: (e) => e.department?.name || '-',
      },
      {
        key: 'position',
        title: 'Pozisyon',
        render: (e) => e.position || '-',
      },
      {
        key: 'hire_date',
        title: 'İşe Giriş',
        width: '96px',
        render: (e) =>
          e.hire_date ? new Date(e.hire_date).toLocaleDateString('tr-TR') : '-',
      },
      {
        key: 'status',
        title: 'Durum',
        width: '100px',
        render: (e) => getStatusBadge(e),
      },
      {
        key: 'portal',
        title: 'Portal',
        width: '72px',
        render: (e) =>
          e.user ? (
            <span className="badge badge-success">
              <BsKeyFill /> Var
            </span>
          ) : (
            <span className="badge badge-secondary">
              <BsKey /> Yok
            </span>
          ),
      },
      {
        key: 'actions',
        title: 'İşlemler',
        align: 'right',
        width: '120px',
        render: (employee) => (
          <div className="table-actions">
            <button
              type="button"
              className="btn btn-ghost btn-icon"
              onClick={() => navigate(`/employees/${employee.id}`)}
              title="Detay"
              aria-label="Detay"
            >
              <BsEye />
            </button>
            <button
              type="button"
              className="btn btn-ghost btn-icon"
              onClick={() => navigate(`/employees/${employee.id}/edit`)}
              title="Düzenle"
              aria-label="Düzenle"
            >
              <BsPencil />
            </button>
            <button
              type="button"
              className="btn btn-ghost btn-icon"
              onClick={() => handleDelete(employee.id)}
              title="Sil"
              aria-label="Sil"
              style={{ color: 'var(--danger)' }}
            >
              <BsTrash />
            </button>
          </div>
        ),
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps -- handlers stable enough for list render
    [employees, selectedIds, navigate, statusOptions]
  );

  return (
    <div className="animate-fade-in list-page">
      {/* Tek satır başlık şeridi (TASARIM_REHBERI Bölüm 6) */}
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Personel</h1>
          {totalCount > 0 && (
            <span className="page-subtitle">{totalCount} kayıt</span>
          )}
        </div>
        <div className="page-header-actions">
          {/* FAZ3: density doğrulama toggle — görsel onay sonrası kalıcı UI'ya taşınır */}
          <button
            type="button"
            className="btn btn-ghost btn-sm"
            onClick={() => dispatch(toggleDensity())}
            title="Density değiştir (geçici doğrulama)"
          >
            Density: {density}
          </button>
          <button type="button" className="btn btn-secondary btn-sm" onClick={() => setImportModalOpen(true)}>
            <BsUpload /> Import
          </button>
          <button type="button" className="btn btn-secondary btn-sm" onClick={handleExport}>
            <BsDownload /> Export
          </button>
          <button type="button" className="btn btn-primary btn-sm" onClick={() => navigate('/employees/new')}>
            <BsPlus /> Yeni Personel
          </button>
        </div>
      </div>

      {selectedIds.length > 0 && (
        <div
          className="list-filter-bar"
          style={{
            background: 'var(--primary-soft)',
            borderColor: 'var(--primary)',
            marginBottom: 'var(--sp-2)',
          }}
        >
          <span style={{ fontWeight: 500, fontSize: 'var(--fs-body)' }}>
            {selectedIds.length} personel seçildi
          </span>
          <div className="d-flex gap-2" style={{ marginLeft: 'auto' }}>
            <button
              type="button"
              className="btn btn-secondary btn-sm"
              onClick={() => {
                setBulkStatusDialogOpen(true);
                setShowBulkMenu(false);
              }}
            >
              Durumu Değiştir
            </button>
            <button
              type="button"
              className="btn btn-danger btn-sm"
              onClick={() => {
                setBulkDeleteDialogOpen(true);
                setShowBulkMenu(false);
              }}
            >
              <BsTrash /> Sil
            </button>
            <button type="button" className="btn btn-ghost btn-sm" onClick={() => setSelectedIds([])}>
              İptal
            </button>
          </div>
        </div>
      )}

      {/* Yatay filtre şeridi */}
      <div className="list-filter-bar">
        <div className="list-filter-search input-group">
          <span className="input-icon"><BsSearch /></span>
          <input
            type="text"
            className="form-control"
            placeholder="Ara (ad, sicil, pozisyon...)"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setCurrentPage(1);
            }}
            style={{ paddingLeft: '2.25rem' }}
          />
        </div>
        <button
          type="button"
          className={`btn btn-sm ${showFilters || hasActiveFilters ? 'btn-primary' : 'btn-secondary'}`}
          onClick={() => setShowFilters(!showFilters)}
        >
          <BsFilter /> Filtreler{advancedFilterCount > 0 ? ` (${advancedFilterCount})` : ''}
        </button>
        {hasActiveFilters && (
          <button type="button" className="btn btn-ghost btn-sm" onClick={clearFilters}>
            Temizle
          </button>
        )}

        {showFilters && (
          <div className="list-filter-advanced">
            <div>
              <label className="form-label">Durum</label>
              <Select
                value={statusFilter}
                onChange={(v) => {
                  setStatusFilter(v);
                  setCurrentPage(1);
                }}
                options={statusOptions.map((opt) => ({
                  value: opt.value,
                  label: opt.label,
                  color: opt.color,
                }))}
                allowEmpty
                clearable
                emptyLabel="Tümü"
                aria-label="Durum filtresi"
              />
            </div>
            <div>
              <label className="form-label">Departman</label>
              <Select
                value={departmentFilter}
                onChange={(v) => {
                  setDepartmentFilter(v);
                  setCurrentPage(1);
                }}
                options={departments.map((dept) => ({
                  value: String(dept.id),
                  label: dept.name,
                }))}
                allowEmpty
                clearable
                emptyLabel="Tümü"
                aria-label="Departman filtresi"
              />
            </div>
            <div>
              <label className="form-label">Sözleşme Tipi</label>
              <Select
                value={contractTypeFilter}
                onChange={(v) => {
                  setContractTypeFilter(v);
                  setCurrentPage(1);
                }}
                options={contractOptions.map((opt) => ({
                  value: opt.value,
                  label: opt.label,
                  color: opt.color,
                }))}
                allowEmpty
                clearable
                emptyLabel="Tümü"
                aria-label="Sözleşme tipi filtresi"
              />
            </div>
          </div>
        )}
      </div>

      <DataTable
        columns={columns}
        data={employees}
        loading={loading}
        emptyMessage="Henüz personel kaydı bulunmuyor"
        emptyIcon={<BsPersonBadge size={40} className="text-muted" />}
        emptyAction={(
          <button type="button" className="btn btn-primary btn-sm" onClick={() => navigate('/employees/new')}>
            <BsPlus /> İlk Personeli Ekle
          </button>
        )}
        currentPage={currentPage}
        totalPages={totalPages}
        total={totalCount}
        onPageChange={setCurrentPage}
        isRowSelected={(e) => selectedIds.includes(e.id)}
      />

      <EmployeeImportModal
        isOpen={importModalOpen}
        onClose={() => setImportModalOpen(false)}
        onSuccess={loadEmployees}
      />

      <ConfirmDialog
        isOpen={bulkDeleteDialogOpen}
        onClose={() => setBulkDeleteDialogOpen(false)}
        onConfirm={handleBulkDelete}
        title="Toplu Silme"
        message={`${selectedIds.length} personeli silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.`}
        confirmText="Sil"
        variant="danger"
      />

      {bulkStatusDialogOpen && (
        <div className="modal-overlay" onClick={() => setBulkStatusDialogOpen(false)}>
          <div
            className="modal modal-sm"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="modal-header">
              <h3 className="modal-title">Durum Güncelle</h3>
              <button
                type="button"
                className="modal-close"
                onClick={() => setBulkStatusDialogOpen(false)}
              >
                ×
              </button>
            </div>
            <div className="modal-body">
              <p style={{ marginBottom: 'var(--sp-3)', color: 'var(--text-secondary)', fontSize: 'var(--fs-body)' }}>
                {selectedIds.length} personelin durumunu güncelleyin
              </p>
              <div className="form-group">
                <label className="form-label">Yeni Durum</label>
                <Select
                  value={newBulkStatus}
                  onChange={setNewBulkStatus}
                  options={statusOptions.map((opt) => ({
                    value: opt.value,
                    label: opt.label,
                    color: opt.color,
                  }))}
                  allowEmpty
                  placeholder="Seçiniz..."
                  aria-label="Yeni Durum"
                />
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={() => setBulkStatusDialogOpen(false)}>
                İptal
              </button>
              <button
                type="button"
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
