import React from 'react';
import { BsCalendarCheck, BsClock } from 'react-icons/bs';

export interface LeaveBalance {
  id: number;
  leave_type: {
    id: number;
    name: string;
    color?: string;
  };
  entitled_days: number;
  used_days: number;
  remaining_days: number;
}

export interface LeaveRequest {
  id: number;
  leave_type: {
    id: number;
    name: string;
    color?: string;
  };
  start_date: string;
  end_date: string;
  total_days: number;
  status: string;
  reason?: string;
  created_at: string;
}

export interface EmployeeLeaveData {
  balances: LeaveBalance[];
  requests: LeaveRequest[];
}

interface LeavesTabProps {
  balances: LeaveBalance[];
  requests: LeaveRequest[];
}

const LeavesTab: React.FC<LeavesTabProps> = ({ balances, requests }) => {
  const formatDate = (date?: string) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('tr-TR');
  };

  const getStatusBadge = (status: string) => {
    const map: Record<string, { label: string; className: string }> = {
      pending: { label: 'Beklemede', className: 'badge-warning' },
      approved: { label: 'Onaylandı', className: 'badge-success' },
      rejected: { label: 'Reddedildi', className: 'badge-danger' },
      cancelled: { label: 'İptal', className: 'badge-secondary' },
    };
    const info = map[status] || { label: status, className: 'badge-secondary' };
    return <span className={`badge ${info.className}`}>{info.label}</span>;
  };

  return (
    <div>
      {/* Bakiye Kartları */}
      <h4 style={{ marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <BsCalendarCheck style={{ color: 'var(--primary)' }} />
        İzin Bakiyeleri
      </h4>
      
      {balances.length === 0 ? (
        <div className="card" style={{ marginBottom: '1.5rem' }}>
          <div className="card-body" style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
            İzin bakiyesi bulunmuyor
          </div>
        </div>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
          {balances.map((balance) => (
            <div
              key={balance.id}
              className="card"
              style={{
                borderLeft: `4px solid ${balance.leave_type.color || 'var(--primary)'}`,
              }}
            >
              <div className="card-body" style={{ padding: '1rem' }}>
                <div style={{ fontWeight: 500, marginBottom: '0.75rem' }}>{balance.leave_type.name}</div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                  <span style={{ color: 'var(--text-tertiary)', fontSize: '0.875rem' }}>Hak Edilen</span>
                  <span style={{ fontWeight: 600 }}>{balance.entitled_days} gün</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                  <span style={{ color: 'var(--text-tertiary)', fontSize: '0.875rem' }}>Kullanılan</span>
                  <span style={{ color: 'var(--warning)' }}>{balance.used_days} gün</span>
                </div>
                <div
                  style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    paddingTop: '0.5rem',
                    borderTop: '1px solid var(--border-color)',
                  }}
                >
                  <span style={{ color: 'var(--text-tertiary)', fontSize: '0.875rem' }}>Kalan</span>
                  <span style={{ fontWeight: 700, color: 'var(--success)' }}>{balance.remaining_days} gün</span>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* İzin Talepleri */}
      <h4 style={{ marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
        <BsClock style={{ color: 'var(--primary)' }} />
        İzin Talepleri
      </h4>

      {requests.length === 0 ? (
        <div className="card">
          <div className="card-body" style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
            Henüz izin talebi bulunmuyor
          </div>
        </div>
      ) : (
        <div className="card">
          <div className="table-responsive">
            <table className="table">
              <thead>
                <tr>
                  <th>İzin Türü</th>
                  <th>Tarih Aralığı</th>
                  <th>Süre</th>
                  <th>Durum</th>
                  <th>Talep Tarihi</th>
                </tr>
              </thead>
              <tbody>
                {requests.map((request) => (
                  <tr key={request.id}>
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                        <div
                          style={{
                            width: 8,
                            height: 8,
                            borderRadius: '50%',
                            background: request.leave_type.color || 'var(--primary)',
                          }}
                        />
                        {request.leave_type.name}
                      </div>
                    </td>
                    <td>
                      {formatDate(request.start_date)} - {formatDate(request.end_date)}
                    </td>
                    <td>{request.total_days} gün</td>
                    <td>{getStatusBadge(request.status)}</td>
                    <td>{formatDate(request.created_at)}</td>
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

export default LeavesTab;

