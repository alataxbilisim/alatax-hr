import React, { useEffect, useState, useCallback, useMemo } from 'react';
import { leavesApi, usersApi } from '@shared/services/api';
import { Select } from '@shared/components';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import { Modal } from '../ui';
import { BsPencil, BsSearch, BsCalendar3 } from 'react-icons/bs';

interface LeaveBalance {
  id: number;
  user_id: number;
  leave_type_id: number;
  year: number;
  total_days: number;
  used_days: number;
  pending_days: number;
  carried_over: number;
  accrued: number;
  expired: number;
  user?: {
    id: number;
    name: string;
    email: string;
  };
  leave_type?: {
    id: number;
    name: string;
    code?: string;
  };
}

interface User {
  id: number;
  name: string;
  email: string;
}

const LeaveBalancesTab: React.FC = () => {
  const { t } = useTranslation('common');
  const [balances, setBalances] = useState<LeaveBalance[]>([]);
  const [loading, setLoading] = useState(true);
  const [users, setUsers] = useState<User[]>([]);
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [selectedUserId, setSelectedUserId] = useState<number | ''>('');
  const [searchTerm, setSearchTerm] = useState('');

  // Edit modal
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [editingBalance, setEditingBalance] = useState<LeaveBalance | null>(null);
  const [editForm, setEditForm] = useState({ total_days: 0, carried_over: 0, reason: '' });
  const [saving, setSaving] = useState(false);

  const loadUsers = useCallback(async () => {
    try {
      const response = await usersApi.list({ per_page: 1000 });
      const data = response.data.data;
      setUsers(Array.isArray(data) ? data : data?.data || []);
    } catch {
      console.error('Kullanıcılar yüklenemedi');
    }
  }, []);

  const loadBalances = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, unknown> = { year: selectedYear };
      if (selectedUserId) params.user_id = selectedUserId;
      
      const response = await leavesApi.balance.list(params);
      const data = response.data.data;
      setBalances(Array.isArray(data) ? data : data?.data || []);
    } catch {
      toast.error('Bakiyeler yüklenemedi');
    } finally {
      setLoading(false);
    }
  }, [selectedYear, selectedUserId]);

  useEffect(() => {
    loadUsers();
  }, [loadUsers]);

  useEffect(() => {
    loadBalances();
  }, [loadBalances]);

  

  

  const handleEdit = (balance: LeaveBalance) => {
    setEditingBalance(balance);
    setEditForm({
      total_days: balance.total_days,
      carried_over: balance.carried_over || 0,
      reason: '',
    });
    setEditModalOpen(true);
  };

  const handleSave = async () => {
    if (!editingBalance) return;
    if (editForm.reason.trim().length < 3) {
      toast.error(t('leaves.balanceReasonRequired'));
      return;
    }

    setSaving(true);
    try {
      await leavesApi.balance.update(editingBalance.id, {
        total_days: editForm.total_days,
        carried_over: editForm.carried_over,
        reason: editForm.reason.trim(),
      });
      toast.success(t('leaves.balanceUpdated'));
      setEditModalOpen(false);
      loadBalances();
    } catch {
      toast.error(t('leaves.balanceUpdateFailed'));
    } finally {
      setSaving(false);
    }
  };

  const getAvailableDays = (balance: LeaveBalance) => {
    return balance.total_days - balance.used_days - balance.pending_days;
  };

  const filteredBalances = balances.filter((balance) => {
    if (!searchTerm) return true;
    const userName = balance.user?.name?.toLowerCase() || '';
    const leaveTypeName = balance.leave_type?.name?.toLowerCase() || '';
    return userName.includes(searchTerm.toLowerCase()) || leaveTypeName.includes(searchTerm.toLowerCase());
  });

  // Group balances by user
  const groupedBalances = filteredBalances.reduce((acc, balance) => {
    const userId = balance.user_id;
    if (!acc[userId]) {
      acc[userId] = {
        user: balance.user,
        balances: [],
      };
    }
    acc[userId].balances.push(balance);
    return acc;
  }, {} as Record<number, { user?: User; balances: LeaveBalance[] }>);

  const years = useMemo(
    () => Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 2 + i),
    []
  );

  const yearOptions = useMemo(
    () => years.map((year) => ({ value: String(year), label: String(year) })),
    [years]
  );

  return (
    <div>
      {/* Filters */}
      <div className="card mb-3">
        <div className="card-body" style={{ padding: '0.75rem 1rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '1rem', flexWrap: 'wrap' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <BsCalendar3 size={16} style={{ color: 'var(--text-tertiary)' }} />
              <div style={{ minWidth: 100 }}>
                <Select
                  value={String(selectedYear)}
                  onChange={(v) => setSelectedYear(Number(v))}
                  options={yearOptions}
                  aria-label="Yıl filtresi"
                />
              </div>
            </div>

            <div style={{ minWidth: 200 }}>
              <Select
                value={selectedUserId === '' ? '' : String(selectedUserId)}
                onChange={(v) => setSelectedUserId(v ? Number(v) : '')}
                options={users.map((user) => ({
                  value: String(user.id),
                  label: user.name,
                }))}
                allowEmpty
                emptyLabel="Tüm Personeller"
                placeholder="Tüm Personeller"
                aria-label="Personel filtresi"
              />
            </div>

            <div style={{ position: 'relative', flex: 1, maxWidth: '300px' }}>
              <BsSearch
                size={14}
                style={{
                  position: 'absolute',
                  left: '0.75rem',
                  top: '50%',
                  transform: 'translateY(-50%)',
                  color: 'var(--text-tertiary)',
                }}
              />
              <input
                type="text"
                className="form-control"
                placeholder="Ara..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                style={{ paddingLeft: '2rem' }}
              />
            </div>
          </div>
        </div>
      </div>

      {/* Content */}
      {loading ? (
        <div className="page-loading">
          <div className="loading-spinner" />
        </div>
      ) : Object.keys(groupedBalances).length === 0 ? (
        <div className="card">
          <div className="card-body empty-state">
            <BsCalendar3 size={48} style={{ color: 'var(--text-muted)' }} />
            <h3 className="empty-state-title mt-3">Bakiye Bulunamadı</h3>
            <p className="empty-state-text">
              Seçili kriterlere uygun izin bakiyesi bulunamadı.
            </p>
          </div>
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          {Object.values(groupedBalances).map(({ user, balances: userBalances }) => (
            <div key={user?.id} className="card">
              <div className="card-header" style={{ padding: '0.75rem 1rem', borderBottom: '1px solid var(--border-color)' }}>
                <h3 style={{ margin: 0, fontSize: '0.9375rem', fontWeight: 600 }}>
                  {user?.name || 'Bilinmeyen Kullanıcı'}
                </h3>
                <span style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{user?.email}</span>
              </div>
              <div className="card-body" style={{ padding: '0' }}>
                <div className="table-container">
                  <table className="table" style={{ marginBottom: 0 }}>
                    <thead>
                      <tr>
                        <th>İzin Türü</th>
                        <th style={{ textAlign: 'center' }}>Toplam</th>
                        <th style={{ textAlign: 'center' }}>Kullanılan</th>
                        <th style={{ textAlign: 'center' }}>Bekleyen</th>
                        <th style={{ textAlign: 'center' }}>Kalan</th>
                        <th style={{ textAlign: 'center' }}>Devir</th>
                        <th style={{ textAlign: 'right' }}>İşlem</th>
                      </tr>
                    </thead>
                    <tbody>
                      {userBalances.map((balance) => (
                        <tr key={balance.id}>
                          <td>
                            <span style={{ fontWeight: 500 }}>{balance.leave_type?.name}</span>
                            {balance.leave_type?.code && (
                              <span className="badge badge-secondary ms-2">{balance.leave_type.code}</span>
                            )}
                          </td>
                          <td style={{ textAlign: 'center' }}>
                            <span className="badge badge-primary">{balance.total_days}</span>
                          </td>
                          <td style={{ textAlign: 'center' }}>
                            <span className="badge badge-danger">{balance.used_days}</span>
                          </td>
                          <td style={{ textAlign: 'center' }}>
                            <span className="badge badge-warning">{balance.pending_days}</span>
                          </td>
                          <td style={{ textAlign: 'center' }}>
                            <span className={`badge ${getAvailableDays(balance) > 0 ? 'badge-success' : 'badge-secondary'}`}>
                              {getAvailableDays(balance)}
                            </span>
                          </td>
                          <td style={{ textAlign: 'center' }}>
                            <span className="badge badge-info">{balance.carried_over || 0}</span>
                          </td>
                          <td style={{ textAlign: 'right' }}>
                            <button
                              className="btn btn-ghost btn-icon btn-sm"
                              onClick={() => handleEdit(balance)}
                              title="Düzenle"
                            >
                              <BsPencil />
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Edit Modal */}
      <Modal
        isOpen={editModalOpen}
        onClose={() => setEditModalOpen(false)}
        title={t('leaves.editBalance')}
        size="sm"
        footer={
          <>
            <button className="btn btn-secondary" onClick={() => setEditModalOpen(false)} disabled={saving}>
              {t('cancel')}
            </button>
            <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
              {saving ? t('loading') : t('save')}
            </button>
          </>
        }
      >
        {editingBalance && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
            <div>
              <label className="form-label text-muted">{t('leaves.employee')}</label>
              <p style={{ fontWeight: 500, margin: 0 }}>{editingBalance.user?.name}</p>
            </div>
            <div>
              <label className="form-label text-muted">{t('leaves.leaveType')}</label>
              <p style={{ fontWeight: 500, margin: 0 }}>{editingBalance.leave_type?.name}</p>
            </div>
            <div className="form-group">
              <label className="form-label">{t('leaves.totalDays')}</label>
              <input
                type="number"
                className="form-control"
                value={editForm.total_days}
                onChange={(e) => setEditForm({ ...editForm, total_days: Number(e.target.value) })}
                min={0}
              />
            </div>
            <div className="form-group">
              <label className="form-label">{t('leaves.carriedOver')}</label>
              <input
                type="number"
                className="form-control"
                value={editForm.carried_over}
                onChange={(e) => setEditForm({ ...editForm, carried_over: Number(e.target.value) })}
                min={0}
              />
            </div>
            <div className="form-group">
              <label className="form-label">{t('leaves.balanceReason')}</label>
              <textarea
                className="form-control"
                value={editForm.reason}
                onChange={(e) => setEditForm({ ...editForm, reason: e.target.value })}
                rows={3}
                required
              />
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
};

export default LeaveBalancesTab;

