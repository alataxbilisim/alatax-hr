import React from 'react';
import { useNavigate } from 'react-router-dom';
import { BsLaptop, BsArrowRight, BsCalendar } from 'react-icons/bs';

export interface EmployeeAssetItem {
  id: number;
  name: string;
  asset_code?: string;
  brand?: string;
  model?: string;
  serial_number?: string;
  status: string;
  category?: {
    id: number;
    name: string;
  };
}

export interface EmployeeAssetAssignment {
  id: number;
  asset: EmployeeAssetItem;
  assigned_date: string;
  returned_date?: string;
  notes?: string;
}

export interface EmployeeAssetData {
  active: EmployeeAssetAssignment[];
  history: EmployeeAssetAssignment[];
}

interface AssetsTabProps {
  active: EmployeeAssetAssignment[];
  history: EmployeeAssetAssignment[];
}

const AssetsTab: React.FC<AssetsTabProps> = ({ active, history }) => {
  const navigate = useNavigate();

  const formatDate = (date?: string) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('tr-TR');
  };

  const getStatusBadge = (status: string) => {
    const map: Record<string, { label: string; className: string }> = {
      available: { label: 'Müsait', className: 'badge-success' },
      assigned: { label: 'Zimmetli', className: 'badge-info' },
      maintenance: { label: 'Bakımda', className: 'badge-warning' },
      retired: { label: 'Emekli', className: 'badge-secondary' },
    };
    const info = map[status] || { label: status, className: 'badge-secondary' };
    return <span className={`badge ${info.className}`}>{info.label}</span>;
  };

  return (
    <div>
      {/* Aktif Zimmetler */}
      <h4 style={{ marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <BsLaptop style={{ color: 'var(--primary)' }} />
        Aktif Zimmetler
      </h4>

      {active.length === 0 ? (
        <div className="card" style={{ marginBottom: '1.5rem' }}>
          <div className="card-body" style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
            Aktif zimmet bulunmuyor
          </div>
        </div>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
          {active.map((assignment) => (
            <div key={assignment.id} className="card" style={{ cursor: 'pointer' }} onClick={() => navigate(`/assets/${assignment.asset.id}`)}>
              <div className="card-body">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '0.75rem' }}>
                  <div>
                    <div style={{ fontWeight: 500, marginBottom: '0.25rem' }}>{assignment.asset.name}</div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                      {assignment.asset.brand} {assignment.asset.model}
                    </div>
                  </div>
                  {getStatusBadge(assignment.asset.status)}
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', fontSize: '0.875rem' }}>
                  {assignment.asset.asset_code && (
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                      <span style={{ color: 'var(--text-tertiary)' }}>Demirbaş No</span>
                      <span className="badge badge-secondary">{assignment.asset.asset_code}</span>
                    </div>
                  )}
                  {assignment.asset.category && (
                    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                      <span style={{ color: 'var(--text-tertiary)' }}>Kategori</span>
                      <span>{assignment.asset.category.name}</span>
                    </div>
                  )}
                  <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                    <span style={{ color: 'var(--text-tertiary)' }}>Zimmet Tarihi</span>
                    <span>{formatDate(assignment.assigned_date)}</span>
                  </div>
                </div>

                {assignment.notes && (
                  <div style={{ marginTop: '0.75rem', paddingTop: '0.75rem', borderTop: '1px solid var(--border-color)', fontSize: '0.75rem', color: 'var(--text-secondary)' }}>
                    {assignment.notes}
                  </div>
                )}

                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '0.75rem' }}>
                  <span style={{ fontSize: '0.75rem', color: 'var(--primary)', display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                    Detay <BsArrowRight />
                  </span>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Zimmet Geçmişi */}
      <h4 style={{ marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <BsCalendar style={{ color: 'var(--text-tertiary)' }} />
        Zimmet Geçmişi
      </h4>

      {history.length === 0 ? (
        <div className="card">
          <div className="card-body" style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
            Zimmet geçmişi bulunmuyor
          </div>
        </div>
      ) : (
        <div className="card">
          <div className="table-responsive">
            <table className="table">
              <thead>
                <tr>
                  <th>Varlık</th>
                  <th>Demirbaş No</th>
                  <th>Zimmet Tarihi</th>
                  <th>İade Tarihi</th>
                </tr>
              </thead>
              <tbody>
                {history.map((assignment) => (
                  <tr key={assignment.id}>
                    <td>
                      <div style={{ fontWeight: 500 }}>{assignment.asset.name}</div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                        {assignment.asset.brand} {assignment.asset.model}
                      </div>
                    </td>
                    <td>
                      {assignment.asset.asset_code && (
                        <span className="badge badge-secondary">{assignment.asset.asset_code}</span>
                      )}
                    </td>
                    <td>{formatDate(assignment.assigned_date)}</td>
                    <td>{formatDate(assignment.returned_date)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
};

export default AssetsTab;

