import React, { useEffect, useState } from 'react';
import { webhooksApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import {
  BsPlus,
  BsPencil,
  BsTrash,
  BsPlay,
  BsCheckCircle,
  BsXCircle,
  BsActivity,
} from 'react-icons/bs';
import { DataTable, ConfirmDialog, Modal } from '../../components/ui';

interface Webhook {
  id: number;
  name: string;
  url: string;
  events: string[];
  is_active: boolean;
  timeout: number;
  retry_count: number;
  last_triggered_at: string | null;
  success_count: number;
  failure_count: number;
  created_at: string;
}

interface WebhookLog {
  id: number;
  event: string;
  status_code: number | null;
  is_successful: boolean;
  error_message: string | null;
  triggered_at: string;
}

const WebhooksPage: React.FC = () => {
  const [webhooks, setWebhooks] = useState<Webhook[]>([]);
  const [loading, setLoading] = useState(true);
  const [formOpen, setFormOpen] = useState(false);
  const [selectedWebhook, setSelectedWebhook] = useState<Webhook | undefined>();
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [webhookToDelete, setWebhookToDelete] = useState<Webhook | null>(null);
  const [logsModalOpen, setLogsModalOpen] = useState(false);
  const [selectedWebhookForLogs, setSelectedWebhookForLogs] = useState<Webhook | null>(null);
  const [logs, setLogs] = useState<WebhookLog[]>([]);
  const [logsLoading, setLogsLoading] = useState(false);

  const availableEvents = [
    'user.created',
    'user.updated',
    'user.deleted',
    'leave.created',
    'leave.approved',
    'leave.rejected',
    'attendance.created',
    'document.uploaded',
    'webhook.test',
  ];

  useEffect(() => {
    loadWebhooks();
  }, []);

  const loadWebhooks = async () => {
    try {
      setLoading(true);
      const response = await webhooksApi.list();
      setWebhooks(response.data.data || []);
    } catch {
      toast.error('Webhook\'lar yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const loadLogs = async (webhookId: number) => {
    try {
      setLogsLoading(true);
      const response = await webhooksApi.getLogs(webhookId, { per_page: 50 });
      setLogs(response.data.data?.data || response.data.data || []);
    } catch {
      toast.error('Log\'lar yüklenemedi');
    } finally {
      setLogsLoading(false);
    }
  };

  const handleDelete = (webhook: Webhook) => {
    setWebhookToDelete(webhook);
    setDeleteDialogOpen(true);
  };

  const confirmDelete = async () => {
    if (!webhookToDelete) return;

    try {
      await webhooksApi.delete(webhookToDelete.id);
      toast.success('Webhook silindi');
      setDeleteDialogOpen(false);
      setWebhookToDelete(null);
      loadWebhooks();
    } catch {
      toast.error('Webhook silinemedi');
    }
  };

  const handleTest = async (webhook: Webhook) => {
    try {
      await webhooksApi.test(webhook.id);
      toast.success('Test webhook gönderildi');
      loadWebhooks();
    } catch {
      toast.error('Test webhook gönderilemedi');
    }
  };

  const handleViewLogs = async (webhook: Webhook) => {
    setSelectedWebhookForLogs(webhook);
    setLogsModalOpen(true);
    await loadLogs(webhook.id);
  };

  const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('tr-TR', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const columns = [
    {
      key: 'name',
      title: 'Webhook',
      render: (webhook: Webhook) => (
        <div>
          <div style={{ fontWeight: 500, color: 'var(--text-primary)' }}>{webhook.name}</div>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{webhook.url}</div>
        </div>
      ),
    },
    {
      key: 'events',
      title: 'Olaylar',
      render: (webhook: Webhook) => (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.25rem' }}>
          {webhook.events?.slice(0, 3).map((event, idx) => (
            <span key={idx} className="badge badge-secondary" style={{ fontSize: '0.6875rem' }}>
              {event}
            </span>
          ))}
          {webhook.events && webhook.events.length > 3 && (
            <span className="badge badge-primary" style={{ fontSize: '0.6875rem' }}>
              +{webhook.events.length - 3}
            </span>
          )}
        </div>
      ),
    },
    {
      key: 'status',
      title: 'Durum',
      width: '100px',
      render: (webhook: Webhook) => (
        <span className={`badge ${webhook.is_active ? 'badge-success' : 'badge-secondary'}`}>
          {webhook.is_active ? 'Aktif' : 'Pasif'}
        </span>
      ),
    },
    {
      key: 'stats',
      title: 'İstatistikler',
      width: '150px',
      render: (webhook: Webhook) => (
        <div style={{ fontSize: '0.875rem' }}>
          <div style={{ color: 'var(--success)' }}>✓ {webhook.success_count}</div>
          <div style={{ color: 'var(--danger)' }}>✗ {webhook.failure_count}</div>
        </div>
      ),
    },
    {
      key: 'actions',
      title: 'İşlemler',
      width: '200px',
      align: 'right' as const,
      render: (webhook: Webhook) => (
        <div style={{ display: 'flex', gap: '0.25rem', justifyContent: 'flex-end' }}>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => handleViewLogs(webhook)}
            title="Log'ları Görüntüle"
          >
            <BsActivity />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => handleTest(webhook)}
            title="Test Et"
          >
            <BsPlay />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => {
              setSelectedWebhook(webhook);
              setFormOpen(true);
            }}
            title="Düzenle"
          >
            <BsPencil />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => handleDelete(webhook)}
            title="Sil"
            style={{ color: 'var(--danger)' }}
          >
            <BsTrash />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1>Webhook'lar</h1>
          <p>Dış sistemlerle entegrasyon için webhook'ları yönetin</p>
        </div>
        <div className="page-header-actions">
          <button
            className="btn btn-primary"
            onClick={() => {
              setSelectedWebhook(undefined);
              setFormOpen(true);
            }}
          >
            <BsPlus /> Yeni Webhook
          </button>
        </div>
      </div>

      {loading ? (
        <div className="page-loading">
          <div className="loading-spinner" />
        </div>
      ) : (
        <div className="card">
          <div className="card-body">
            <DataTable
              data={webhooks}
              columns={columns}
            />
          </div>
        </div>
      )}

      {/* Webhook Form Modal - Basit bir form */}
      <Modal
        isOpen={formOpen}
        onClose={() => {
          setFormOpen(false);
          setSelectedWebhook(undefined);
        }}
        title={selectedWebhook ? 'Webhook Düzenle' : 'Yeni Webhook'}
        size="md"
      >
        <WebhookForm
          webhook={selectedWebhook}
          onSuccess={() => {
            setFormOpen(false);
            setSelectedWebhook(undefined);
            loadWebhooks();
          }}
          onCancel={() => {
            setFormOpen(false);
            setSelectedWebhook(undefined);
          }}
          availableEvents={availableEvents}
        />
      </Modal>

      {/* Logs Modal */}
      <Modal
        isOpen={logsModalOpen}
        onClose={() => {
          setLogsModalOpen(false);
          setSelectedWebhookForLogs(null);
          setLogs([]);
        }}
        title={`Webhook Log'ları - ${selectedWebhookForLogs?.name}`}
        size="lg"
      >
        {logsLoading ? (
          <div className="page-loading" style={{ minHeight: 200 }}>
            <div className="loading-spinner" />
          </div>
        ) : logs.length === 0 ? (
          <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text-tertiary)' }}>
            Log kaydı bulunmuyor
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            {logs.map((log) => (
              <div
                key={log.id}
                style={{
                  padding: '0.75rem',
                  background: log.is_successful ? 'var(--success-soft)' : 'var(--danger-soft)',
                  borderRadius: 'var(--radius-sm)',
                  border: `1px solid ${log.is_successful ? 'var(--success)' : 'var(--danger)'}`,
                }}
              >
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    {log.is_successful ? (
                      <BsCheckCircle style={{ color: 'var(--success)' }} />
                    ) : (
                      <BsXCircle style={{ color: 'var(--danger)' }} />
                    )}
                    <span style={{ fontWeight: 500 }}>{log.event}</span>
                  </div>
                  <span style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
                    {formatDateTime(log.triggered_at)}
                  </span>
                </div>
                {log.status_code && (
                  <div style={{ fontSize: '0.875rem', marginBottom: '0.25rem' }}>
                    Status: <strong>{log.status_code}</strong>
                  </div>
                )}
                {log.error_message && (
                  <div style={{ fontSize: '0.875rem', color: 'var(--danger)' }}>
                    Hata: {log.error_message}
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </Modal>

      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        onConfirm={confirmDelete}
        title="Webhook'u Sil"
        message={`"${webhookToDelete?.name}" webhook'unu silmek istediğinize emin misiniz?`}
        confirmText="Sil"
        variant="danger"
      />
    </div>
  );
};

// Basit Webhook Form Component
const WebhookForm: React.FC<{
  webhook?: Webhook;
  onSuccess: () => void;
  onCancel: () => void;
  availableEvents: string[];
}> = ({ webhook, onSuccess, onCancel, availableEvents }) => {
  const [formData, setFormData] = useState({
    name: webhook?.name || '',
    url: webhook?.url || '',
    events: webhook?.events || [],
    is_active: webhook?.is_active ?? true,
    timeout: webhook?.timeout || 30,
    retry_count: webhook?.retry_count || 3,
  });
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.name || !formData.url || formData.events.length === 0) {
      toast.error('Lütfen tüm gerekli alanları doldurun');
      return;
    }

    setLoading(true);
    try {
      if (webhook) {
        await webhooksApi.update(webhook.id, formData);
        toast.success('Webhook güncellendi');
      } else {
        await webhooksApi.create(formData);
        toast.success('Webhook oluşturuldu');
      }
      onSuccess();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'İşlem başarısız'));
    } finally {
      setLoading(false);
    }
  };

  const toggleEvent = (event: string) => {
    setFormData((prev) => ({
      ...prev,
      events: prev.events.includes(event)
        ? prev.events.filter((e) => e !== event)
        : [...prev.events, event],
    }));
  };

  return (
    <form onSubmit={handleSubmit}>
      <div className="form-group">
        <label className="form-label">Ad *</label>
        <input
          type="text"
          className="form-control"
          value={formData.name}
          onChange={(e) => setFormData({ ...formData, name: e.target.value })}
          required
        />
      </div>

      <div className="form-group">
        <label className="form-label">URL *</label>
        <input
          type="url"
          className="form-control"
          value={formData.url}
          onChange={(e) => setFormData({ ...formData, url: e.target.value })}
          required
        />
      </div>

      <div className="form-group">
        <label className="form-label">Olaylar *</label>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem', maxHeight: '200px', overflowY: 'auto', padding: '0.75rem', background: 'var(--bg-secondary)', borderRadius: 'var(--radius-sm)' }}>
          {availableEvents.map((event) => (
            <label key={event} style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', cursor: 'pointer' }}>
              <input
                type="checkbox"
                checked={formData.events.includes(event)}
                onChange={() => toggleEvent(event)}
              />
              <span style={{ fontSize: '0.875rem' }}>{event}</span>
            </label>
          ))}
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
        <div className="form-group">
          <label className="form-label">Timeout (saniye)</label>
          <input
            type="number"
            className="form-control"
            value={formData.timeout}
            onChange={(e) => setFormData({ ...formData, timeout: parseInt(e.target.value) || 30 })}
            min={5}
            max={300}
          />
        </div>

        <div className="form-group">
          <label className="form-label">Tekrar Sayısı</label>
          <input
            type="number"
            className="form-control"
            value={formData.retry_count}
            onChange={(e) => setFormData({ ...formData, retry_count: parseInt(e.target.value) || 3 })}
            min={0}
            max={10}
          />
        </div>
      </div>

      <div className="form-group">
        <label className="form-check">
          <input
            type="checkbox"
            checked={formData.is_active}
            onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
          />
          <span>Aktif</span>
        </label>
      </div>

      <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.5rem' }}>
        <button type="button" className="btn btn-secondary" onClick={onCancel} disabled={loading}>
          İptal
        </button>
        <button type="submit" className="btn btn-primary" disabled={loading}>
          {loading ? 'Kaydediliyor...' : webhook ? 'Güncelle' : 'Oluştur'}
        </button>
      </div>
    </form>
  );
};

export default WebhooksPage;

