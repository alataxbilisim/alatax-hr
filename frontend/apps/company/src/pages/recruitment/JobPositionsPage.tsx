import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { recruitmentApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { DataTable, ConfirmDialog } from '../../components/ui';
import JobPositionForm from '../../components/recruitment/JobPositionForm';
import {
  BsPlus,
  BsBriefcase,
  BsPencil,
  BsTrash,
  BsEye,
  BsPeople,
} from 'react-icons/bs';

interface JobPosition {
  id: number;
  title: string;
  department: string;
  location: string;
  employment_type: string;
  experience_level: string;
  status: 'draft' | 'active' | 'paused' | 'closed';
  applications_count?: number;
  created_at: string;
}

const JobPositionsPage: React.FC = () => {
  const navigate = useNavigate();
  const [positions, setPositions] = useState<JobPosition[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [search, setSearch] = useState('');

  // Modal states
  const [formOpen, setFormOpen] = useState(false);
  const [selectedPosition, setSelectedPosition] = useState<JobPosition | undefined>();
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [positionToDelete, setPositionToDelete] = useState<JobPosition | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const loadPositions = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = { page, per_page: 15 };
      if (search) params.search = search;

      const response = await recruitmentApi.positions.list(params);
      const data = response.data.data;

      if (Array.isArray(data)) {
        setPositions(data);
        setTotalPages(1);
        setTotal(data.length);
      } else if (data?.data) {
        setPositions(data.data);
        setTotalPages(data.meta?.last_page || data.last_page || 1);
        setTotal(data.meta?.total || data.total || 0);
      }
    } catch {
      toast.error('Pozisyonlar yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [page, search]);

  useEffect(() => {
    loadPositions();
  }, [loadPositions]);

  

  const handleEdit = (pos: JobPosition) => {
    setSelectedPosition(pos);
    setFormOpen(true);
  };

  const handleDelete = (pos: JobPosition) => {
    setPositionToDelete(pos);
    setDeleteDialogOpen(true);
  };

  const confirmDelete = async () => {
    if (!positionToDelete) return;

    setDeleteLoading(true);
    try {
      await recruitmentApi.positions.delete(positionToDelete.id);
      toast.success('Pozisyon silindi');
      setDeleteDialogOpen(false);
      setPositionToDelete(null);
      loadPositions();
    } catch {
      toast.error('Pozisyon silinemedi');
    } finally {
      setDeleteLoading(false);
    }
  };

  const getStatusBadge = (status: string) => {
    const statusMap: Record<string, { label: string; class: string }> = {
      draft: { label: 'Taslak', class: 'badge-secondary' },
      active: { label: 'Aktif / Yayında', class: 'badge-success' },
      paused: { label: 'Duraklatıldı', class: 'badge-warning' },
      closed: { label: 'Kapalı', class: 'badge-danger' },
    };
    const s = statusMap[status] || { label: status, class: 'badge-secondary' };
    return <span className={`badge ${s.class}`}>{s.label}</span>;
  };

  const getEmploymentLabel = (type: string) => {
    const labels: Record<string, string> = {
      full_time: 'Tam Zamanlı',
      part_time: 'Yarı Zamanlı',
      contract: 'Sözleşmeli',
      internship: 'Stajyer',
      remote: 'Uzaktan',
    };
    return labels[type] || type;
  };

  const columns = [
    {
      key: 'title',
      title: 'Pozisyon',
      render: (pos: JobPosition) => (
        <div>
          <div style={{ fontWeight: 500, color: 'var(--text-primary)' }}>{pos.title}</div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
            {pos.department} • {pos.location}
          </div>
        </div>
      ),
    },
    {
      key: 'type',
      title: 'Çalışma Şekli',
      render: (pos: JobPosition) => (
        <span className="badge badge-info">{getEmploymentLabel(pos.employment_type)}</span>
      ),
    },
    {
      key: 'applications',
      title: 'Başvuru',
      width: '80px',
      align: 'center' as const,
      render: (pos: JobPosition) => (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.25rem' }}>
          <BsPeople style={{ color: 'var(--text-tertiary)' }} />
          <span style={{ fontWeight: 500 }}>{pos.applications_count || 0}</span>
        </div>
      ),
    },
    {
      key: 'status',
      title: 'Durum',
      width: '100px',
      render: (pos: JobPosition) => getStatusBadge(pos.status),
    },
    {
      key: 'actions',
      title: 'İşlemler',
      width: '120px',
      align: 'right' as const,
      render: (pos: JobPosition) => (
        <div style={{ display: 'flex', gap: '0.25rem', justifyContent: 'flex-end' }}>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => navigate('/recruitment/applications')}
            title="Başvuruları Gör"
          >
            <BsEye />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => handleEdit(pos)}
            title="Düzenle"
          >
            <BsPencil />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => handleDelete(pos)}
            title="Sil"
            style={{ color: 'var(--danger)' }}
          >
            <BsTrash />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1>İş İlanları</h1>
          <p>Açık pozisyonları yönetin</p>
        </div>
        <div className="page-header-actions">
          <button
            className="btn btn-secondary"
            onClick={() => navigate('/recruitment/applications')}
          >
            <BsPeople size={16} /> Başvurular
          </button>
          <button
            className="btn btn-primary"
            onClick={() => {
              setSelectedPosition(undefined);
              setFormOpen(true);
            }}
          >
            <BsPlus size={18} /> Yeni İlan
          </button>
        </div>
      </div>

      {/* Data Table */}
      <DataTable
        columns={columns}
        data={positions}
        loading={loading}
        emptyMessage="Pozisyon bulunamadı"
        emptyIcon={<BsBriefcase size={32} />}
        currentPage={page}
        totalPages={totalPages}
        total={total}
        onPageChange={setPage}
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        searchPlaceholder="Pozisyon ara..."
      />

      {/* Form Modal */}
      <JobPositionForm
        isOpen={formOpen}
        onClose={() => setFormOpen(false)}
        onSuccess={loadPositions}
        position={selectedPosition}
      />

      {/* Delete Confirmation */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        onConfirm={confirmDelete}
        title="Pozisyonu Sil"
        message={`"${positionToDelete?.title}" pozisyonunu silmek istediğinize emin misiniz? Bu pozisyona yapılan tüm başvurular da silinecek.`}
        confirmText="Sil"
        variant="danger"
        loading={deleteLoading}
      />
    </div>
  );
};

export default JobPositionsPage;
