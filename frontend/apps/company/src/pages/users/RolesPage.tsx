import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { rolesApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { ConfirmDialog, DataTable } from '../../components/ui';
import type { Column } from '../../components/ui/DataTable';
import RoleForm from '../../components/RoleForm';
import {
  BsPlus,
  BsPencil,
  BsTrash,
  BsShieldLock,
  BsEye,
} from 'react-icons/bs';

interface Role {
  id: number;
  name: string;
  guard_name: string;
  permissions: Array<{ id: number; name: string }>;
  users_count?: number;
  created_at: string;
}

const RolesPage: React.FC = () => {
  const navigate = useNavigate();
  const [roles, setRoles] = useState<Role[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');

  const [formOpen, setFormOpen] = useState(false);
  const [selectedRole, setSelectedRole] = useState<Role | undefined>();
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [roleToDelete, setRoleToDelete] = useState<Role | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  useEffect(() => {
    loadRoles();
  }, []);

  const loadRoles = async () => {
    try {
      setLoading(true);
      const response = await rolesApi.list();
      const data = response.data.data;
      setRoles(Array.isArray(data) ? data : data?.data || []);
    } catch {
      toast.error('Roller yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const handleEdit = (role: Role) => {
    setSelectedRole(role);
    setFormOpen(true);
  };

  const handleDelete = (role: Role) => {
    setRoleToDelete(role);
    setDeleteDialogOpen(true);
  };

  const confirmDelete = async () => {
    if (!roleToDelete) return;

    setDeleteLoading(true);
    try {
      await rolesApi.delete(roleToDelete.id);
      toast.success('Rol silindi');
      setDeleteDialogOpen(false);
      setRoleToDelete(null);
      loadRoles();
    } catch {
      toast.error('Rol silinemedi');
    } finally {
      setDeleteLoading(false);
    }
  };

  const filteredRoles = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return roles;
    return roles.filter((r) => r.name.toLowerCase().includes(q));
  }, [roles, search]);

  const columns: Column<Role>[] = useMemo(
    () => [
      {
        key: 'name',
        title: 'Rol',
        render: (role) => (
          <span style={{ fontWeight: 500 }}>{role.name}</span>
        ),
      },
      {
        key: 'users',
        title: 'Kullanıcı',
        width: '100px',
        render: (role) => role.users_count ?? 0,
      },
      {
        key: 'permissions',
        title: 'Yetki',
        render: (role) => (
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 'var(--sp-1)' }}>
            {role.permissions?.slice(0, 4).map((perm, idx) => (
              <span key={perm?.id || `perm-${idx}`} className="badge badge-secondary">
                {perm?.name ? perm.name.split('.').pop() : 'N/A'}
              </span>
            ))}
            {(role.permissions?.length || 0) > 4 && (
              <span className="badge badge-primary">+{(role.permissions?.length || 0) - 4}</span>
            )}
            {(!role.permissions || role.permissions.length === 0) && (
              <span style={{ color: 'var(--text-tertiary)', fontSize: 'var(--fs-caption)' }}>Yetki yok</span>
            )}
          </div>
        ),
      },
      {
        key: 'actions',
        title: 'İşlemler',
        align: 'right',
        width: '120px',
        render: (role) => (
          <div className="table-actions">
            <button
              type="button"
              className="btn btn-ghost btn-icon"
              onClick={() => navigate(`/roles/${role.id}`)}
              title="Detay"
              aria-label="Detay"
            >
              <BsEye />
            </button>
            <button
              type="button"
              className="btn btn-ghost btn-icon"
              onClick={() => handleEdit(role)}
              title="Düzenle"
              aria-label="Düzenle"
            >
              <BsPencil />
            </button>
            <button
              type="button"
              className="btn btn-ghost btn-icon"
              onClick={() => handleDelete(role)}
              title="Sil"
              aria-label="Sil"
              style={{ color: 'var(--danger)' }}
            >
              <BsTrash />
            </button>
          </div>
        ),
      },
    ],
    [navigate]
  );

  return (
    <div className="animate-fade-in list-page">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Roller</h1>
          {roles.length > 0 && (
            <span className="page-subtitle">{roles.length} kayıt</span>
          )}
        </div>
        <div className="page-header-actions">
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={() => {
              setSelectedRole(undefined);
              setFormOpen(true);
            }}
          >
            <BsPlus /> Yeni Rol
          </button>
        </div>
      </div>

      <div className="list-filter-bar">
        <div className="list-filter-search input-group">
          <input
            type="text"
            className="form-control"
            placeholder="Rol ara..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
      </div>

      <DataTable
        columns={columns}
        data={filteredRoles}
        loading={loading}
        emptyMessage="Rol bulunamadı"
        emptyIcon={<BsShieldLock size={32} />}
        emptyAction={
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={() => {
              setSelectedRole(undefined);
              setFormOpen(true);
            }}
          >
            <BsPlus /> Rol Oluştur
          </button>
        }
      />

      <RoleForm
        isOpen={formOpen}
        onClose={() => setFormOpen(false)}
        onSuccess={loadRoles}
        role={selectedRole}
      />

      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        onConfirm={confirmDelete}
        title="Rolü Sil"
        message={`"${roleToDelete?.name}" rolünü silmek istediğinize emin misiniz? Bu rol ile ilişkili kullanıcılar bu yetkilerini kaybedecek.`}
        confirmText="Sil"
        variant="danger"
        loading={deleteLoading}
      />
    </div>
  );
};

export default RolesPage;
