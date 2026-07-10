import React, { useEffect, useState } from 'react';
import { useSelector } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import { RootState } from '../store';
import { portalApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import {
  BsCalendarPlus,
  BsFileEarmarkText,
  BsCurrencyDollar,
  BsInboxes,
  BsMegaphone,
  BsArrowRight,
} from 'react-icons/bs';

interface DashboardData {
  employee: {
    name: string;
    position: string;
    department: string;
    hire_date: string;
  };
  stats: {
    leave_balance: Array<{
      type: string;
      remaining: number;
    }>;
    pending_requests: number;
  };
  announcements: Array<{
    id: number;
    title: string;
    summary: string;
    type: string;
    published_at: string;
    is_pinned: boolean;
  }>;
  latest_payslip: {
    id: number;
    period_label: string;
    is_viewed: boolean;
  } | null;
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
      const response = await portalApi.dashboard();
      setData(response.data.data);
    } catch {
      toast.error('Dashboard yüklenemedi');
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

  return (
    <div className="animate-fade-in">
      {/* Welcome Banner */}
      <div className="welcome-banner">
        <h1>Hoş Geldiniz, {user?.name}!</h1>
        <p>
          {data?.employee?.position || 'Personel'} - {data?.employee?.department || ''}
        </p>
      </div>

      {/* Quick Actions */}
      <div className="action-cards">
        <div className="action-card" onClick={() => navigate('/leaves')}>
          <div className="action-icon">
            <BsCalendarPlus />
          </div>
          <div className="action-title">İzin Talebi</div>
        </div>

        <div className="action-card" onClick={() => navigate('/requests')}>
          <div className="action-icon">
            <BsInboxes />
          </div>
          <div className="action-title">Yeni Talep</div>
        </div>

        <div className="action-card" onClick={() => navigate('/payslips')}>
          <div className="action-icon">
            <BsCurrencyDollar />
          </div>
          <div className="action-title">Bordrolarım</div>
        </div>

        <div className="action-card" onClick={() => navigate('/documents')}>
          <div className="action-icon">
            <BsFileEarmarkText />
          </div>
          <div className="action-title">Belgelerim</div>
        </div>
      </div>

      <div className="row">
        {/* Leave Balance */}
        <div className="col-lg-4 mb-4">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">İzin Bakiyem</h3>
            </div>
            <div className="card-body">
              {data?.stats?.leave_balance && data.stats.leave_balance.length > 0 ? (
                data.stats.leave_balance.map((balance, index) => (
                  <div key={index} className="balance-card mb-2">
                    <div className="balance-info">
                      <div className="balance-type">{balance.type}</div>
                      <div className="balance-value">{balance.remaining}</div>
                      <div className="balance-label">gün kaldı</div>
                    </div>
                  </div>
                ))
              ) : (
                <p className="text-muted text-center">İzin bakiyesi yok</p>
              )}
            </div>
          </div>
        </div>

        {/* Latest Payslip */}
        <div className="col-lg-4 mb-4">
          <div className="card">
            <div className="card-header d-flex justify-content-between align-items-center">
              <h3 className="card-title">Son Bordro</h3>
              <button
                className="btn btn-sm btn-ghost"
                onClick={() => navigate('/payslips')}
              >
                Tümü <BsArrowRight />
              </button>
            </div>
            <div className="card-body">
              {data?.latest_payslip ? (
                <div
                  className="payslip-card"
                  onClick={() => navigate('/payslips')}
                >
                  <div>
                    <div className="payslip-period">{data.latest_payslip.period_label}</div>
                    <div className={`payslip-status ${data.latest_payslip.is_viewed ? 'viewed' : 'new'}`}>
                      {data.latest_payslip.is_viewed ? 'Görüntülendi' : 'Yeni'}
                    </div>
                  </div>
                </div>
              ) : (
                <p className="text-muted text-center">Bordro yok</p>
              )}
            </div>
          </div>
        </div>

        {/* Pending Requests */}
        <div className="col-lg-4 mb-4">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Bekleyen Talepler</h3>
            </div>
            <div className="card-body text-center">
              <div className="stat-value" style={{ fontSize: '3rem', color: 'var(--primary)' }}>
                {data?.stats?.pending_requests || 0}
              </div>
              <p className="text-muted">adet talep bekliyor</p>
              <button
                className="btn btn-outline-primary btn-sm"
                onClick={() => navigate('/requests')}
              >
                Taleplerime Git
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Announcements */}
      <div className="card">
        <div className="card-header d-flex justify-content-between align-items-center">
          <h3 className="card-title">
            <BsMegaphone className="me-2" /> Duyurular
          </h3>
          <button
            className="btn btn-sm btn-ghost"
            onClick={() => navigate('/announcements')}
          >
            Tümü <BsArrowRight />
          </button>
        </div>
        <div className="card-body">
          {data?.announcements && data.announcements.length > 0 ? (
            data.announcements.map((announcement) => (
              <div
                key={announcement.id}
                className={`announcement-item ${announcement.type === 'urgent' ? 'urgent' : ''}`}
                onClick={() => navigate(`/announcements/${announcement.id}`)}
              >
                <div className="announcement-header">
                  <span className="announcement-title">
                    {announcement.is_pinned && '📌 '}
                    {announcement.title}
                  </span>
                  <span className="announcement-date">
                    {new Date(announcement.published_at).toLocaleDateString('tr-TR')}
                  </span>
                </div>
                <div className="announcement-summary">
                  {announcement.summary || ''}
                </div>
              </div>
            ))
          ) : (
            <p className="text-muted text-center">Henüz duyuru yok</p>
          )}
        </div>
      </div>
    </div>
  );
};

export default DashboardPage;

