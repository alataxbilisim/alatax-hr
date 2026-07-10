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
      {/* Page Header */}
      <div className="page-header" style={{ flexShrink: 0 }}>
        <div className="page-header-content">
          <button
            className="btn btn-ghost btn-sm"
            onClick={() => navigate('/recruitment/positions')}
            style={{ marginBottom: '0.5rem' }}
          >
            <BsArrowLeft /> Pozisyonlara Dön
          </button>
          <h1>Başvurular</h1>
          <p>Tüm iş başvurularını yönetin</p>
        </div>
      </div>

      {/* Kanban Board */}
      <div
        className="kanban-board"
        style={{
          display: 'flex',
          gap: '0.75rem',
          overflowX: 'auto',
          overflowY: 'hidden',
          flex: 1,
          minHeight: 0,
          paddingBottom: '1rem',
          WebkitOverflowScrolling: 'touch',
        }}
      >
        {statusColumns.map((col) => {
          const colApps = getApplicationsByStatus(col.key);
          return (
            <div
              key={col.key}
              className="kanban-column"
              style={{
                minWidth: 240,
                maxWidth: 300,
                flex: '0 0 auto',
                display: 'flex',
                flexDirection: 'column',
                background: 'var(--surface-glass)',
                borderRadius: 'var(--radius-lg)',
                border: '1px solid var(--border-primary)',
                maxHeight: '100%',
              }}
            >
              {/* Column Header */}
              <div
                style={{
                  padding: '0.75rem 1rem',
                  borderBottom: '1px solid var(--border-primary)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  flexShrink: 0,
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <div
                    style={{
                      width: 10,
                      height: 10,
                      borderRadius: '50%',
                      background: col.color,
                    }}
                  />
                  <span style={{ fontWeight: 600, fontSize: '0.875rem', color: 'var(--text-primary)' }}>
                    {col.label}
                  </span>
                </div>
                <span className="badge badge-secondary">{colApps.length}</span>
              </div>

              {/* Column Content */}
              <div
                style={{
                  padding: '0.75rem',
                  display: 'flex',
                  flexDirection: 'column',
                  gap: '0.5rem',
                  flex: 1,
                  minHeight: 0,
                  overflowY: 'auto',
                }}
              >
                {colApps.length === 0 ? (
                  <div style={{ 
                    padding: '2rem 1rem', 
                    textAlign: 'center', 
                    color: 'var(--text-muted)',
                    fontSize: '0.8125rem',
                  }}>
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

      {/* Application Detail Modal */}
      <ApplicationDetailModal
        isOpen={detailModalOpen}
        onClose={() => setDetailModalOpen(false)}
        applicationId={selectedApplicationId}
        onUpdate={loadApplications}
      />
    </div>
  );
};

// Application Card Component
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
      style={{
        background: 'var(--surface-primary)',
        border: '1px solid var(--border-primary)',
        borderRadius: 'var(--radius-md)',
        padding: '0.75rem',
        cursor: 'pointer',
        transition: 'all 0.15s ease',
      }}
      onClick={() => onViewDetail(application.id)}
      onMouseEnter={() => setShowActions(true)}
      onMouseLeave={() => setShowActions(false)}
    >
      {/* Header */}
      <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
        <div>
          <div style={{ fontWeight: 600, fontSize: '0.875rem', color: 'var(--text-primary)' }}>
            {application.full_name}
          </div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
            {application.job_position.title}
          </div>
        </div>
        <div
          style={{
            width: 32,
            height: 32,
            borderRadius: 'var(--radius-md)',
            background: 'var(--gradient-primary)',
            color: 'white',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: '0.75rem',
            fontWeight: 600,
            flexShrink: 0,
          }}
        >
          {application.full_name.charAt(0)}
        </div>
      </div>

      {/* Contact */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem', marginBottom: '0.5rem' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.375rem', fontSize: '0.75rem', color: 'var(--text-secondary)' }}>
          <BsEnvelope size={12} />
          <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{application.email}</span>
        </div>
        {application.phone && (
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.375rem', fontSize: '0.75rem', color: 'var(--text-secondary)' }}>
            <BsTelephone size={12} />
            <span>{application.phone}</span>
          </div>
        )}
      </div>

      {/* Rating */}
      <div style={{ display: 'flex', alignItems: 'center', gap: '0.125rem', marginBottom: '0.5rem' }}>
        {[1, 2, 3, 4, 5].map((star) => (
          <button
            key={star}
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
            {star <= (application.rating || 0) ? <BsStarFill size={14} /> : <BsStar size={14} />}
          </button>
        ))}
      </div>

      {/* Actions */}
      {showActions && !isLoading && (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.25rem', marginTop: '0.5rem', paddingTop: '0.5rem', borderTop: '1px solid var(--border-primary)' }}>
          {statusColumns
            .filter((s) => s.key !== application.status)
            .slice(0, 3)
            .map((s) => (
              <button
                key={s.key}
                className="btn btn-ghost btn-sm"
                onClick={(e) => {
                  e.stopPropagation();
                  onStatusChange(application, s.key);
                }}
                style={{ fontSize: '0.6875rem', padding: '0.25rem 0.5rem' }}
              >
                {s.label}
              </button>
            ))}
        </div>
      )}

      {isLoading && (
        <div style={{ display: 'flex', justifyContent: 'center', marginTop: '0.5rem' }}>
          <div className="loading-spinner" style={{ width: 16, height: 16, borderWidth: 2 }} />
        </div>
      )}

      {/* Date */}
      <div style={{ fontSize: '0.6875rem', color: 'var(--text-muted)', marginTop: '0.5rem' }}>
        {new Date(application.created_at).toLocaleDateString('tr-TR')}
      </div>
    </div>
  );
};

export default ApplicationsPage;
