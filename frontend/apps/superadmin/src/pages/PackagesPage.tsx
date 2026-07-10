import React, { useEffect, useState } from 'react';
import { adminApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import {
  BsPlus,
  BsPencil,
  BsTrash,
  BsCheckCircle,
  BsCopy,
  BsBox,
  BsArrowClockwise,
} from 'react-icons/bs';
import { PackageForm, ConfirmDialog } from '../components';

interface LicensePackage {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  base_price: number;
  user_limit: number;
  location_limit: number;
  employee_limit: number;
  storage_limit_gb: number;
  duration_months: number | null;
  is_active: boolean;
  modules: Array<{
    id: number;
    name: string;
    pivot: {
      is_included: boolean;
      additional_price: number | null;
    };
  }>;
}

const PackagesPage: React.FC = () => {
  const [packages, setPackages] = useState<LicensePackage[]>([]);
  const [loading, setLoading] = useState(true);
  
  // Modal states
  const [showForm, setShowForm] = useState(false);
  const [editingPackage, setEditingPackage] = useState<LicensePackage | null>(null);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [deletingPackage, setDeletingPackage] = useState<LicensePackage | null>(null);
  const [deleting, setDeleting] = useState(false);

  useEffect(() => {
    loadPackages();
  }, []);

  const loadPackages = async () => {
    try {
      setLoading(true);
      const response = await adminApi.licensePackages.list();
      setPackages(response.data.data || []);
    } catch {
      toast.error('Paketler yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const handleEdit = (pkg: LicensePackage) => {
    setEditingPackage(pkg);
    setShowForm(true);
  };

  const handleDuplicate = async (pkg: LicensePackage) => {
    try {
      await adminApi.licensePackages.duplicate(pkg.id);
      toast.success('Paket kopyalandı');
      loadPackages();
    } catch {
      toast.error('Paket kopyalanamadı');
    }
  };

  const handleDelete = (pkg: LicensePackage) => {
    setDeletingPackage(pkg);
    setShowDeleteConfirm(true);
  };

  const confirmDelete = async () => {
    if (!deletingPackage) return;
    setDeleting(true);
    try {
      await adminApi.licensePackages.delete(deletingPackage.id);
      toast.success('Paket silindi');
      loadPackages();
    } catch {
      toast.error('Paket silinemedi');
    } finally {
      setDeleting(false);
      setShowDeleteConfirm(false);
      setDeletingPackage(null);
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('tr-TR', {
      style: 'currency',
      currency: 'TRY',
    }).format(amount);
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Lisans Paketleri</h1>
          <p className="page-subtitle">Satış paketlerini tanımlayın ve yönetin</p>
        </div>
        <div className="page-header-actions">
          <button
            className="btn btn-secondary"
            onClick={loadPackages}
            disabled={loading}
          >
            <BsArrowClockwise className={loading ? 'animate-spin' : ''} />
          </button>
          <button
            className="btn btn-primary"
            onClick={() => {
              setEditingPackage(null);
              setShowForm(true);
            }}
          >
            <BsPlus /> Yeni Paket
          </button>
        </div>
      </div>

      {loading ? (
        <div className="page-loading">
          <div className="loading-spinner"></div>
          <p>Yükleniyor...</p>
        </div>
      ) : packages.length > 0 ? (
        <div className="row">
          {packages.map((pkg) => (
            <div key={pkg.id} className="col-lg-4 col-md-6 mb-4">
              <div className={`package-card ${!pkg.is_active ? 'opacity-50' : ''}`}>
                <div className="d-flex justify-content-between align-items-start mb-3">
                  <div>
                    <h3 className="package-name">{pkg.name}</h3>
                    {!pkg.is_active && (
                      <span className="status-badge inactive">Pasif</span>
                    )}
                  </div>
                  <div className="btn-group">
                    <button
                      className="btn btn-sm btn-ghost"
                      title="Düzenle"
                      onClick={() => handleEdit(pkg)}
                    >
                      <BsPencil />
                    </button>
                    <button
                      className="btn btn-sm btn-ghost"
                      title="Kopyala"
                      onClick={() => handleDuplicate(pkg)}
                    >
                      <BsCopy />
                    </button>
                    <button
                      className="btn btn-sm btn-ghost text-danger"
                      title="Sil"
                      onClick={() => handleDelete(pkg)}
                    >
                      <BsTrash />
                    </button>
                  </div>
                </div>

                <div className="package-price">
                  {formatCurrency(pkg.base_price)}
                  <span>
                    /{pkg.duration_months ? `${pkg.duration_months} ay` : 'süresiz'}
                  </span>
                </div>

                {pkg.description && (
                  <p className="text-muted small mt-2">{pkg.description}</p>
                )}

                <div className="package-features">
                  <div className="package-feature">
                    <BsCheckCircle />
                    <span>{pkg.user_limit || '∞'} Kullanıcı</span>
                  </div>
                  <div className="package-feature">
                    <BsCheckCircle />
                    <span>{pkg.location_limit || '∞'} Şube</span>
                  </div>
                  <div className="package-feature">
                    <BsCheckCircle />
                    <span>{pkg.employee_limit || '∞'} Personel</span>
                  </div>
                  <div className="package-feature">
                    <BsCheckCircle />
                    <span>{pkg.storage_limit_gb || '∞'} GB Depolama</span>
                  </div>

                  {pkg.modules && pkg.modules.length > 0 && (
                    <>
                      <hr className="my-2" />
                      <div className="small text-muted mb-2">Dahil Modüller:</div>
                      {pkg.modules
                        .filter((m) => m.pivot.is_included)
                        .slice(0, 4)
                        .map((module) => (
                          <div key={module.id} className="package-feature">
                            <BsCheckCircle />
                            <span>{module.name}</span>
                          </div>
                        ))}
                      {pkg.modules.filter((m) => m.pivot.is_included).length > 4 && (
                        <div className="package-feature text-muted">
                          <span>
                            +{pkg.modules.filter((m) => m.pivot.is_included).length - 4} modül daha
                          </span>
                        </div>
                      )}
                    </>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="card">
          <div className="card-body empty-state">
            <BsBox size={48} />
            <h3>Henüz paket tanımlanmamış</h3>
            <p>İlk lisans paketinizi oluşturarak başlayın</p>
            <button
              className="btn btn-primary"
              onClick={() => {
                setEditingPackage(null);
                setShowForm(true);
              }}
            >
              <BsPlus /> Yeni Paket Oluştur
            </button>
          </div>
        </div>
      )}

      {/* Modals */}
      <PackageForm
        isOpen={showForm}
        onClose={() => {
          setShowForm(false);
          setEditingPackage(null);
        }}
        onSuccess={loadPackages}
        packageData={editingPackage}
      />

      <ConfirmDialog
        isOpen={showDeleteConfirm}
        onClose={() => {
          setShowDeleteConfirm(false);
          setDeletingPackage(null);
        }}
        onConfirm={confirmDelete}
        title="Paket Sil"
        message={`"${deletingPackage?.name}" paketini silmek istediğinize emin misiniz? Bu paket kullanan firmalar etkilenebilir.`}
        confirmText="Sil"
        type="danger"
        loading={deleting}
      />
    </div>
  );
};

export default PackagesPage;
