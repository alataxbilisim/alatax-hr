import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { usersApi, rolesApi } from '@shared/services/api';
import { useTranslation } from '@shared/i18n';
import { usePermission } from '@shared/hooks';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { DataTable, ConfirmDialog } from '../../components/ui';
import UserForm from '../../components/UserForm';
import UserInviteForm from '../../components/UserInviteForm';
import UserImportForm from '../../components/UserImportForm';
import GrantPanelAccessModal from '../../components/GrantPanelAccessModal';
import {
  BsPlus,
  BsPencil,
  BsTrash,
  BsToggleOn,
  BsToggleOff,
  BsPeople,
  BsDownload,
  BsEye,
  BsEnvelope,
  BsUpload,
  BsShieldLock,
  BsShieldX,
} from 'react-icons/bs';

interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  is_active: boolean;
  type: string;
  roles: Array<{ id: number; name: string }>;
  employee?: { id: number; employee_code?: string } | null;
  last_login_at: string | null;
  last_login_ip: string | null;
  created_at: string;
}

interface PaginatedResponse {
  data: User[];
  meta?: {
    current_page: number;
    last_page: number;
    total: number;
  };
  current_page?: number;
  last_page?: number;
  total?: number;
}

const UsersPage: React.FC = () => {
  const navigate = useNavigate();
  const { t } = useTranslation('common');
  const { canEdit } = usePermission();
  const canEditUsers = canEdit('management', 'users');

  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);

  // Modal states
  const [formOpen, setFormOpen] = useState(false);
  const [inviteFormOpen, setInviteFormOpen] = useState(false);
  const [importFormOpen, setImportFormOpen] = useState(false);
  const [grantPanelOpen, setGrantPanelOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState<User | undefined>();
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [userToDelete, setUserToDelete] = useState<User | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [revokeDialogOpen, setRevokeDialogOpen] = useState(false);
  const [userToRevoke, setUserToRevoke] = useState<User | null>(null);
  const [revokeLoading, setRevokeLoading] = useState(false);

  // Bulk action states
  const [selectedUsers, setSelectedUsers] = useState<number[]>([]);
  const [bulkAction, setBulkAction] = useState<string>('');
  const [bulkRoleId, setBulkRoleId] = useState<number | null>(null);
  const [allRoles, setAllRoles] = useState<Array<{ id: number; name: string }>>([]);

  const loadRoles = useCallback(async () => {
    try {
      const response = await rolesApi.list();
      const data = response.data.data;
      setAllRoles(Array.isArray(data) ? data : (data?.data || []));
    } catch {
      // Silent fail
    }
  }, []);

  const loadUsers = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = { page, per_page: 15 };
      if (search) params.search = search;

      const response = await usersApi.list(params);
      const resData = response.data.data as PaginatedResponse;
      
      // Handle different response formats
      if (Array.isArray(resData)) {
        setUsers(resData);
        setTotalPages(1);
        setTotal(resData.length);
      } else if (resData.data) {
        setUsers(resData.data);
        setTotalPages(resData.meta?.last_page || resData.last_page || 1);
        setTotal(resData.meta?.total || resData.total || 0);
      } else {
        setUsers([]);
      }
    } catch {
      toast.error('Kullanıcılar yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [page, search]);

  useEffect(() => {
    loadUsers();
    loadRoles();
  }, [loadRoles, loadUsers]);

  

  

  const handleToggleStatus = async (user: User) => {
    try {
      await usersApi.toggleStatus(user.id);
      toast.success(user.is_active ? 'Kullanıcı pasif yapıldı' : 'Kullanıcı aktif yapıldı');
      loadUsers();
    } catch {
      toast.error('Durum güncellenemedi');
    }
  };

  const handleEdit = (user: User) => {
    setSelectedUser(user);
    setFormOpen(true);
  };

  const handleDelete = (user: User) => {
    setUserToDelete(user);
    setDeleteDialogOpen(true);
  };

  const confirmDelete = async () => {
    if (!userToDelete) return;

    setDeleteLoading(true);
    try {
      await usersApi.delete(userToDelete.id);
      toast.success('Kullanıcı silindi');
      setDeleteDialogOpen(false);
      setUserToDelete(null);
      loadUsers();
    } catch {
      toast.error('Kullanıcı silinemedi');
    } finally {
      setDeleteLoading(false);
    }
  };

  const handleRevokePanel = (user: User) => {
    setUserToRevoke(user);
    setRevokeDialogOpen(true);
  };

  const confirmRevokePanel = async () => {
    if (!userToRevoke) return;
    setRevokeLoading(true);
    try {
      await usersApi.revokePanelAccess(userToRevoke.id);
      toast.success(t('users.panelRevokeSuccess'));
      setRevokeDialogOpen(false);
      setUserToRevoke(null);
      loadUsers();
    } catch (err) {
      toast.error(getErrorMessage(err, t('users.panelRevokeError')));
    } finally {
      setRevokeLoading(false);
    }
  };

  const handleBulkAction = async () => {
    if (selectedUsers.length === 0) {
      toast.error('Lütfen en az bir kullanıcı seçin');
      return;
    }

    if (!bulkAction) {
      toast.error('Lütfen bir işlem seçin');
      return;
    }

    if ((bulkAction === 'assign_role' || bulkAction === 'remove_role') && !bulkRoleId) {
      toast.error('Lütfen bir rol seçin');
      return;
    }

    if (bulkAction === 'delete' && !confirm(`${selectedUsers.length} kullanıcıyı silmek istediğinize emin misiniz? Bu işlem geri alınamaz.`)) {
      return;
    }

    try {
      const payload: Record<string, unknown> = {
        user_ids: selectedUsers,
        action: bulkAction,
      };
      if (bulkRoleId) {
        payload.role_id = bulkRoleId;
      }

      const response = await usersApi.bulkUpdate(payload);
      toast.success(response.data.message || 'İşlem başarıyla tamamlandı');
      setSelectedUsers([]);
      setBulkAction('');
      setBulkRoleId(null);
      loadUsers();
    } catch {
      toast.error('Toplu işlem başarısız');
    }
  };

  const columns = [
    {
      key: 'checkbox',
      title: '',
      width: '40px',
      render: (user: User) => (
        <input
          type="checkbox"
          checked={selectedUsers.includes(user.id)}
          onChange={(e) => {
            if (e.target.checked) {
              setSelectedUsers([...selectedUsers, user.id]);
            } else {
              setSelectedUsers(selectedUsers.filter(id => id !== user.id));
            }
          }}
        />
      ),
    },
    {
      key: 'name',
      title: 'Kullanıcı',
      render: (user: User) => (
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.625rem' }}>
          <div
            style={{
              width: 32,
              height: 32,
              borderRadius: 'var(--radius-md)',
              background: 'var(--gradient-primary)',
              color: 'white',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: '0.75rem',
              fontWeight: 600,
              flexShrink: 0,
            }}
          >
            {user.name.charAt(0).toUpperCase()}
          </div>
          <div>
            <div style={{ fontWeight: 500, color: 'var(--text-primary)', display: 'flex', alignItems: 'center', gap: '0.35rem' }}>
              {user.name}
              <span className="badge badge-secondary" style={{ fontSize: '0.65rem' }}>
                {t('users.panelBadge')}
              </span>
            </div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{user.email}</div>
          </div>
        </div>
      ),
    },
    {
      key: 'roles',
      title: 'Rol',
      render: (user: User) => (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.25rem' }}>
          {user.roles?.length > 0 ? (
            user.roles.map((role) => (
              <span key={role.id} className="badge badge-primary">
                {role.name}
              </span>
            ))
          ) : (
            <span className="badge badge-secondary">Rol yok</span>
          )}
        </div>
      ),
    },
    {
      key: 'phone',
      title: 'Telefon',
      render: (user: User) => (
        <span style={{ color: 'var(--text-secondary)' }}>{user.phone || '-'}</span>
      ),
    },
    {
      key: 'last_login_at',
      title: 'Son Giriş',
      render: (user: User) => {
        if (!user.last_login_at) {
          return <span style={{ color: 'var(--text-tertiary)', fontSize: '0.875rem' }}>Henüz giriş yapmadı</span>;
        }
        const date = new Date(user.last_login_at);
        const now = new Date();
        const diff = now.getTime() - date.getTime();
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        
        return (
          <div style={{ fontSize: '0.875rem' }}>
            <div style={{ color: 'var(--text-primary)' }}>
              {date.toLocaleDateString('tr-TR', { day: '2-digit', month: 'short', year: 'numeric' })}
            </div>
            <div style={{ color: 'var(--text-tertiary)', fontSize: '0.75rem' }}>
              {days > 0 ? `${days} gün önce` : hours > 0 ? `${hours} saat önce` : 'Az önce'}
            </div>
            {user.last_login_ip && (
              <div style={{ color: 'var(--text-tertiary)', fontSize: '0.75rem' }}>
                IP: {user.last_login_ip}
              </div>
            )}
          </div>
        );
      },
    },
    {
      key: 'is_active',
      title: 'Durum',
      width: '80px',
      render: (user: User) => (
        <button
          className="btn btn-ghost btn-icon"
          onClick={() => handleToggleStatus(user)}
          title={user.is_active ? 'Pasif yap' : 'Aktif yap'}
        >
          {user.is_active ? (
            <BsToggleOn size={22} style={{ color: 'var(--success)' }} />
          ) : (
            <BsToggleOff size={22} style={{ color: 'var(--text-muted)' }} />
          )}
        </button>
      ),
    },
    {
      key: 'actions',
      title: 'İşlemler',
      width: '100px',
      align: 'right' as const,
      render: (user: User) => (
        <div className="table-actions">
          <button
            type="button"
            className="btn btn-ghost btn-icon"
            onClick={() => navigate(`/users/${user.id}`)}
            title="Detay"
            aria-label="Detay"
          >
            <BsEye />
          </button>
          <button
            type="button"
            className="btn btn-ghost btn-icon"
            onClick={() => handleEdit(user)}
            title="Düzenle"
            aria-label="Düzenle"
          >
            <BsPencil />
          </button>
          {canEditUsers && user.employee && user.type === 'user' && (
            <button
              type="button"
              className="btn btn-ghost btn-icon"
              onClick={() => handleRevokePanel(user)}
              title={t('users.panelRevoke')}
              aria-label={t('users.panelRevoke')}
              style={{ color: 'var(--warning)' }}
            >
              <BsShieldX />
            </button>
          )}
          <button
            type="button"
            className="btn btn-ghost btn-icon"
            onClick={() => handleDelete(user)}
            title="Sil"
            aria-label="Sil"
            style={{ color: 'var(--danger)' }}
          >
            <BsTrash />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="animate-fade-in list-page">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Kullanıcılar</h1>
          {total > 0 && <span className="page-subtitle">{total} kayıt</span>}
        </div>
        <div className="page-header-actions">
          {canEditUsers && (
            <button
              type="button"
              className="btn btn-secondary btn-sm"
              onClick={() => setGrantPanelOpen(true)}
            >
              <BsShieldLock /> {t('users.panelGrantTitle')}
            </button>
          )}
          <button
            type="button"
            className="btn btn-secondary btn-sm"
            onClick={() => setInviteFormOpen(true)}
          >
            <BsEnvelope /> Davet Et
          </button>
          <button
            type="button"
            className="btn btn-secondary btn-sm"
            onClick={() => setImportFormOpen(true)}
          >
            <BsUpload /> Import
          </button>
          <button
            type="button"
            className="btn btn-ghost btn-sm"
            onClick={async () => {
              try {
                const params: Record<string, unknown> = {};
                if (search) params.search = search;

                const response = await usersApi.export(params);
                const url = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', `users_${new Date().toISOString().split('T')[0]}.csv`);
                document.body.appendChild(link);
                link.click();
                link.remove();
                toast.success('Kullanıcılar export edildi');
              } catch {
                toast.error('Export başarısız');
              }
            }}
          >
            <BsDownload /> Export
          </button>
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={() => {
              setSelectedUser(undefined);
              setFormOpen(true);
            }}
          >
            <BsPlus /> Yeni Kullanıcı
          </button>
        </div>
      </div>

      {selectedUsers.length > 0 && (
        <div
          className="list-filter-bar"
          style={{
            background: 'var(--primary-soft)',
            borderColor: 'var(--primary)',
            marginBottom: 'var(--sp-2)',
          }}
        >
          <span style={{ fontWeight: 500, fontSize: 'var(--fs-body)' }}>
            {selectedUsers.length} kullanıcı seçildi
          </span>
          <select
            className="form-control"
            value={bulkAction}
            onChange={(e) => setBulkAction(e.target.value)}
            style={{ minWidth: 140, width: 'auto' }}
          >
            <option value="">İşlem Seçin</option>
            <option value="activate">Aktifleştir</option>
            <option value="deactivate">Pasifleştir</option>
            <option value="assign_role">Rol Ata</option>
            <option value="remove_role">Rol Kaldır</option>
            <option value="delete">Sil</option>
          </select>
          {(bulkAction === 'assign_role' || bulkAction === 'remove_role') && (
            <select
              className="form-control"
              value={bulkRoleId || ''}
              onChange={(e) => setBulkRoleId(e.target.value ? parseInt(e.target.value) : null)}
              style={{ minWidth: 140, width: 'auto' }}
            >
              <option value="">Rol Seçin</option>
              {allRoles.map((role) => (
                <option key={role.id} value={role.id}>
                  {role.name}
                </option>
              ))}
            </select>
          )}
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={handleBulkAction}
            disabled={!bulkAction || (bulkAction.includes('role') && !bulkRoleId)}
          >
            Uygula
          </button>
          <button
            type="button"
            className="btn btn-ghost btn-sm"
            onClick={() => {
              setSelectedUsers([]);
              setBulkAction('');
              setBulkRoleId(null);
            }}
            style={{ marginLeft: 'auto' }}
          >
            İptal
          </button>
        </div>
      )}

      <div className="list-filter-bar">
        <div className="list-filter-search input-group">
          <input
            type="text"
            className="form-control"
            placeholder="Kullanıcı ara..."
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
          />
        </div>
      </div>

      <DataTable
        columns={columns}
        data={users}
        loading={loading}
        emptyMessage="Kullanıcı bulunamadı"
        emptyIcon={<BsPeople size={32} />}
        currentPage={page}
        totalPages={totalPages}
        total={total}
        onPageChange={setPage}
      />

      {/* User Form Modal */}
      <UserForm
        isOpen={formOpen}
        onClose={() => setFormOpen(false)}
        onSuccess={loadUsers}
        user={selectedUser}
      />

      {/* User Invite Form Modal */}
      <UserInviteForm
        isOpen={inviteFormOpen}
        onClose={() => setInviteFormOpen(false)}
        onSuccess={loadUsers}
      />

      <UserImportForm
        isOpen={importFormOpen}
        onClose={() => setImportFormOpen(false)}
        onSuccess={loadUsers}
      />

      <GrantPanelAccessModal
        isOpen={grantPanelOpen}
        onClose={() => setGrantPanelOpen(false)}
        onSuccess={loadUsers}
      />

      {/* Delete Confirmation */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        onConfirm={confirmDelete}
        title="Kullanıcıyı Sil"
        message={`"${userToDelete?.name}" kullanıcısını silmek istediğinize emin misiniz? Bu işlem geri alınamaz.`}
        confirmText="Sil"
        variant="danger"
        loading={deleteLoading}
      />

      <ConfirmDialog
        isOpen={revokeDialogOpen}
        onClose={() => {
          setRevokeDialogOpen(false);
          setUserToRevoke(null);
        }}
        onConfirm={confirmRevokePanel}
        title={t('users.panelRevokeConfirmTitle')}
        message={t('users.panelRevokeConfirm', { name: userToRevoke?.name ?? '' })}
        confirmText={t('users.panelRevoke')}
        variant="danger"
        loading={revokeLoading}
      />
    </div>
  );
};

export default UsersPage;
