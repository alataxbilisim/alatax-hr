import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { recruitmentApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import {
  BsArrowLeft,
  BsGraphUp,
  BsPeople,
  BsBriefcase,
  BsClockHistory,
  BsCheckCircle,
  BsFunnel,
  BsCalendar,
} from 'react-icons/bs';

interface SummaryData {
  total_positions: number;
  active_positions: number;
  total_applications: number;
  total_interviews: number;
  hired: number;
  rejected: number;
  conversion_rate: number;
  interview_rate: number;
  applications_by_status: Record<string, number>;
}

interface PositionReport {
  id: number;
  title: string;
  department: string;
  status: string;
  total_applications: number;
  new_count: number;
  reviewing_count: number;
  interview_count: number;
  hired_count: number;
  rejected_count: number;
  conversion_rate: number;
}

interface SourceReport {
  source: string;
  total: number;
  hired: number;
  rejected: number;
  avg_rating: number | null;
  conversion_rate: number;
}

interface TrendData {
  period: string;
  total: number;
  hired: number;
  rejected: number;
}

interface TimeToHireData {
  overall_avg_days: number;
  total_hired: number;
  by_position: Array<{
    position: string;
    avg_days: number;
    hired_count: number;
  }>;
}

const statusLabels: Record<string, string> = {
  new: 'Yeni',
  reviewing: 'İnceleniyor',
  shortlisted: 'Ön Seçim',
  interview_scheduled: 'Mülakat Planlandı',
  interviewed: 'Mülakat Yapıldı',
  offer_sent: 'Teklif Gönderildi',
  hired: 'İşe Alındı',
  rejected: 'Reddedildi',
  withdrawn: 'Çekildi',
};

const sourceLabels: Record<string, string> = {
  website: 'Web Sitesi',
  linkedin: 'LinkedIn',
  kariyer_net: 'Kariyer.net',
  indeed: 'Indeed',
  referral: 'Referans',
  other: 'Diğer',
};

const RecruitmentReportsPage: React.FC = () => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'summary' | 'positions' | 'sources' | 'trends' | 'time'>('summary');
  
  // Date filters
  const [startDate, setStartDate] = useState(() => {
    const date = new Date();
    date.setMonth(date.getMonth() - 3);
    return date.toISOString().split('T')[0];
  });
  const [endDate, setEndDate] = useState(() => new Date().toISOString().split('T')[0]);

  // Data states
  const [summary, setSummary] = useState<SummaryData | null>(null);
  const [positions, setPositions] = useState<PositionReport[]>([]);
  const [sources, setSources] = useState<SourceReport[]>([]);
  const [trends, setTrends] = useState<TrendData[]>([]);
  const [timeToHire, setTimeToHire] = useState<TimeToHireData | null>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const params = { start_date: startDate, end_date: endDate };

      switch (activeTab) {
        case 'summary': {
          const summaryRes = await recruitmentApi.reports.summary(params);
          setSummary(summaryRes.data.data);
          break;
        }
        case 'positions': {
          const posRes = await recruitmentApi.reports.byPosition(params);
          setPositions(posRes.data.data || []);
          break;
        }
        case 'sources': {
          const srcRes = await recruitmentApi.reports.bySource(params);
          setSources(srcRes.data.data || []);
          break;
        }
        case 'trends': {
          const trendRes = await recruitmentApi.reports.trends(params);
          setTrends(trendRes.data.data || []);
          break;
        }
        case 'time': {
          const timeRes = await recruitmentApi.reports.timeToHire(params);
          setTimeToHire(timeRes.data.data);
          break;
        }
      }
    } catch {
      toast.error('Rapor verileri yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [startDate, endDate, activeTab]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  

  const tabs = [
    { key: 'summary', label: 'Özet', icon: <BsGraphUp /> },
    { key: 'positions', label: 'Pozisyonlar', icon: <BsBriefcase /> },
    { key: 'sources', label: 'Kaynaklar', icon: <BsFunnel /> },
    { key: 'trends', label: 'Trendler', icon: <BsCalendar /> },
    { key: 'time', label: 'İşe Alım Süresi', icon: <BsClockHistory /> },
  ];

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div className="page-header-content">
          <button
            className="btn btn-ghost btn-sm"
            onClick={() => navigate('/recruitment/positions')}
            style={{ marginBottom: '0.5rem' }}
          >
            <BsArrowLeft /> Geri
          </button>
          <h1>İşe Alım Raporları</h1>
          <p>Başvuru ve işe alım süreçlerinin detaylı analizi</p>
        </div>
      </div>

      {/* Date Filters */}
      <div className="card" style={{ marginBottom: '1.5rem' }}>
        <div className="card-body filter-bar">
          <div className="form-group" style={{ marginBottom: 0, minWidth: '140px' }}>
            <label className="form-label" style={{ marginBottom: '0.25rem' }}>Başlangıç</label>
            <input
              type="date"
              className="form-control"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
            />
          </div>
          <div className="form-group" style={{ marginBottom: 0, minWidth: '140px' }}>
            <label className="form-label" style={{ marginBottom: '0.25rem' }}>Bitiş</label>
            <input
              type="date"
              className="form-control"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
            />
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="tabs" style={{ marginBottom: '1.5rem' }}>
        {tabs.map((tab) => (
          <button
            key={tab.key}
            className={`tab ${activeTab === tab.key ? 'active' : ''}`}
            onClick={() => setActiveTab(tab.key as typeof activeTab)}
          >
            {tab.icon}
            <span>{tab.label}</span>
          </button>
        ))}
      </div>

      {/* Content */}
      {loading ? (
        <div className="page-loading">
          <div className="loading-spinner" />
        </div>
      ) : (
        <>
          {/* Summary Tab */}
          {activeTab === 'summary' && summary && (
            <div>
              {/* KPI Cards */}
              <div className="stats-grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', marginBottom: '1.5rem' }}>
                <div className="card stat-card">
                  <div className="card-body">
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                      <div style={{ width: 48, height: 48, borderRadius: 'var(--radius-lg)', background: 'var(--primary-soft)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <BsBriefcase size={24} style={{ color: 'var(--primary)' }} />
                      </div>
                      <div>
                        <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-primary)' }}>{summary.active_positions}</div>
                        <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>Aktif Pozisyon</div>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="card stat-card">
                  <div className="card-body">
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                      <div style={{ width: 48, height: 48, borderRadius: 'var(--radius-lg)', background: 'rgba(59, 130, 246, 0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <BsPeople size={24} style={{ color: '#3b82f6' }} />
                      </div>
                      <div>
                        <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-primary)' }}>{summary.total_applications}</div>
                        <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>Toplam Başvuru</div>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="card stat-card">
                  <div className="card-body">
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                      <div style={{ width: 48, height: 48, borderRadius: 'var(--radius-lg)', background: 'rgba(16, 185, 129, 0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <BsCheckCircle size={24} style={{ color: '#10b981' }} />
                      </div>
                      <div>
                        <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-primary)' }}>{summary.hired}</div>
                        <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>İşe Alınan</div>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="card stat-card">
                  <div className="card-body">
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                      <div style={{ width: 48, height: 48, borderRadius: 'var(--radius-lg)', background: 'rgba(245, 158, 11, 0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <BsGraphUp size={24} style={{ color: '#f59e0b' }} />
                      </div>
                      <div>
                        <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-primary)' }}>%{summary.conversion_rate}</div>
                        <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>Dönüşüm Oranı</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Status Distribution */}
              <div className="card">
                <div className="card-header">
                  <h3 className="card-title">Durum Dağılımı</h3>
                </div>
                <div className="card-body">
                  <div className="stats-grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))' }}>
                    {Object.entries(summary.applications_by_status).map(([status, count]) => (
                      <div key={status} style={{ padding: '0.75rem', background: 'var(--surface-secondary)', borderRadius: 'var(--radius-md)', textAlign: 'center' }}>
                        <div style={{ fontSize: '1.125rem', fontWeight: 600, color: 'var(--text-primary)' }}>{count}</div>
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{statusLabels[status] || status}</div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Positions Tab */}
          {activeTab === 'positions' && (
            <div className="card">
              <div className="table-responsive">
                <table className="table">
                  <thead>
                    <tr>
                      <th>Pozisyon</th>
                      <th>Departman</th>
                      <th style={{ textAlign: 'center' }}>Toplam</th>
                      <th style={{ textAlign: 'center' }}>Yeni</th>
                      <th style={{ textAlign: 'center' }}>Mülakat</th>
                      <th style={{ textAlign: 'center' }}>İşe Alındı</th>
                      <th style={{ textAlign: 'center' }}>Reddedildi</th>
                      <th style={{ textAlign: 'center' }}>Dönüşüm</th>
                    </tr>
                  </thead>
                  <tbody>
                    {positions.length === 0 ? (
                      <tr>
                        <td colSpan={8} style={{ textAlign: 'center', padding: '2rem' }}>
                          Veri bulunamadı
                        </td>
                      </tr>
                    ) : (
                      positions.map((pos) => (
                        <tr key={pos.id}>
                          <td><strong>{pos.title}</strong></td>
                          <td>{pos.department}</td>
                          <td style={{ textAlign: 'center' }}>{pos.total_applications}</td>
                          <td style={{ textAlign: 'center' }}><span className="badge badge-secondary">{pos.new_count}</span></td>
                          <td style={{ textAlign: 'center' }}><span className="badge badge-info">{pos.interview_count}</span></td>
                          <td style={{ textAlign: 'center' }}><span className="badge badge-success">{pos.hired_count}</span></td>
                          <td style={{ textAlign: 'center' }}><span className="badge badge-danger">{pos.rejected_count}</span></td>
                          <td style={{ textAlign: 'center' }}>%{pos.conversion_rate}</td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* Sources Tab */}
          {activeTab === 'sources' && (
            <div className="card">
              <div className="table-responsive">
                <table className="table">
                  <thead>
                    <tr>
                      <th>Kaynak</th>
                      <th style={{ textAlign: 'center' }}>Toplam Başvuru</th>
                      <th style={{ textAlign: 'center' }}>İşe Alındı</th>
                      <th style={{ textAlign: 'center' }}>Reddedildi</th>
                      <th style={{ textAlign: 'center' }}>Ort. Puan</th>
                      <th style={{ textAlign: 'center' }}>Dönüşüm</th>
                    </tr>
                  </thead>
                  <tbody>
                    {sources.length === 0 ? (
                      <tr>
                        <td colSpan={6} style={{ textAlign: 'center', padding: '2rem' }}>
                          Veri bulunamadı
                        </td>
                      </tr>
                    ) : (
                      sources.map((src, idx) => (
                        <tr key={idx}>
                          <td><strong>{sourceLabels[src.source] || src.source}</strong></td>
                          <td style={{ textAlign: 'center' }}>{src.total}</td>
                          <td style={{ textAlign: 'center' }}><span className="badge badge-success">{src.hired}</span></td>
                          <td style={{ textAlign: 'center' }}><span className="badge badge-danger">{src.rejected}</span></td>
                          <td style={{ textAlign: 'center' }}>{src.avg_rating ? `⭐ ${src.avg_rating}` : '-'}</td>
                          <td style={{ textAlign: 'center' }}>%{src.conversion_rate}</td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* Trends Tab */}
          {activeTab === 'trends' && (
            <div className="card">
              <div className="card-header">
                <h3 className="card-title">Aylık Başvuru Trendi</h3>
              </div>
              <div className="card-body">
                {trends.length === 0 ? (
                  <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
                    Veri bulunamadı
                  </div>
                ) : (
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                    {trends.map((t, idx) => {
                      const maxTotal = Math.max(...trends.map(x => x.total), 1);
                      const width = (t.total / maxTotal) * 100;
                      return (
                        <div key={idx} style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', flexWrap: 'wrap' }}>
                          <div style={{ width: '70px', flexShrink: 0, fontSize: '0.8125rem', color: 'var(--text-secondary)' }}>{t.period}</div>
                          <div style={{ flex: '1 1 150px', minWidth: 0, height: '28px', background: 'var(--surface-secondary)', borderRadius: 'var(--radius-sm)', overflow: 'hidden', position: 'relative' }}>
                            <div style={{ 
                              width: `${width}%`, 
                              height: '100%', 
                              background: 'var(--gradient-primary)', 
                              transition: 'width 0.3s ease',
                              display: 'flex',
                              alignItems: 'center',
                              paddingLeft: '0.5rem',
                              minWidth: '24px',
                            }}>
                              <span style={{ color: 'white', fontSize: '0.6875rem', fontWeight: 600 }}>{t.total}</span>
                            </div>
                          </div>
                          <div style={{ display: 'flex', gap: '0.5rem', fontSize: '0.6875rem', flexShrink: 0 }}>
                            <span style={{ color: '#10b981' }}>✓{t.hired}</span>
                            <span style={{ color: '#ef4444' }}>✗{t.rejected}</span>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            </div>
          )}

          {/* Time to Hire Tab */}
          {activeTab === 'time' && timeToHire && (
            <div>
              {/* Overall Metric */}
              <div className="card" style={{ marginBottom: '1.5rem' }}>
                <div className="card-body" style={{ textAlign: 'center', padding: '2rem' }}>
                  <div style={{ fontSize: '3rem', fontWeight: 700, color: 'var(--primary)' }}>{timeToHire.overall_avg_days}</div>
                  <div style={{ fontSize: '1rem', color: 'var(--text-secondary)' }}>Ortalama İşe Alım Süresi (Gün)</div>
                  <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)', marginTop: '0.5rem' }}>
                    Toplam {timeToHire.total_hired} kişi işe alındı
                  </div>
                </div>
              </div>

              {/* By Position */}
              <div className="card">
                <div className="card-header">
                  <h3 className="card-title">Pozisyon Bazlı İşe Alım Süresi</h3>
                </div>
                <div className="table-responsive">
                  <table className="table">
                    <thead>
                      <tr>
                        <th>Pozisyon</th>
                        <th style={{ textAlign: 'center' }}>İşe Alınan</th>
                        <th style={{ textAlign: 'center' }}>Ort. Süre (Gün)</th>
                      </tr>
                    </thead>
                    <tbody>
                      {timeToHire.by_position.length === 0 ? (
                        <tr>
                          <td colSpan={3} style={{ textAlign: 'center', padding: '2rem' }}>
                            Veri bulunamadı
                          </td>
                        </tr>
                      ) : (
                        timeToHire.by_position.map((pos, idx) => (
                          <tr key={idx}>
                            <td><strong>{pos.position}</strong></td>
                            <td style={{ textAlign: 'center' }}>{pos.hired_count}</td>
                            <td style={{ textAlign: 'center' }}>
                              <span className={`badge ${pos.avg_days <= 30 ? 'badge-success' : pos.avg_days <= 60 ? 'badge-warning' : 'badge-danger'}`}>
                                {pos.avg_days} gün
                              </span>
                            </td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
};

export default RecruitmentReportsPage;

