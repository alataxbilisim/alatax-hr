import React, { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { portalApi } from '@shared/services/api';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import {
  BsClock,
  BsBoxArrowInRight,
  BsBoxArrowRight,
  BsCup,
  BsPlay,
  BsCalendar3,
  BsQrCodeScan,
} from 'react-icons/bs';

interface TodayStatus {
  date: string;
  is_clocked_in: boolean;
  is_clocked_out: boolean;
  is_on_break: boolean;
  clock_in: string | null;
  clock_out: string | null;
  break_start: string | null;
  break_end: string | null;
  total_hours: number | null;
  working_duration: string | null;
}

interface WeeklyRecord {
  date: string;
  day_name: string;
  clock_in: string | null;
  clock_out: string | null;
  total_hours: number | null;
  status: string;
}

interface WeeklySummary {
  week_start: string;
  week_end: string;
  total_hours: number;
  working_days: number;
  records: WeeklyRecord[];
}

const TimesheetPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [todayStatus, setTodayStatus] = useState<TodayStatus | null>(null);
  const [weeklySummary, setWeeklySummary] = useState<WeeklySummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [activeTab, setActiveTab] = useState<'today' | 'weekly' | 'shifts'>('today');
  const [currentTime, setCurrentTime] = useState(new Date());
  const [myShifts, setMyShifts] = useState<Array<{
    date: string;
    day_name: string;
    shift: { id: number; name: string; start_time: string; end_time: string } | null;
    notes?: string | null;
  }>>([]);

  // Update current time every second
  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentTime(new Date());
    }, 1000);
    return () => clearInterval(timer);
  }, []);

  const loadData = useCallback(async () => {
    try {
      setLoading(true);
      const [todayRes, weeklyRes, shiftsRes] = await Promise.all([
        portalApi.timesheet.todayStatus(),
        portalApi.timesheet.weeklyRecords(),
        portalApi.timesheet.shifts(),
      ]);
      setTodayStatus(todayRes.data.data);
      setWeeklySummary(weeklyRes.data.data);
      const shiftPayload = shiftsRes.data.data;
      setMyShifts(shiftPayload?.shifts || []);
    } catch {
      toast.error(t('portalShifts.loadFailed'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleClockIn = async () => {
    setActionLoading(true);
    try {
      // Try to get location
      let locationData: { latitude?: number; longitude?: number } = {};
      if (navigator.geolocation) {
        try {
          const position = await new Promise<GeolocationPosition>((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 5000 });
          });
          locationData = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
          };
        } catch {
          // Location not available, continue without it
        }
      }
      
      await portalApi.timesheet.clockIn(locationData);
      toast.success('Giriş yapıldı!');
      loadData();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err.response?.data?.message || 'Giriş yapılamadı');
    } finally {
      setActionLoading(false);
    }
  };

  const handleClockOut = async () => {
    setActionLoading(true);
    try {
      let locationData: { latitude?: number; longitude?: number } = {};
      if (navigator.geolocation) {
        try {
          const position = await new Promise<GeolocationPosition>((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 5000 });
          });
          locationData = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
          };
        } catch {
          // Continue without location
        }
      }
      
      await portalApi.timesheet.clockOut(locationData);
      toast.success('Çıkış yapıldı!');
      loadData();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err.response?.data?.message || 'Çıkış yapılamadı');
    } finally {
      setActionLoading(false);
    }
  };

  const handleStartBreak = async () => {
    setActionLoading(true);
    try {
      await portalApi.timesheet.startBreak();
      toast.success('Mola başladı');
      loadData();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err.response?.data?.message || 'Mola başlatılamadı');
    } finally {
      setActionLoading(false);
    }
  };

  const handleEndBreak = async () => {
    setActionLoading(true);
    try {
      await portalApi.timesheet.endBreak();
      toast.success('Mola bitti');
      loadData();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err.response?.data?.message || 'Mola bitirilemedi');
    } finally {
      setActionLoading(false);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'present': return 'success';
      case 'late': return 'warning';
      case 'absent': return 'danger';
      case 'leave': return 'info';
      default: return 'secondary';
    }
  };

  const getStatusLabel = (status: string) => {
    switch (status) {
      case 'present': return 'Mevcut';
      case 'late': return 'Geç';
      case 'absent': return 'Yok';
      case 'leave': return 'İzinli';
      case 'no_record': return '-';
      default: return status;
    }
  };

  if (loading) {
    return (
      <div className="page-loading">
        <div className="loading-spinner"></div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Puantaj</h1>
          <p className="page-subtitle">Giriş/çıkış saatlerinizi kaydedin</p>
        </div>
        <Link to="/timesheet/qr" className="btn btn-outline-primary btn-touch">
          <BsQrCodeScan className="me-2" />
          {t('pdks.openQrScan')}
        </Link>
      </div>

      {/* Tabs */}
      <div className="nav-tabs-mobile mb-4">
        <button
          className={`nav-tab-mobile ${activeTab === 'today' ? 'active' : ''}`}
          onClick={() => setActiveTab('today')}
        >
          <BsClock className="me-2" />
          Bugün
        </button>
        <button
          className={`nav-tab-mobile ${activeTab === 'weekly' ? 'active' : ''}`}
          onClick={() => setActiveTab('weekly')}
        >
          <BsCalendar3 className="me-2" />
          Haftalık
        </button>
        <button
          className={`nav-tab-mobile ${activeTab === 'shifts' ? 'active' : ''}`}
          onClick={() => setActiveTab('shifts')}
        >
          <BsCalendar3 className="me-2" />
          {t('portalShifts.tab')}
        </button>
      </div>

      {activeTab === 'today' && todayStatus && (
        <>
          {/* Current Time Display */}
          <div className="card mb-4">
            <div className="card-body text-center">
              <div style={{ fontSize: '3rem', fontWeight: 700, color: 'var(--portal-primary)' }}>
                {currentTime.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
              </div>
              <div className="text-muted">
                {currentTime.toLocaleDateString('tr-TR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
              </div>
            </div>
          </div>

          {/* Status Card */}
          <div className="card mb-4">
            <div className="card-body">
              <div className="row text-center">
                <div className="col-4">
                  <div className="text-muted small mb-1">Giriş</div>
                  <div className="fw-semibold" style={{ fontSize: '1.25rem' }}>
                    {todayStatus.clock_in || '--:--'}
                  </div>
                </div>
                <div className="col-4">
                  <div className="text-muted small mb-1">Çıkış</div>
                  <div className="fw-semibold" style={{ fontSize: '1.25rem' }}>
                    {todayStatus.clock_out || '--:--'}
                  </div>
                </div>
                <div className="col-4">
                  <div className="text-muted small mb-1">Süre</div>
                  <div className="fw-semibold" style={{ fontSize: '1.25rem', color: 'var(--portal-primary)' }}>
                    {todayStatus.working_duration || (todayStatus.total_hours ? `${todayStatus.total_hours} saat` : '--:--')}
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Action Buttons */}
          <div className="card">
            <div className="card-body">
              {!todayStatus.is_clocked_in ? (
                // Not clocked in yet
                <button
                  className="btn btn-primary btn-block btn-touch"
                  style={{ fontSize: '1.25rem', padding: '1.5rem' }}
                  onClick={handleClockIn}
                  disabled={actionLoading}
                >
                  <BsBoxArrowInRight size={24} className="me-2" />
                  {actionLoading ? 'İşleniyor...' : 'GİRİŞ YAP'}
                </button>
              ) : todayStatus.is_clocked_out ? (
                // Already clocked out
                <div className="text-center">
                  <div className="badge bg-success" style={{ fontSize: '1rem', padding: '0.75rem 1.5rem' }}>
                    Bugünkü mesai tamamlandı
                  </div>
                  <div className="text-muted mt-2">
                    Toplam: {todayStatus.total_hours} saat
                  </div>
                </div>
              ) : (
                // Clocked in, show break and clock out buttons
                <div className="d-flex flex-column gap-3">
                  {/* Break Button */}
                  {!todayStatus.is_on_break ? (
                    <button
                      className="btn btn-outline-primary btn-touch"
                      style={{ fontSize: '1.1rem', padding: '1rem' }}
                      onClick={handleStartBreak}
                      disabled={actionLoading}
                    >
                      <BsCup size={20} className="me-2" />
                      {actionLoading ? 'İşleniyor...' : 'Mola Başlat'}
                    </button>
                  ) : (
                    <button
                      className="btn btn-warning btn-touch"
                      style={{ fontSize: '1.1rem', padding: '1rem' }}
                      onClick={handleEndBreak}
                      disabled={actionLoading}
                    >
                      <BsPlay size={20} className="me-2" />
                      {actionLoading ? 'İşleniyor...' : 'Molayı Bitir'}
                    </button>
                  )}

                  {/* Clock Out Button */}
                  <button
                    className="btn btn-danger btn-touch"
                    style={{ fontSize: '1.25rem', padding: '1.5rem' }}
                    onClick={handleClockOut}
                    disabled={actionLoading || todayStatus.is_on_break}
                  >
                    <BsBoxArrowRight size={24} className="me-2" />
                    {actionLoading ? 'İşleniyor...' : 'ÇIKIŞ YAP'}
                  </button>

                  {todayStatus.is_on_break && (
                    <div className="text-center text-muted small">
                      Çıkış yapmak için önce molayı bitirin
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </>
      )}

      {activeTab === 'weekly' && weeklySummary && (
        <>
          {/* Weekly Summary */}
          <div className="card mb-4">
            <div className="card-body">
              <div className="row text-center">
                <div className="col-6">
                  <div className="text-muted small mb-1">Toplam Saat</div>
                  <div className="stat-value">{weeklySummary.total_hours.toFixed(1)}</div>
                </div>
                <div className="col-6">
                  <div className="text-muted small mb-1">Çalışılan Gün</div>
                  <div className="stat-value">{weeklySummary.working_days}</div>
                </div>
              </div>
            </div>
          </div>

          {/* Weekly Records */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">
                {new Date(weeklySummary.week_start).toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' })} - {new Date(weeklySummary.week_end).toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' })}
              </h3>
            </div>
            <div className="card-body p-0">
              <div className="mobile-card-list" style={{ display: 'flex' }}>
                {weeklySummary.records.map((record) => (
                  <div key={record.date} className="mobile-card">
                    <div className="mobile-card-header">
                      <div>
                        <div className="mobile-card-title">{record.day_name}</div>
                        <div className="mobile-card-subtitle">
                          {new Date(record.date).toLocaleDateString('tr-TR')}
                        </div>
                      </div>
                      <span className={`badge bg-${getStatusColor(record.status)}`}>
                        {getStatusLabel(record.status)}
                      </span>
                    </div>
                    <div className="mobile-card-body">
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">Giriş</span>
                        <span className="mobile-card-value">{record.clock_in || '-'}</span>
                      </div>
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">Çıkış</span>
                        <span className="mobile-card-value">{record.clock_out || '-'}</span>
                      </div>
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">Süre</span>
                        <span className="mobile-card-value text-primary fw-semibold">
                          {record.total_hours ? `${record.total_hours} saat` : '-'}
                        </span>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </>
      )}

      {activeTab === 'shifts' && (
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">{t('portalShifts.tab')}</h3>
          </div>
          <div className="card-body p-0">
            {myShifts.length === 0 ? (
              <div className="p-4 text-muted text-center">{t('portalShifts.empty')}</div>
            ) : (
              <div className="mobile-card-list" style={{ display: 'flex' }}>
                {myShifts.map((day) => (
                  <div key={day.date} className="mobile-card">
                    <div className="mobile-card-header">
                      <div>
                        <div className="mobile-card-title">{day.day_name}</div>
                        <div className="mobile-card-subtitle">{day.date}</div>
                      </div>
                    </div>
                    <div className="mobile-card-body">
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">{t('portalShifts.shift')}</span>
                        <span className="mobile-card-value">{day.shift?.name || '—'}</span>
                      </div>
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">{t('portalShifts.hours')}</span>
                        <span className="mobile-card-value">
                          {day.shift
                            ? `${String(day.shift.start_time).slice(0, 5)} – ${String(day.shift.end_time).slice(0, 5)}`
                            : '—'}
                        </span>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default TimesheetPage;

