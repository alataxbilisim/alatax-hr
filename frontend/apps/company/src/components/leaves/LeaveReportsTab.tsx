import React, { useEffect, useState, useCallback, useMemo } from 'react';
import { leavesApi } from '@shared/services/api';
import { Select } from '@shared/components';
import toast from 'react-hot-toast';
import { BsBarChart, BsDownload, BsCalendar3, BsPeople } from 'react-icons/bs';

interface LeaveStats {
  total_requests: number;
  pending_requests: number;
  approved_requests: number;
  rejected_requests: number;
  total_days_used: number;
  avg_days_per_request: number;
}

interface LeaveTypeStats {
  leave_type_id: number;
  leave_type_name: string;
  total_requests: number;
  total_days: number;
  approved_count: number;
}

interface MonthlyStats {
  month: number;
  year: number;
  month_name: string;
  total_requests: number;
  total_days: number;
}

const LeaveReportsTab: React.FC = () => {
  const [loading, setLoading] = useState(true);
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [stats, setStats] = useState<LeaveStats | null>(null);
  const [typeStats, setTypeStats] = useState<LeaveTypeStats[]>([]);
  const [monthlyStats, setMonthlyStats] = useState<MonthlyStats[]>([]);

  const loadReports = useCallback(async () => {
    try {
      setLoading(true);
      
      // Load leave requests for stats calculation
      const startDate = `${selectedYear}-01-01`;
      const endDate = `${selectedYear}-12-31`;
      
      const response = await leavesApi.requests.list({ 
        per_page: 1000,
        start_date: startDate,
        end_date: endDate,
      });
      
      const requests = response.data.data?.data || response.data.data || [];
      
      // Calculate stats
      const calculatedStats: LeaveStats = {
        total_requests: requests.length,
        pending_requests: requests.filter((r: { status: string }) => r.status === 'pending').length,
        approved_requests: requests.filter((r: { status: string }) => r.status === 'approved').length,
        rejected_requests: requests.filter((r: { status: string }) => r.status === 'rejected').length,
        total_days_used: requests
          .filter((r: { status: string }) => r.status === 'approved')
          .reduce((sum: number, r: { total_days: number }) => sum + (r.total_days || 0), 0),
        avg_days_per_request: 0,
      };
      
      if (calculatedStats.approved_requests > 0) {
        calculatedStats.avg_days_per_request = 
          Math.round((calculatedStats.total_days_used / calculatedStats.approved_requests) * 10) / 10;
      }
      
      setStats(calculatedStats);
      
      // Calculate type stats
      const typeMap: Record<number, LeaveTypeStats> = {};
      requests.forEach((r: { leave_type?: { id: number; name: string }; status: string; total_days: number }) => {
        if (!r.leave_type) return;
        const typeId = r.leave_type.id;
        if (!typeMap[typeId]) {
          typeMap[typeId] = {
            leave_type_id: typeId,
            leave_type_name: r.leave_type.name,
            total_requests: 0,
            total_days: 0,
            approved_count: 0,
          };
        }
        typeMap[typeId].total_requests++;
        if (r.status === 'approved') {
          typeMap[typeId].approved_count++;
          typeMap[typeId].total_days += r.total_days || 0;
        }
      });
      setTypeStats(Object.values(typeMap).sort((a, b) => b.total_requests - a.total_requests));
      
      // Calculate monthly stats
      const monthMap: Record<string, MonthlyStats> = {};
      const monthNames = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 
                          'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
      
      for (let m = 0; m < 12; m++) {
        monthMap[`${selectedYear}-${m}`] = {
          month: m + 1,
          year: selectedYear,
          month_name: monthNames[m],
          total_requests: 0,
          total_days: 0,
        };
      }
      
      requests.forEach((r: { start_date: string; status: string; total_days: number }) => {
        if (r.status !== 'approved') return;
        const date = new Date(r.start_date);
        const month = date.getMonth();
        const key = `${selectedYear}-${month}`;
        if (monthMap[key]) {
          monthMap[key].total_requests++;
          monthMap[key].total_days += r.total_days || 0;
        }
      });
      
      setMonthlyStats(Object.values(monthMap));
      
    } catch {
      toast.error('Rapor verileri yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [selectedYear]);

  useEffect(() => {
    loadReports();
  }, [loadReports]);

  

  const handleExport = () => {
    toast.success('Excel export özelliği yakında eklenecek');
  };

  const years = useMemo(
    () => Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 2 + i),
    []
  );

  const yearOptions = useMemo(
    () => years.map((year) => ({ value: String(year), label: String(year) })),
    [years]
  );

  const getMaxDays = () => {
    return Math.max(...monthlyStats.map(m => m.total_days), 1);
  };

  return (
    <div>
      {/* Header */}
      <div className="card mb-3">
        <div className="card-body" style={{ padding: '0.75rem 1rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '1rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <BsCalendar3 size={16} style={{ color: 'var(--text-tertiary)' }} />
                <div style={{ minWidth: 100 }}>
                  <Select
                    value={String(selectedYear)}
                    onChange={(v) => setSelectedYear(Number(v))}
                    options={yearOptions}
                    aria-label="Yıl filtresi"
                  />
                </div>
              </div>
            </div>
            <button className="btn btn-secondary" onClick={handleExport}>
              <BsDownload size={16} />
              Excel İndir
            </button>
          </div>
        </div>
      </div>

      {loading ? (
        <div className="page-loading">
          <div className="loading-spinner" />
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          {/* Summary Cards */}
          <div className="row">
            <div className="col-md-3 col-sm-6 mb-3">
              <div className="card">
                <div className="card-body" style={{ padding: '1rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                    <div
                      style={{
                        width: 40,
                        height: 40,
                        borderRadius: 8,
                        background: 'var(--primary-soft)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                      }}
                    >
                      <BsBarChart size={20} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                      <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-primary)' }}>
                        {stats?.total_requests || 0}
                      </div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Toplam Talep</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div className="col-md-3 col-sm-6 mb-3">
              <div className="card">
                <div className="card-body" style={{ padding: '1rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                    <div
                      style={{
                        width: 40,
                        height: 40,
                        borderRadius: 8,
                        background: 'rgba(16, 185, 129, 0.1)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                      }}
                    >
                      <BsCalendar3 size={20} style={{ color: '#10b981' }} />
                    </div>
                    <div>
                      <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-primary)' }}>
                        {stats?.approved_requests || 0}
                      </div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Onaylanan</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div className="col-md-3 col-sm-6 mb-3">
              <div className="card">
                <div className="card-body" style={{ padding: '1rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                    <div
                      style={{
                        width: 40,
                        height: 40,
                        borderRadius: 8,
                        background: 'rgba(245, 158, 11, 0.1)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                      }}
                    >
                      <BsPeople size={20} style={{ color: '#f59e0b' }} />
                    </div>
                    <div>
                      <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-primary)' }}>
                        {stats?.total_days_used || 0}
                      </div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Toplam Gün</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div className="col-md-3 col-sm-6 mb-3">
              <div className="card">
                <div className="card-body" style={{ padding: '1rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                    <div
                      style={{
                        width: 40,
                        height: 40,
                        borderRadius: 8,
                        background: 'rgba(139, 92, 246, 0.1)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                      }}
                    >
                      <BsBarChart size={20} style={{ color: '#8b5cf6' }} />
                    </div>
                    <div>
                      <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-primary)' }}>
                        {stats?.avg_days_per_request || 0}
                      </div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Ort. Gün/Talep</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Charts Row */}
          <div className="row">
            {/* Monthly Chart */}
            <div className="col-lg-8 mb-3">
              <div className="card">
                <div className="card-header" style={{ padding: '0.75rem 1rem', borderBottom: '1px solid var(--border-color)' }}>
                  <h3 style={{ margin: 0, fontSize: '0.9375rem', fontWeight: 600 }}>Aylık İzin Kullanımı</h3>
                </div>
                <div className="card-body" style={{ padding: '1rem' }}>
                  <div style={{ display: 'flex', alignItems: 'flex-end', gap: '4px', height: '200px' }}>
                    {monthlyStats.map((month) => (
                      <div
                        key={month.month}
                        style={{
                          flex: 1,
                          display: 'flex',
                          flexDirection: 'column',
                          alignItems: 'center',
                          gap: '0.25rem',
                        }}
                      >
                        <span style={{ fontSize: '0.625rem', color: 'var(--text-tertiary)' }}>
                          {month.total_days > 0 ? month.total_days : ''}
                        </span>
                        <div
                          style={{
                            width: '100%',
                            height: `${(month.total_days / getMaxDays()) * 150}px`,
                            minHeight: month.total_days > 0 ? '4px' : '0',
                            background: 'var(--primary)',
                            borderRadius: '4px 4px 0 0',
                            transition: 'height 0.3s ease',
                          }}
                        />
                        <span style={{ fontSize: '0.625rem', color: 'var(--text-tertiary)' }}>
                          {month.month_name.substring(0, 3)}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>

            {/* Type Distribution */}
            <div className="col-lg-4 mb-3">
              <div className="card">
                <div className="card-header" style={{ padding: '0.75rem 1rem', borderBottom: '1px solid var(--border-color)' }}>
                  <h3 style={{ margin: 0, fontSize: '0.9375rem', fontWeight: 600 }}>İzin Türü Dağılımı</h3>
                </div>
                <div className="card-body" style={{ padding: '1rem' }}>
                  {typeStats.length === 0 ? (
                    <p style={{ textAlign: 'center', color: 'var(--text-tertiary)', margin: 0 }}>
                      Veri bulunamadı
                    </p>
                  ) : (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                      {typeStats.map((type) => {
                        const percentage = stats?.total_requests 
                          ? Math.round((type.total_requests / stats.total_requests) * 100) 
                          : 0;
                        return (
                          <div key={type.leave_type_id}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.25rem' }}>
                              <span style={{ fontSize: '0.8125rem', fontWeight: 500 }}>{type.leave_type_name}</span>
                              <span style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                                {type.total_requests} talep
                              </span>
                            </div>
                            <div
                              style={{
                                height: '8px',
                                background: 'var(--bg-tertiary)',
                                borderRadius: '4px',
                                overflow: 'hidden',
                              }}
                            >
                              <div
                                style={{
                                  height: '100%',
                                  width: `${percentage}%`,
                                  background: 'var(--primary)',
                                  borderRadius: '4px',
                                }}
                              />
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>

          {/* Type Stats Table */}
          <div className="card">
            <div className="card-header" style={{ padding: '0.75rem 1rem', borderBottom: '1px solid var(--border-color)' }}>
              <h3 style={{ margin: 0, fontSize: '0.9375rem', fontWeight: 600 }}>İzin Türü Detayları</h3>
            </div>
            <div className="table-container">
              <table className="table" style={{ marginBottom: 0 }}>
                <thead>
                  <tr>
                    <th>İzin Türü</th>
                    <th style={{ textAlign: 'center' }}>Toplam Talep</th>
                    <th style={{ textAlign: 'center' }}>Onaylanan</th>
                    <th style={{ textAlign: 'center' }}>Toplam Gün</th>
                    <th style={{ textAlign: 'center' }}>Onay Oranı</th>
                  </tr>
                </thead>
                <tbody>
                  {typeStats.length === 0 ? (
                    <tr>
                      <td colSpan={5} style={{ textAlign: 'center', color: 'var(--text-tertiary)' }}>
                        Bu yıl için izin verisi bulunamadı
                      </td>
                    </tr>
                  ) : (
                    typeStats.map((type) => {
                      const approvalRate = type.total_requests > 0 
                        ? Math.round((type.approved_count / type.total_requests) * 100) 
                        : 0;
                      return (
                        <tr key={type.leave_type_id}>
                          <td style={{ fontWeight: 500 }}>{type.leave_type_name}</td>
                          <td style={{ textAlign: 'center' }}>
                            <span className="badge badge-primary">{type.total_requests}</span>
                          </td>
                          <td style={{ textAlign: 'center' }}>
                            <span className="badge badge-success">{type.approved_count}</span>
                          </td>
                          <td style={{ textAlign: 'center' }}>
                            <span className="badge badge-info">{type.total_days} gün</span>
                          </td>
                          <td style={{ textAlign: 'center' }}>
                            <span className={`badge ${approvalRate >= 80 ? 'badge-success' : approvalRate >= 50 ? 'badge-warning' : 'badge-danger'}`}>
                              %{approvalRate}
                            </span>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default LeaveReportsTab;

