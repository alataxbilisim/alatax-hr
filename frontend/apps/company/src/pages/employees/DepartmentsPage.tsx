import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  BsPlus,
  BsSearch,
  BsBuilding,
  BsPencil,
  BsTrash,
  BsPeople,
  BsArrowLeft,
  BsPersonBadge,
} from 'react-icons/bs';
import { departmentsApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { Modal, ConfirmDialog } from '../../components/ui';

interface Manager {
  id: number;
  employee_code?: string;
  user?: {
    name: string;
  };
}

interface Department {
  id: number;
  name: string;
  code?: string;
  description?: string;
  parent_id?: number;
  parent?: Department;
  manager_id?: number;
  manager?: Manager;
  is_active: boolean;
  employee_count?: number;
  children?: Department[];
}

interface DepartmentFormData {
  name: string;
  code: string;
  description: string;
  parent_id: number | null;
  manager_id: number | null;
  is_active: boolean;
}

const DepartmentsPage: React.FC = () => {
  const navigate = useNavigate();
  const [departments, setDepartments] = useState<Department[]>([]);
  const [allDepartments, setAllDepartments] = useState<Department[]>([]);
  const [managers, setManagers] = useState<Manager[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  
  // Modal durumları
  const [formModalOpen, setFormModalOpen] = useState(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [selectedDepartment, setSelectedDepartment] = useState<Department | null>(null);
  const [saving, setSaving] = useState(false);
  
  // Form durumu
  const [formData, setFormData] = useState<DepartmentFormData>({
    name: '',
    code: '',
    description: '',
    parent_id: null,
    manager_id: null,
    is_active: true,
  });

  useEffect(() => {
    loadDepartments();
    loadManagers();
  }, []);

  const loadDepartments = async () => {
    try {
      setLoading(true);
      const response = await departmentsApi.getAll({ with_counts: true });
      const data = response.data.data || [];
      setDepartments(data);
      setAllDepartments(data);
    } catch {
      toast.error('Departmanlar yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const loadManagers = async () => {
    try {
      const response = await departmentsApi.getManagers();
      setManagers(response.data.data || []);
    } catch (error) {
      console.error('Yöneticiler yüklenemedi:', error);
    }
  };

  const filteredDepartments = departments.filter(dept =>
    dept.name.toLowerCase().includes(search.toLowerCase()) ||
    dept.code?.toLowerCase().includes(search.toLowerCase())
  );

  const handleOpenForm = (department?: Department) => {
    if (department) {
      setSelectedDepartment(department);
      setFormData({
        name: department.name,
        code: department.code || '',
        description: department.description || '',
        parent_id: department.parent_id || null,
        manager_id: department.manager_id || null,
        is_active: department.is_active,
      });
    } else {
      setSelectedDepartment(null);
      setFormData({
        name: '',
        code: '',
        description: '',
        parent_id: null,
        manager_id: null,
        is_active: true,
      });
    }
    setFormModalOpen(true);
  };

  const generateCode = (name: string) => {
    return name
      .toUpperCase()
      .replace(/ğ/gi, 'G')
      .replace(/ü/gi, 'U')
      .replace(/ş/gi, 'S')
      .replace(/ı/gi, 'I')
      .replace(/ö/gi, 'O')
      .replace(/ç/gi, 'C')
      .replace(/[^A-Z0-9]+/g, '')
      .slice(0, 10);
  };

  const handleSave = async () => {
    if (!formData.name) {
      toast.error('Departman adı gereklidir');
      return;
    }

    try {
      setSaving(true);
      const payload = {
        ...formData,
        code: formData.code || generateCode(formData.name),
      };

      if (selectedDepartment) {
        await departmentsApi.update(selectedDepartment.id, payload);
        toast.success('Departman güncellendi');
      } else {
        await departmentsApi.create(payload);
        toast.success('Departman oluşturuldu');
      }

      setFormModalOpen(false);
      loadDepartments();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'İşlem başarısız'));
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!selectedDepartment) return;

    try {
      await departmentsApi.delete(selectedDepartment.id);
      toast.success('Departman silindi');
      setDeleteDialogOpen(false);
      setSelectedDepartment(null);
      loadDepartments();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'Silme işlemi başarısız'));
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <button
            className="btn btn-ghost btn-sm"
            onClick={() => navigate('/employees')}
            style={{ marginBottom: '0.5rem' }}
          >
            <BsArrowLeft /> Personel Listesi
          </button>
          <h1 className="page-title">Departman Yönetimi</h1>
          <p className="page-subtitle">
            {departments.length > 0 ? `${departments.length} departman kayıtlı` : 'Şirket departmanlarını yönetin'}
          </p>
        </div>
        <div className="page-header-actions">
          <button className="btn btn-primary" onClick={() => handleOpenForm()}>
            <BsPlus /> Yeni Departman
          </button>
        </div>
      </div>

      {/* Arama */}
      <div className="card mb-3">
        <div className="card-body">
          <div className="input-group">
            <span className="input-icon"><BsSearch /></span>
            <input
              type="text"
              className="form-control"
              placeholder="Departman ara (ad, kod...)"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
        </div>
      </div>

      {/* Tablo */}
      <div className="card">
        <div className="table-responsive">
          <table className="table">
            <thead>
              <tr>
                <th>Departman Adı</th>
                <th>Kod</th>
                <th>Üst Departman</th>
                <th>Yönetici</th>
                <th>Personel Sayısı</th>
                <th>Durum</th>
                <th className="text-end">İşlemler</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={7} className="text-center py-4">
                    <div className="spinner-border spinner-border-sm" role="status" />
                  </td>
                </tr>
              ) : filteredDepartments.length === 0 ? (
                <tr>
                  <td colSpan={7} className="text-center py-4">
                    <BsBuilding size={48} className="text-muted mb-2" style={{ display: 'block', margin: '0 auto' }} />
                    <p className="text-muted mb-2">Henüz departman tanımlanmamış</p>
                    <button className="btn btn-primary btn-sm" onClick={() => handleOpenForm()}>
                      <BsPlus /> İlk Departmanı Ekle
                    </button>
                  </td>
                </tr>
              ) : (
                filteredDepartments.map((department) => (
                  <tr key={department.id}>
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                        <div
                          style={{
                            width: 36,
                            height: 36,
                            borderRadius: 'var(--radius-md)',
                            background: 'var(--primary-soft)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            color: 'var(--primary)',
                          }}
                        >
                          <BsBuilding />
                        </div>
                        <div>
                          <strong>{department.name}</strong>
                          {department.description && (
                            <p style={{ margin: 0, fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                              {department.description.slice(0, 50)}
                              {department.description.length > 50 ? '...' : ''}
                            </p>
                          )}
                        </div>
                      </div>
                    </td>
                    <td>
                      <span className="badge badge-secondary">{department.code || '-'}</span>
                    </td>
                    <td>{department.parent?.name || '-'}</td>
                    <td>
                      {department.manager?.user?.name ? (
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                          <BsPersonBadge style={{ color: 'var(--primary)' }} />
                          {department.manager.user.name}
                        </div>
                      ) : (
                        <span className="text-muted">-</span>
                      )}
                    </td>
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                        <BsPeople style={{ color: 'var(--text-tertiary)' }} />
                        {department.employee_count || 0}
                      </div>
                    </td>
                    <td>
                      {department.is_active ? (
                        <span className="badge badge-success">Aktif</span>
                      ) : (
                        <span className="badge badge-secondary">Pasif</span>
                      )}
                    </td>
                    <td>
                      <div className="btn-group btn-group-sm">
                        <button
                          className="btn btn-primary"
                          onClick={() => handleOpenForm(department)}
                          title="Düzenle"
                        >
                          <BsPencil />
                        </button>
                        <button
                          className="btn btn-danger"
                          onClick={() => {
                            setSelectedDepartment(department);
                            setDeleteDialogOpen(true);
                          }}
                          title="Sil"
                          disabled={!!(department.employee_count && department.employee_count > 0)}
                        >
                          <BsTrash />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Form Modal */}
      <Modal
        isOpen={formModalOpen}
        onClose={() => setFormModalOpen(false)}
        title={selectedDepartment ? 'Departman Düzenle' : 'Yeni Departman'}
        size="md"
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <div className="row">
            <div className="col-md-8">
              <div className="form-group">
                <label className="form-label">Departman Adı *</label>
                <input
                  type="text"
                  className="form-control"
                  value={formData.name}
                  onChange={(e) => {
                    setFormData({
                      ...formData,
                      name: e.target.value,
                      code: formData.code || generateCode(e.target.value),
                    });
                  }}
                  placeholder="Örn: İnsan Kaynakları"
                />
              </div>
            </div>
            <div className="col-md-4">
              <div className="form-group">
                <label className="form-label">Kod</label>
                <input
                  type="text"
                  className="form-control"
                  value={formData.code}
                  onChange={(e) => setFormData({ ...formData, code: e.target.value.toUpperCase() })}
                  placeholder="IK"
                  maxLength={10}
                />
              </div>
            </div>
          </div>

          <div className="form-group">
            <label className="form-label">Açıklama</label>
            <textarea
              className="form-control"
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              placeholder="Departman hakkında kısa açıklama"
              rows={2}
            />
          </div>

          <div className="row">
            <div className="col-md-6">
              <div className="form-group">
                <label className="form-label">Üst Departman</label>
                <select
                  className="form-select"
                  value={formData.parent_id || ''}
                  onChange={(e) => setFormData({ ...formData, parent_id: e.target.value ? Number(e.target.value) : null })}
                >
                  <option value="">Yok (Kök Departman)</option>
                  {allDepartments
                    .filter(d => d.id !== selectedDepartment?.id)
                    .map((dept) => (
                      <option key={dept.id} value={dept.id}>{dept.name}</option>
                    ))
                  }
                </select>
              </div>
            </div>
            <div className="col-md-6">
              <div className="form-group">
                <label className="form-label">Departman Yöneticisi</label>
                <select
                  className="form-select"
                  value={formData.manager_id || ''}
                  onChange={(e) => setFormData({ ...formData, manager_id: e.target.value ? Number(e.target.value) : null })}
                >
                  <option value="">Seçiniz...</option>
                  {managers.map((manager) => (
                    <option key={manager.id} value={manager.id}>
                      {manager.user?.name || manager.employee_code}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          <div className="form-check">
            <input
              type="checkbox"
              className="form-check-input"
              id="is_active"
              checked={formData.is_active}
              onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
            />
            <label className="form-check-label" htmlFor="is_active">Aktif</label>
          </div>

          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1rem' }}>
            <button className="btn btn-secondary" onClick={() => setFormModalOpen(false)}>
              İptal
            </button>
            <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
              {saving ? 'Kaydediliyor...' : selectedDepartment ? 'Güncelle' : 'Oluştur'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Silme Onay Dialog */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        onConfirm={handleDelete}
        title="Departmanı Sil"
        message={`"${selectedDepartment?.name}" departmanını silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.`}
        confirmText="Sil"
        variant="danger"
      />
    </div>
  );
};

export default DepartmentsPage;

