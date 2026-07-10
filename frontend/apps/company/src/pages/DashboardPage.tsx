import React, { useEffect, useState } from 'react';
import { useSelector } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import { RootState } from '../store';
import { dashboardApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import {
  BsPeople,
  BsCalendarCheck,
  BsBriefcase,
  BsFileEarmarkText,
  BsArrowRight,
  BsPersonPlus,
  BsFolderPlus,
  BsCalendarPlus,
  BsGrid,
  BsClock,
} from 'react-icons/bs';

interface DashboardData {
  welcome_message: string;
  is_super_admin?: boolean;
  redirect_to_admin?: boolean;
  company: {
    id: number;
    name: string;
    status: string;
    package_type: string;
    trial_ends_at: string | null;
    license_end_date: string | null;
  } | null;
  stats: {
    total_users?: number;
    active_modules?: number;
    pending_leaves?: number;
    open_positions?: number;
    total_documents?: number;
    total_companies?: number;
    active_companies?: number;
  };
  modules: string[];
  quick_actions: Array<{
    label: string;
    icon: string;
    route: string;
  }>;
  recent_activities: Array<{
    id: number;
    description: string;
    causer?: { name: string };
    created_at: string;
  }>;
}

const DashboardPage: React.FC = () => {
  const navigate = useNavigate();
  const { user } = useSelector((state: RootState) => state.auth);
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadDashboard();
  }, []);

  const loadDashboard = async () => {
    try {
      const response = await dashboardApi.get();
      const dashboardData = response.data.data;
      
      // SuperAdmin ise admin paneline yönlendir
      if (dashboardData.redirect_to_admin) {
        window.location.href = 'http://localhost:3001/dashboard';
        return;
      }
      
      setData(dashboardData);
    } catch (error) {
      console.error('Dashboard error:', error);
      toast.error('Dashboard verileri yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="page-loading">
        <div className="loading-spinner"></div>
      </div>
    );
  }

  // Eğer company yoksa
  if (!data?.company) {
    return (
      <div className="animate-fade-in">
        <div className="page-header">
          <div className="page-header-content">
            <h1>Hoş Geldiniz, {user?.name}!</h1>
            <p>Firma bilgileriniz yüklenemiyor. Lütfen yöneticinizle iletişime geçin.</p>
          </div>
        </div>
        
        <div className="card">
          <div className="card-body empty-state">
            <div className="empty-state-icon"><BsGrid /></div>
            <h3 className="empty-state-title">Firma Bulunamadı</h3>
            <p className="empty-state-text">
              Hesabınız bir firmaya bağlı değil. Lütfen sistem yöneticinizle iletişime geçin.
            </p>
          </div>
        </div>
      </div>
    );
  }

  const activeModules = data.modules || [];

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1>Hoş Geldiniz, {user?.name?.split(' ')[0]}!</h1>
          <p>{data.company.name} - Yönetim Paneli</p>
        </div>
        
        {data.company.license_end_date && (
          <div className="badge badge-info" style={{ padding: '0.5rem 0.75rem' }}>
            <BsClock style={{ marginRight: 4 }} />
            Lisans: {data.company.license_end_date}
          </div>
        )}
      </div>

      {/* Stats Grid */}
      <div className="stats-grid">
        {/* Total Users */}
        <div className="stat-card">
          <div className="stat-card-header">
            <div className="stat-card-icon primary">
              <BsPeople />
            </div>
          </div>
          <div className="stat-card-value">{data.stats.total_users || 0}</div>
          <div className="stat-card-label">Aktif Kullanıcı</div>
        </div>

        {/* Active Modules */}
        <div className="stat-card">
          <div className="stat-card-header">
            <div className="stat-card-icon success">
              <BsGrid />
            </div>
          </div>
          <div className="stat-card-value">{data.stats.active_modules || 0}</div>
          <div className="stat-card-label">Aktif Modül</div>
        </div>

        {/* Pending Leaves - if module active */}
        {activeModules.includes('leave-management') && (
          <div className="stat-card">
            <div className="stat-card-header">
              <div className="stat-card-icon warning">
                <BsCalendarCheck />
              </div>
            </div>
            <div className="stat-card-value">{data.stats.pending_leaves || 0}</div>
            <div className="stat-card-label">Bekleyen İzin</div>
          </div>
        )}

        {/* Open Positions - if module active */}
        {activeModules.includes('job-applications') && (
          <div className="stat-card">
            <div className="stat-card-header">
              <div className="stat-card-icon info">
                <BsBriefcase />
              </div>
            </div>
            <div className="stat-card-value">{data.stats.open_positions || 0}</div>
            <div className="stat-card-label">Açık Pozisyon</div>
          </div>
        )}

        {/* Documents - if module active */}
        {activeModules.includes('document-management') && (
          <div className="stat-card">
            <div className="stat-card-header">
              <div className="stat-card-icon primary">
                <BsFileEarmarkText />
              </div>
            </div>
            <div className="stat-card-value">{data.stats.total_documents || 0}</div>
            <div className="stat-card-label">Toplam Evrak</div>
          </div>
        )}
      </div>

      {/* Content Grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '1rem' }}>
        {/* Quick Actions */}
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Hızlı İşlemler</h3>
          </div>
          <div className="card-body">
            <div className="quick-actions">
              {/* User Management */}
              <div className="quick-action" onClick={() => navigate('/users')}>
                <div className="quick-action-icon">
                  <BsPersonPlus />
                </div>
                <span className="quick-action-label">Kullanıcı Ekle</span>
              </div>

              {/* Leave Management */}
              {activeModules.includes('leave-management') && (
                <div className="quick-action" onClick={() => navigate('/leaves')}>
                  <div className="quick-action-icon">
                    <BsCalendarPlus />
                  </div>
                  <span className="quick-action-label">İzin Talebi</span>
                </div>
              )}

              {/* Document Management */}
              {activeModules.includes('document-management') && (
                <div className="quick-action" onClick={() => navigate('/documents')}>
                  <div className="quick-action-icon">
                    <BsFolderPlus />
                  </div>
                  <span className="quick-action-label">Evrak Yükle</span>
                </div>
              )}

              {/* Job Applications */}
              {activeModules.includes('job-applications') && (
                <div className="quick-action" onClick={() => navigate('/recruitment/positions')}>
                  <div className="quick-action-icon">
                    <BsBriefcase />
                  </div>
                  <span className="quick-action-label">İlan Oluştur</span>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Active Modules */}
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Aktif Modüller</h3>
          </div>
          <div className="card-body">
            {activeModules.length > 0 ? (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                {activeModules.includes('leave-management') && (
                  <ModuleItem
                    icon={<BsCalendarCheck />}
                    label="İzin Yönetimi"
                    onClick={() => navigate('/leaves')}
                  />
                )}
                {activeModules.includes('document-management') && (
                  <ModuleItem
                    icon={<BsFileEarmarkText />}
                    label="Evrak Yönetimi"
                    onClick={() => navigate('/documents')}
                  />
                )}
                {activeModules.includes('job-applications') && (
                  <ModuleItem
                    icon={<BsBriefcase />}
                    label="İşe Alım"
                    onClick={() => navigate('/recruitment/positions')}
                  />
                )}
              </div>
            ) : (
              <div className="empty-state" style={{ padding: '1.5rem' }}>
                <p className="text-tertiary" style={{ fontSize: '0.8125rem' }}>
                  Henüz aktif modül bulunmuyor
                </p>
              </div>
            )}
          </div>
        </div>

        {/* Recent Activity */}
        <div className="card" style={{ gridColumn: 'span 1' }}>
          <div className="card-header">
            <h3 className="card-title">Son Aktiviteler</h3>
          </div>
          <div className="card-body">
            {data.recent_activities && data.recent_activities.length > 0 ? (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                {data.recent_activities.slice(0, 5).map((activity) => (
                  <div key={activity.id} style={{ 
                    display: 'flex', 
                    alignItems: 'flex-start', 
                    gap: '0.75rem',
                    paddingBottom: '0.75rem',
                    borderBottom: '1px solid var(--border-primary)'
                  }}>
                    <div style={{
                      width: 8,
                      height: 8,
                      borderRadius: '50%',
                      background: 'var(--primary)',
                      marginTop: 6,
                      flexShrink: 0
                    }} />
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <p style={{ 
                        fontSize: '0.8125rem', 
                        color: 'var(--text-primary)',
                        margin: 0,
                        lineHeight: 1.4
                      }}>
                        <strong>{activity.causer?.name || 'Sistem'}</strong>
                        {' - '}
                        {activity.description}
                      </p>
                      <span style={{ 
                        fontSize: '0.6875rem', 
                        color: 'var(--text-muted)' 
                      }}>
                        {new Date(activity.created_at).toLocaleString('tr-TR')}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="empty-state" style={{ padding: '1.5rem' }}>
                <BsClock size={24} style={{ color: 'var(--text-muted)', marginBottom: '0.5rem' }} />
                <p className="text-tertiary" style={{ fontSize: '0.8125rem', margin: 0 }}>
                  Henüz aktivite yok
                </p>
              </div>
            )}
          </div>
        </div>

        {/* Company Info */}
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Firma Bilgileri</h3>
          </div>
          <div className="card-body">
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              <InfoRow label="Firma" value={data.company.name} />
              <InfoRow 
                label="Durum" 
                value={
                  <span className={`badge badge-${data.company.status === 'active' ? 'success' : 'warning'}`}>
                    {data.company.status === 'active' ? 'Aktif' : data.company.status}
                  </span>
                } 
              />
              <InfoRow label="Paket" value={data.company.package_type || 'Standart'} />
              {data.company.license_end_date && (
                <InfoRow label="Lisans Bitiş" value={data.company.license_end_date} />
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

// Helper Components
const ModuleItem: React.FC<{ icon: React.ReactNode; label: string; onClick: () => void }> = ({ icon, label, onClick }) => (
  <div
    onClick={onClick}
    style={{
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      padding: '0.625rem 0.75rem',
      background: 'var(--surface-glass)',
      borderRadius: 'var(--radius-md)',
      cursor: 'pointer',
      transition: 'all 0.15s ease',
    }}
    onMouseEnter={(e) => {
      e.currentTarget.style.background = 'var(--primary-soft)';
    }}
    onMouseLeave={(e) => {
      e.currentTarget.style.background = 'var(--surface-glass)';
    }}
  >
    <div style={{ display: 'flex', alignItems: 'center', gap: '0.625rem' }}>
      <span style={{ color: 'var(--primary)', display: 'flex' }}>{icon}</span>
      <span style={{ fontSize: '0.8125rem', fontWeight: 500, color: 'var(--text-primary)' }}>{label}</span>
    </div>
    <BsArrowRight style={{ color: 'var(--text-tertiary)', fontSize: '0.875rem' }} />
  </div>
);

const InfoRow: React.FC<{ label: string; value: React.ReactNode }> = ({ label, value }) => (
  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
    <span style={{ fontSize: '0.8125rem', color: 'var(--text-tertiary)' }}>{label}</span>
    <span style={{ fontSize: '0.8125rem', fontWeight: 500, color: 'var(--text-primary)' }}>{value}</span>
  </div>
);

export default DashboardPage;
