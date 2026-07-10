import React from 'react';
import { FiStar, FiTrash2, FiEdit2, FiShare2, FiUser, FiClock } from 'react-icons/fi';
import type { Dashboard } from './types';

interface DashboardListProps {
  isOpen: boolean;
  onClose: () => void;
  dashboards: Dashboard[];
  onSelect: (dashboard: Dashboard) => void;
  onDelete: (id: number) => void;
  onToggleFavorite: (id: number) => void;
  currentDashboardId?: number | null;
}

const DashboardList: React.FC<DashboardListProps> = ({
  isOpen,
  onClose,
  dashboards,
  onSelect,
  onDelete,
  onToggleFavorite,
  currentDashboardId,
}) => {
  const [activeTab, setActiveTab] = React.useState<'all' | 'favorites' | 'shared'>('all');

  if (!isOpen) return null;

  const filteredDashboards = dashboards.filter((d) => {
    if (activeTab === 'favorites') return d.is_favorite;
    if (activeTab === 'shared') return d.is_shared;
    return true;
  });

  const formatDate = (dateString?: string) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('tr-TR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content dashboard-list-modal" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h3>Kayıtlı Raporlar</h3>
          <button className="modal-close" onClick={onClose}>×</button>
        </div>

        <div className="dashboard-tabs">
          <button
            className={`tab ${activeTab === 'all' ? 'active' : ''}`}
            onClick={() => setActiveTab('all')}
          >
            Tümü ({dashboards.length})
          </button>
          <button
            className={`tab ${activeTab === 'favorites' ? 'active' : ''}`}
            onClick={() => setActiveTab('favorites')}
          >
            Favoriler ({dashboards.filter((d) => d.is_favorite).length})
          </button>
          <button
            className={`tab ${activeTab === 'shared' ? 'active' : ''}`}
            onClick={() => setActiveTab('shared')}
          >
            Paylaşılanlar ({dashboards.filter((d) => d.is_shared).length})
          </button>
        </div>

        <div className="modal-body">
          {filteredDashboards.length === 0 ? (
            <div className="empty-state">
              <p>Rapor bulunamadı</p>
            </div>
          ) : (
            <div className="dashboard-grid">
              {filteredDashboards.map((dashboard) => (
                <div
                  key={dashboard.id}
                  className={`dashboard-card ${currentDashboardId === dashboard.id ? 'active' : ''}`}
                  onClick={() => {
                    onSelect(dashboard);
                    onClose();
                  }}
                >
                  <div className="dashboard-card-header">
                    <h4>{dashboard.name}</h4>
                    <div className="dashboard-card-actions">
                      <button
                        className={`action-btn favorite ${dashboard.is_favorite ? 'active' : ''}`}
                        onClick={(e) => {
                          e.stopPropagation();
                          if (dashboard.id) onToggleFavorite(dashboard.id);
                        }}
                        title={dashboard.is_favorite ? 'Favorilerden çıkar' : 'Favorilere ekle'}
                      >
                        <FiStar />
                      </button>
                      <button
                        className="action-btn delete"
                        onClick={(e) => {
                          e.stopPropagation();
                          if (dashboard.id) onDelete(dashboard.id);
                        }}
                        title="Sil"
                      >
                        <FiTrash2 />
                      </button>
                    </div>
                  </div>
                  {dashboard.description && (
                    <p className="dashboard-card-desc">{dashboard.description}</p>
                  )}
                  <div className="dashboard-card-meta">
                    <span className="meta-item">
                      <FiEdit2 size={12} />
                      {dashboard.widgets?.length || 0} widget
                    </span>
                    {dashboard.is_shared && (
                      <span className="meta-item shared">
                        <FiShare2 size={12} />
                        Paylaşılmış
                      </span>
                    )}
                    {dashboard.user && (
                      <span className="meta-item">
                        <FiUser size={12} />
                        {dashboard.user.name}
                      </span>
                    )}
                    {dashboard.updated_at && (
                      <span className="meta-item">
                        <FiClock size={12} />
                        {formatDate(dashboard.updated_at)}
                      </span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default DashboardList;

