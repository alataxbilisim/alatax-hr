import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { usersApi, activityLogsApi } from '@shared/services/api';
import type { Employee as EmployeeEntity } from '@shared/types/modules';
import toast from 'react-hot-toast';
import {
  BsArrowLeft,
  BsPencil,
  BsPerson,
  BsTelephone,
  BsShieldCheck,
  BsCalendar,
  BsClock,
  BsActivity,
  BsBuilding,
  BsKey,
  BsShieldLock,
  BsQrCode,
  BsXCircle,
  BsLaptop,
  BsTrash,
} from 'react-icons/bs';
import { DataTable, Modal } from '../../components/ui';
import AvatarUpload from '../../components/ui/AvatarUpload';

interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  avatar: string | null;
  title: string | null;
  department: string | null;
  type: string;
  is_active: boolean;
  two_factor_enabled?: boolean;
  last_login_at: string | null;
  last_login_ip: string | null;
  created_at: string;
  roles: Array<{ id: number; name: string }>;
  created_by: { id: number; name: string } | null;
  employee?: EmployeeEntity | null;
}

interface UserDetailResponse {
  user: User;
  stats: UserStats;
}

interface UserStats {
  total_actions: number;
  last_activity: string | null;
  active_sessions: number;
}

interface ActivityLog {
  id: number;
  action: string;
  description: string | null;
  created_at: string;
  ip_address: string | null;
  is_successful: boolean;
}

const actionLabels: Record<string, string> = {
  created: 'Oluşturuldu',
  updated: 'Güncellendi',
  deleted: 'Silindi',
  viewed: 'Görüntülendi',
  login: 'Giriş Yapıldı',
  logout: 'Çıkış Yapıldı',
  approved: 'Onaylandı',
  rejected: 'Reddedildi',
};

const UserDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [user, setUser] = useState<User | null>(null);
  const [stats, setStats] = useState<UserStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [activities, setActivities] = useState<ActivityLog[]>([]);
  const [activitiesLoading, setActivitiesLoading] = useState(false);
  const [activitiesPage, setActivitiesPage] = useState(1);
  const [activitiesTotalPages, setActivitiesTotalPages] = useState(1);
  const [twoFactorModalOpen, setTwoFactorModalOpen] = useState(false);
  const [twoFactorStep, setTwoFactorStep] = useState<'enable' | 'verify' | 'recovery'>('enable');
  const [qrCodeUrl, setQrCodeUrl] = useState<string>('');
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [verificationCode, setVerificationCode] = useState('');
  const [twoFactorLoading, setTwoFactorLoading] = useState(false);
  const [sessions, setSessions] = useState<Array<{ id: number; name: string; last_used_at: string | null; created_at: string; is_current: boolean }>>([]);
  const [sessionsLoading, setSessionsLoading] = useState(false);

  const loadUser = useCallback(async () => {
    try {
      setLoading(true);
      const response = await usersApi.get(Number(id));
      const data = response.data.data as UserDetailResponse | User;
      // Backend'den gelen response formatını kontrol et
      if ('user' in data && 'stats' in data) {
        setUser(data.user);
        setStats(data.stats);
      } else {
        // Eğer direkt user objesi geliyorsa
        setUser(data as User);
        setStats({
          total_actions: 0,
          last_activity: null,
          active_sessions: 0,
        });
      }
    } catch {
      toast.error('Kullanıcı bilgileri yüklenemedi');
      navigate('/users');
    } finally {
      setLoading(false);
    }
  }, [id, navigate]);

  const loadActivities = useCallback(async (page: number = 1) => {
    try {
      setActivitiesLoading(true);
      const response = await activityLogsApi.list({
        user_id: id,
        page,
        per_page: 10,
      });
      const data = response.data.data;
      setActivities(data.data || []);
      setActivitiesTotalPages(data.last_page || 1);
    } catch {
      toast.error('Aktiviteler yüklenemedi');
    } finally {
      setActivitiesLoading(false);
    }
  }, [id]);

  const loadSessions = useCallback(async () => {
    if (!user?.id) return;
    try {
      setSessionsLoading(true);
      const response = await usersApi.getSessions(user.id);
      setSessions(response.data.data || []);
    } catch {
      toast.error('Oturumlar yüklenemedi');
    } finally {
      setSessionsLoading(false);
    }
  }, [user?.id]);

  useEffect(() => {
    if (id) {
      loadUser();
      loadActivities(1);
    }
  }, [id, loadUser, loadActivities]);

  useEffect(() => {
    if (user?.id) {
      loadSessions();
    }
  }, [user?.id, loadSessions]);

  

  

  

  const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleString('tr-TR', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const formatRelativeTime = (date: string | null) => {
    if (!date) return '-';
    const now = new Date();
    const then = new Date(date);
    const diff = now.getTime() - then.getTime();
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (days > 0) return `${days} gün önce`;
    if (hours > 0) return `${hours} saat önce`;
    if (minutes > 0) return `${minutes} dakika önce`;
    return 'Az önce';
  };

  const formatDateTime = (dateString: string | null) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('tr-TR', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  if (loading) {
    return (
      <div className="page-loading">
        <div className="loading-spinner" />
      </div>
    );
  }

  if (!user) {
    return null;
  }

  const activityColumns = [
    {
      key: 'created_at',
      title: 'Tarih',
      render: (log: ActivityLog) => (
        <div style={{ fontSize: '0.875rem' }}>{formatDate(log.created_at)}</div>
      ),
    },
    {
      key: 'action',
      title: 'İşlem',
      render: (log: ActivityLog) => (
        <span className={`badge ${
          log.action === 'created' ? 'badge-success' :
          log.action === 'updated' ? 'badge-info' :
          log.action === 'deleted' ? 'badge-danger' :
          log.action === 'login' ? 'badge-success' :
          'badge-secondary'
        }`}>
          {actionLabels[log.action] || log.action}
        </span>
      ),
    },
    {
      key: 'description',
      title: 'Açıklama',
      render: (log: ActivityLog) => (
        <div style={{ fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
          {log.description || '-'}
        </div>
      ),
    },
    {
      key: 'ip_address',
      title: 'IP Adresi',
      render: (log: ActivityLog) => log.ip_address || '-',
    },
  ];

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
          <button
            className="btn btn-ghost btn-icon"
            onClick={() => navigate('/users')}
            title="Geri"
          >
            <BsArrowLeft />
          </button>
          <div>
            <h1 className="page-title">{user.name}</h1>
            <p className="page-subtitle">Kullanıcı Detayları</p>
          </div>
        </div>
        <button
          className="btn btn-primary"
          onClick={() => navigate(`/users?edit=${user.id}`)}
        >
          <BsPencil /> Düzenle
        </button>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: '1.5rem' }}>
        {/* Sol Panel - Kullanıcı Bilgileri */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          {/* Kullanıcı Kartı */}
          <div className="card">
            <div className="card-body" style={{ textAlign: 'center' }}>
              <AvatarUpload
                currentAvatarUrl={user.avatar}
                onUpload={async (file) => {
                  try {
                    const response = await usersApi.uploadAvatar(user.id, file);
                    setUser({ ...user, avatar: response.data.data.avatar_url });
                    toast.success('Profil fotoğrafı güncellendi');
                  } catch {
                    toast.error('Profil fotoğrafı yüklenemedi');
                  }
                }}
                onDelete={async () => {
                  try {
                    await usersApi.deleteAvatar(user.id);
                    setUser({ ...user, avatar: null });
                    toast.success('Profil fotoğrafı silindi');
                  } catch {
                    toast.error('Profil fotoğrafı silinemedi');
                  }
                }}
                userId={user.id}
                userName={user.name}
              />
              <h3 style={{ margin: '1rem 0 0.5rem', fontSize: '1.25rem' }}>{user.name}</h3>
              <p style={{ margin: '0 0 1rem', color: 'var(--text-tertiary)', fontSize: '0.875rem' }}>
                {user.email}
              </p>
              <span className={`badge ${user.is_active ? 'badge-success' : 'badge-secondary'}`}>
                {user.is_active ? 'Aktif' : 'Pasif'}
              </span>
            </div>
          </div>

          {/* İstatistikler */}
          {stats && (
            <div className="card">
              <div className="card-header">
                <h3 className="card-title">İstatistikler</h3>
              </div>
              <div className="card-body">
                <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span style={{ color: 'var(--text-secondary)' }}>Toplam İşlem</span>
                    <span style={{ fontWeight: 600 }}>{stats.total_actions}</span>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span style={{ color: 'var(--text-secondary)' }}>Aktif Oturum</span>
                    <span style={{ fontWeight: 600 }}>{stats.active_sessions}</span>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span style={{ color: 'var(--text-secondary)' }}>Son Aktivite</span>
                    <span style={{ fontWeight: 600, fontSize: '0.875rem' }}>
                      {stats.last_activity ? formatRelativeTime(stats.last_activity) : '-'}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Bilgiler */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Bilgiler</h3>
            </div>
            <div className="card-body">
              <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                {user.phone && (
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                    <BsTelephone size={18} style={{ color: 'var(--text-tertiary)' }} />
                    <span>{user.phone}</span>
                  </div>
                )}
                {user.title && (
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                    <BsPerson size={18} style={{ color: 'var(--text-tertiary)' }} />
                    <span>{user.title}</span>
                  </div>
                )}
                {user.department && (
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                    <BsBuilding size={18} style={{ color: 'var(--text-tertiary)' }} />
                    <span>{user.department}</span>
                  </div>
                )}
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                  <BsShieldCheck size={18} style={{ color: 'var(--text-tertiary)' }} />
                  <span>{user.type === 'company_admin' ? 'Firma Admini' : 'Kullanıcı'}</span>
                </div>
                {user.last_login_at && (
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                    <BsClock size={18} style={{ color: 'var(--text-tertiary)' }} />
                    <div>
                      <div>{formatDate(user.last_login_at)}</div>
                      {user.last_login_ip && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                          IP: {user.last_login_ip}
                        </div>
                      )}
                    </div>
                  </div>
                )}
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                  <BsCalendar size={18} style={{ color: 'var(--text-tertiary)' }} />
                  <span>Oluşturulma: {formatDate(user.created_at)}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Roller */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Roller</h3>
            </div>
            <div className="card-body">
              {user.roles && user.roles.length > 0 ? (
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem' }}>
                  {user.roles.map((role) => (
                    <span key={role.id} className="badge badge-primary">
                      {role.name}
                    </span>
                  ))}
                </div>
              ) : (
                <span style={{ color: 'var(--text-tertiary)' }}>Rol atanmamış</span>
              )}
            </div>
          </div>

          {/* Güvenlik */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Güvenlik</h3>
            </div>
            <div className="card-body" style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              {/* 2FA Durumu */}
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', paddingBottom: '0.75rem', borderBottom: '1px solid var(--border-color)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <BsShieldLock style={{ color: user.two_factor_enabled ? 'var(--success)' : 'var(--text-tertiary)' }} />
                  <span>İki Faktörlü Doğrulama</span>
                </div>
                <span className={`badge ${user.two_factor_enabled ? 'badge-success' : 'badge-secondary'}`}>
                  {user.two_factor_enabled ? 'Aktif' : 'Pasif'}
                </span>
              </div>

              {/* 2FA Butonları */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                {!user.two_factor_enabled ? (
                  <button
                    className="btn btn-primary btn-sm"
                    onClick={async () => {
                      try {
                        setTwoFactorLoading(true);
                        const response = await usersApi.enable2FA(user.id);
                        setQrCodeUrl(response.data.data.qr_code_url);
                        setRecoveryCodes(response.data.data.recovery_codes);
                        setTwoFactorStep('verify');
                        setTwoFactorModalOpen(true);
                      } catch {
                        toast.error('2FA etkinleştirilemedi');
                      } finally {
                        setTwoFactorLoading(false);
                      }
                    }}
                    disabled={twoFactorLoading}
                  >
                    {twoFactorLoading ? (
                      <>
                        <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                        Yükleniyor...
                      </>
                    ) : (
                      <>
                        <BsShieldLock style={{ marginRight: 6 }} /> 2FA'yı Etkinleştir
                      </>
                    )}
                  </button>
                ) : (
                  <>
                    <button
                      className="btn btn-outline-secondary btn-sm"
                      onClick={async () => {
                        setTwoFactorStep('recovery');
                        try {
                          const response = await usersApi.getRecoveryCodes(user.id);
                          setRecoveryCodes(response.data.data.recovery_codes);
                          setTwoFactorModalOpen(true);
                        } catch {
                          toast.error('Recovery code\'lar yüklenemedi');
                        }
                      }}
                    >
                      <BsQrCode style={{ marginRight: 6 }} /> Recovery Code'ları Görüntüle
                    </button>
                    <button
                      className="btn btn-danger btn-sm"
                      onClick={async () => {
                        if (!confirm('2FA\'yı devre dışı bırakmak istediğinize emin misiniz?')) {
                          return;
                        }
                        try {
                          await usersApi.disable2FA(user.id);
                          setUser({ ...user, two_factor_enabled: false });
                          toast.success('2FA devre dışı bırakıldı');
                        } catch {
                          toast.error('2FA devre dışı bırakılamadı');
                        }
                      }}
                    >
                      <BsXCircle style={{ marginRight: 6 }} /> 2FA'yı Devre Dışı Bırak
                    </button>
                  </>
                )}
              </div>

              {/* Şifre Sıfırla */}
              <div style={{ paddingTop: '0.75rem', borderTop: '1px solid var(--border-color)' }}>
                <button
                  className="btn btn-secondary btn-sm btn-block"
                  onClick={async () => {
                    if (!confirm('Bu kullanıcının şifresini sıfırlamak istediğinize emin misiniz? Kullanıcının tüm aktif oturumları sonlandırılacaktır.')) {
                      return;
                    }
                    const newPassword = prompt('Yeni şifreyi girin (en az 8 karakter):');
                    if (!newPassword || newPassword.length < 8) {
                      toast.error('Şifre en az 8 karakter olmalıdır');
                      return;
                    }
                    const confirmPassword = prompt('Şifreyi tekrar girin:');
                    if (newPassword !== confirmPassword) {
                      toast.error('Şifreler eşleşmiyor');
                      return;
                    }
                    try {
                      await usersApi.resetPassword(user.id, {
                        password: newPassword,
                        password_confirmation: confirmPassword,
                      });
                      toast.success('Şifre başarıyla sıfırlandı');
                    } catch {
                      toast.error('Şifre sıfırlanamadı');
                    }
                  }}
                >
                  <BsKey style={{ marginRight: 6 }} /> Şifre Sıfırla
                </button>
              </div>
            </div>
          </div>

          {/* Aktif Oturumlar */}
          <div className="card">
            <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <h3 className="card-title">Aktif Oturumlar ({sessions.length})</h3>
              {sessions.length > 1 && (
                <button
                  className="btn btn-danger btn-sm"
                  onClick={async () => {
                    if (!confirm('Tüm oturumları sonlandırmak istediğinize emin misiniz? (Mevcut oturum hariç)')) {
                      return;
                    }
                    try {
                      await usersApi.revokeAllSessions(user.id);
                      toast.success('Tüm oturumlar sonlandırıldı');
                      loadSessions();
                    } catch {
                      toast.error('Oturumlar sonlandırılamadı');
                    }
                  }}
                >
                  <BsTrash style={{ marginRight: 6 }} /> Tümünü Sonlandır
                </button>
              )}
            </div>
            <div className="card-body">
              {sessionsLoading ? (
                <div className="page-loading" style={{ minHeight: 100 }}>
                  <div className="loading-spinner" />
                </div>
              ) : sessions.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '1rem', color: 'var(--text-tertiary)' }}>
                  Aktif oturum bulunmuyor
                </div>
              ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                  {sessions.map((session) => (
                    <div
                      key={session.id}
                      style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        padding: '0.75rem',
                        background: session.is_current ? 'var(--primary-soft)' : 'var(--bg-secondary)',
                        borderRadius: 'var(--radius-sm)',
                        border: session.is_current ? '1px solid var(--primary)' : '1px solid var(--border-color)',
                      }}
                    >
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', flex: 1 }}>
                        <BsLaptop style={{ color: 'var(--text-tertiary)' }} />
                        <div style={{ flex: 1 }}>
                          <div style={{ fontWeight: 500, marginBottom: '0.25rem' }}>
                            {session.name}
                            {session.is_current && (
                              <span className="badge badge-success" style={{ marginLeft: '0.5rem' }}>
                                Mevcut Oturum
                              </span>
                            )}
                          </div>
                          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                            {session.last_used_at
                              ? `Son kullanım: ${formatDateTime(session.last_used_at)}`
                              : `Oluşturulma: ${formatDateTime(session.created_at)}`}
                          </div>
                        </div>
                      </div>
                      {!session.is_current && (
                        <button
                          className="btn btn-ghost btn-icon btn-sm"
                          onClick={async () => {
                            if (!confirm('Bu oturumu sonlandırmak istediğinize emin misiniz?')) {
                              return;
                            }
                            try {
                              await usersApi.revokeSession(user.id, session.id);
                              toast.success('Oturum sonlandırıldı');
                              loadSessions();
                            } catch {
                              toast.error('Oturum sonlandırılamadı');
                            }
                          }}
                          title="Oturumu Sonlandır"
                        >
                          <BsTrash />
                        </button>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Sağ Panel - Aktiviteler */}
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">Son Aktiviteler</h3>
          </div>
          <div className="card-body">
            {activitiesLoading ? (
              <div className="page-loading" style={{ minHeight: 200 }}>
                <div className="loading-spinner" />
              </div>
            ) : activities.length === 0 ? (
              <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
                <BsActivity size={48} style={{ marginBottom: '1rem', opacity: 0.5 }} />
                <p>Henüz aktivite kaydı yok</p>
              </div>
            ) : (
              <>
                <DataTable
                  columns={activityColumns}
                  data={activities}
                  loading={false}
                  currentPage={activitiesPage}
                  totalPages={activitiesTotalPages}
                  onPageChange={(page) => {
                    setActivitiesPage(page);
                    loadActivities(page);
                  }}
                />
              </>
            )}
          </div>
        </div>
      </div>

      {/* 2FA Modal */}
      <Modal
        isOpen={twoFactorModalOpen}
        onClose={() => {
          setTwoFactorModalOpen(false);
          setTwoFactorStep('enable');
          setVerificationCode('');
        }}
        title="İki Faktörlü Doğrulama"
        size="md"
      >
        {twoFactorStep === 'verify' && (
          <div>
            <div className="alert alert-info" style={{ marginBottom: '1.5rem' }}>
              <p style={{ marginBottom: '0.5rem' }}>
                <strong>Adım 1:</strong> QR kodu Google Authenticator veya benzeri bir uygulamayla tarayın.
              </p>
              <p>
                <strong>Adım 2:</strong> Uygulamadan gelen 6 haneli kodu girin.
              </p>
            </div>

            {qrCodeUrl && (
              <div style={{ textAlign: 'center', marginBottom: '1.5rem' }}>
                <img
                  src={`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrCodeUrl)}`}
                  alt="QR Code"
                  style={{ border: '1px solid var(--border-color)', borderRadius: 'var(--radius-md)', padding: '0.5rem' }}
                />
              </div>
            )}

            <div className="form-group">
              <label className="form-label">Doğrulama Kodu *</label>
              <input
                type="text"
                className="form-control"
                value={verificationCode}
                onChange={(e) => setVerificationCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                placeholder="000000"
                maxLength={6}
                style={{ textAlign: 'center', fontSize: '1.5rem', letterSpacing: '0.5rem' }}
              />
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.5rem' }}>
              <button
                className="btn btn-secondary"
                onClick={() => {
                  setTwoFactorModalOpen(false);
                  setTwoFactorStep('enable');
                  setVerificationCode('');
                }}
                disabled={twoFactorLoading}
              >
                İptal
              </button>
              <button
                className="btn btn-primary"
                onClick={async () => {
                  if (verificationCode.length !== 6) {
                    toast.error('Lütfen 6 haneli doğrulama kodunu girin');
                    return;
                  }
                  try {
                    setTwoFactorLoading(true);
                    await usersApi.verify2FA(user.id, { code: verificationCode });
                    setUser({ ...user, two_factor_enabled: true });
                    setTwoFactorModalOpen(false);
                    setTwoFactorStep('enable');
                    setVerificationCode('');
                    toast.success('2FA başarıyla etkinleştirildi');
                  } catch {
                    toast.error('Doğrulama kodu geçersiz');
                  } finally {
                    setTwoFactorLoading(false);
                  }
                }}
                disabled={twoFactorLoading || verificationCode.length !== 6}
              >
                {twoFactorLoading ? (
                  <>
                    <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                    Doğrulanıyor...
                  </>
                ) : (
                  'Doğrula ve Etkinleştir'
                )}
              </button>
            </div>
          </div>
        )}

        {twoFactorStep === 'recovery' && (
          <div>
            <div className="alert alert-warning" style={{ marginBottom: '1.5rem' }}>
              <p>
                <strong>Önemli:</strong> Bu recovery code'ları güvenli bir yerde saklayın. 
                Eğer telefonunuzu kaybederseniz, bu code'ları kullanarak hesabınıza erişebilirsiniz.
              </p>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '0.5rem', marginBottom: '1.5rem' }}>
              {recoveryCodes.map((code, index) => (
                <div
                  key={index}
                  style={{
                    padding: '0.75rem',
                    background: 'var(--bg-secondary)',
                    borderRadius: 'var(--radius-sm)',
                    fontFamily: 'monospace',
                    textAlign: 'center',
                    fontWeight: 600,
                  }}
                >
                  {code}
                </div>
              ))}
            </div>

            <div style={{ display: 'flex', justifyContent: 'space-between', gap: '0.75rem' }}>
              <button
                className="btn btn-outline-secondary"
                onClick={async () => {
                  if (!confirm('Recovery code\'ları yenilemek istediğinize emin misiniz? Eski code\'lar geçersiz olacaktır.')) {
                    return;
                  }
                  try {
                    setTwoFactorLoading(true);
                    const response = await usersApi.regenerateRecoveryCodes(user.id);
                    setRecoveryCodes(response.data.data.recovery_codes);
                    toast.success('Recovery code\'lar yenilendi');
                  } catch {
                    toast.error('Recovery code\'lar yenilenemedi');
                  } finally {
                    setTwoFactorLoading(false);
                  }
                }}
                disabled={twoFactorLoading}
              >
                {twoFactorLoading ? (
                  <>
                    <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                    Yenileniyor...
                  </>
                ) : (
                  'Yenile'
                )}
              </button>
              <button
                className="btn btn-primary"
                onClick={() => {
                  setTwoFactorModalOpen(false);
                  setTwoFactorStep('enable');
                }}
              >
                Kapat
              </button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
};

export default UserDetailPage;

