import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { assetsApi, lookupsApi, type LookupItem } from '@shared/services/api';
import toast from 'react-hot-toast';
import { ConfirmDialog } from '../../components/ui';
import AssignmentForm from '../../components/assets/AssignmentForm';
import {
  BsArrowLeft,
  BsLaptop,
  BsPersonPlus,
  BsArrowReturnLeft,
  BsCurrencyDollar,
} from 'react-icons/bs';

interface Asset {
  id: number;
  name: string;
  description?: string;
  category?: { id: number; name: string };
  asset_code?: string;
  serial_number?: string;
  brand?: string;
  model?: string;
  purchase_date?: string;
  purchase_price?: number;
  warranty_end_date?: string;
  status: string;
  condition: string;
  current_assignment?: {
    user: { id: number; name: string };
    assigned_date: string;
    notes?: string;
  };
  assignments_history?: Array<{
    id: number;
    user: { id: number; name: string };
    assigned_date: string;
    returned_date?: string;
    notes?: string;
  }>;
}

const statusFallbackColors: Record<string, string> = {
  available: 'var(--success)',
  assigned: 'var(--info)',
  maintenance: 'var(--warning)',
  disposed: 'var(--text-tertiary)',
};

const AssetDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [asset, setAsset] = useState<Asset | null>(null);
  const [loading, setLoading] = useState(true);

  // Assignment state
  const [assignmentFormOpen, setAssignmentFormOpen] = useState(false);
  const [returnDialogOpen, setReturnDialogOpen] = useState(false);
  const [statusOptions, setStatusOptions] = useState<LookupItem[]>([]);
  const [conditionOptions, setConditionOptions] = useState<LookupItem[]>([]);

  const loadAsset = useCallback(async () => {
    try {
      setLoading(true);
      const response = await assetsApi.items.get(Number(id));
      setAsset(response.data.data);
    } catch {
      toast.error('Varlık yüklenemedi');
      navigate('/assets');
    } finally {
      setLoading(false);
    }
  }, [id, navigate]);

  const loadLookups = useCallback(async () => {
    try {
      const [statusRes, conditionRes] = await Promise.all([
        lookupsApi.forType('asset_status'),
        lookupsApi.forType('asset_condition'),
      ]);
      setStatusOptions(statusRes.data.data ?? []);
      setConditionOptions(conditionRes.data.data ?? []);
    } catch {
      console.error('Varlık lookup listeleri yüklenemedi');
    }
  }, []);

  useEffect(() => {
    loadLookups();
  }, [loadLookups]);

  useEffect(() => {
    if (id) {
      loadAsset();
    }
  }, [id, loadAsset]);

  

  const statusLookup = asset
    ? statusOptions.find((o) => o.value === asset.status)
    : undefined;
  const statusLabel = statusLookup?.label || asset?.status || '';
  const statusColor =
    statusLookup?.color ||
    (asset ? statusFallbackColors[asset.status] : undefined) ||
    'var(--text-tertiary)';
  const conditionLabel =
    conditionOptions.find((o) => o.value === asset?.condition)?.label ||
    asset?.condition ||
    '';

  const handleAssign = async (data: { user_id: number; notes?: string; assigned_date?: string }) => {
    try {
      await assetsApi.items.assign(Number(id), data);
      toast.success('Varlık zimmetlendi');
      loadAsset();
    } catch {
      throw new Error('Assignment failed');
    }
  };

  const handleReturn = async () => {
    try {
      await assetsApi.items.return(Number(id));
      toast.success('Varlık iade alındı');
      setReturnDialogOpen(false);
      loadAsset();
    } catch {
      // Error handled by interceptor
    }
  };

  const formatDate = (date?: string) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('tr-TR');
  };

  const formatCurrency = (amount?: number) => {
    if (!amount) return '-';
    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
  };

  if (loading) {
    return <div className="loading-container"><div className="loading-spinner" /></div>;
  }

  if (!asset) return null;

  return (
    <div className="animate-fade-in">
      {/* Header */}
      <div style={{ marginBottom: '1.5rem' }}>
        <button
          className="btn btn-ghost btn-sm"
          onClick={() => navigate('/assets')}
          style={{ marginBottom: '1rem' }}
        >
          <BsArrowLeft size={16} />
          Geri
        </button>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
          <div>
            <h1 className="page-title">{asset.name}</h1>
            <p className="page-subtitle">
              {asset.brand} {asset.model} • {asset.asset_code || asset.serial_number}
            </p>
          </div>
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            {asset.status === 'available' && (
              <button className="btn btn-primary" onClick={() => setAssignmentFormOpen(true)}>
                <BsPersonPlus size={16} />
                Zimmetle
              </button>
            )}
            {asset.status === 'assigned' && (
              <button className="btn btn-warning" onClick={() => setReturnDialogOpen(true)}>
                <BsArrowReturnLeft size={16} />
                İade Al
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Status Banner */}
      <div
        className="card"
        style={{
          marginBottom: '1.5rem',
          background: `${statusColor}20`,
          border: `1px solid ${statusColor}40`,
        }}
      >
        <div className="card-body" style={{ padding: '1rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
            <div
              style={{
                width: '12px',
                height: '12px',
                borderRadius: '50%',
                background: statusColor,
              }}
            />
            <span style={{ fontWeight: 500 }}>{statusLabel}</span>
            {asset.current_assignment && (
              <span style={{ color: 'var(--text-secondary)' }}>
                • Zimmetli: {asset.current_assignment.user.name}
              </span>
            )}
          </div>
          <span style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
            Kondisyon: {conditionLabel}
          </span>
        </div>
      </div>

      {/* Info Grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: '1.5rem', marginBottom: '1.5rem' }}>
        {/* Basic Info */}
        <div className="card">
          <div className="card-header">
            <h3 className="card-title"><BsLaptop size={16} style={{ marginRight: '0.5rem' }} />Temel Bilgiler</h3>
          </div>
          <div className="card-body">
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-tertiary)' }}>Kategori</span>
                <span>{asset.category?.name || '-'}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-tertiary)' }}>Marka</span>
                <span>{asset.brand || '-'}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-tertiary)' }}>Model</span>
                <span>{asset.model || '-'}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-tertiary)' }}>Demirbaş Kodu</span>
                <span>{asset.asset_code || '-'}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-tertiary)' }}>Seri No</span>
                <span>{asset.serial_number || '-'}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Financial Info */}
        <div className="card">
          <div className="card-header">
            <h3 className="card-title"><BsCurrencyDollar size={16} style={{ marginRight: '0.5rem' }} />Finansal Bilgiler</h3>
          </div>
          <div className="card-body">
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-tertiary)' }}>Satın Alma Tarihi</span>
                <span>{formatDate(asset.purchase_date)}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-tertiary)' }}>Satın Alma Fiyatı</span>
                <span>{formatCurrency(asset.purchase_price)}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <span style={{ color: 'var(--text-tertiary)' }}>Garanti Bitiş</span>
                <span>{formatDate(asset.warranty_end_date)}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Description */}
      {asset.description && (
        <div className="card" style={{ marginBottom: '1.5rem' }}>
          <div className="card-header">
            <h3 className="card-title">Açıklama</h3>
          </div>
          <div className="card-body">
            <p style={{ color: 'var(--text-secondary)', margin: 0 }}>{asset.description}</p>
          </div>
        </div>
      )}

      {/* Assignment History */}
      <div className="card">
        <div className="card-header">
          <h3 className="card-title">Zimmet Geçmişi</h3>
        </div>
        <div className="card-body">
          {(!asset.assignments_history || asset.assignments_history.length === 0) && !asset.current_assignment ? (
            <p style={{ color: 'var(--text-tertiary)', textAlign: 'center', padding: '2rem' }}>
              Henüz zimmet geçmişi yok
            </p>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              {asset.current_assignment && (
                <div
                  style={{
                    padding: '1rem',
                    background: 'var(--accent-bg)',
                    borderRadius: 'var(--radius-md)',
                    border: '1px solid var(--accent)',
                  }}
                >
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                      <div style={{ fontWeight: 500 }}>{asset.current_assignment.user.name}</div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                        Zimmet Tarihi: {formatDate(asset.current_assignment.assigned_date)}
                      </div>
                    </div>
                    <span className="badge badge-info">Şu An Zimmetli</span>
                  </div>
                  {asset.current_assignment.notes && (
                    <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', marginTop: '0.5rem', marginBottom: 0 }}>
                      {asset.current_assignment.notes}
                    </p>
                  )}
                </div>
              )}
              {asset.assignments_history?.map((assignment) => (
                <div
                  key={assignment.id}
                  style={{
                    padding: '1rem',
                    background: 'var(--bg-tertiary)',
                    borderRadius: 'var(--radius-md)',
                    border: '1px solid var(--border-color)',
                  }}
                >
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                      <div style={{ fontWeight: 500 }}>{assignment.user.name}</div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                        {formatDate(assignment.assigned_date)} - {formatDate(assignment.returned_date)}
                      </div>
                    </div>
                    <span className="badge badge-secondary">Tamamlandı</span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Assignment Form */}
      <AssignmentForm
        isOpen={assignmentFormOpen}
        onClose={() => setAssignmentFormOpen(false)}
        onSubmit={handleAssign}
        assetName={asset.name}
      />

      {/* Return Dialog */}
      <ConfirmDialog
        isOpen={returnDialogOpen}
        onClose={() => setReturnDialogOpen(false)}
        onConfirm={handleReturn}
        title="Varlık İade"
        message={`"${asset.name}" varlığını ${asset.current_assignment?.user?.name}'dan iade almak istiyor musunuz?`}
        confirmText="İade Al"
        variant="warning"
      />
    </div>
  );
};

export default AssetDetailPage;

