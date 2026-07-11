import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { recruitmentApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import ApplicationDetailModal from './ApplicationDetailModal';
import {
  BsArrowLeft,
  BsStarFill,
  BsStar,
  BsEnvelope,
  BsTelephone,
} from 'react-icons/bs';

interface Application {
  id: number;
  job_position: { id: number; title: string };
  full_name: string;
  email: string;
  phone?: string;
  status: 'pending' | 'reviewing' | 'interview' | 'offered' | 'hired' | 'rejected';
  rating?: number;
  notes?: string;
  created_at: string;
}

const statusColumns = [
  { key: 'new', label: 'Yeni', color: '#94a3b8' },
  { key: 'reviewing', label: 'İnceleniyor', color: '#f59e0b' },
  { key: 'shortlisted', label: 'Ön Seçim', color: '#8b5cf6' },
  { key: 'interview_scheduled', label: 'Mülakat Planlandı', color: '#3b82f6' },
  { key: 'interviewed', label: 'Mülakat Yapıldı', color: '#0ea5e9' },
  { key: 'offer_sent', label: 'Teklif Gönderildi', color: '#6366f1' },
  { key: 'hired', label: 'İşe Alındı', color: '#10b981' },
  { key: 'rejected', label: 'Reddedildi', color: '#ef4444' },
];

const ApplicationsPage: React.FC = () => {
  const navigate = useNavigate();
  const [applications, setApplications] = useState<Application[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  
  // Detail modal
  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [selectedApplicationId, setSelectedApplicationId] = useState<number | null>(null);

  useEffect(() => {
    loadApplications();
  }, []);

  const handleViewDetail = (applicationId: number) => {
    setSelectedApplicationId(applicationId);
    setDetailModalOpen(true);
  };

  const loadApplications = async () => {
    try {
      setLoading(true);
      const response = await recruitmentApi.applications.list({ per_page: 100 });
      const data = response.data.data;

      if (Array.isArray(data)) {
        setApplications(data);
      } else if (data?.data) {
        setApplications(data.data);
      }
    } catch {
      toast.error('Başvurular yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const handleStatusChange = async (app: Application, newStatus: string) => {
    setActionLoading(app.id);
    try {
      await recruitmentApi.applications.updateStatus(app.id, { status: newStatus });
      toast.success('Durum güncellendi');
      loadApplications();
    } catch {
      toast.error('Durum güncellenemedi');
    } finally {
      setActionLoading(null);
    }
  };

  const handleRate = async (app: Application, rating: number) => {
    try {
      await recruitmentApi.applications.rate(app.id, rating);
      toast.success('Puanlama kaydedildi');
      loadApplications();
    } catch {
      toast.error('Puanlama kaydedilemedi');
    }
  };

  const getApplicationsByStatus = (status: string) => {
    return applications.filter((a) => a.status === status);
  };

  if (loading) {
    return (
      <div className="page-loading">
        <div className="loading-spinner" />
      </div>
    );
  }

  return (
    <div className="animate-fade-in" style={{ display: 'flex', flexDirection: 'column', height: '100%', overflow: 'hidden' }}>
      <div className="page-header" style={{ flexShrink: 0 }}>
        <div className="page-header-content">
          <button
            type="button"
            className="btn btn-ghost btn-sm"
            onClick={() => navigate('/recruitment/positions')}
            style={{ marginBottom: 'var(--sp-1)' }}
          >
            <BsArrowLeft /> Pozisyonlara Dön
          </button>
          <h1 className="page-title">Başvurular</h1>
        </div>
      </div>

      <div className="kanban-board">
        {statusColumns.map((col) => {
          const colApps = getApplicationsByStatus(col.key);
          return (
            <div key={col.key} className="kanban-column">
              <div className="kanban-column-header">
                <div className="kanban-column-title">
                  <span className="kanban-column-dot" style={{ background: col.color }} />
                  {col.label}
                </div>
                <span className="badge badge-secondary">{colApps.length}</span>
              </div>

              <div className="kanban-column-body">
                {colApps.length === 0 ? (
                  <div
                    style={{
                      padding: 'var(--sp-5)',
                      textAlign: 'center',
                      color: 'var(--text-muted)',
                      fontSize: 'var(--fs-caption)',
                    }}
                  >
                    Başvuru yok
                  </div>
                ) : (
                  colApps.map((app) => (
                    <ApplicationCard
                      key={app.id}
                      application={app}
                      onStatusChange={handleStatusChange}
                      onRate={handleRate}
                      onViewDetail={handleViewDetail}
                      isLoading={actionLoading === app.id}
                      statusColumns={statusColumns}
                    />
                  ))
                )}
              </div>
            </div>
          );
        })}
      </div>

      <ApplicationDetailModal
        isOpen={detailModalOpen}
        onClose={() => setDetailModalOpen(false)}
        applicationId={selectedApplicationId}
        onUpdate={loadApplications}
      />
    </div>
  );
};

interface ApplicationCardProps {
  application: Application;
  onStatusChange: (app: Application, status: string) => void;
  onRate: (app: Application, rating: number) => void;
  onViewDetail: (id: number) => void;
  isLoading: boolean;
  statusColumns: typeof statusColumns;
}

const ApplicationCard: React.FC<ApplicationCardProps> = ({
  application,
  onStatusChange,
  onRate,
  onViewDetail,
  isLoading,
  statusColumns,
}) => {
  const [showActions, setShowActions] = useState(false);

  return (
    <div
      className="kanban-card"
      onClick={() => onViewDetail(application.id)}
      onMouseEnter={() => setShowActions(true)}
      onMouseLeave={() => setShowActions(false)}
    >
      <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 'var(--sp-2)', gap: 'var(--sp-2)' }}>
        <div style={{ minWidth: 0 }}>
          <div className="kanban-card-name">{application.full_name}</div>
          <div className="kanban-card-meta">{application.job_position.title}</div>
        </div>
        <div className="kanban-card-avatar" aria-hidden>
          {application.full_name.charAt(0)}
        </div>
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 2, marginBottom: 'var(--sp-2)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-1)', fontSize: 'var(--fs-caption)', color: 'var(--text-secondary)' }}>
          <BsEnvelope size={12} />
          <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{application.email}</span>
        </div>
        {application.phone && (
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-1)', fontSize: 'var(--fs-caption)', color: 'var(--text-secondary)' }}>
            <BsTelephone size={12} />
            <span>{application.phone}</span>
          </div>
        )}
      </div>

      <div style={{ display: 'flex', alignItems: 'center', gap: 2, marginBottom: 'var(--sp-2)' }}>
        {[1, 2, 3, 4, 5].map((star) => (
          <button
            key={star}
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onRate(application, star);
            }}
            style={{
              background: 'none',
              border: 'none',
              padding: 0,
              cursor: 'pointer',
              color: star <= (application.rating || 0) ? '#f59e0b' : 'var(--text-muted)',
            }}
          >
            {star <= (application.rating || 0) ? <BsStarFill size={12} /> : <BsStar size={12} />}
          </button>
        ))}
      </div>

      {showActions && !isLoading && (
        <div
          style={{
            display: 'flex',
            flexWrap: 'wrap',
            gap: 2,
            marginTop: 'var(--sp-1)',
            paddingTop: 'var(--sp-2)',
            borderTop: '1px solid var(--border-primary)',
          }}
        >
          {statusColumns
            .filter((s) => s.key !== application.status)
            .slice(0, 3)
            .map((s) => (
              <button
                key={s.key}
                type="button"
                className="btn btn-ghost btn-sm"
                onClick={(e) => {
                  e.stopPropagation();
                  onStatusChange(application, s.key);
                }}
                style={{ fontSize: 'var(--fs-caption)', padding: '2px var(--sp-2)' }}
              >
                {s.label}
              </button>
            ))}
        </div>
      )}

      {isLoading && (
        <div style={{ display: 'flex', justifyContent: 'center', marginTop: 'var(--sp-2)' }}>
          <div className="loading-spinner" style={{ width: 16, height: 16, borderWidth: 2 }} />
        </div>
      )}

      <div style={{ fontSize: 'var(--fs-caption)', color: 'var(--text-muted)', marginTop: 'var(--sp-1)' }}>
        {new Date(application.created_at).toLocaleDateString('tr-TR')}
      </div>
    </div>
  );
};

export default ApplicationsPage;
