import React, { useEffect, useState } from 'react';
import { adminApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import {
  BsPlus,
  BsPencil,
  BsPuzzle,
  BsToggleOn,
  BsToggleOff,
  BsArrowClockwise,
  BsTrash,
} from 'react-icons/bs';
import { ModuleForm, ConfirmDialog } from '../components';

interface Module {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  icon: string | null;
  is_active: boolean;
  is_core: boolean;
  price_monthly: number;
  price_yearly: number;
  sort_order: number;
  companies_count: number;
}

const ModulesPage: React.FC = () => {
  const [modules, setModules] = useState<Module[]>([]);
  const [loading, setLoading] = useState(true);

  // Modal states
  const [showForm, setShowForm] = useState(false);
  const [editingModule, setEditingModule] = useState<Module | null>(null);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [deletingModule, setDeletingModule] = useState<Module | null>(null);
  const [deleting, setDeleting] = useState(false);

  useEffect(() => {
    loadModules();
  }, []);

  const loadModules = async () => {
    try {
      setLoading(true);
      const response = await adminApi.modules.list();
      setModules(response.data.data || []);
    } catch {
      toast.error('Modüller yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const handleEdit = (module: Module) => {
    setEditingModule(module);
    setShowForm(true);
  };

  const handleDelete = (module: Module) => {
    setDeletingModule(module);
    setShowDeleteConfirm(true);
  };

  const confirmDelete = async () => {
    if (!deletingModule) return;
    setDeleting(true);
    try {
      await adminApi.modules.delete(deletingModule.id);
      toast.success('Modül silindi');
      loadModules();
    } catch {
      toast.error('Modül silinemedi');
    } finally {
      setDeleting(false);
      setShowDeleteConfirm(false);
      setDeletingModule(null);
    }
  };

  const handleToggleActive = async (module: Module) => {
    try {
      await adminApi.modules.update(module.id, { is_active: !module.is_active });
      toast.success(`Modül ${module.is_active ? 'devre dışı bırakıldı' : 'aktifleştirildi'}`);
      loadModules();
    } catch {
      toast.error('Durum güncellenemedi');
    }
  };

  const formatCurrency = (amount: number | null | undefined) => {
    if (!amount || amount === 0) return 'Ücretsiz';
    return new Intl.NumberFormat('tr-TR', {
      style: 'currency',
      currency: 'TRY',
    }).format(amount);
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Modüller</h1>
          <p className="page-subtitle">Sistem modüllerini yönetin</p>
        </div>
        <div className="page-header-actions">
          <button
            className="btn btn-secondary"
            onClick={loadModules}
            disabled={loading}
          >
            <BsArrowClockwise className={loading ? 'animate-spin' : ''} />
          </button>
          <button
            className="btn btn-primary"
            onClick={() => {
              setEditingModule(null);
              setShowForm(true);
            }}
          >
            <BsPlus /> Yeni Modül
          </button>
        </div>
      </div>

      {loading ? (
        <div className="page-loading">
          <div className="loading-spinner"></div>
          <p>Yükleniyor...</p>
        </div>
      ) : modules.length > 0 ? (
        <div className="card">
          <div className="card-body p-0">
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Modül</th>
                    <th>Açıklama</th>
                    <th>Fiyat</th>
                    <th>Kullanan Firma</th>
                    <th>Tip</th>
                    <th>Durum</th>
                    <th>İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  {modules.map((module) => (
                    <tr key={module.id}>
                      <td>
                        <div className="d-flex align-items-center gap-2">
                          <div
                            className="stat-icon primary"
                            style={{ width: 36, height: 36, fontSize: '1rem' }}
                          >
                            <BsPuzzle />
                          </div>
                          <div>
                            <div className="fw-semibold">{module.name}</div>
                            <div className="text-muted small">{module.slug}</div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span className="text-muted">
                          {module.description
                            ? module.description.length > 50
                              ? module.description.substring(0, 50) + '...'
                              : module.description
                            : '-'}
                        </span>
                      </td>
                      <td>
                        <div>
                          <div>{formatCurrency(module.price_monthly)}/ay</div>
                          <div className="text-muted small">{formatCurrency(module.price_yearly)}/yıl</div>
                        </div>
                      </td>
                      <td>
                        <span className="fw-semibold">{module.companies_count}</span>
                      </td>
                      <td>
                        {module.is_core ? (
                          <span className="status-badge active">Çekirdek</span>
                        ) : (
                          <span className="status-badge trial">Eklenti</span>
                        )}
                      </td>
                      <td>
                        <button
                          className="btn btn-ghost p-0"
                          onClick={() => handleToggleActive(module)}
                          disabled={module.is_core}
                          title={module.is_core ? 'Çekirdek modüller devre dışı bırakılamaz' : ''}
                        >
                          {module.is_active ? (
                            <BsToggleOn size={24} className="text-success" />
                          ) : (
                            <BsToggleOff size={24} className="text-muted" />
                          )}
                        </button>
                      </td>
                      <td>
                        <div className="btn-group">
                          <button
                            className="btn btn-sm btn-ghost"
                            title="Düzenle"
                            onClick={() => handleEdit(module)}
                          >
                            <BsPencil />
                          </button>
                          {!module.is_core && (
                            <button
                              className="btn btn-sm btn-ghost text-danger"
                              title="Sil"
                              onClick={() => handleDelete(module)}
                            >
                              <BsTrash />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      ) : (
        <div className="card">
          <div className="card-body empty-state">
            <BsPuzzle size={48} />
            <h3>Henüz modül tanımlanmamış</h3>
            <p>Sistemde kullanılacak modülleri oluşturun</p>
            <button
              className="btn btn-primary"
              onClick={() => {
                setEditingModule(null);
                setShowForm(true);
              }}
            >
              <BsPlus /> Yeni Modül Oluştur
            </button>
          </div>
        </div>
      )}

      {/* Modals */}
      <ModuleForm
        isOpen={showForm}
        onClose={() => {
          setShowForm(false);
          setEditingModule(null);
        }}
        onSuccess={loadModules}
        module={editingModule}
      />

      <ConfirmDialog
        isOpen={showDeleteConfirm}
        onClose={() => {
          setShowDeleteConfirm(false);
          setDeletingModule(null);
        }}
        onConfirm={confirmDelete}
        title="Modül Sil"
        message={`"${deletingModule?.name}" modülünü silmek istediğinize emin misiniz? Bu modülü kullanan paketler etkilenebilir.`}
        confirmText="Sil"
        type="danger"
        loading={deleting}
      />
    </div>
  );
};

export default ModulesPage;
