import React, { useState, useEffect, useCallback } from 'react';
import { rolesApi } from '../../services/api';
import toast from 'react-hot-toast';

interface Permission {
  group: string;
  permissions: string[];
}

interface Role {
  id: number;
  name: string;
  permissions: string[];
  users_count: number;
}

interface RoleFormData {
  name: string;
  permissions: string[];
}

const initialFormData: RoleFormData = {
  name: '',
  permissions: [],
};

// Korunan roller (düzenlenemez/silinemez)
const protectedRoles = ['admin', 'hr_manager', 'hr_specialist', 'manager', 'employee'];

// Rol adı çevirileri
const roleTranslations: Record<string, string> = {
  admin: 'Yönetici',
  hr_manager: 'İK Müdürü',
  hr_specialist: 'İK Uzmanı',
  manager: 'Departman Yöneticisi',
  employee: 'Çalışan',
};

// Yetki grubu çevirileri
const groupTranslations: Record<string, string> = {
  users: 'Kullanıcılar',
  roles: 'Roller',
  company: 'Firma',
  recruitment: 'İşe Alım',
  documents: 'Evraklar',
  onboarding: 'Onboarding',
  leaves: 'İzinler',
  performance: 'Performans',
  training: 'Eğitim',
  assets: 'Varlıklar',
  reports: 'Raporlar',
  settings: 'Ayarlar',
};

// Yetki çevirileri
const permissionTranslations: Record<string, string> = {
  view: 'Görüntüle',
  create: 'Oluştur',
  edit: 'Düzenle',
  delete: 'Sil',
  approve: 'Onayla',
  export: 'Dışa Aktar',
  manage: 'Yönet',
};

