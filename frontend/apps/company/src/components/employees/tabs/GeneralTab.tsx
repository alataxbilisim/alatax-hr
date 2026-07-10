import React from 'react';
import { BsKey, BsKeyFill, BsPerson, BsBuilding, BsEnvelope, BsCalendar } from 'react-icons/bs';

interface Employee {
  id: number;
  employee_code: string;
  position?: string;
  title?: string;
  status: string;
  hire_date?: string;
  personal_email?: string;
  personal_phone?: string;
  user?: {
    id: number;
    name: string;
    email: string;
  };
  department?: {
    id: number;
    name: string;
  };
  manager?: {
    id: number;
    user?: {
      name: string;
    };
  };
  subordinates?: Array<{
    id: number;
    user?: { name: string };
    position?: string;
  }>;
}

interface GeneralTabProps {
  employee: Employee;
  onCreatePortalAccess: () => void;
  onRevokePortalAccess: () => void;
}

const GeneralTab: React.FC<GeneralTabProps> = ({ employee, onCreatePortalAccess, onRevokePortalAccess }) => {
  const getStatusBadge = (status: string) => {
    const statusMap: Record<string, { label: string; className: string }> = {
      active: { label: 'Aktif', className: 'badge-success' },
      on_leave: { label: 'İzinli', className: 'badge-warning' },
      suspended: { label: 'Askıda', className: 'badge-danger' },
      terminated: { label: 'İşten Çıkmış', className: 'badge-secondary' },
    };
    const statusInfo = statusMap[status] || { label: status, className: 'badge-secondary' };
    return <span className={`badge ${statusInfo.className}`}>{statusInfo.label}</span>;
  };

  const formatDate = (date?: string) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('tr-TR', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  };

  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '1.5rem' }}>
      {/* Temel Bilgiler */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title"><BsPerson style={{ marginRight: '0.5rem' }} />Temel Bilgiler</h3>
        </div>
        <div className="card-body">
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Sicil No</span>
              <span className="badge badge-secondary">{employee.employee_code}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Ad Soyad</span>
              <span style={{ fontWeight: 500 }}>{employee.user?.name || '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Pozisyon</span>
              <span>{employee.position || '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Ünvan</span>
              <span>{employee.title || '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Durum</span>
              {getStatusBadge(employee.status)}
            </div>
          </div>
        </div>
      </div>

      {/* Departman ve Yönetim */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title"><BsBuilding style={{ marginRight: '0.5rem' }} />Departman & Yönetim</h3>
        </div>
        <div className="card-body">
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Departman</span>
              <span>{employee.department?.name || '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Yönetici</span>
              <span>{employee.manager?.user?.name || '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Ekip Üyesi Sayısı</span>
              <span>{employee.subordinates?.length || 0}</span>
            </div>
          </div>

          {employee.subordinates && employee.subordinates.length > 0 && (
            <div style={{ marginTop: '1rem', paddingTop: '1rem', borderTop: '1px solid var(--border-color)' }}>
              <div style={{ fontSize: '0.875rem', fontWeight: 500, marginBottom: '0.5rem' }}>Ekip Üyeleri</div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                {employee.subordinates.slice(0, 5).map((sub) => (
                  <div key={sub.id} style={{ fontSize: '0.875rem', display: 'flex', justifyContent: 'space-between' }}>
                    <span>{sub.user?.name || '-'}</span>
                    <span style={{ color: 'var(--text-tertiary)' }}>{sub.position || '-'}</span>
                  </div>
                ))}
                {employee.subordinates.length > 5 && (
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                    +{employee.subordinates.length - 5} daha
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* İletişim */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title"><BsEnvelope style={{ marginRight: '0.5rem' }} />İletişim</h3>
        </div>
        <div className="card-body">
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Email (Portal)</span>
              <span>{employee.user?.email || '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Kişisel Email</span>
              <span>{employee.personal_email || '-'}</span>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>Telefon</span>
              <span>{employee.personal_phone || '-'}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Portal Erişimi */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title">
            {employee.user ? <BsKeyFill style={{ marginRight: '0.5rem', color: 'var(--success)' }} /> : <BsKey style={{ marginRight: '0.5rem' }} />}
            Portal Erişimi
          </h3>
        </div>
        <div className="card-body">
          {employee.user ? (
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '1rem' }}>
                <span className="badge badge-success">Aktif</span>
                <span style={{ color: 'var(--text-secondary)' }}>{employee.user.email}</span>
              </div>
              <button className="btn btn-warning btn-sm" onClick={onRevokePortalAccess}>
                <BsKey /> Erişimi Kaldır
              </button>
            </div>
          ) : (
            <div>
              <p style={{ color: 'var(--text-tertiary)', marginBottom: '1rem' }}>
                Bu personelin portal erişimi bulunmuyor.
              </p>
              <button className="btn btn-success btn-sm" onClick={onCreatePortalAccess}>
                <BsKeyFill /> Portal Erişimi Ver
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Tarihler */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title"><BsCalendar style={{ marginRight: '0.5rem' }} />Tarihler</h3>
        </div>
        <div className="card-body">
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ color: 'var(--text-tertiary)' }}>İşe Giriş Tarihi</span>
              <span>{formatDate(employee.hire_date)}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default GeneralTab;

