import React, { useState, useEffect, useMemo } from 'react';
import { Modal } from './ui';
import { rolesApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsChevronDown, BsChevronRight, BsCheckAll, BsXCircle } from 'react-icons/bs';
import {
  MODULE_LABELS,
  PAGE_LABELS,
  ACTION_LABELS,
  PAGE_ACTIONS,
  ActionType,
} from '@shared/constants/permissions';

interface Permission {
  id: number;
  name: string;
  guard_name: string;
}

interface Role {
  id?: number;
  name: string;
  permissions: string[];
}

interface RoleFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  role?: {
    id?: number;
    name?: string;
    permissions?: Array<string | { id: number; name: string }>;
  };
}

// Hiyerarşik yetki yapısı
interface PermissionNode {
  module: string;
  moduleLabel: string;
  pages: {
    page: string;
    pageLabel: string;
    actions: {
      action: ActionType;
      actionLabel: string;
      permissionKey: string;
    }[];
  }[];
}

const RoleForm: React.FC<RoleFormProps> = ({
  isOpen,
  onClose,
  onSuccess,
  role,
}) => {
  const isEditing = !!role?.id;
  const [loading, setLoading] = useState(false);
  const [permissions, setPermissions] = useState<Permission[]>([]);
  const [formData, setFormData] = useState<Role>({
    name: '',
    permissions: [],
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [expandedModules, setExpandedModules] = useState<Set<string>>(new Set());

  useEffect(() => {
    if (isOpen) {
      loadPermissions();
      if (role) {
        const permNames = Array.isArray(role.permissions)
          ? role.permissions.map((p) => (typeof p === 'string' ? p : p.name))
          : [];
        setFormData({
          name: role.name || '',
          permissions: permNames,
        });
        // Seçili modülleri otomatik aç
        const selectedModules = new Set<string>();
        permNames.forEach(p => {
          const parts = p.split('.');
          if (parts.length >= 2) {
            selectedModules.add(parts[0]);
          }
        });
        setExpandedModules(selectedModules);
      } else {
        setFormData({
          name: '',
          permissions: [],
        });
        setExpandedModules(new Set());
      }
      setErrors({});
    }
  }, [isOpen, role]);

  const loadPermissions = async () => {
    try {
      const response = await rolesApi.permissions();
      setPermissions(response.data.data || []);
    } catch {
      // Permissions might not be accessible
    }
  };

  // Mevcut yetkileri hiyerarşik yapıya dönüştür
  const hierarchicalPermissions = useMemo((): PermissionNode[] => {
    const permissionSet = new Set(permissions.map(p => p.name));
    const nodes: PermissionNode[] = [];

    Object.entries(PAGE_ACTIONS).forEach(([module, pages]) => {
      const moduleLabel = MODULE_LABELS[module as keyof typeof MODULE_LABELS] || module;
      
      const pageNodes = Object.entries(pages).map(([page, actions]) => {
        const pageLabel = PAGE_LABELS[module]?.[page] || page;
        
        const actionNodes = actions
          .filter(action => {
            const permKey = `${module}.${page}.${action}`;
            return permissionSet.has(permKey);
          })
          .map(action => ({
            action: action as ActionType,
            actionLabel: ACTION_LABELS[action as ActionType] || action,
            permissionKey: `${module}.${page}.${action}`,
          }));

        return {
          page,
          pageLabel,
          actions: actionNodes,
        };
      }).filter(p => p.actions.length > 0);

      if (pageNodes.length > 0) {
        nodes.push({
          module,
          moduleLabel,
          pages: pageNodes,
        });
      }
    });

    return nodes;
  }, [permissions]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  };

  // Tek yetki toggle
  const handlePermissionChange = (permName: string) => {
    setFormData((prev) => ({
      ...prev,
      permissions: prev.permissions.includes(permName)
        ? prev.permissions.filter((p) => p !== permName)
        : [...prev.permissions, permName],
    }));
  };

  // Modül toggle (tüm alt yetkiler)
  const handleModuleToggle = (moduleNode: PermissionNode) => {
    const allPerms = moduleNode.pages.flatMap(p => p.actions.map(a => a.permissionKey));
    const allSelected = allPerms.every((p) => formData.permissions.includes(p));

    setFormData((prev) => ({
      ...prev,
      permissions: allSelected
        ? prev.permissions.filter((p) => !allPerms.includes(p))
        : [...new Set([...prev.permissions, ...allPerms])],
    }));
  };

  // Sayfa toggle (sayfadaki tüm aksiyonlar)
  const handlePageToggle = (actions: { permissionKey: string }[]) => {
    const pagePerms = actions.map(a => a.permissionKey);
    const allSelected = pagePerms.every((p) => formData.permissions.includes(p));

    setFormData((prev) => ({
      ...prev,
      permissions: allSelected
        ? prev.permissions.filter((p) => !pagePerms.includes(p))
        : [...new Set([...prev.permissions, ...pagePerms])],
    }));
  };

  // Modül expand/collapse
  const toggleModuleExpand = (module: string) => {
    setExpandedModules(prev => {
      const newSet = new Set(prev);
      if (newSet.has(module)) {
        newSet.delete(module);
      } else {
        newSet.add(module);
      }
      return newSet;
    });
  };

  // Tümünü genişlet/daralt
  const expandAll = () => {
    const allModules = hierarchicalPermissions.map(m => m.module);
    setExpandedModules(new Set(allModules));
  };

  const collapseAll = () => {
    setExpandedModules(new Set());
  };

  // Tümünü seç/kaldır
  const selectAll = () => {
    const allPerms = hierarchicalPermissions.flatMap(m => 
      m.pages.flatMap(p => p.actions.map(a => a.permissionKey))
    );
    setFormData(prev => ({ ...prev, permissions: allPerms }));
  };

  const deselectAll = () => {
    setFormData(prev => ({ ...prev, permissions: [] }));
  };

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.name.trim()) {
      newErrors.name = 'Rol adı gerekli';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validate()) return;

    setLoading(true);
    try {
      const payload = {
        name: formData.name,
        permissions: formData.permissions,
      };

      if (isEditing && role?.id) {
        await rolesApi.update(role.id, payload);
        toast.success('Rol güncellendi');
      } else {
        await rolesApi.create(payload);
        toast.success('Rol oluşturuldu');
      }

      onSuccess();
      onClose();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { errors?: Record<string, string[]> } } };
      if (err.response?.data?.errors) {
        const backendErrors: Record<string, string> = {};
        Object.entries(err.response.data.errors).forEach(([key, msgs]) => {
          backendErrors[key] = msgs[0];
        });
        setErrors(backendErrors);
      }
    } finally {
      setLoading(false);
    }
  };

  // Seçili yetki sayısı hesaplama
  const getModuleStats = (moduleNode: PermissionNode) => {
    const allPerms = moduleNode.pages.flatMap(p => p.actions.map(a => a.permissionKey));
    const selected = allPerms.filter(p => formData.permissions.includes(p)).length;
    return { selected, total: allPerms.length };
  };

  const getPageStats = (actions: { permissionKey: string }[]) => {
    const selected = actions.filter(a => formData.permissions.includes(a.permissionKey)).length;
    return { selected, total: actions.length };
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? 'Rol Düzenle' : 'Yeni Rol'}
      size="xxl"
      footer={
        <>
          <button className="btn btn-secondary" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button className="btn btn-primary" onClick={handleSubmit} disabled={loading}>
            {loading ? 'Kaydediliyor...' : isEditing ? 'Güncelle' : 'Oluştur'}
          </button>
        </>
      }
    >
      <form onSubmit={handleSubmit}>
        {/* Role Name */}
        <div className="form-group" style={{ marginBottom: '1.5rem' }}>
          <label className="form-label">Rol Adı *</label>
          <input
            type="text"
            name="name"
            className={`form-control ${errors.name ? 'is-invalid' : ''}`}
            value={formData.name}
            onChange={handleChange}
            placeholder="Örn: Editör, Muhasebeci, vb."
            style={{ maxWidth: '400px' }}
          />
          {errors.name && <div className="form-error">{errors.name}</div>}
        </div>

        {/* Permissions Header */}
        <div className="form-group">
          <div style={{ 
            display: 'flex', 
            justifyContent: 'space-between', 
            alignItems: 'center', 
            marginBottom: '1rem',
            flexWrap: 'wrap',
            gap: '0.75rem'
          }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
              <label className="form-label" style={{ margin: 0, fontSize: '1rem' }}>Yetkiler</label>
              <span style={{ 
                fontSize: '0.85rem', 
                color: 'var(--primary)',
                fontWeight: 600,
                background: 'var(--primary-soft)',
                padding: '0.25rem 0.75rem',
                borderRadius: '20px',
              }}>
                {formData.permissions.length} seçili
              </span>
            </div>
            <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
              <button type="button" className="btn btn-sm btn-secondary" onClick={expandAll}>
                Tümünü Aç
              </button>
              <button type="button" className="btn btn-sm btn-secondary" onClick={collapseAll}>
                Tümünü Kapat
              </button>
              <button type="button" className="btn btn-sm btn-primary" onClick={selectAll}>
                <BsCheckAll style={{ marginRight: '0.25rem' }} /> Tümünü Seç
              </button>
              <button type="button" className="btn btn-sm btn-secondary" onClick={deselectAll}>
                <BsXCircle style={{ marginRight: '0.25rem' }} /> Temizle
              </button>
            </div>
          </div>
          
          {/* Permission Grid */}
          {hierarchicalPermissions.length > 0 ? (
            <div style={{ 
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
              gap: '1rem',
              maxHeight: '55vh',
              overflowY: 'auto',
              padding: '0.5rem',
            }}>
              {hierarchicalPermissions.map((moduleNode) => {
                const isModuleExpanded = expandedModules.has(moduleNode.module);
                const moduleStats = getModuleStats(moduleNode);
                const isModuleFullySelected = moduleStats.selected === moduleStats.total;
                const isModulePartiallySelected = moduleStats.selected > 0 && !isModuleFullySelected;

                return (
                  <div
                    key={moduleNode.module}
                    style={{
                      background: 'var(--bg-secondary)',
                      border: `2px solid ${isModuleFullySelected ? 'var(--primary)' : isModulePartiallySelected ? 'rgba(20, 184, 166, 0.4)' : 'var(--border-primary)'}`,
                      borderRadius: '12px',
                      overflow: 'hidden',
                      transition: 'border-color 0.2s ease',
                    }}
                  >
                    {/* Module Header */}
                    <div
                      style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '0.75rem',
                        padding: '1rem',
                        background: isModuleFullySelected 
                          ? 'rgba(20, 184, 166, 0.15)' 
                          : isModulePartiallySelected 
                            ? 'rgba(20, 184, 166, 0.08)' 
                            : 'var(--bg-primary)',
                        borderBottom: isModuleExpanded ? '1px solid var(--border-primary)' : 'none',
                        cursor: 'pointer',
                      }}
                      onClick={() => toggleModuleExpand(moduleNode.module)}
                    >
                      <div
                        style={{
                          width: '28px',
                          height: '28px',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          background: 'var(--bg-tertiary)',
                          borderRadius: '6px',
                          color: 'var(--text-secondary)',
                          flexShrink: 0,
                        }}
                      >
                        {isModuleExpanded ? <BsChevronDown /> : <BsChevronRight />}
                      </div>
                      
                      <input
                        type="checkbox"
                        checked={isModuleFullySelected}
                        ref={(el) => {
                          if (el) el.indeterminate = isModulePartiallySelected;
                        }}
                        onChange={(e) => {
                          e.stopPropagation();
                          handleModuleToggle(moduleNode);
                        }}
                        onClick={(e) => e.stopPropagation()}
                        style={{ 
                          width: '20px', 
                          height: '20px', 
                          accentColor: 'var(--primary)',
                          cursor: 'pointer',
                          flexShrink: 0,
                        }}
                      />
                      
                      <span style={{ 
                        fontWeight: 600, 
                        fontSize: '0.95rem', 
                        color: 'var(--text-primary)',
                        flex: 1,
                      }}>
                        {moduleNode.moduleLabel}
                      </span>
                      
                      <span style={{ 
                        fontSize: '0.8rem', 
                        color: isModuleFullySelected ? 'var(--primary)' : 'var(--text-tertiary)',
                        fontWeight: isModuleFullySelected ? 600 : 500,
                        background: isModuleFullySelected ? 'rgba(20, 184, 166, 0.2)' : 'var(--bg-tertiary)',
                        padding: '0.2rem 0.6rem',
                        borderRadius: '12px',
                        flexShrink: 0,
                      }}>
                        {moduleStats.selected}/{moduleStats.total}
                      </span>
                    </div>

                    {/* Pages */}
                    {isModuleExpanded && (
                      <div style={{ padding: '0.75rem' }}>
                        {moduleNode.pages.map((pageNode) => {
                          const pageStats = getPageStats(pageNode.actions);
                          const isPageFullySelected = pageStats.selected === pageStats.total;
                          const isPagePartiallySelected = pageStats.selected > 0 && !isPageFullySelected;

                          return (
                            <div
                              key={`${moduleNode.module}.${pageNode.page}`}
                              style={{
                                marginBottom: '0.75rem',
                                padding: '0.75rem',
                                borderRadius: '8px',
                                background: isPageFullySelected 
                                  ? 'rgba(20, 184, 166, 0.1)' 
                                  : 'var(--bg-primary)',
                                border: `1px solid ${isPageFullySelected ? 'var(--primary)' : isPagePartiallySelected ? 'rgba(20, 184, 166, 0.3)' : 'var(--border-primary)'}`,
                              }}
                            >
                              {/* Page Header */}
                              <div
                                style={{
                                  display: 'flex',
                                  alignItems: 'center',
                                  gap: '0.6rem',
                                  marginBottom: '0.6rem',
                                }}
                              >
                                <input
                                  type="checkbox"
                                  checked={isPageFullySelected}
                                  ref={(el) => {
                                    if (el) el.indeterminate = isPagePartiallySelected;
                                  }}
                                  onChange={() => handlePageToggle(pageNode.actions)}
                                  style={{ 
                                    width: '16px', 
                                    height: '16px', 
                                    accentColor: 'var(--primary)',
                                    cursor: 'pointer',
                                  }}
                                />

                                <span style={{
                                  fontSize: '0.85rem',
                                  color: 'var(--text-primary)',
                                  fontWeight: 500,
                                  flex: 1,
                                }}>
                                  {pageNode.pageLabel}
                                </span>

                                <span style={{ 
                                  fontSize: '0.7rem', 
                                  color: isPageFullySelected ? 'var(--primary)' : 'var(--text-tertiary)',
                                }}>
                                  {pageStats.selected}/{pageStats.total}
                                </span>
                              </div>

                              {/* Actions */}
                              <div style={{ 
                                display: 'flex', 
                                flexWrap: 'wrap', 
                                gap: '0.4rem',
                              }}>
                                {pageNode.actions.map((actionNode) => {
                                  const isSelected = formData.permissions.includes(actionNode.permissionKey);

                                  return (
                                    <label
                                      key={actionNode.permissionKey}
                                      style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        padding: '0.3rem 0.6rem',
                                        background: isSelected ? 'var(--primary)' : 'var(--bg-secondary)',
                                        border: `1px solid ${isSelected ? 'var(--primary)' : 'var(--border-primary)'}`,
                                        borderRadius: '6px',
                                        cursor: 'pointer',
                                        fontSize: '0.75rem',
                                        fontWeight: 500,
                                        color: isSelected ? 'white' : 'var(--text-secondary)',
                                        transition: 'all 0.15s ease',
                                        userSelect: 'none',
                                      }}
                                    >
                                      <input
                                        type="checkbox"
                                        checked={isSelected}
                                        onChange={() => handlePermissionChange(actionNode.permissionKey)}
                                        style={{ display: 'none' }}
                                      />
                                      {actionNode.actionLabel}
                                    </label>
                                  );
                                })}
                              </div>
                            </div>
                          );
                        })}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          ) : (
            <div style={{ 
              padding: '3rem', 
              textAlign: 'center', 
              color: 'var(--text-tertiary)', 
              fontSize: '0.9rem',
              background: 'var(--bg-secondary)',
              borderRadius: '12px',
            }}>
              <div style={{ marginBottom: '0.5rem' }}>Yetki bulunamadı veya yükleniyor...</div>
              <div style={{ fontSize: '0.8rem' }}>Lütfen bekleyin veya sayfayı yenileyin.</div>
            </div>
          )}
        </div>
      </form>
    </Modal>
  );
};

export default RoleForm;
