import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { rolesApi, usersApi, activityLogsApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import {
  BsArrowLeft,
  BsPencil,
  BsShieldLock,
  BsPeople,
  BsCalendar,
  BsCopy,
} from 'react-icons/bs';
import { DataTable } from '../../components/ui';
import RoleForm from '../../components/RoleForm';

interface Role {
  id: number;
  name: string;
  guard_name: string;
  permissions: Array<{ id: number; name: string }>;
  users_count?: number;
  created_at: string;
  updated_at: string;
  created_by?: { id: number; name: string } | null;
  updated_by?: { id: number; name: string } | null;
}

interface User {
  id: number;
  name: string;
  email: string;
  is_active: boolean;
  created_at: string;
}

interface ActivityLog {
  id: number;
  action: string;
  description: string | null;
  created_at: string;
  ip_address: string | null;
  is_successful: boolean;
  causer?: { id: number; name: string } | null;
}

const actionLabels: Record<string, string> = {
  created: 'Oluşturuldu',
  updated: 'Güncellendi',
  deleted: 'Silindi',
  viewed: 'Görüntülendi',
};

const RoleDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [role, setRole] = useState<Role | null>(null);
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [usersLoading, setUsersLoading] = useState(false);
  const [activities, setActivities] = useState<ActivityLog[]>([]);
  const [activitiesLoading, setActivitiesLoading] = useState(false);
  const [formOpen, setFormOpen] = useState(false);
  const [duplicateLoading, setDuplicateLoading] = useState(false);

  const loadRole = useCallback(async () => {
    try {
      setLoading(true);
      const response = await rolesApi.get(Number(id));
      setRole(response.data.data as Role);
    } catch {
      toast.error('Rol yüklenemedi');
      navigate('/roles');
    } finally {
      setLoading(false);
    }
  }, [id, navigate]);

  const loadUsers = useCallback(async () => {
    try {
      setUsersLoading(true);
      const response = await usersApi.list({ role_id: id });
      setUsers(response.data.data || []);
    } catch {
      toast.error('Kullanıcılar yüklenemedi');
    } finally {
      setUsersLoading(false);
    }
  }, [id]);

  const loadActivities = useCallback(async () => {
    try {
      setActivitiesLoading(true);
      const response = await activityLogsApi.list({
        subject_type: 'App\\Models\\Role',
        subject_id: id,
        per_page: 10,
      });
      setActivities(response.data.data || []);
    } catch {
      toast.error('Aktivite logları yüklenemedi');
    } finally {
      setActivitiesLoading(false);
    }
  }, [id]);

  useEffect(() => {
    if (id) {
      loadRole();
      loadUsers();
      loadActivities();
    }
  }, [id, loadRole, loadUsers, loadActivities]);

  

  

  

  const handleDuplicate = async () => {
    if (!role) return;

    setDuplicateLoading(true);
    try {
      // Duplicate işlemi için önce yeni bir rol oluştur
      const newRoleName = `${role.name} (Kopya)`;
      const response = await rolesApi.create({
        name: newRoleName,
        permissions: role.permissions.map((p) => p.name),
      });
      toast.success('Rol başarıyla kopyalandı');
      navigate(`/roles/${response.data.data.id}`);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'Rol kopyalanamadı'));
    } finally {
      setDuplicateLoading(false);
    }
  };

  const formatDateTime = (dateString: string) => {
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

  if (!role) {
    return null;
  }

  const userColumns = [
    {
      key: 'name',
      title: 'Kullanıcı',
      render: (user: User) => (
        <div>
          <div style={{ fontWeight: 500, color: 'var(--text-primary)' }}>{user.name}</div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{user.email}</div>
        </div>
      ),
    },
    {
      key: 'status',
      title: 'Durum',
      width: '100px',
      render: (user: User) => (
        <span className={`badge ${user.is_active ? 'badge-success' : 'badge-secondary'}`}>
          {user.is_active ? 'Aktif' : 'Pasif'}
        </span>
      ),
    },
    {
      key: 'created_at',
      title: 'Oluşturulma',
      width: '150px',
      render: (user: User) => formatDateTime(user.created_at),
    },
    {
      key: 'actions',
      title: 'İşlemler',
      width: '100px',
      align: 'right' as const,
      render: (user: User) => (
        <button
          className="btn btn-ghost btn-sm"
          onClick={() => navigate(`/users/${user.id}`)}
        >
          Detay
        </button>
      ),
    },
  ];

  const activityColumns = [
    {
      key: 'action',
      title: 'İşlem',
      width: '120px',
      render: (log: ActivityLog) => (
        <span className={`badge ${log.is_successful ? 'badge-success' : 'badge-danger'}`}>
          {actionLabels[log.action] || log.action}
        </span>
      ),
    },
    {
      key: 'description',
      title: 'Açıklama',
      render: (log: ActivityLog) => log.description || '-',
    },
    {
      key: 'causer',
      title: 'Kullanıcı',
      width: '150px',
      render: (log: ActivityLog) => log.causer?.name || 'Sistem',
    },
    {
      key: 'created_at',
      title: 'Tarih',
      width: '150px',
      render: (log: ActivityLog) => formatDateTime(log.created_at),
    },
  ];

  // Yetkileri modüllere göre grupla
  const groupedPermissions: Record<string, Array<{ id: number; name: string }>> = {};
  (role.permissions || []).forEach((perm) => {
    if (!perm?.name) return;
    const module = perm.name.split('.')[0] || 'Diğer';
    if (!groupedPermissions[module]) {
      groupedPermissions[module] = [];
    }
    groupedPermissions[module].push(perm);
  });

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <div className="page-header-content">
          <button
            className="btn btn-ghost btn-icon"
            onClick={() => navigate('/roles')}
            style={{ marginRight: '0.75rem' }}
          >
            <BsArrowLeft />
          </button>
          <div>
            <h1>{role.name}</h1>
            <p>Rol detayları ve yetkileri</p>
          </div>
        </div>
        <div className="page-header-actions" style={{ display: 'flex', gap: '0.5rem' }}>
          <button
            className="btn btn-outline-secondary"
            onClick={handleDuplicate}
            disabled={duplicateLoading}
          >
            {duplicateLoading ? (
              <>
                <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                Kopyalanıyor...
              </>
            ) : (
              <>
                <BsCopy style={{ marginRight: 6 }} /> Kopyala
              </>
            )}
          </button>
          <button
            className="btn btn-primary"
            onClick={() => setFormOpen(true)}
          >
            <BsPencil style={{ marginRight: 6 }} /> Düzenle
          </button>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: '1.5rem' }}>
        {/* Sol Panel - Rol Bilgileri */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          {/* Rol Kartı */}
          <div className="card">
            <div className="card-body" style={{ textAlign: 'center' }}>
              <div
                style={{
                  width: 80,
                  height: 80,
                  borderRadius: 'var(--radius-md)',
                  background: 'var(--primary-soft)',
                  color: 'var(--primary)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  margin: '0 auto 1rem',
                }}
              >
                <BsShieldLock size={40} />
              </div>
              <h3 style={{ marginBottom: '0.5rem' }}>{role.name}</h3>
              <p style={{ color: 'var(--text-tertiary)', fontSize: '0.875rem', marginBottom: '1rem' }}>
                {role.guard_name}
              </p>
            </div>
          </div>

          {/* İstatistikler */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">İstatistikler</h3>
            </div>
            <div className="card-body">
              <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <BsPeople style={{ color: 'var(--primary)' }} />
                    <span>Kullanıcı Sayısı</span>
                  </div>
                  <strong>{role.users_count || users.length}</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <BsShieldLock style={{ color: 'var(--primary)' }} />
                    <span>Yetki Sayısı</span>
                  </div>
                  <strong>{role.permissions?.length || 0}</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <BsCalendar style={{ color: 'var(--primary)' }} />
                    <span>Oluşturulma</span>
                  </div>
                  <span style={{ fontSize: '0.875rem' }}>{formatDateTime(role.created_at)}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Bilgiler */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Bilgiler</h3>
            </div>
            <div className="card-body">
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                {role.created_by && (
                  <div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>
                      Oluşturan
                    </div>
                    <div>{role.created_by.name}</div>
                  </div>
                )}
                {role.updated_by && (
                  <div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>
                      Son Güncelleyen
                    </div>
                    <div>{role.updated_by.name}</div>
                  </div>
                )}
                <div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem' }}>
                    Son Güncelleme
                  </div>
                  <div>{formatDateTime(role.updated_at)}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Sağ Panel - Yetkiler ve Kullanıcılar */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          {/* Yetkiler */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Yetkiler ({role.permissions?.length || 0})</h3>
            </div>
            <div className="card-body">
              {Object.keys(groupedPermissions).length > 0 ? (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
                  {Object.entries(groupedPermissions).map(([module, perms]) => (
                    <div key={module}>
                      <div
                        style={{
                          fontSize: '0.875rem',
                          fontWeight: 600,
                          color: 'var(--text-secondary)',
                          marginBottom: '0.75rem',
                          textTransform: 'uppercase',
                        }}
                      >
                        {module}
                      </div>
                      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem' }}>
                        {perms.map((perm) => (
                          <span key={perm.id} className="badge badge-primary">
                            {perm.name.split('.').slice(1).join('.') || perm.name}
                          </span>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
                  Bu role henüz yetki atanmamış
                </div>
              )}
            </div>
          </div>

          {/* Kullanıcılar */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Bu Role Sahip Kullanıcılar ({users.length})</h3>
            </div>
            <div className="card-body">
              {usersLoading ? (
                <div className="page-loading">
                  <div className="loading-spinner" />
                </div>
              ) : users.length > 0 ? (
                <DataTable
                  data={users}
                  columns={userColumns}
                />
              ) : (
                <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
                  Bu role sahip kullanıcı bulunmuyor
                </div>
              )}
            </div>
          </div>

          {/* Aktivite Logları */}
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Aktivite Logları</h3>
            </div>
            <div className="card-body">
              {activitiesLoading ? (
                <div className="page-loading">
                  <div className="loading-spinner" />
                </div>
              ) : activities.length > 0 ? (
                <DataTable
                  data={activities}
                  columns={activityColumns}
                />
              ) : (
                <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
                  Aktivite logu bulunmuyor
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Role Form Modal */}
      <RoleForm
        isOpen={formOpen}
        onClose={() => setFormOpen(false)}
        onSuccess={() => {
          setFormOpen(false);
          loadRole();
        }}
        role={role}
      />
    </div>
  );
};

export default RoleDetailPage;

