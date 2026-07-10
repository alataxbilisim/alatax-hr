import React, { useEffect, useState, useCallback } from 'react';
import { performanceApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { DataTable, ConfirmDialog, EmptyState } from '../../components/ui';
import PeriodForm from '../../components/performance/PeriodForm';
import CriteriaForm from '../../components/performance/CriteriaForm';
import ReviewForm from '../../components/performance/ReviewForm';
import { useNavigate } from 'react-router-dom';
import {
  BsPlus,
  BsGraphUp,
  BsCalendarRange,
  BsListCheck,
  BsPencil,
  BsTrash,
  BsEye,
  BsPlayFill,
  BsStopFill,
} from 'react-icons/bs';

interface Period {
  id: number;
  name: string;
  description?: string;
  start_date: string;
  end_date: string;
  status: 'draft' | 'active' | 'closed';
  reviews_count?: number;
}

interface Criteria {
  id: number;
  name: string;
  description?: string;
  weight: number;
  max_score: number;
  is_active: boolean;
}

interface Review {
  id: number;
  period: { id: number; name: string };
  employee: { id: number; name: string; email: string };
  reviewer: { id: number; name: string };
  status: 'draft' | 'submitted' | 'approved' | 'rejected';
  overall_score?: number;
  created_at: string;
}

type TabType = 'reviews' | 'periods' | 'criteria';

const statusBadgeClass: Record<string, string> = {
  draft: 'badge-warning',
  active: 'badge-success',
  closed: 'badge-secondary',
  submitted: 'badge-info',
  approved: 'badge-success',
  rejected: 'badge-danger',
};

const statusLabels: Record<string, string> = {
  draft: 'Taslak',
  active: 'Aktif',
  closed: 'Kapalı',
  submitted: 'Gönderildi',
  approved: 'Onaylandı',
  rejected: 'Reddedildi',
};

const PerformancePage: React.FC = () => {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState<TabType>('reviews');

  // Reviews state
  const [reviews, setReviews] = useState<Review[]>([]);
  const [reviewsLoading, setReviewsLoading] = useState(true);
  const [reviewPage, setReviewPage] = useState(1);
  const [reviewTotalPages, setReviewTotalPages] = useState(1);
  const [reviewFormOpen, setReviewFormOpen] = useState(false);

  // Periods state
  const [periods, setPeriods] = useState<Period[]>([]);
  const [periodsLoading, setPeriodsLoading] = useState(true);
  const [periodPage, setPeriodPage] = useState(1);
  const [periodTotalPages, setPeriodTotalPages] = useState(1);
  const [periodFormOpen, setPeriodFormOpen] = useState(false);
  const [selectedPeriod, setSelectedPeriod] = useState<Period | null>(null);

  // Criteria state
  const [criteria, setCriteria] = useState<Criteria[]>([]);
  const [criteriaLoading, setCriteriaLoading] = useState(true);
  const [criteriaFormOpen, setCriteriaFormOpen] = useState(false);
  const [selectedCriteria, setSelectedCriteria] = useState<Criteria | null>(null);

  // Delete state
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [itemToDelete, setItemToDelete] = useState<{ type: 'period' | 'criteria' | 'review'; item: Period | Criteria | Review } | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const loadReviews = useCallback(async () => {
    try {
      setReviewsLoading(true);
      const response = await performanceApi.reviews.list({ page: reviewPage });
      const data = response.data.data;
      setReviews(data.data || []);
      setReviewTotalPages(data.last_page || 1);
    } catch {
      toast.error('Değerlendirmeler yüklenemedi');
    } finally {
      setReviewsLoading(false);
    }
  }, [reviewPage]);

  const loadPeriods = useCallback(async () => {
    try {
      setPeriodsLoading(true);
      const response = await performanceApi.periods.list({ page: periodPage });
      const data = response.data.data;
      setPeriods(data.data || []);
      setPeriodTotalPages(data.last_page || 1);
    } catch {
      toast.error('Dönemler yüklenemedi');
    } finally {
      setPeriodsLoading(false);
    }
  }, [periodPage]);

  const loadCriteria = useCallback(async () => {
    try {
      setCriteriaLoading(true);
      const response = await performanceApi.criteria.list();
      setCriteria(response.data.data || []);
    } catch {
      toast.error('Kriterler yüklenemedi');
    } finally {
      setCriteriaLoading(false);
    }
  }, []);

  useEffect(() => {
    if (activeTab === 'reviews') {
      loadReviews();
    } else if (activeTab === 'periods') {
      loadPeriods();
    } else {
      loadCriteria();
    }
  }, [activeTab, loadReviews, loadPeriods, loadCriteria]);

  

  

  

  const handlePeriodSubmit = async (data: Omit<Period, 'id' | 'status'>) => {
    if (selectedPeriod) {
      await performanceApi.periods.update(selectedPeriod.id, data);
      toast.success('Dönem güncellendi');
    } else {
      await performanceApi.periods.create(data);
      toast.success('Dönem oluşturuldu');
    }
    loadPeriods();
  };

  const handleCriteriaSubmit = async (data: {
    name: string;
    description?: string;
    weight: number;
    max_score: number;
    is_active?: boolean;
  }) => {
    if (selectedCriteria) {
      await performanceApi.criteria.update(selectedCriteria.id, data);
      toast.success('Kriter güncellendi');
    } else {
      await performanceApi.criteria.create(data);
      toast.success('Kriter oluşturuldu');
    }
    loadCriteria();
  };

  const handleActivatePeriod = async (period: Period) => {
    try {
      await performanceApi.periods.activate(period.id);
      toast.success('Dönem aktifleştirildi');
      loadPeriods();
    } catch {
      // Error handled by interceptor
    }
  };

  const handleClosePeriod = async (period: Period) => {
    try {
      await performanceApi.periods.close(period.id);
      toast.success('Dönem kapatıldı');
      loadPeriods();
    } catch {
      // Error handled by interceptor
    }
  };

  const handleDelete = async () => {
    if (!itemToDelete) return;
    setDeleteLoading(true);
    try {
      if (itemToDelete.type === 'period') {
        await performanceApi.periods.delete((itemToDelete.item as Period).id);
        toast.success('Dönem silindi');
        loadPeriods();
      } else if (itemToDelete.type === 'criteria') {
        await performanceApi.criteria.delete((itemToDelete.item as Criteria).id);
        toast.success('Kriter silindi');
        loadCriteria();
      } else {
        await performanceApi.reviews.delete((itemToDelete.item as Review).id);
        toast.success('Değerlendirme silindi');
        loadReviews();
      }
      setDeleteDialogOpen(false);
      setItemToDelete(null);
    } catch {
      // Error handled by interceptor
    } finally {
      setDeleteLoading(false);
    }
  };

  const formatDate = (date: string) => new Date(date).toLocaleDateString('tr-TR');

  const reviewColumns = [
    {
      key: 'employee',
      title: 'Çalışan',
      render: (review: Review) => (
        <div>
          <div style={{ fontWeight: 500 }}>{review.employee?.name}</div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{review.employee?.email}</div>
        </div>
      ),
    },
    {
      key: 'period',
      title: 'Dönem',
      render: (review: Review) => review.period?.name,
    },
    {
      key: 'reviewer',
      title: 'Değerlendiren',
      render: (review: Review) => review.reviewer?.name,
    },
    {
      key: 'overall_score',
      title: 'Puan',
      render: (review: Review) => review.overall_score?.toFixed(1) || '-',
    },
    {
      key: 'status',
      title: 'Durum',
      render: (review: Review) => (
        <span className={`badge ${statusBadgeClass[review.status]}`}>
          {statusLabels[review.status]}
        </span>
      ),
    },
    {
      key: 'actions',
      title: '',
      render: (review: Review) => (
        <div style={{ display: 'flex', gap: '0.25rem' }}>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => navigate(`/performance/reviews/${review.id}`)}
            title="Detay"
          >
            <BsEye size={14} />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => {
              setItemToDelete({ type: 'review', item: review });
              setDeleteDialogOpen(true);
            }}
            title="Sil"
            style={{ color: 'var(--danger)' }}
            disabled={review.status === 'approved'}
          >
            <BsTrash size={14} />
          </button>
        </div>
      ),
    },
  ];

  const periodColumns = [
    {
      key: 'name',
      title: 'Dönem Adı',
      render: (period: Period) => (
        <div>
          <div style={{ fontWeight: 500 }}>{period.name}</div>
          {period.description && (
            <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{period.description}</div>
          )}
        </div>
      ),
    },
    {
      key: 'dates',
      title: 'Tarih Aralığı',
      render: (period: Period) => `${formatDate(period.start_date)} - ${formatDate(period.end_date)}`,
    },
    {
      key: 'reviews_count',
      title: 'Değerlendirme',
      render: (period: Period) => `${period.reviews_count || 0} adet`,
    },
    {
      key: 'status',
      title: 'Durum',
      render: (period: Period) => (
        <span className={`badge ${statusBadgeClass[period.status]}`}>
          {statusLabels[period.status]}
        </span>
      ),
    },
    {
      key: 'actions',
      title: '',
      render: (period: Period) => (
        <div style={{ display: 'flex', gap: '0.25rem' }}>
          {period.status === 'draft' && (
            <button
              className="btn btn-ghost btn-icon btn-sm"
              onClick={() => handleActivatePeriod(period)}
              title="Aktifleştir"
              style={{ color: 'var(--success)' }}
            >
              <BsPlayFill size={16} />
            </button>
          )}
          {period.status === 'active' && (
            <button
              className="btn btn-ghost btn-icon btn-sm"
              onClick={() => handleClosePeriod(period)}
              title="Kapat"
              style={{ color: 'var(--warning)' }}
            >
              <BsStopFill size={16} />
            </button>
          )}
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => {
              setSelectedPeriod(period);
              setPeriodFormOpen(true);
            }}
            title="Düzenle"
          >
            <BsPencil size={14} />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => {
              setItemToDelete({ type: 'period', item: period });
              setDeleteDialogOpen(true);
            }}
            title="Sil"
            style={{ color: 'var(--danger)' }}
          >
            <BsTrash size={14} />
          </button>
        </div>
      ),
    },
  ];

  const criteriaColumns = [
    {
      key: 'name',
      label: 'Kriter Adı',
      render: (c: Criteria) => (
        <div>
          <div style={{ fontWeight: 500 }}>{c.name}</div>
          {c.description && (
            <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{c.description}</div>
          )}
        </div>
      ),
    },
    {
      key: 'weight',
      label: 'Ağırlık',
      render: (c: Criteria) => `%${c.weight}`,
    },
    {
      key: 'max_score',
      label: 'Maks. Puan',
      render: (c: Criteria) => c.max_score,
    },
    {
      key: 'is_active',
      label: 'Durum',
      render: (c: Criteria) => (
        <span className={`badge ${c.is_active ? 'badge-success' : 'badge-warning'}`}>
          {c.is_active ? 'Aktif' : 'Pasif'}
        </span>
      ),
    },
    {
      key: 'actions',
      label: '',
      render: (c: Criteria) => (
        <div style={{ display: 'flex', gap: '0.25rem' }}>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => {
              setSelectedCriteria(c);
              setCriteriaFormOpen(true);
            }}
            title="Düzenle"
          >
            <BsPencil size={14} />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => {
              setItemToDelete({ type: 'criteria', item: c });
              setDeleteDialogOpen(true);
            }}
            title="Sil"
            style={{ color: 'var(--danger)' }}
          >
            <BsTrash size={14} />
          </button>
        </div>
      ),
    },
  ];

  const renderContent = () => {
    if (activeTab === 'reviews') {
      if (reviewsLoading) return <div className="loading-container"><div className="loading-spinner" /></div>;
      if (reviews.length === 0) {
        return (
          <EmptyState
            icon={<BsGraphUp size={48} />}
            title="Henüz değerlendirme yok"
            description="Yeni bir performans değerlendirmesi oluşturarak başlayın."
            action={
              <button className="btn btn-primary" onClick={() => setReviewFormOpen(true)}>
                <BsPlus size={18} />
                İlk Değerlendirmeyi Oluştur
              </button>
            }
          />
        );
      }
      return <DataTable columns={reviewColumns} data={reviews} currentPage={reviewPage} totalPages={reviewTotalPages} onPageChange={setReviewPage} />;
    }

    if (activeTab === 'periods') {
      if (periodsLoading) return <div className="loading-container"><div className="loading-spinner" /></div>;
      if (periods.length === 0) {
        return (
          <EmptyState
            icon={<BsCalendarRange size={48} />}
            title="Henüz dönem yok"
            description="Performans değerlendirmesi için önce bir dönem oluşturun."
            action={
              <button className="btn btn-primary" onClick={() => { setSelectedPeriod(null); setPeriodFormOpen(true); }}>
                <BsPlus size={18} />
                İlk Dönemi Oluştur
              </button>
            }
          />
        );
      }
      return <DataTable columns={periodColumns} data={periods} currentPage={periodPage} totalPages={periodTotalPages} onPageChange={setPeriodPage} />;
    }

    if (criteriaLoading) return <div className="loading-container"><div className="loading-spinner" /></div>;
    if (criteria.length === 0) {
      return (
        <EmptyState
          icon={<BsListCheck size={48} />}
          title="Henüz kriter yok"
          description="Performans değerlendirmesi için kriterler tanımlayın."
          action={
            <button className="btn btn-primary" onClick={() => { setSelectedCriteria(null); setCriteriaFormOpen(true); }}>
              <BsPlus size={18} />
              İlk Kriteri Oluştur
            </button>
          }
        />
      );
    }
    return (
      <div className="card">
        <div className="card-body" style={{ padding: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                {criteriaColumns.map(col => <th key={col.key}>{col.label}</th>)}
              </tr>
            </thead>
            <tbody>
              {criteria.map(c => (
                <tr key={c.id}>
                  {criteriaColumns.map(col => <td key={col.key}>{col.render(c)}</td>)}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    );
  };

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div>
          <h1 className="page-title">Performans Değerlendirme</h1>
          <p className="page-subtitle">Dönem, kriter ve değerlendirme yönetimi</p>
        </div>
        <button
          className="btn btn-primary"
          onClick={() => {
            if (activeTab === 'reviews') {
              setReviewFormOpen(true);
            } else if (activeTab === 'periods') {
              setSelectedPeriod(null);
              setPeriodFormOpen(true);
            } else {
              setSelectedCriteria(null);
              setCriteriaFormOpen(true);
            }
          }}
        >
          <BsPlus size={18} />
          {activeTab === 'reviews' ? 'Yeni Değerlendirme' : activeTab === 'periods' ? 'Yeni Dönem' : 'Yeni Kriter'}
        </button>
      </div>

      {/* Tabs */}
      <div className="tabs" style={{ marginBottom: '1.5rem' }}>
        <button
          className={`tab ${activeTab === 'reviews' ? 'active' : ''}`}
          onClick={() => setActiveTab('reviews')}
        >
          <BsGraphUp size={16} />
          Değerlendirmeler
        </button>
        <button
          className={`tab ${activeTab === 'periods' ? 'active' : ''}`}
          onClick={() => setActiveTab('periods')}
        >
          <BsCalendarRange size={16} />
          Dönemler
        </button>
        <button
          className={`tab ${activeTab === 'criteria' ? 'active' : ''}`}
          onClick={() => setActiveTab('criteria')}
        >
          <BsListCheck size={16} />
          Kriterler
        </button>
      </div>

      {/* Content */}
      {renderContent()}

      {/* Forms */}
      <PeriodForm
        isOpen={periodFormOpen}
        onClose={() => { setPeriodFormOpen(false); setSelectedPeriod(null); }}
        onSubmit={handlePeriodSubmit}
        period={selectedPeriod}
      />

      <CriteriaForm
        isOpen={criteriaFormOpen}
        onClose={() => { setCriteriaFormOpen(false); setSelectedCriteria(null); }}
        onSubmit={handleCriteriaSubmit}
        criteria={selectedCriteria}
      />

      <ReviewForm
        isOpen={reviewFormOpen}
        onClose={() => setReviewFormOpen(false)}
        onSuccess={loadReviews}
      />

      {/* Delete Confirm */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => { setDeleteDialogOpen(false); setItemToDelete(null); }}
        onConfirm={handleDelete}
        title={itemToDelete?.type === 'period' ? 'Dönemi Sil' : itemToDelete?.type === 'criteria' ? 'Kriteri Sil' : 'Değerlendirmeyi Sil'}
        message={`"${(itemToDelete?.item as Period | Criteria)?.name || 'Bu öğe'}" silinecek. Emin misiniz?`}
        confirmText="Sil"
        variant="danger"
        loading={deleteLoading}
      />
    </div>
  );
};

export default PerformancePage;

