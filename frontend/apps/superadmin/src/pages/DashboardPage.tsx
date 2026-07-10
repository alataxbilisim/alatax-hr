import React, { useEffect, useState } from 'react';
import { adminApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import {
  BsBuilding,
  BsPeople,
  BsClockHistory,
  BsCurrencyDollar,
  BsArrowUp,
  BsCheckCircle,
  BsExclamationTriangle,
  BsArrowClockwise,
} from 'react-icons/bs';

interface DashboardStats {
  stats: {
    total_companies: number;
    active_companies: number;
    trial_companies: number;
    suspended_companies: number;
    total_users: number;
    active_users: number;
    total_modules: number;
    new_companies_this_month: number;
  };
  package_distribution: Record<string, number>;
  recent_companies: Array<{
    id: number;
    name: string;
    status: string;
    package_type: string;
    created_at: string;
  }>;
  recent_activities: Array<{
    id: number;
    user_id: number;
    user_name: string;
    action: string;
    description: string;
    created_at: string;
  }>;
  expiring_trials: Array<{
    id: number;
    name: string;
    trial_ends_at: string;
  }>;
  expiring_licenses: Array<{
    id: number;
    name: string;
    license_end_date: string;
  }>;
}

const DashboardPage: React.FC = () => {
  const [data, setData] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadDashboard();
  }, []);

  const loadDashboard = async () => {
    try {
      setLoading(true);
      const response = await adminApi.dashboard();
      setData(response.data.data);
    } catch {
      toast.error('Dashboard verileri yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="page-loading">
        <div className="loading-spinner"></div>
        <p>Yükleniyor...</p>
      </div>
    );
  }

  const stats = data?.stats;

  const getStatusBadge = (status: string) => {
    const statusMap: Record<string, { label: string; class: string }> = {
      active: { label: 'Aktif', class: 'active' },
      trial: { label: 'Deneme', class: 'trial' },
      suspended: { label: 'Askıda', class: 'suspended' },
      inactive: { label: 'Pasif', class: 'inactive' },
    };
    const s = statusMap[status] || { label: status, class: '' };
    return <span className={`status-badge ${s.class}`}>{s.label}</span>;
  };

  const getDaysLeft = (dateStr: string) => {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.ceil((date.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    return diff;
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Dashboard</h1>
          <p className="page-subtitle">Sistem genel durumu</p>
        </div>
        <div className="page-header-actions">
          <button
            className="btn btn-secondary"
            onClick={loadDashboard}
            disabled={loading}
          >
            <BsArrowClockwise className={loading ? 'animate-spin' : ''} /> Yenile
          </button>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="stats-grid">
        <div className="stat-card">
          <div className="stat-icon primary">
            <BsBuilding />
          </div>
          <div className="stat-content">
            <div className="stat-value">{stats?.total_companies || 0}</div>
            <div className="stat-label">Toplam Firma</div>
            <div className="stat-change positive">
              <BsArrowUp /> {stats?.active_companies || 0} aktif
            </div>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon success">
            <BsPeople />
          </div>
          <div className="stat-content">
            <div className="stat-value">{stats?.total_users || 0}</div>
            <div className="stat-label">Toplam Kullanıcı</div>
            <div className="stat-change positive">
              <BsArrowUp /> {stats?.active_users || 0} aktif
            </div>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon warning">
            <BsClockHistory />
          </div>
          <div className="stat-content">
            <div className="stat-value">{stats?.trial_companies || 0}</div>
            <div className="stat-label">Deneme Sürümünde</div>
          </div>
        </div>

        <div className="stat-card">
          <div className="stat-icon danger">
            <BsCurrencyDollar />
          </div>
          <div className="stat-content">
            <div className="stat-value">{stats?.new_companies_this_month || 0}</div>
            <div className="stat-label">Bu Ay Yeni Firma</div>
          </div>
        </div>
      </div>

      <div className="row mt-4">
        {/* Recent Companies */}
        <div className="col-lg-6 mb-4">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Son Eklenen Firmalar</h3>
            </div>
            <div className="card-body p-0">
              {data?.recent_companies && data.recent_companies.length > 0 ? (
                <div className="list-group">
                  {data.recent_companies.map((company) => (
                    <div key={company.id} className="company-item">
                      <div className="company-logo">{company.name.charAt(0)}</div>
                      <div className="company-info">
                        <div className="company-name">{company.name}</div>
                        <div className="company-meta">
                          <span>{company.package_type || 'Standart'}</span>
                          <span>
                            {new Date(company.created_at).toLocaleDateString('tr-TR')}
                          </span>
                        </div>
                      </div>
                      {getStatusBadge(company.status)}
                    </div>
                  ))}
                </div>
              ) : (
                <div className="empty-state small">
                  <BsBuilding size={32} />
                  <p>Henüz firma eklenmemiş</p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Expiring Licenses */}
        <div className="col-lg-6 mb-4">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Süresi Dolacak Lisanslar</h3>
            </div>
            <div className="card-body p-0">
              {(data?.expiring_licenses && data.expiring_licenses.length > 0) ||
              (data?.expiring_trials && data.expiring_trials.length > 0) ? (
                <div className="list-group">
                  {data?.expiring_trials?.map((company) => (
                    <div key={`trial-${company.id}`} className="company-item">
                      <div className="company-logo">{company.name.charAt(0)}</div>
                      <div className="company-info">
                        <div className="company-name">{company.name}</div>
                        <div className="company-meta">
                          <span>Deneme Süresi</span>
                          <span>
                            {new Date(company.trial_ends_at).toLocaleDateString('tr-TR')}
                          </span>
                        </div>
                      </div>
                      <span
                        className={`status-badge ${
                          getDaysLeft(company.trial_ends_at) <= 3 ? 'inactive' : 'trial'
                        }`}
                      >
                        <BsExclamationTriangle />
                        {getDaysLeft(company.trial_ends_at)} gün
                      </span>
                    </div>
                  ))}
                  {data?.expiring_licenses?.map((company) => (
                    <div key={`license-${company.id}`} className="company-item">
                      <div className="company-logo">{company.name.charAt(0)}</div>
                      <div className="company-info">
                        <div className="company-name">{company.name}</div>
                        <div className="company-meta">
                          <span>Lisans Bitişi</span>
                          <span>
                            {new Date(company.license_end_date).toLocaleDateString(
                              'tr-TR'
                            )}
                          </span>
                        </div>
                      </div>
                      <span
                        className={`status-badge ${
                          getDaysLeft(company.license_end_date) <= 7
                            ? 'inactive'
                            : 'trial'
                        }`}
                      >
                        <BsExclamationTriangle />
                        {getDaysLeft(company.license_end_date)} gün
                      </span>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="empty-state small">
                  <BsCheckCircle size={32} />
                  <p>Yaklaşan lisans bitimi yok</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Recent Activities */}
      {data?.recent_activities && data.recent_activities.length > 0 && (
        <div className="card mt-4">
          <div className="card-header">
            <h3 className="card-title">Son Aktiviteler</h3>
          </div>
          <div className="card-body p-0">
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Tarih</th>
                    <th>Kullanıcı</th>
                    <th>İşlem</th>
                    <th>Açıklama</th>
                  </tr>
                </thead>
                <tbody>
                  {data.recent_activities.map((activity) => (
                    <tr key={activity.id}>
                      <td className="text-nowrap">
                        {new Date(activity.created_at).toLocaleString('tr-TR')}
                      </td>
                      <td>{activity.user_name || 'Sistem'}</td>
                      <td>
                        <span className="status-badge active">{activity.action}</span>
                      </td>
                      <td>{activity.description}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default DashboardPage;
