import React, { useState } from 'react';
import { BsGraphUp, BsDownload, BsFileEarmarkPdf, BsFileEarmarkExcel, BsPeople, BsPersonBadge, BsCalendarCheck, BsFileEarmarkText } from 'react-icons/bs';
import toast from 'react-hot-toast';

interface ReportCard {
  id: string;
  title: string;
  description: string;
  icon: React.ReactNode;
  category: 'recruitment' | 'hr' | 'documents';
}

const ReportsPage: React.FC = () => {
  const [selectedCategory, setSelectedCategory] = useState<string>('all');

  const reports: ReportCard[] = [
    {
      id: 'applications-summary',
      title: 'Başvuru Özeti',
      description: 'Tüm başvuruların durumlarına göre özet raporu',
      icon: <BsPersonBadge />,
      category: 'recruitment',
    },
    {
      id: 'applications-by-position',
      title: 'Pozisyon Bazlı Başvurular',
      description: 'Her pozisyon için alınan başvuru sayıları',
      icon: <BsPersonBadge />,
      category: 'recruitment',
    },
    {
      id: 'cv-pool-stats',
      title: 'CV Havuzu İstatistikleri',
      description: 'CV havuzundaki adayların demografik analizi',
      icon: <BsPeople />,
      category: 'recruitment',
    },
    {
      id: 'leave-summary',
      title: 'İzin Özeti',
      description: 'Çalışanların izin kullanım durumları',
      icon: <BsCalendarCheck />,
      category: 'hr',
    },
    {
      id: 'leave-by-type',
      title: 'İzin Türü Analizi',
      description: 'İzin türlerine göre kullanım istatistikleri',
      icon: <BsCalendarCheck />,
      category: 'hr',
    },
    {
      id: 'onboarding-progress',
      title: 'Onboarding İlerleme',
      description: 'Aktif onboarding süreçlerinin durumu',
      icon: <BsPeople />,
      category: 'hr',
    },
    {
      id: 'documents-by-category',
      title: 'Kategori Bazlı Evraklar',
      description: 'Evrakların kategorilere göre dağılımı',
      icon: <BsFileEarmarkText />,
      category: 'documents',
    },
    {
      id: 'documents-expiring',
      title: 'Süresi Dolacak Evraklar',
      description: 'Yaklaşan son kullanma tarihli evraklar',
      icon: <BsFileEarmarkText />,
      category: 'documents',
    },
  ];

  const filteredReports = selectedCategory === 'all' 
    ? reports 
    : reports.filter(r => r.category === selectedCategory);

  const handleDownload = (reportId: string, format: 'pdf' | 'excel') => {
    toast.success(`${format.toUpperCase()} raporu hazırlanıyor...`);
    // Gerçek implementasyonda API çağrısı yapılacak
  };

  const getCategoryLabel = (category: string) => {
    const labels: Record<string, string> = {
      recruitment: 'İşe Alım',
      hr: 'İK',
      documents: 'Evrak',
    };
    return labels[category] || category;
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Raporlar</h1>
          <p className="page-subtitle">Detaylı raporlar ve analizler</p>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--primary-soft)', color: 'var(--primary)' }}>
            <BsGraphUp />
          </div>
          <div className="stat-card-value">{reports.length}</div>
          <div className="stat-card-label">Toplam Rapor</div>
        </div>
        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--success-soft)', color: 'var(--success)' }}>
            <BsPersonBadge />
          </div>
          <div className="stat-card-value">{reports.filter(r => r.category === 'recruitment').length}</div>
          <div className="stat-card-label">İşe Alım</div>
        </div>
        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--info-soft)', color: 'var(--info)' }}>
            <BsPeople />
          </div>
          <div className="stat-card-value">{reports.filter(r => r.category === 'hr').length}</div>
          <div className="stat-card-label">İK</div>
        </div>
        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--warning-soft)', color: 'var(--warning)' }}>
            <BsFileEarmarkText />
          </div>
          <div className="stat-card-value">{reports.filter(r => r.category === 'documents').length}</div>
          <div className="stat-card-label">Evrak</div>
        </div>
      </div>

      {/* Filter Tabs */}
      <div className="tabs mb-4">
        <button 
          className={`tab ${selectedCategory === 'all' ? 'active' : ''}`}
          onClick={() => setSelectedCategory('all')}
        >
          Tümü
        </button>
        <button 
          className={`tab ${selectedCategory === 'recruitment' ? 'active' : ''}`}
          onClick={() => setSelectedCategory('recruitment')}
        >
          <BsPersonBadge /> İşe Alım
        </button>
        <button 
          className={`tab ${selectedCategory === 'hr' ? 'active' : ''}`}
          onClick={() => setSelectedCategory('hr')}
        >
          <BsPeople /> İK
        </button>
        <button 
          className={`tab ${selectedCategory === 'documents' ? 'active' : ''}`}
          onClick={() => setSelectedCategory('documents')}
        >
          <BsFileEarmarkText /> Evrak
        </button>
      </div>

      {/* Reports Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {filteredReports.map((report) => (
          <div key={report.id} className="card">
            <div className="card-body">
              <div className="d-flex align-items-start gap-3">
                <div 
                  className="report-icon" 
                  style={{ 
                    width: '48px', 
                    height: '48px', 
                    borderRadius: '12px', 
                    backgroundColor: 'var(--primary-soft)', 
                    color: 'var(--primary)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: '1.5rem',
                    flexShrink: 0,
                  }}
                >
                  {report.icon}
                </div>
                <div>
                  <h4 className="card-title mb-1">{report.title}</h4>
                  <p className="text-secondary mb-2" style={{ fontSize: '0.875rem' }}>{report.description}</p>
                  <span className="badge badge-secondary">{getCategoryLabel(report.category)}</span>
                </div>
              </div>
              <div className="d-flex gap-2 mt-4 pt-3 border-top">
                <button 
                  className="btn btn-sm btn-outline-primary flex-grow-1"
                  onClick={() => handleDownload(report.id, 'excel')}
                >
                  <BsFileEarmarkExcel /> Excel
                </button>
                <button 
                  className="btn btn-sm btn-outline-danger flex-grow-1"
                  onClick={() => handleDownload(report.id, 'pdf')}
                >
                  <BsFileEarmarkPdf /> PDF
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>

      {filteredReports.length === 0 && (
        <div className="card">
          <div className="card-body">
            <div className="empty-state">
              <div className="empty-state-icon"><BsGraphUp /></div>
              <h3 className="empty-state-title">Bu kategoride rapor bulunamadı</h3>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ReportsPage;

