import React, { useEffect, useState } from 'react';
import { leavesApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { ConfirmDialog } from '../ui';
import AccrualPolicyForm from './AccrualPolicyForm';
import { BsPlus, BsPencil, BsTrash, BsGear, BsToggleOn, BsToggleOff } from 'react-icons/bs';

interface TenureRule {
  years: number;
  days: number;
}

interface AccrualPolicy {
  id: number;
  name: string;
  description?: string;
  leave_type_id: number;
  leave_type?: {
    id: number;
    name: string;
    code?: string;
  };
  accrual_type: 'annual' | 'monthly' | 'per_pay_period' | 'hourly' | 'custom';
  accrual_rate: number;
  max_balance?: number;
  min_balance: number;
  tenure_rules?: TenureRule[];
  allow_carryover: boolean;
  max_carryover_days?: number;
  carryover_expiry_date?: string;
  allow_encashment: boolean;
  max_encashment_days?: number;
  encashment_rate: number;
  waiting_period_days: number;
  prorate_first_year: boolean;
  is_active: boolean;
}

const AccrualPoliciesTab: React.FC = () => {
  const [policies, setPolicies] = useState<AccrualPolicy[]>([]);
  const [loading, setLoading] = useState(true);

  // Form modal
  const [formOpen, setFormOpen] = useState(false);
  const [selectedPolicy, setSelectedPolicy] = useState<AccrualPolicy | undefined>();

  // Delete dialog
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [policyToDelete, setPolicyToDelete] = useState<AccrualPolicy | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  useEffect(() => {
    loadPolicies();
  }, []);

  const loadPolicies = async () => {
    try {
      setLoading(true);
      const response = await leavesApi.accrualPolicies.list();
      setPolicies(response.data.data || []);
    } catch {
      toast.error('Hakediş politikaları yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = () => {
    setSelectedPolicy(undefined);
    setFormOpen(true);
  };

  const handleEdit = (policy: AccrualPolicy) => {
    setSelectedPolicy(policy);
    setFormOpen(true);
  };

  const handleDelete = (policy: AccrualPolicy) => {
    setPolicyToDelete(policy);
    setDeleteDialogOpen(true);
  };

  const confirmDelete = async () => {
    if (!policyToDelete) return;

    setDeleteLoading(true);
    try {
      await leavesApi.accrualPolicies.delete(policyToDelete.id);
      toast.success('Politika silindi');
      setDeleteDialogOpen(false);
      setPolicyToDelete(null);
      loadPolicies();
    } catch {
      toast.error('Politika silinemedi');
    } finally {
      setDeleteLoading(false);
    }
  };

  const getAccrualTypeBadge = (type: string) => {
    const typeMap: Record<string, { label: string; class: string }> = {
      annual: { label: 'Yıllık', class: 'badge-primary' },
      monthly: { label: 'Aylık', class: 'badge-success' },
      per_pay_period: { label: 'Dönemsel', class: 'badge-warning' },
      hourly: { label: 'Saatlik', class: 'badge-info' },
      custom: { label: 'Özel', class: 'badge-secondary' },
    };
    const t = typeMap[type] || { label: type, class: 'badge-secondary' };
    return <span className={`badge ${t.class}`}>{t.label}</span>;
  };

  return (
    <div>
      {/* Header */}
      <div className="card mb-3">
        <div className="card-body" style={{ padding: '0.75rem 1rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <BsGear size={16} style={{ color: 'var(--text-tertiary)' }} />
              <span style={{ fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
                İzin hakediş kurallarını ve devir politikalarını tanımlayın
              </span>
            </div>
            <button className="btn btn-primary" onClick={handleCreate}>
              <BsPlus size={18} />
              Yeni Politika
            </button>
          </div>
        </div>
      </div>

      {/* Content */}
      {loading ? (
        <div className="page-loading">
          <div className="loading-spinner" />
        </div>
      ) : policies.length === 0 ? (
        <div className="card">
          <div className="card-body empty-state">
            <BsGear size={48} style={{ color: 'var(--text-muted)' }} />
            <h3 className="empty-state-title mt-3">Hakediş Politikası Bulunamadı</h3>
            <p className="empty-state-text">
              Henüz tanımlı hakediş politikası yok. İlk politikayı oluşturun.
            </p>
            <button className="btn btn-primary mt-2" onClick={handleCreate}>
              <BsPlus /> Politika Oluştur
            </button>
          </div>
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          {policies.map((policy) => (
            <div key={policy.id} className="card">
              <div className="card-body" style={{ padding: '1rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '1rem' }}>
                  {/* Left side - Info */}
                  <div style={{ flex: 1 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '0.5rem' }}>
                      <h3 style={{ margin: 0, fontSize: '1rem', fontWeight: 600 }}>{policy.name}</h3>
                      {getAccrualTypeBadge(policy.accrual_type)}
                      <span className={`badge ${policy.is_active ? 'badge-success' : 'badge-secondary'}`}>
                        {policy.is_active ? <BsToggleOn /> : <BsToggleOff />}
                        <span style={{ marginLeft: '0.25rem' }}>{policy.is_active ? 'Aktif' : 'Pasif'}</span>
                      </span>
                    </div>
                    {policy.description && (
                      <p style={{ margin: '0 0 0.75rem 0', fontSize: '0.8125rem', color: 'var(--text-tertiary)' }}>
                        {policy.description}
                      </p>
                    )}
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.75rem' }}>
                      <span style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>İzin Türü:</span>
                      <span className="badge badge-primary">{policy.leave_type?.name || '-'}</span>
                    </div>

                    {/* Stats */}
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '1.5rem', fontSize: '0.8125rem' }}>
                      <div>
                        <span style={{ color: 'var(--text-tertiary)' }}>Hakediş Oranı:</span>{' '}
                        <strong>{policy.accrual_rate} gün</strong>
                      </div>
                      {policy.max_balance && (
                        <div>
                          <span style={{ color: 'var(--text-tertiary)' }}>Maks. Bakiye:</span>{' '}
                          <strong>{policy.max_balance} gün</strong>
                        </div>
                      )}
                      <div>
                        <span style={{ color: 'var(--text-tertiary)' }}>Devir:</span>{' '}
                        <span className={`badge ${policy.allow_carryover ? 'badge-success' : 'badge-secondary'}`}>
                          {policy.allow_carryover ? 'Evet' : 'Hayır'}
                        </span>
                        {policy.allow_carryover && policy.max_carryover_days && (
                          <span style={{ marginLeft: '0.25rem' }}>(Maks: {policy.max_carryover_days} gün)</span>
                        )}
                      </div>
                      {policy.waiting_period_days > 0 && (
                        <div>
                          <span style={{ color: 'var(--text-tertiary)' }}>Bekleme Süresi:</span>{' '}
                          <strong>{policy.waiting_period_days} gün</strong>
                        </div>
                      )}
                    </div>

                    {/* Tenure Rules */}
                    {policy.tenure_rules && policy.tenure_rules.length > 0 && (
                      <div style={{ marginTop: '0.75rem' }}>
                        <span style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginBottom: '0.25rem', display: 'block' }}>
                          Kıdem Kuralları:
                        </span>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem' }}>
                          {policy.tenure_rules.map((rule, index) => (
                            <span key={index} className="badge badge-info">
                              {rule.years}+ yıl → {rule.days} gün
                            </span>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Right side - Actions */}
                  <div style={{ display: 'flex', gap: '0.25rem' }}>
                    <button
                      className="btn btn-ghost btn-icon btn-sm"
                      onClick={() => handleEdit(policy)}
                      title="Düzenle"
                    >
                      <BsPencil />
                    </button>
                    <button
                      className="btn btn-ghost btn-icon btn-sm"
                      onClick={() => handleDelete(policy)}
                      title="Sil"
                      style={{ color: 'var(--danger)' }}
                    >
                      <BsTrash />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Form Modal */}
      <AccrualPolicyForm
        isOpen={formOpen}
        onClose={() => setFormOpen(false)}
        onSuccess={loadPolicies}
        policy={selectedPolicy}
      />

      {/* Delete Dialog */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        onConfirm={confirmDelete}
        title="Politikayı Sil"
        message={`"${policyToDelete?.name}" politikasını silmek istediğinize emin misiniz?`}
        confirmText="Sil"
        variant="danger"
        loading={deleteLoading}
      />
    </div>
  );
};

export default AccrualPoliciesTab;

