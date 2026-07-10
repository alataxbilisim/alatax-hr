import React, { useState, useEffect } from 'react';
import { adminApi } from '../../services/api';
import toast from 'react-hot-toast';

interface Module {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  icon: string | null;
  is_core: boolean;
  is_active: boolean;
  price_monthly: number | null;
  price_yearly: number | null;
  sort_order: number;
  created_at: string;
}

const AdminModulesPage: React.FC = () => {
  const [modules, setModules] = useState<Module[]>([]);
  const [loading, setLoading] = useState(true);

  const loadModules = async () => {
    try {
      setLoading(true);
      const response = await adminApi.modules.list();
      setModules(response.data.data || []);
    } catch (error) {
      console.error('Modüller yüklenemedi:', error);
      toast.error('Modüller yüklenirken bir hata oluştu');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadModules();
  }, []);

  const getModuleIcon = (icon: string | null, name: string) => {
    if (icon) return <i className={`bi bi-${icon}`}></i>;
    
    // Default icons based on module name
    const icons: Record<string, string> = {
      'job-applications': 'briefcase',
      'document-management': 'file-earmark-text',
      'onboarding': 'person-check',
      'leave-management': 'calendar-check',
      'performance': 'graph-up',
      'training': 'mortarboard',
    };
    
    const iconName = icons[name.toLowerCase()] || 'box';
    return <i className={`bi bi-${iconName}`}></i>;
  };

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Modüller</h1>
          <p className="page-subtitle">
            Sistem modüllerini yönetin
          </p>
        </div>
      </div>

      {/* Modules Grid */}
      {loading ? (
        <div className="text-center py-5">
          <div className="spinner-border text-primary" role="status">
            <span className="visually-hidden">Yükleniyor...</span>
          </div>
        </div>
      ) : (
        <div className="row g-4">
          {modules.map((module) => (
            <div key={module.id} className="col-md-6 col-lg-4">
              <div className="card h-100 module-card">
                <div className="card-body">
                  <div className="d-flex align-items-start justify-content-between mb-3">
                    <div className="module-icon" style={{
                      width: '48px',
                      height: '48px',
                      borderRadius: '12px',
                      background: module.is_core ? 'var(--primary)' : 'var(--surface-tertiary)',
                      color: module.is_core ? 'white' : 'var(--text-secondary)',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      fontSize: '20px'
                    }}>
                      {getModuleIcon(module.icon, module.slug)}
                    </div>
                    <div className="d-flex gap-2">
                      {module.is_core && (
                        <span className="badge badge-primary">Core</span>
                      )}
                      <span className={`badge ${module.is_active ? 'badge-success' : 'badge-secondary'}`}>
                        {module.is_active ? 'Aktif' : 'Pasif'}
                      </span>
                    </div>
                  </div>

                  <h5 className="card-title mb-2">{module.name}</h5>
                  <p className="text-muted small mb-3">
                    {module.description || 'Açıklama bulunmuyor'}
                  </p>

                  {!module.is_core && (module.price_monthly || module.price_yearly) && (
                    <div className="d-flex gap-3 mb-3">
                      {module.price_monthly && (
                        <div>
                          <small className="text-muted">Aylık</small>
                          <div className="fw-semibold">{module.price_monthly.toLocaleString('tr-TR')} ₺</div>
                        </div>
                      )}
                      {module.price_yearly && (
                        <div>
                          <small className="text-muted">Yıllık</small>
                          <div className="fw-semibold">{module.price_yearly.toLocaleString('tr-TR')} ₺</div>
                        </div>
                      )}
                    </div>
                  )}

                  <div className="d-flex gap-2">
                    <code className="small" style={{ 
                      background: 'var(--surface-tertiary)',
                      padding: '4px 8px',
                      borderRadius: '4px'
                    }}>
                      {module.slug}
                    </code>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      <style>{`
        .module-card {
          transition: all 0.2s ease;
        }
        .module-card:hover {
          transform: translateY(-2px);
          box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
      `}</style>
    </div>
  );
};

export default AdminModulesPage;

