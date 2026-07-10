import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { rolesApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { ConfirmDialog } from '../../components/ui';
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

  // Modal states
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
      setRoles(response.data.data || []);
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

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1>Roller</h1>
          <p>Kullanıcı rollerini ve yetkilerini yönetin</p>
        </div>
        <div className="page-header-actions">
          <button
            className="btn btn-primary"
            onClick={() => {
              setSelectedRole(undefined);
              setFormOpen(true);
            }}
          >
            <BsPlus size={18} /> Yeni Rol
          </button>
        </div>
      </div>

      {/* Roles Grid */}
      {loading ? (
        <div className="page-loading">
          <div className="loading-spinner" />
        </div>
      ) : roles.length === 0 ? (
        <div className="card">
          <div className="card-body empty-state">
            <BsShieldLock size={48} style={{ color: 'var(--text-muted)' }} />
            <h3 className="empty-state-title mt-3">Rol Bulunamadı</h3>
            <p className="empty-state-text">
              Henüz tanımlı rol yok. İlk rolü oluşturmak için butona tıklayın.
            </p>
            <button
              className="btn btn-primary mt-2"
              onClick={() => {
                setSelectedRole(undefined);
                setFormOpen(true);
              }}
            >
              <BsPlus /> Rol Oluştur
            </button>
          </div>
        </div>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '1rem' }}>
          {roles.map((role) => (
            <div key={role.id} className="card">
              <div className="card-body">
                {/* Header */}
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '0.75rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.625rem' }}>
                    <div
                      style={{
                        width: 36,
                        height: 36,
                        borderRadius: 'var(--radius-md)',
                        background: 'var(--primary-soft)',
                        color: 'var(--primary)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                      }}
                    >
                      <BsShieldLock size={16} />
                    </div>
                    <div>
                      <h4 style={{ fontSize: '0.9375rem', fontWeight: 600, color: 'var(--text-primary)', margin: 0 }}>
                        {role.name}
                      </h4>
                      {role.users_count !== undefined && (
                        <span style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                          {role.users_count} kullanıcı
                        </span>
                      )}
                    </div>
                  </div>
                  
                  {/* Actions */}
                  <div style={{ display: 'flex', gap: '0.25rem' }}>
                    <button
                      className="btn btn-ghost btn-icon btn-sm"
                      onClick={() => navigate(`/roles/${role.id}`)}
                      title="Detay"
                    >
                      <BsEye />
                    </button>
                    <button
                      className="btn btn-ghost btn-icon btn-sm"
                      onClick={() => handleEdit(role)}
                      title="Düzenle"
                    >
                      <BsPencil />
                    </button>
                    <button
                      className="btn btn-ghost btn-icon btn-sm"
                      onClick={() => handleDelete(role)}
                      title="Sil"
                      style={{ color: 'var(--danger)' }}
                    >
                      <BsTrash />
                    </button>
                  </div>
                </div>

                {/* Permissions */}
                <div style={{ marginTop: '0.75rem' }}>
                  <div style={{ fontSize: '0.6875rem', fontWeight: 600, textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: '0.375rem' }}>
                    Yetkiler ({role.permissions?.length || 0})
                  </div>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.25rem' }}>
                    {role.permissions?.slice(0, 5).map((perm, idx) => (
                      <span key={perm?.id || `perm-${idx}`} className="badge badge-secondary">
                        {perm?.name ? perm.name.split('.').pop() : 'N/A'}
                      </span>
                    ))}
                    {role.permissions && role.permissions.length > 5 && (
                      <span className="badge badge-primary">
                        +{role.permissions.length - 5}
                      </span>
                    )}
                    {(!role.permissions || role.permissions.length === 0) && (
                      <span style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Yetki yok</span>
                    )}
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Role Form Modal */}
      <RoleForm
        isOpen={formOpen}
        onClose={() => setFormOpen(false)}
        onSuccess={loadRoles}
        role={selectedRole}
      />

      {/* Delete Confirmation */}
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
