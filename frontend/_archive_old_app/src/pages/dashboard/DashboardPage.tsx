import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { RootState } from '../../store';
import { dashboardApi } from '../../services/api';
import { DashboardData } from '../../types';
import {
  BsPeople,
  BsBox,
  BsArrowRight,
  BsClock,
  BsExclamationTriangle,
  BsFileEarmarkText,
  BsPersonPlus,
  BsCalendarCheck,
} from 'react-icons/bs';

interface RecruitmentStats {
  total_applications: number;
  new_applications: number;
  interviews_scheduled: number;
  open_positions: number;
}

const DashboardPage: React.FC = () => {
  const navigate = useNavigate();
  const { user } = useSelector((state: RootState) => state.auth);
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [recruitmentStats, setRecruitmentStats] = useState<RecruitmentStats | null>(null);

  useEffect(() => {
    loadDashboard();
  }, []);

  const loadDashboard = async () => {
    try {
      const response = await dashboardApi.get();
      setData(response.data.data);
      
      // Mock recruitment stats - backend'den gelecek
      setRecruitmentStats({
        total_applications: response.data.data?.recruitment_stats?.total_applications || 0,
        new_applications: response.data.data?.recruitment_stats?.new_applications || 0,
        interviews_scheduled: response.data.data?.recruitment_stats?.interviews_scheduled || 0,
        open_positions: response.data.data?.recruitment_stats?.open_positions || 0,
      });
    } catch (error) {
      console.error('Dashboard yüklenemedi:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div style={{ padding: '2rem' }}>
        <div className="skeleton skeleton-title" style={{ width: 300, marginBottom: '2rem' }} />
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(250px, 1fr))', gap: '1rem' }}>
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="skeleton" style={{ height: 120 }} />
          ))}
        </div>
      </div>
    );
  }

  const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Günaydın';
    if (hour < 18) return 'İyi günler';
    return 'İyi akşamlar';
  };

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">{getGreeting()}, {user?.name?.split(' ')[0]}!</h1>
          <p className="page-subtitle">
            {data?.company?.name || 'Alatax HR'} platformuna hoş geldiniz
          </p>
        </div>
      </div>

      {/* Trial Warning */}
      {data?.company?.status === 'trial' && data?.company?.trial_ends_at && (
        <div style={{
          background: 'var(--warning-soft)',
          border: '1px solid var(--warning)',
          borderRadius: 'var(--radius-lg)',
          padding: '1rem 1.25rem',
          marginBottom: '1.5rem',
          display: 'flex',
          alignItems: 'center',
          gap: '0.75rem',
        }}>
          <BsExclamationTriangle style={{ color: 'var(--warning)', fontSize: '1.25rem' }} />
          <div style={{ flex: 1 }}>
            <strong style={{ color: 'var(--warning-text)' }}>Deneme Süresi</strong>
            <p style={{ margin: 0, fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
              Deneme süreniz {data.company.trial_ends_at} tarihinde sona erecek.
            </p>
          </div>
          <button className="btn btn-secondary btn-sm" onClick={() => navigate('/company')}>
            Planı Yükselt
          </button>
        </div>
      )}

      {/* Main Stats */}
      <div style={{ 
        display: 'grid', 
        gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', 
        gap: '1rem',
        marginBottom: '2rem',
      }}>
        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--primary-soft)', color: 'var(--primary)' }}>
            <BsPeople />
          </div>
          <div className="stat-card-value">{data?.stats?.total_users || 0}</div>
          <div className="stat-card-label">Toplam Kullanıcı</div>
        </div>

        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--success-soft)', color: 'var(--success)' }}>
            <BsBox />
          </div>
          <div className="stat-card-value">{data?.stats?.active_modules || 0}</div>
          <div className="stat-card-label">Aktif Modül</div>
        </div>

        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--info-soft)', color: 'var(--info)' }}>
            <BsPersonPlus />
          </div>
          <div className="stat-card-value">{recruitmentStats?.new_applications || 0}</div>
          <div className="stat-card-label">Yeni Başvuru</div>
        </div>

        <div className="stat-card">
          <div className="stat-card-icon" style={{ background: 'var(--accent-soft)', color: 'var(--accent)' }}>
            <BsFileEarmarkText />
          </div>
          <div className="stat-card-value">{recruitmentStats?.open_positions || 0}</div>
          <div className="stat-card-label">Açık Pozisyon</div>
        </div>
      </div>

      <div className="row g-4">
        {/* Left Column */}
        <div className="col-md-8">
          {/* Quick Actions */}
          <div className="card mb-4">
            <div className="card-header">
              <h3 className="card-title">Hızlı İşlemler</h3>
            </div>
            <div className="card-body">
              <div style={{ 
                display: 'grid', 
                gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', 
                gap: '0.75rem' 
              }}>
                <button
                  className="btn btn-secondary"
                  onClick={() => navigate('/recruitment/positions')}
                  style={{ justifyContent: 'space-between' }}
                >
                  <span style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <i className="bi bi-briefcase" />
                    İş İlanları
                  </span>
                  <BsArrowRight />
                </button>
                <button
                  className="btn btn-secondary"
                  onClick={() => navigate('/recruitment/applications')}
                  style={{ justifyContent: 'space-between' }}
                >
                  <span style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <i className="bi bi-people" />
                    Başvurular
                  </span>
                  <BsArrowRight />
                </button>
                <button
                  className="btn btn-secondary"
                  onClick={() => navigate('/recruitment/cv-pool')}
                  style={{ justifyContent: 'space-between' }}
                >
                  <span style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <i className="bi bi-person-lines-fill" />
                    CV Havuzu
                  </span>
                  <BsArrowRight />
                </button>
                <button
                  className="btn btn-secondary"
                  onClick={() => navigate('/documents')}
                  style={{ justifyContent: 'space-between' }}
                >
                  <span style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <i className="bi bi-folder" />
                    Evraklar
                  </span>
                  <BsArrowRight />
                </button>
                <button
                  className="btn btn-secondary"
                  onClick={() => navigate('/users')}
                  style={{ justifyContent: 'space-between' }}
                >
                  <span style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <i className="bi bi-person-plus" />
                    Kullanıcı Ekle
                  </span>
                  <BsArrowRight />
                </button>
                <button
                  className="btn btn-secondary"
                  onClick={() => navigate('/company')}
                  style={{ justifyContent: 'space-between' }}
                >
                  <span style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <i className="bi bi-gear" />
                    Ayarlar
                  </span>
                  <BsArrowRight />
                </button>
              </div>
            </div>
          </div>

          {/* Recruitment Overview */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">İşe Alım Özeti</h3>
            </div>
            <div className="card-body">
              <div className="row g-3">
                <div className="col-6 col-md-3">
                  <div className="text-center p-3 rounded-lg" style={{ background: 'var(--surface-secondary)' }}>
                    <div className="text-2xl font-bold text-[var(--primary)]">
                      {recruitmentStats?.total_applications || 0}
                    </div>
                    <div className="text-sm text-[var(--text-secondary)]">Toplam Başvuru</div>
                  </div>
                </div>
                <div className="col-6 col-md-3">
                  <div className="text-center p-3 rounded-lg" style={{ background: 'var(--surface-secondary)' }}>
                    <div className="text-2xl font-bold text-[var(--success)]">
                      {recruitmentStats?.new_applications || 0}
                    </div>
                    <div className="text-sm text-[var(--text-secondary)]">Yeni Başvuru</div>
                  </div>
                </div>
                <div className="col-6 col-md-3">
                  <div className="text-center p-3 rounded-lg" style={{ background: 'var(--surface-secondary)' }}>
                    <div className="text-2xl font-bold text-[var(--info)]">
                      {recruitmentStats?.interviews_scheduled || 0}
                    </div>
                    <div className="text-sm text-[var(--text-secondary)]">Planlı Mülakat</div>
                  </div>
                </div>
                <div className="col-6 col-md-3">
                  <div className="text-center p-3 rounded-lg" style={{ background: 'var(--surface-secondary)' }}>
                    <div className="text-2xl font-bold text-[var(--accent)]">
                      {recruitmentStats?.open_positions || 0}
                    </div>
                    <div className="text-sm text-[var(--text-secondary)]">Açık Pozisyon</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Right Column */}
        <div className="col-md-4">
          {/* Company Info */}
          <div className="card mb-4">
            <div className="card-header">
              <h3 className="card-title">Firma Bilgileri</h3>
            </div>
            <div className="card-body">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-12 h-12 rounded-lg bg-gradient-to-br from-[var(--primary)] to-purple-600 flex items-center justify-center text-white text-xl font-bold">
                  {data?.company?.name?.charAt(0) || 'A'}
                </div>
                <div>
                  <div className="font-semibold">{data?.company?.name || 'Firma Adı'}</div>
                  <span className={`badge ${data?.company?.status === 'active' ? 'badge-success' : 'badge-warning'}`}>
                    {data?.company?.status === 'active' ? 'Aktif' : 'Deneme'}
                  </span>
                </div>
              </div>
              <div className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">Paket</span>
                  <span className="font-medium capitalize">{data?.company?.package_type || 'Starter'}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">Kullanıcı</span>
                  <span className="font-medium">{data?.stats?.total_users || 0} / {data?.company?.user_limit || 5}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[var(--text-secondary)]">Modül</span>
                  <span className="font-medium">{data?.stats?.active_modules || 0} aktif</span>
                </div>
              </div>
              <button
                className="btn btn-outline-primary btn-sm w-100 mt-3"
                onClick={() => navigate('/company')}
              >
                Ayarları Düzenle
              </button>
            </div>
          </div>

          {/* Today's Schedule */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">
                <BsCalendarCheck className="me-2" />
                Bugün
              </h3>
            </div>
            <div className="card-body">
              <div className="text-center py-4">
                <BsClock className="text-4xl text-[var(--text-muted)] mb-2" />
                <p className="text-[var(--text-secondary)]">
                  Bugün için planlanmış etkinlik yok
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DashboardPage;
