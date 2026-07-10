import React, { useEffect, useState } from 'react';
import { analyticsApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsBarChartLine, BsPeople, BsArrowUpRight, BsArrowDownRight, BsCalendar, BsMortarboard } from 'react-icons/bs';

interface AnalyticsSummary {
  total_employees: number;
  pending_leaves: number;
  active_onboarding: number;
  open_positions: number;
  new_applications_this_week: number;
  pending_reviews: number;
}

interface WorkforceData {
  total: number;
  by_department: Array<{ department: string; count: number }>;
  by_position: Array<{ position: string; count: number }>;
  by_employment_type: Array<{ type: string; count: number }>;
  gender_distribution: { male: number; female: number; other: number };
  age_distribution: Array<{ range: string; count: number }>;
}

interface TurnoverData {
  new_hires_this_month: number;
  terminations_this_month: number;
  turnover_rate: number;
  avg_tenure_months: number;
  by_month: Array<{ month: string; hires: number; terminations: number }>;
}

interface LeaveData {
  total_requests: number;
  approved: number;
  pending: number;
  rejected: number;
  by_type: Array<{ type: string; count: number; days: number }>;
  avg_days_per_employee: number;
}

interface TrainingData {
  total_trainings: number;
  completed: number;
  in_progress: number;
  total_participants: number;
  avg_score: number;
  by_category: Array<{ category: string; count: number }>;
}