const RolesPage: React.FC = () => {
  const [roles, setRoles] = useState<Role[]>([]);
  const [permissions, setPermissions] = useState<Permission[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editingRole, setEditingRole] = useState<Role | null>(null);
  const [formData, setFormData] = useState<RoleFormData>(initialFormData);
  const [submitting, setSubmitting] = useState(false);
  const [expandedGroups, setExpandedGroups] = useState<string[]>([]);

  // Rolleri yükle
  const loadRoles = useCallback(async () => {
    try {
      setLoading(true);
      const response = await rolesApi.list();
      setRoles(response.data.data || []);
    } catch (error) {
      console.error('Roller yüklenemedi:', error);
      toast.error('Roller yüklenirken bir hata oluştu');
    } finally {
      setLoading(false);
    }
  }, []);

  // Yetkileri yükle
  const loadPermissions = async () => {
    try {
      const response = await rolesApi.permissions();
      setPermissions(response.data.data || []);
    } catch (error) {
      console.error('Yetkiler yüklenemedi:', error);
    }
  };

  useEffect(() => {
    loadRoles();
    loadPermissions();
  }, [loadRoles]);

  // Modal aç - Yeni rol
  const handleAddClick = () => {
    setEditingRole(null);
    setFormData(initialFormData);
    setExpandedGroups([]);
    setShowModal(true);
  };

  // Modal aç - Düzenle
  const handleEditClick = (role: Role) => {
    if (protectedRoles.includes(role.name)) {
      toast.error('Varsayılan roller düzenlenemez');
      return;
    }
    setEditingRole(role);
    setFormData({
      name: role.name || '',
      permissions: role.permissions || [],
    });
    // Seçili yetkilerin gruplarını genişlet
    const groups = [...new Set(role.permissions.map(p => p.split('.')[0]))];
    setExpandedGroups(groups);
    setShowModal(true);
  };

  // Form gönder
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (formData.permissions.length === 0) {
      toast.error('En az bir yetki seçmelisiniz');
      return;
    }

    setSubmitting(true);

    try {
      if (editingRole) {
        await rolesApi.update(editingRole.id, formData);
        toast.success('Rol güncellendi');
      } else {
        await rolesApi.create(formData);
        toast.success('Rol oluşturuldu');
      }
      setShowModal(false);
      loadRoles();
    } catch (error) {
      console.error('Rol kaydedilemedi:', error);
    } finally {
      setSubmitting(false);
    }
  };

  // Rol sil
  const handleDelete = async (role: Role) => {
    if (protectedRoles.includes(role.name)) {
      toast.error('Varsayılan roller silinemez');
      return;
    }

    if (!confirm(`"${role.name}" rolünü silmek istediğinize emin misiniz?`)) return;
    
    try {
      await rolesApi.delete(role.id);
      toast.success('Rol silindi');
      loadRoles();
    } catch (error) {
      console.error('Rol silinemedi:', error);
    }
  };

  // Yetki toggle
  const handlePermissionToggle = (permission: string) => {
    setFormData(prev => ({
      ...prev,
      permissions: prev.permissions.includes(permission)
        ? prev.permissions.filter(p => p !== permission)
        : [...prev.permissions, permission]
    }));
  };

  // Grup toggle
  const handleGroupToggle = (group: string) => {
    setExpandedGroups(prev => 
      prev.includes(group)
        ? prev.filter(g => g !== group)
        : [...prev, group]
    );
  };

  // Gruptaki tüm yetkileri seç/kaldır
  const handleSelectAllInGroup = (group: Permission) => {
    const allSelected = group.permissions.every(p => formData.permissions.includes(p));
    
    if (allSelected) {
      setFormData(prev => ({
        ...prev,
        permissions: prev.permissions.filter(p => !group.permissions.includes(p))
      }));
    } else {
      setFormData(prev => ({
        ...prev,
        permissions: [...new Set([...prev.permissions, ...group.permissions])]
      }));
    }
  };

  // Yetki adını çevir
  const translatePermission = (permission: string) => {
    const parts = permission.split('.');
    const action = parts[1] || '';
    return permissionTranslations[action] || action;
  };

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Roller ve Yetkiler</h1>
          <p className="page-subtitle">
            Kullanıcı rollerini ve yetkilerini yönetin
          </p>
        </div>
        <button className="btn btn-primary" onClick={handleAddClick}>
          <i className="bi bi-plus-lg me-2"></i>
          Yeni Rol
        </button>
      </div>

      {/* Roles Grid */}
      {loading ? (
        <div className="text-center py-5">
          <div className="spinner-border text-primary" role="status">
            <span className="visually-hidden">Yükleniyor...</span>
          </div>
        </div>
      ) : (
        <div className="row g-4">
          {roles.map((role) => {
            const isProtected = protectedRoles.includes(role.name);
            const displayName = roleTranslations[role.name] || role.name;
            
            return (
              <div key={role.id} className="col-md-6 col-lg-4">
                <div className="card h-100 role-card">
                  <div className="card-body">
                    <div className="d-flex align-items-start justify-content-between mb-3">
                      <div className="role-icon" style={{
                        width: '48px',
                        height: '48px',
                        borderRadius: '12px',
                        background: isProtected ? 'var(--primary)' : 'var(--surface-tertiary)',
                        color: isProtected ? 'white' : 'var(--text-secondary)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        fontSize: '20px'
                      }}>
                        <i className="bi bi-shield-check"></i>
                      </div>
                      {isProtected && (
                        <span className="badge badge-secondary">
                          <i className="bi bi-lock me-1"></i>
                          Korumalı
                        </span>
                      )}
                    </div>

                    <h5 className="card-title mb-1">{displayName}</h5>
                    <p className="text-muted small mb-3">
                      <code>{role.name}</code>
                    </p>

                    <div className="d-flex gap-3 mb-3">
                      <div>
                        <small className="text-muted">Yetkiler</small>
                        <div className="fw-semibold">{role.permissions.length}</div>
                      </div>
                      <div>
                        <small className="text-muted">Kullanıcılar</small>
                        <div className="fw-semibold">{role.users_count}</div>
                      </div>
                    </div>

                    <div className="d-flex flex-wrap gap-1 mb-3">
                      {role.permissions.slice(0, 4).map((perm) => (
                        <span key={perm} className="badge badge-secondary small">
                          {perm}
                        </span>
                      ))}
                      {role.permissions.length > 4 && (
                        <span className="badge badge-secondary small">
                          +{role.permissions.length - 4}
                        </span>
                      )}
                    </div>

                    {!isProtected && (
                      <div className="d-flex gap-2">
                        <button
                          className="btn btn-sm btn-outline-secondary flex-grow-1"
                          onClick={() => handleEditClick(role)}
                        >
                          <i className="bi bi-pencil me-1"></i>
                          Düzenle
                        </button>
                        <button
                          className="btn btn-sm btn-outline-danger"
                          onClick={() => handleDelete(role)}
                        >
                          <i className="bi bi-trash"></i>
                        </button>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Role Modal */}
      {showModal && (
        <div className="modal-backdrop show" onClick={() => setShowModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog modal-lg" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">
                    {editingRole ? 'Rol Düzenle' : 'Yeni Rol'}
                  </h5>
                  <button
                    type="button"
                    className="btn-close"
                    onClick={() => setShowModal(false)}
                  ></button>
                </div>
                <form onSubmit={handleSubmit}>
                  <div className="modal-body">
                    <div className="mb-4">
                      <label className="form-label">Rol Adı *</label>
                      <input
                        type="text"
                        className="form-control"
                        value={formData.name}
                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                        required
                        placeholder="ör: muhasebe_sorumlusu"
                      />
                      <small className="text-muted">
                        Rol adı küçük harfler ve alt çizgi içerebilir
                      </small>
                    </div>

                    <div>
                      <label className="form-label mb-3">
                        Yetkiler * ({formData.permissions.length} seçili)
                      </label>
                      <div className="permissions-list">
                        {permissions.map((group) => {
                          const isExpanded = expandedGroups.includes(group.group);
                          const selectedCount = group.permissions.filter(p => 
                            formData.permissions.includes(p)
                          ).length;
                          const allSelected = group.permissions.every(p => 
                            formData.permissions.includes(p)
                          );

                          return (
                            <div key={group.group} className="permission-group mb-2">
                              <div 
                                className="permission-group-header"
                                onClick={() => handleGroupToggle(group.group)}
                              >
                                <div className="d-flex align-items-center gap-2">
                                  <i className={`bi ${isExpanded ? 'bi-chevron-down' : 'bi-chevron-right'}`}></i>
                                  <span className="fw-semibold">
                                    {groupTranslations[group.group] || group.group}
                                  </span>
                                  <span className="badge badge-secondary">
                                    {selectedCount}/{group.permissions.length}
                                  </span>
                                </div>
                                <button
                                  type="button"
                                  className="btn btn-sm btn-link"
                                  onClick={(e) => {
                                    e.stopPropagation();
                                    handleSelectAllInGroup(group);
                                  }}
                                >
                                  {allSelected ? 'Tümünü Kaldır' : 'Tümünü Seç'}
                                </button>
                              </div>
                              {isExpanded && (
                                <div className="permission-group-body">
                                  <div className="row g-2">
                                    {group.permissions.map((perm) => (
                                      <div key={perm} className="col-md-6 col-lg-4">
                                        <div className="form-check">
                                          <input
                                            className="form-check-input"
                                            type="checkbox"
                                            id={`perm-${perm}`}
                                            checked={formData.permissions.includes(perm)}
                                            onChange={() => handlePermissionToggle(perm)}
                                          />
                                          <label 
                                            className="form-check-label" 
                                            htmlFor={`perm-${perm}`}
                                          >
                                            {translatePermission(perm)}
                                            <br />
                                            <small className="text-muted">{perm}</small>
                                          </label>
                                        </div>
                                      </div>
                                    ))}
                                  </div>
                                </div>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  </div>
                  <div className="modal-footer">
                    <button
                      type="button"
                      className="btn btn-secondary"
                      onClick={() => setShowModal(false)}
                    >
                      İptal
                    </button>
                    <button
                      type="submit"
                      className="btn btn-primary"
                      disabled={submitting}
                    >
                      {submitting ? (
                        <>
                          <span className="spinner-border spinner-border-sm me-2"></span>
                          Kaydediliyor...
                        </>
                      ) : (
                        <>
                          <i className="bi bi-check-lg me-2"></i>
                          Kaydet
                        </>
                      )}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      )}

      <style>{`
        .role-card {
          transition: all 0.2s ease;
        }
        .role-card:hover {
          transform: translateY(-2px);
          box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        .modal-backdrop.show {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.5);
          backdrop-filter: blur(4px);
          z-index: 1050;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .modal.show {
          position: relative;
          z-index: 1051;
        }
        .modal-dialog {
          margin: 0;
          max-height: 90vh;
        }
        .modal-content {
          background: var(--surface-primary);
          border: 1px solid var(--border-primary);
          border-radius: 12px;
          max-height: 90vh;
          overflow: hidden;
        }
        .modal-header {
          border-bottom: 1px solid var(--border-primary);
          padding: 1rem 1.5rem;
        }
        .modal-body {
          padding: 1.5rem;
          overflow-y: auto;
          max-height: 60vh;
        }
        .modal-footer {
          border-top: 1px solid var(--border-primary);
          padding: 1rem 1.5rem;
        }
        .permissions-list {
          max-height: 400px;
          overflow-y: auto;
        }
        .permission-group {
          border: 1px solid var(--border-primary);
          border-radius: 8px;
          overflow: hidden;
        }
        .permission-group-header {
          background: var(--surface-secondary);
          padding: 0.75rem 1rem;
          cursor: pointer;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }
        .permission-group-header:hover {
          background: var(--surface-tertiary);
        }
        .permission-group-body {
          padding: 1rem;
          background: var(--surface-primary);
        }
      `}</style>
    </div>
  );
};

export default RolesPage;

