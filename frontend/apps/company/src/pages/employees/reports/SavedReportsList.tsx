import React, { useState } from 'react';
import {
  BsStar,
  BsStarFill,
  BsTrash,
  BsShare,
  BsFolder,
  BsClock,
  BsPerson,
} from 'react-icons/bs';
import Modal from '../../../components/ui/Modal';
import type { ReportConfig } from './ReportBuilder';

export interface SavedReport {
  id: number;
  name: string;
  description?: string;
  config: ReportConfig;
  is_favorite: boolean;
  is_shared: boolean;
  is_owner: boolean;
  created_at: string;
  updated_at: string;
  user: {
    id: number;
    name: string;
  };
}

interface SavedReportsListProps {
  reports: SavedReport[];
  loading: boolean;
  onSelect: (report: SavedReport) => void;
  onToggleFavorite: (id: number) => void;
  onDelete: (id: number) => void;
  onClose: () => void;
  isOpen: boolean;
}

const SavedReportsList: React.FC<SavedReportsListProps> = ({
  reports,
  loading,
  onSelect,
  onToggleFavorite,
  onDelete,
  onClose,
  isOpen,
}) => {
  const [filter, setFilter] = useState<'all' | 'favorites' | 'shared'>('all');
  const [deleteConfirmId, setDeleteConfirmId] = useState<number | null>(null);

  const filteredReports = reports.filter((report) => {
    if (filter === 'favorites') return report.is_favorite;
    if (filter === 'shared') return report.is_shared && !report.is_owner;
    return true;
  });

  const favoriteReports = filteredReports.filter((r) => r.is_favorite);
  const otherReports = filteredReports.filter((r) => !r.is_favorite);

  const handleDelete = (id: number) => {
    onDelete(id);
    setDeleteConfirmId(null);
  };

  const getChartTypeLabel = (chartType: string) => {
    const labels: Record<string, string> = {
      bar: 'Çubuk Grafik',
      horizontal_bar: 'Yatay Çubuk',
      pie: 'Pasta Grafik',
      donut: 'Halka Grafik',
      line: 'Çizgi Grafik',
      area: 'Alan Grafik',
      table: 'Tablo',
    };
    return labels[chartType] || chartType;
  };

  const ReportItem: React.FC<{ report: SavedReport }> = ({ report }) => (
    <div className="saved-report-item">
      <div className="saved-report-content" onClick={() => onSelect(report)}>
        <div className="saved-report-header">
          <h4 className="saved-report-name">{report.name}</h4>
          <div className="saved-report-badges">
            {report.is_shared && (
              <span className="badge badge-info" title="Paylaşımlı">
                <BsShare />
              </span>
            )}
            {!report.is_owner && (
              <span className="badge badge-secondary" title={`${report.user.name} tarafından`}>
                <BsPerson />
              </span>
            )}
          </div>
        </div>
        {report.description && (
          <p className="saved-report-description">{report.description}</p>
        )}
        <div className="saved-report-meta">
          <span className="saved-report-type">
            {getChartTypeLabel(report.config.chartType)}
          </span>
          <span className="saved-report-date">
            <BsClock /> {report.updated_at}
          </span>
        </div>
      </div>
      <div className="saved-report-actions">
        <button
          className={`btn-icon ${report.is_favorite ? 'active' : ''}`}
          onClick={(e) => {
            e.stopPropagation();
            onToggleFavorite(report.id);
          }}
          title={report.is_favorite ? 'Favorilerden Çıkar' : 'Favorilere Ekle'}
        >
          {report.is_favorite ? <BsStarFill /> : <BsStar />}
        </button>
        {report.is_owner && (
          <button
            className="btn-icon text-danger"
            onClick={(e) => {
              e.stopPropagation();
              setDeleteConfirmId(report.id);
            }}
            title="Sil"
          >
            <BsTrash />
          </button>
        )}
      </div>
    </div>
  );

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Kayıtlı Raporlar" size="lg">
      <div className="saved-reports-modal">
        {/* Filtre Sekmeleri */}
        <div className="saved-reports-tabs">
          <button
            className={`saved-reports-tab ${filter === 'all' ? 'active' : ''}`}
            onClick={() => setFilter('all')}
          >
            <BsFolder /> Tümü ({reports.length})
          </button>
          <button
            className={`saved-reports-tab ${filter === 'favorites' ? 'active' : ''}`}
            onClick={() => setFilter('favorites')}
          >
            <BsStarFill /> Favoriler ({reports.filter((r) => r.is_favorite).length})
          </button>
          <button
            className={`saved-reports-tab ${filter === 'shared' ? 'active' : ''}`}
            onClick={() => setFilter('shared')}
          >
            <BsShare /> Paylaşılanlar ({reports.filter((r) => r.is_shared && !r.is_owner).length})
          </button>
        </div>

        {/* Rapor Listesi */}
        <div className="saved-reports-list">
          {loading ? (
            <div className="saved-reports-loading">
              <div className="spinner-border spinner-border-sm" role="status" />
              <span>Raporlar yükleniyor...</span>
            </div>
          ) : filteredReports.length === 0 ? (
            <div className="saved-reports-empty">
              <div className="saved-reports-empty-icon">📋</div>
              <h4>Rapor Bulunamadı</h4>
              <p>
                {filter === 'all'
                  ? 'Henüz kayıtlı raporunuz yok. Yeni bir rapor oluşturup kaydedebilirsiniz.'
                  : filter === 'favorites'
                  ? 'Favori raporunuz yok. Bir raporu favorilere eklemek için yıldız ikonuna tıklayın.'
                  : 'Sizinle paylaşılan rapor bulunmuyor.'}
              </p>
            </div>
          ) : (
            <>
              {/* Favoriler */}
              {favoriteReports.length > 0 && filter !== 'favorites' && (
                <div className="saved-reports-section">
                  <h5 className="saved-reports-section-title">
                    <BsStarFill /> Favoriler
                  </h5>
                  {favoriteReports.map((report) => (
                    <ReportItem key={report.id} report={report} />
                  ))}
                </div>
              )}

              {/* Diğerleri veya Tümü */}
              {otherReports.length > 0 && (
                <div className="saved-reports-section">
                  {favoriteReports.length > 0 && filter !== 'favorites' && (
                    <h5 className="saved-reports-section-title">Diğer Raporlar</h5>
                  )}
                  {otherReports.map((report) => (
                    <ReportItem key={report.id} report={report} />
                  ))}
                </div>
              )}

              {/* Sadece favoriler görünüyorsa */}
              {filter === 'favorites' &&
                favoriteReports.map((report) => (
                  <ReportItem key={report.id} report={report} />
                ))}
            </>
          )}
        </div>
      </div>

      {/* Silme Onay Modal */}
      {deleteConfirmId && (
        <div className="delete-confirm-overlay" onClick={() => setDeleteConfirmId(null)}>
          <div className="delete-confirm-dialog" onClick={(e) => e.stopPropagation()}>
            <h4>Raporu Sil</h4>
            <p>Bu raporu silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.</p>
            <div className="delete-confirm-actions">
              <button className="btn btn-secondary" onClick={() => setDeleteConfirmId(null)}>
                İptal
              </button>
              <button className="btn btn-danger" onClick={() => handleDelete(deleteConfirmId)}>
                Sil
              </button>
            </div>
          </div>
        </div>
      )}
    </Modal>
  );
};

export default SavedReportsList;