const AnalyticsPage: React.FC = () => {
  const [summary, setSummary] = useState<AnalyticsSummary | null>(null);
  const [workforce, setWorkforce] = useState<WorkforceData | null>(null);
  const [turnover, setTurnover] = useState<TurnoverData | null>(null);
  const [leaves, setLeaves] = useState<LeaveData | null>(null);
  const [training, setTraining] = useState<TrainingData | null>(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'summary' | 'workforce' | 'turnover' | 'leaves' | 'training'>('summary');

  useEffect(() => {
    loadAllData();
  }, []);

  const loadAllData = async () => {
    try {
      setLoading(true);
      const [summaryRes, workforceRes, turnoverRes, leavesRes, trainingRes] = await Promise.all([
        analyticsApi.summary(),
        analyticsApi.workforce(),
        analyticsApi.turnover(),
        analyticsApi.leaves(),
        analyticsApi.training(),
      ]);
      setSummary(summaryRes.data.data);
      setWorkforce(workforceRes.data.data);
      setTurnover(turnoverRes.data.data);
      setLeaves(leavesRes.data.data);
      setTraining(trainingRes.data.data);
    } catch {
      toast.error('Analitik veriler yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const statCards = [
    {
      title: 'Toplam Çalışan',
      value: summary?.total_employees || 0,
      icon: <BsPeople size={28} />,
      color: '#3b82f6',
      bgColor: 'rgba(59, 130, 246, 0.1)',
    },
    {
      title: 'Bekleyen İzin',
      value: summary?.pending_leaves || 0,
      icon: <BsCalendar size={28} />,
      color: '#f59e0b',
      bgColor: 'rgba(245, 158, 11, 0.1)',
    },
    {
      title: 'Aktif Onboarding',
      value: summary?.active_onboarding || 0,
      icon: <BsArrowUpRight size={28} />,
      color: '#10b981',
      bgColor: 'rgba(16, 185, 129, 0.1)',
    },
    {
      title: 'Açık Pozisyonlar',
      value: summary?.open_positions || 0,
      icon: <BsArrowDownRight size={28} />,
      color: '#8b5cf6',
      bgColor: 'rgba(139, 92, 246, 0.1)',
    },
    {
      title: 'Haftalık Başvuru',
      value: summary?.new_applications_this_week || 0,
      icon: <BsPeople size={28} />,
      color: '#06b6d4',
      bgColor: 'rgba(6, 182, 212, 0.1)',
    },
    {
      title: 'Bekleyen Değerlendirme',
      value: summary?.pending_reviews || 0,
      icon: <BsBarChartLine size={28} />,
      color: '#ef4444',
      bgColor: 'rgba(239, 68, 68, 0.1)',
    },
  ];

  // Simple bar chart component
  const SimpleBarChart: React.FC<{ data: Array<{ label: string; value: number }>; color?: string }> = ({ data, color = '#3b82f6' }) => {
    const maxValue = Math.max(...data.map(d => d.value), 1);
    return (
      <div className="simple-bar-chart">
        {data.slice(0, 6).map((item, index) => (
          <div key={index} className="bar-item" style={{ marginBottom: '0.75rem' }}>
            <div className="d-flex justify-content-between align-items-center mb-1">
              <span className="small text-truncate" style={{ maxWidth: '60%' }}>{item.label}</span>
              <span className="small fw-semibold">{item.value}</span>
            </div>
            <div style={{ height: '8px', background: 'var(--border)', borderRadius: '4px', overflow: 'hidden' }}>
              <div
                style={{
                  height: '100%',
                  width: `${(item.value / maxValue) * 100}%`,
                  background: color,
                  borderRadius: '4px',
                  transition: 'width 0.5s ease',
                }}
              />
            </div>
          </div>
        ))}
      </div>
    );
  };

  // Donut chart component
  const DonutChart: React.FC<{ data: Array<{ label: string; value: number; color: string }> }> = ({ data }) => {
    const total = data.reduce((sum, d) => sum + d.value, 0);
    let cumulativePercent = 0;

    const segments = data.map(item => {
      const percent = total > 0 ? (item.value / total) * 100 : 0;
      const startPercent = cumulativePercent;
      cumulativePercent += percent;
      return { ...item, percent, startPercent };
    });

    const createArc = (startPercent: number, percent: number): string => {
      const start = (startPercent / 100) * 360 - 90;
      const end = ((startPercent + percent) / 100) * 360 - 90;
      const startRad = (start * Math.PI) / 180;
      const endRad = (end * Math.PI) / 180;
      const largeArc = percent > 50 ? 1 : 0;
      const r = 40;
      const x1 = 50 + r * Math.cos(startRad);
      const y1 = 50 + r * Math.sin(startRad);
      const x2 = 50 + r * Math.cos(endRad);
      const y2 = 50 + r * Math.sin(endRad);
      return `M 50 50 L ${x1} ${y1} A ${r} ${r} 0 ${largeArc} 1 ${x2} ${y2} Z`;
    };

    return (
      <div className="d-flex align-items-center">
        <svg viewBox="0 0 100 100" style={{ width: '120px', height: '120px' }}>
          {segments.map((seg, i) => (
            <path key={i} d={createArc(seg.startPercent, seg.percent)} fill={seg.color} />
          ))}
          <circle cx="50" cy="50" r="25" fill="var(--surface)" />
          <text x="50" y="54" textAnchor="middle" fill="var(--text)" fontSize="12" fontWeight="bold">
            {total}
          </text>
        </svg>
        <div className="ms-3">
          {data.map((item, i) => (
            <div key={i} className="d-flex align-items-center mb-1">
              <div style={{ width: '12px', height: '12px', borderRadius: '2px', background: item.color, marginRight: '8px' }} />
              <span className="small">{item.label}: {item.value}</span>
            </div>
          ))}
        </div>
      </div>
    );
  };

  // Line chart component for turnover trends
  const LineChart: React.FC<{ data: Array<{ month: string; hires: number; terminations: number }> }> = ({ data }) => {
    const maxValue = Math.max(...data.flatMap(d => [d.hires, d.terminations]), 1);
    const chartHeight = 150;
    const chartWidth = 100;
    const pointWidth = chartWidth / (data.length - 1 || 1);

    const getY = (value: number) => chartHeight - (value / maxValue) * (chartHeight - 20);

    const hiresPath = data.map((d, i) => `${i === 0 ? 'M' : 'L'} ${i * pointWidth} ${getY(d.hires)}`).join(' ');
    const terminationsPath = data.map((d, i) => `${i === 0 ? 'M' : 'L'} ${i * pointWidth} ${getY(d.terminations)}`).join(' ');

    return (
      <div>
        <svg viewBox={`0 0 ${chartWidth} ${chartHeight}`} style={{ width: '100%', height: '150px' }} preserveAspectRatio="none">
          <path d={hiresPath} fill="none" stroke="#10b981" strokeWidth="2" />
          <path d={terminationsPath} fill="none" stroke="#ef4444" strokeWidth="2" />
          {data.map((d, i) => (
            <g key={i}>
              <circle cx={i * pointWidth} cy={getY(d.hires)} r="3" fill="#10b981" />
              <circle cx={i * pointWidth} cy={getY(d.terminations)} r="3" fill="#ef4444" />
            </g>
          ))}
        </svg>
        <div className="d-flex justify-content-between mt-2">
          {data.slice(0, 6).map((d, i) => (
            <span key={i} className="small text-muted">{d.month}</span>
          ))}
        </div>
        <div className="d-flex gap-4 mt-2">
          <span className="small"><span style={{ color: '#10b981' }}>●</span> İşe Alım</span>
          <span className="small"><span style={{ color: '#ef4444' }}>●</span> Ayrılma</span>
        </div>
      </div>
    );
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">İK Analitiği</h1>
          <p className="page-subtitle">İş gücü metrikleri ve analitik raporlar</p>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className="d-flex gap-2 mb-4 flex-wrap">
        {[
          { key: 'summary', label: 'Özet', icon: <BsBarChartLine /> },
          { key: 'workforce', label: 'İş Gücü', icon: <BsPeople /> },
          { key: 'turnover', label: 'Turnover', icon: <BsArrowUpRight /> },
          { key: 'leaves', label: 'İzinler', icon: <BsCalendar /> },
          { key: 'training', label: 'Eğitimler', icon: <BsMortarboard /> },
        ].map(tab => (
          <button
            key={tab.key}
            className={`btn ${activeTab === tab.key ? 'btn-primary' : 'btn-outline-primary'}`}
            onClick={() => setActiveTab(tab.key as typeof activeTab)}
          >
            {tab.icon}
            <span className="ms-2">{tab.label}</span>
          </button>
        ))}
      </div>

      {loading ? (
        <div className="page-loading">
          <div className="loading-spinner"></div>
        </div>
      ) : (
        <>
          {/* Summary Tab */}
          {activeTab === 'summary' && (
            <div className="row g-4">
              {statCards.map((stat, index) => (
                <div key={index} className="col-sm-6 col-lg-4">
                  <div className="card h-100">
                    <div className="card-body">
                      <div className="d-flex justify-content-between align-items-start">
                        <div>
                          <p className="text-muted mb-1">{stat.title}</p>
                          <h2 className="mb-0" style={{ color: stat.color }}>{stat.value}</h2>
                        </div>
                        <div style={{ background: stat.bgColor, padding: '12px', borderRadius: '12px', color: stat.color }}>
                          {stat.icon}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* Workforce Tab */}
          {activeTab === 'workforce' && workforce && (
            <div className="row g-4">
              <div className="col-md-6">
                <div className="card h-100">
                  <div className="card-header">
                    <h5 className="card-title mb-0">Departman Dağılımı</h5>
                  </div>
                  <div className="card-body">
                    {workforce.by_department?.length > 0 ? (
                      <SimpleBarChart
                        data={workforce.by_department.map(d => ({ label: d.department, value: d.count }))}
                        color="#3b82f6"
                      />
                    ) : (
                      <p className="text-muted">Veri bulunamadı</p>
                    )}
                  </div>
                </div>
              </div>
              <div className="col-md-6">
                <div className="card h-100">
                  <div className="card-header">
                    <h5 className="card-title mb-0">Pozisyon Dağılımı</h5>
                  </div>
                  <div className="card-body">
                    {workforce.by_position?.length > 0 ? (
                      <SimpleBarChart
                        data={workforce.by_position.map(d => ({ label: d.position, value: d.count }))}
                        color="#8b5cf6"
                      />
                    ) : (
                      <p className="text-muted">Veri bulunamadı</p>
                    )}
                  </div>
                </div>
              </div>
              <div className="col-md-6">
                <div className="card h-100">
                  <div className="card-header">
                    <h5 className="card-title mb-0">Cinsiyet Dağılımı</h5>
                  </div>
                  <div className="card-body">
                    {workforce.gender_distribution ? (
                      <DonutChart
                        data={[
                          { label: 'Erkek', value: workforce.gender_distribution.male, color: '#3b82f6' },
                          { label: 'Kadın', value: workforce.gender_distribution.female, color: '#ec4899' },
                          { label: 'Diğer', value: workforce.gender_distribution.other || 0, color: '#9ca3af' },
                        ]}
                      />
                    ) : (
                      <p className="text-muted">Veri bulunamadı</p>
                    )}
                  </div>
                </div>
              </div>
              <div className="col-md-6">
                <div className="card h-100">
                  <div className="card-header">
                    <h5 className="card-title mb-0">Çalışma Tipi</h5>
                  </div>
                  <div className="card-body">
                    {workforce.by_employment_type?.length > 0 ? (
                      <DonutChart
                        data={workforce.by_employment_type.map((d, i) => ({
                          label: d.type,
                          value: d.count,
                          color: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'][i % 4],
                        }))}
                      />
                    ) : (
                      <p className="text-muted">Veri bulunamadı</p>
                    )}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Turnover Tab */}
          {activeTab === 'turnover' && turnover && (
            <div className="row g-4">
              <div className="col-sm-6 col-lg-3">
                <div className="card h-100 text-center">
                  <div className="card-body">
                    <h3 style={{ color: '#10b981' }}>{turnover.new_hires_this_month}</h3>
                    <p className="text-muted mb-0">Bu Ay İşe Alınan</p>
                  </div>
                </div>
              </div>
              <div className="col-sm-6 col-lg-3">
                <div className="card h-100 text-center">
                  <div className="card-body">
                    <h3 style={{ color: '#ef4444' }}>{turnover.terminations_this_month}</h3>
                    <p className="text-muted mb-0">Bu Ay Ayrılan</p>
                  </div>
                </div>
              </div>
              <div className="col-sm-6 col-lg-3">
                <div className="card h-100 text-center">
                  <div className="card-body">
                    <h3 style={{ color: '#f59e0b' }}>%{(turnover.turnover_rate * 100).toFixed(1)}</h3>
                    <p className="text-muted mb-0">Turnover Oranı</p>
                  </div>
                </div>
              </div>
              <div className="col-sm-6 col-lg-3">
                <div className="card h-100 text-center">
                  <div className="card-body">
                    <h3 style={{ color: '#3b82f6' }}>{turnover.avg_tenure_months}</h3>
                    <p className="text-muted mb-0">Ort. Kıdem (Ay)</p>
                  </div>
                </div>
              </div>
              <div className="col-12">
                <div className="card">
                  <div className="card-header">
                    <h5 className="card-title mb-0">Aylık İşe Alım / Ayrılma Trendi</h5>
                  </div>
                  <div className="card-body">
                    {turnover.by_month?.length > 0 ? (
                      <LineChart data={turnover.by_month} />
                    ) : (
                      <p className="text-muted">Veri bulunamadı</p>
                    )}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Leaves Tab */}
          {activeTab === 'leaves' && leaves && (
            <div className="row g-4">
              <div className="col-sm-6 col-lg-3">
                <div className="card h-100 text-center">
                  <div className="card-body">
                    <h3 style={{ color: '#3b82f6' }}>{leaves.total_requests}</h3>
                    <p className="text-muted mb-0">Toplam Talep</p>
                  </div>
                </div>
              </div>
              <div className="col-sm-6 col-lg-3">
                <div className="card h-100 text-center">
                  <div className="card-body">
                    <h3 style={{ color: '#10b981' }}>{leaves.approved}</h3>
                    <p className="text-muted mb-0">Onaylanan</p>
                  </div>
                </div>
              </div>
              <div className="col-sm-6 col-lg-3">
                <div className="card h-100 text-center">
                  <div className="card-body">
                    <h3 style={{ color: '#f59e0b' }}>{leaves.pending}</h3>
                    <p className="text-muted mb-0">Bekleyen</p>
                  </div>
                </div>
              </div>
              <div className="col-sm-6 col-lg-3">
                <div className="card h-100 text-center">
                  <div className="card-body">
                    <h3 style={{ color: '#8b5cf6' }}>{leaves.avg_days_per_employee?.toFixed(1)}</h3>
                    <p className="text-muted mb-0">Kişi Başı Ort. Gün</p>
                  </div>
                </div>
              </div>
              <div className="col-md-6">
                <div className="card h-100">
                  <div className="card-header">
                    <h5 className="card-title mb-0">İzin Türü Dağılımı</h5>
                  </div>
                  <div className="card-body">
                    {leaves.by_type?.length > 0 ? (
                      <SimpleBarChart
                        data={leaves.by_type.map(d => ({ label: d.type, value: d.count }))}
                        color="#10b981"
                      />
                    ) : (
                      <p className="text-muted">Veri bulunamadı</p>
                    )}
                  </div>
                </div>
              </div>
              <div className="col-md-6">
                <div className="card h-100">
                  <div className="card-header">
                    <h5 className="card-title mb-0">İzin Günleri (Türe Göre)</h5>
                  </div>
                  <div className="card-body">
                    {leaves.by_type?.length > 0 ? (
                      <SimpleBarChart
                        data={leaves.by_type.map(d => ({ label: d.type, value: d.days }))}
                        color="#f59e0b"
                      />
                    ) : (
                      <p className="text-muted">Veri bulunamadı</p>
                    )}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Training Tab */}
          {activeTab === 'training' && training && (
            <div className="row g-4">
              <div className="col-sm-6 col-lg-3">
                <div className="card h-100 text-center">
                  <div className="card-body">
                    <h3 style={{ color: '#3b82f6' }}>{training.total_trainings}</h3>
                    <p className="text-muted mb-0">Toplam Eğitim</p>
                  </div>
                </div>
              </div>
              <div className="col-sm-6 col-lg-3">
                <div className="card h-100 text-center">
                  <div className="card-body">
                    <h3 style={{ color: '#10b981' }}>{training.completed}</h3>
                    <p className="text-muted mb-0">Tamamlanan</p>
                  </div>
                </div>
              </div>
              <div className="col-sm-6 col-lg-3">
                <div className="card h-100 text-center">
                  <div className="card-body">
                    <h3 style={{ color: '#8b5cf6' }}>{training.total_participants}</h3>
                    <p className="text-muted mb-0">Toplam Katılımcı</p>
                  </div>
                </div>
              </div>
              <div className="col-sm-6 col-lg-3">
                <div className="card h-100 text-center">
                  <div className="card-body">
                    <h3 style={{ color: '#f59e0b' }}>{training.avg_score?.toFixed(1) || '-'}</h3>
                    <p className="text-muted mb-0">Ortalama Puan</p>
                  </div>
                </div>
              </div>
              <div className="col-12">
                <div className="card">
                  <div className="card-header">
                    <h5 className="card-title mb-0">Kategori Bazında Eğitimler</h5>
                  </div>
                  <div className="card-body">
                    {training.by_category?.length > 0 ? (
                      <SimpleBarChart
                        data={training.by_category.map(d => ({ label: d.category, value: d.count }))}
                        color="#8b5cf6"
                      />
                    ) : (
                      <p className="text-muted">Veri bulunamadı</p>
                    )}
                  </div>
                </div>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
};

export default AnalyticsPage;
