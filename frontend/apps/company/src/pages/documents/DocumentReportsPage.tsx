import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  FiArrowLeft,
  FiRefreshCw,
  FiFileText,
  FiPieChart,
  FiTrendingUp,
  FiFolder,
  FiDownload,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import { documentsApi } from '@shared/services/api';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
  LineChart,
  Line,
} from 'recharts';

interface SummaryData {
  total_documents: number;
  total_size: number;
  total_size_formatted: string;
  categories_count: number;
  this_month: number;
  last_month: number;
  month_change: number;
  trend: { name: string; value: number }[];
  by_category: { name: string; value: number }[];
  by_file_type: { name: string; value: number }[];
}

const COLORS = ['#8b5cf6', '#22c55e', '#f59e0b', '#3b82f6', '#ef4444', '#06b6d4', '#ec4899', '#64748b'];

const DocumentReportsPage: React.FC = () => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [summary, setSummary] = useState<SummaryData | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const loadData = useCallback(async () => {
    try {
      const response = await documentsApi.reports.getSummary();
      setSummary(response.data.data);
    } catch (error) {
      console.error('Error loading summary:', error);
      toast.error('Rapor verileri yüklenemedi');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleRefresh = () => {
    setRefreshing(true);
    loadData();
  };

  if (loading) {
    return (
      <div className="page-loading">
        <div className="loading-spinner"></div>
        <span style={{ marginTop: '1rem', color: 'var(--text-secondary)' }}>Yükleniyor...</span>
      </div>
    );
  }

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <div className="page-header-content">
          <button
            className="btn btn-ghost btn-sm"
            onClick={() => navigate('/documents')}
            style={{ marginBottom: '0.5rem' }}
          >
            <FiArrowLeft /> Evraklara Dön
          </button>
          <h1 className="page-title">Evrak Raporları</h1>
          <p className="page-subtitle">Belge istatistikleri ve analizler</p>
        </div>
        <div className="page-header-actions">
          <button
            className="btn btn-secondary"
            onClick={handleRefresh}
            disabled={refreshing}
          >
            <FiRefreshCw className={refreshing ? 'spin' : ''} />
            Yenile
          </button>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="row mb-4" style={{ gap: '1rem' }}>
        <div className="col-md-3">
          <div className="card">
            <div className="card-body">
              <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                <div
                  style={{
                    width: 48,
                    height: 48,
                    borderRadius: 'var(--radius-lg)',
                    background: 'var(--primary-soft)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                  }}
                >
                  <FiFileText size={24} style={{ color: 'var(--primary)' }} />
                </div>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>
                    Toplam Belge
                  </div>
                  <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-primary)' }}>
                    {summary?.total_documents || 0}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="col-md-3">
          <div className="card">
            <div className="card-body">
              <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                <div
                  style={{
                    width: 48,
                    height: 48,
                    borderRadius: 'var(--radius-lg)',
                    background: 'var(--success-soft)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                  }}
                >
                  <FiDownload size={24} style={{ color: 'var(--success)' }} />
                </div>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>
                    Toplam Boyut
                  </div>
                  <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-primary)' }}>
                    {summary?.total_size_formatted || '0 B'}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="col-md-3">
          <div className="card">
            <div className="card-body">
              <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                <div
                  style={{
                    width: 48,
                    height: 48,
                    borderRadius: 'var(--radius-lg)',
                    background: 'var(--warning-soft)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                  }}
                >
                  <FiFolder size={24} style={{ color: 'var(--warning)' }} />
                </div>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>
                    Kategori
                  </div>
                  <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-primary)' }}>
                    {summary?.categories_count || 0}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="col-md-3">
          <div className="card">
            <div className="card-body">
              <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
                <div
                  style={{
                    width: 48,
                    height: 48,
                    borderRadius: 'var(--radius-lg)',
                    background: 'var(--info-soft)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                  }}
                >
                  <FiTrendingUp size={24} style={{ color: 'var(--info)' }} />
                </div>
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>
                    Bu Ay Yüklenen
                  </div>
                  <div style={{ display: 'flex', alignItems: 'baseline', gap: '0.5rem' }}>
                    <span style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-primary)' }}>
                      {summary?.this_month || 0}
                    </span>
                    {summary?.month_change !== undefined && summary?.month_change !== 0 && (
                      <span
                        style={{
                          fontSize: '0.75rem',
                          fontWeight: 600,
                          color: summary.month_change > 0 ? 'var(--success)' : 'var(--danger)',
                        }}
                      >
                        {summary.month_change > 0 ? '+' : ''}{summary.month_change}%
                      </span>
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Charts Row */}
      <div className="row" style={{ gap: '1.5rem' }}>
        {/* Trend Chart */}
        <div className="col-lg-8">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <FiTrendingUp /> Aylık Yükleme Trendi
              </h3>
            </div>
            <div className="card-body">
              {summary?.trend && summary.trend.length > 0 ? (
                <ResponsiveContainer width="100%" height={300}>
                  <LineChart data={summary.trend}>
                    <CartesianGrid strokeDasharray="3 3" stroke="var(--border-primary)" />
                    <XAxis dataKey="name" stroke="var(--text-tertiary)" fontSize={12} />
                    <YAxis stroke="var(--text-tertiary)" fontSize={12} />
                    <Tooltip
                      contentStyle={{
                        background: 'var(--surface-elevated)',
                        border: '1px solid var(--border-primary)',
                        borderRadius: 'var(--radius-md)',
                      }}
                    />
                    <Line
                      type="monotone"
                      dataKey="value"
                      stroke="var(--primary)"
                      strokeWidth={2}
                      dot={{ fill: 'var(--primary)', strokeWidth: 2, r: 4 }}
                      activeDot={{ r: 6 }}
                      name="Belge Sayısı"
                    />
                  </LineChart>
                </ResponsiveContainer>
              ) : (
                <div style={{ height: 300, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--text-muted)' }}>
                  Yeterli veri yok
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Category Pie Chart */}
        <div className="col-lg-4">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <FiPieChart /> Kategori Dağılımı
              </h3>
            </div>
            <div className="card-body">
              {summary?.by_category && summary.by_category.length > 0 ? (
                <ResponsiveContainer width="100%" height={300}>
                  <PieChart>
                    <Pie
                      data={summary.by_category}
                      cx="50%"
                      cy="50%"
                      innerRadius={60}
                      outerRadius={100}
                      fill="#8884d8"
                      paddingAngle={2}
                      dataKey="value"
                      label={({ name, percent }) => `${name} (${((percent ?? 0) * 100).toFixed(0)}%)`}
                      labelLine={false}
                    >
                      {summary.by_category.map((_entry, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip
                      contentStyle={{
                        background: 'var(--surface-elevated)',
                        border: '1px solid var(--border-primary)',
                        borderRadius: 'var(--radius-md)',
                      }}
                    />
                  </PieChart>
                </ResponsiveContainer>
              ) : (
                <div style={{ height: 300, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--text-muted)' }}>
                  Yeterli veri yok
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* File Type Distribution */}
      <div className="row mt-4">
        <div className="col-12">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <FiFileText /> Dosya Tipi Dağılımı
              </h3>
            </div>
            <div className="card-body">
              {summary?.by_file_type && summary.by_file_type.length > 0 ? (
                <ResponsiveContainer width="100%" height={300}>
                  <BarChart data={summary.by_file_type} layout="vertical">
                    <CartesianGrid strokeDasharray="3 3" stroke="var(--border-primary)" />
                    <XAxis type="number" stroke="var(--text-tertiary)" fontSize={12} />
                    <YAxis type="category" dataKey="name" stroke="var(--text-tertiary)" fontSize={12} width={120} />
                    <Tooltip
                      contentStyle={{
                        background: 'var(--surface-elevated)',
                        border: '1px solid var(--border-primary)',
                        borderRadius: 'var(--radius-md)',
                      }}
                    />
                    <Bar dataKey="value" fill="var(--primary)" radius={[0, 4, 4, 0]} name="Belge Sayısı">
                      {summary.by_file_type.map((_entry, index) => (
                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                      ))}
                    </Bar>
                  </BarChart>
                </ResponsiveContainer>
              ) : (
                <div style={{ height: 300, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--text-muted)' }}>
                  Yeterli veri yok
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Quick Stats Table */}
      <div className="row mt-4">
        <div className="col-md-6">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Kategoriler</h3>
            </div>
            <div className="card-body" style={{ padding: 0 }}>
              {summary?.by_category && summary.by_category.length > 0 ? (
                <table className="table">
                  <thead>
                    <tr>
                      <th>Kategori</th>
                      <th className="text-end">Belge Sayısı</th>
                    </tr>
                  </thead>
                  <tbody>
                    {summary.by_category.map((cat, index) => (
                      <tr key={index}>
                        <td>
                          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                            <div
                              style={{
                                width: 8,
                                height: 8,
                                borderRadius: '50%',
                                background: COLORS[index % COLORS.length],
                              }}
                            />
                            {cat.name}
                          </div>
                        </td>
                        <td className="text-end">
                          <span className="badge badge-secondary">{cat.value}</span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : (
                <div style={{ padding: '2rem', textAlign: 'center', color: 'var(--text-muted)' }}>
                  Henüz kategori yok
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="col-md-6">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Dosya Tipleri</h3>
            </div>
            <div className="card-body" style={{ padding: 0 }}>
              {summary?.by_file_type && summary.by_file_type.length > 0 ? (
                <table className="table">
                  <thead>
                    <tr>
                      <th>Dosya Tipi</th>
                      <th className="text-end">Belge Sayısı</th>
                    </tr>
                  </thead>
                  <tbody>
                    {summary.by_file_type.map((type, index) => (
                      <tr key={index}>
                        <td>
                          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                            <div
                              style={{
                                width: 8,
                                height: 8,
                                borderRadius: '50%',
                                background: COLORS[index % COLORS.length],
                              }}
                            />
                            {type.name}
                          </div>
                        </td>
                        <td className="text-end">
                          <span className="badge badge-secondary">{type.value}</span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : (
                <div style={{ padding: '2rem', textAlign: 'center', color: 'var(--text-muted)' }}>
                  Henüz belge yok
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DocumentReportsPage;

