import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { portalApi, lookupsApi, type LookupItem } from '@shared/services/api';
import {
  Select,
  FormEngine,
  buildRequestTypeFormDefinition,
  type FormDefinitionPayload,
  type FormEngineSubmitPayload,
} from '@shared/components';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import { BsPlus, BsInboxes, BsX } from 'react-icons/bs';

interface RequestType {
  id: number;
  name: string;
  icon: string | null;
  color: string | null;
  description: string | null;
  form_fields?: unknown;
}

interface EmployeeRequest {
  id: number;
  title: string;
  description: string | null;
  request_type: RequestType;
  status: string;
  priority: string;
  created_at: string;
}

const statusClassMap: Record<string, string> = {
  pending: 'pending',
  in_review: 'pending',
  approved: 'approved',
  rejected: 'rejected',
  cancelled: 'cancelled',
};

const RequestsPage: React.FC = () => {
  const { t } = useTranslation(['common', 'validation']);
  const [requests, setRequests] = useState<EmployeeRequest[]>([]);
  const [requestTypes, setRequestTypes] = useState<RequestType[]>([]);
  const [statusLookups, setStatusLookups] = useState<LookupItem[]>([]);
  const [priorityLookups, setPriorityLookups] = useState<LookupItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const [formData, setFormData] = useState({
    request_type_id: '',
    title: '',
    description: '',
    priority: 'normal',
  });

  const selectedType = useMemo(
    () => requestTypes.find((type) => String(type.id) === formData.request_type_id) ?? null,
    [formData.request_type_id, requestTypes]
  );

  const dynamicDefinition: FormDefinitionPayload | null = useMemo(() => {
    if (!selectedType) return null;
    return buildRequestTypeFormDefinition(selectedType.form_fields, selectedType.name);
  }, [selectedType]);

  const loadData = useCallback(async () => {
    try {
      const [requestsRes, typesRes, statusRes, priorityRes] = await Promise.all([
        portalApi.requests.list(),
        portalApi.requests.types(),
        lookupsApi.forType('employee_request_status'),
        lookupsApi.forType('employee_request_priority'),
      ]);
      setRequests(requestsRes.data.data.data || []);
      setRequestTypes(typesRes.data.data || []);
      setStatusLookups(statusRes.data.data ?? []);
      setPriorityLookups(priorityRes.data.data ?? []);
    } catch {
      toast.error(t('formEngine.portalRequestLoadError'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    void loadData();
  }, [loadData]);

  const getStatusBadge = (status: string) => {
    const label = statusLookups.find((o) => o.value === status)?.label || status;
    const className = statusClassMap[status] || '';
    return <span className={`request-status ${className}`}>{label}</span>;
  };

  const getPriorityBadge = (priority: string) => {
    const item = priorityLookups.find((o) => o.value === priority);
    const label = item?.label || priority;
    const color = item?.color;
    if (color) {
      return (
        <span className="badge" style={{ backgroundColor: color, color: '#fff' }}>
          {label}
        </span>
      );
    }
    return <span className="badge bg-secondary">{label}</span>;
  };

  const submitRequest = async (extraFormData: Record<string, unknown>) => {
    if (!formData.request_type_id || !formData.title) {
      toast.error(t('formEngine.portalRequestRequired'));
      return;
    }

    setSubmitting(true);
    try {
      const data = new FormData();
      data.append('request_type_id', formData.request_type_id);
      data.append('title', formData.title);
      data.append('priority', formData.priority);
      if (formData.description) data.append('description', formData.description);
      data.append('form_data', JSON.stringify(extraFormData));

      await portalApi.requests.create(data);
      toast.success(t('formEngine.portalRequestCreated'));
      setShowModal(false);
      setFormData({ request_type_id: '', title: '', description: '', priority: 'normal' });
      void loadData();
    } catch (error: unknown) {
      const message =
        error && typeof error === 'object' && 'response' in error
          ? String(
              (error as { response?: { data?: { message?: string } } }).response?.data?.message ??
                t('formEngine.portalRequestCreateError')
            )
          : t('formEngine.portalRequestCreateError');
      toast.error(message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleDynamicSubmit = async (payload: FormEngineSubmitPayload) => {
    await submitRequest(payload.custom_fields);
  };

  const handleCancel = async (id: number) => {
    if (!confirm(t('formEngine.portalRequestCancelConfirm'))) return;
    try {
      await portalApi.requests.cancel(id);
      toast.success(t('formEngine.portalRequestCancelled'));
      void loadData();
    } catch {
      toast.error(t('formEngine.portalRequestCancelError'));
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">{t('formEngine.portalRequestsTitle')}</h1>
          <p className="page-subtitle">{t('formEngine.portalRequestsSubtitle')}</p>
        </div>
        <div className="page-header-actions">
          <button className="btn btn-primary" onClick={() => setShowModal(true)}>
            <BsPlus size={20} /> {t('formEngine.portalRequestNew')}
          </button>
        </div>
      </div>

      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="page-loading">
              <div className="loading-spinner"></div>
            </div>
          ) : requests.length > 0 ? (
            <>
              <div className="table-responsive desktop-only">
                <table className="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>{t('formEngine.portalColRequest')}</th>
                      <th>{t('formEngine.portalColType')}</th>
                      <th>{t('formEngine.portalColStatus')}</th>
                      <th>{t('formEngine.portalColPriority')}</th>
                      <th>{t('formEngine.portalColDate')}</th>
                      <th>{t('formEngine.portalColActions')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {requests.map((request) => (
                      <tr key={request.id}>
                        <td>
                          <div className="fw-semibold">{request.title}</div>
                          {request.description && (
                            <div className="text-muted small">{request.description.substring(0, 50)}...</div>
                          )}
                        </td>
                        <td>{request.request_type?.name}</td>
                        <td>{getStatusBadge(request.status)}</td>
                        <td>{getPriorityBadge(request.priority)}</td>
                        <td>{new Date(request.created_at).toLocaleDateString('tr-TR')}</td>
                        <td>
                          {request.status === 'pending' && (
                            <button
                              className="btn btn-sm btn-ghost text-danger"
                              onClick={() => void handleCancel(request.id)}
                            >
                              {t('formEngine.portalCancel')}
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="mobile-card-list has-data">
                {requests.map((request) => (
                  <div key={request.id} className="mobile-card">
                    <div className="mobile-card-header">
                      <div>
                        <div className="mobile-card-title">{request.title}</div>
                        <div className="mobile-card-subtitle">{request.request_type?.name}</div>
                      </div>
                      {getStatusBadge(request.status)}
                    </div>
                    <div className="mobile-card-body">
                      {request.description && (
                        <div className="mobile-card-row">
                          <span className="mobile-card-value" style={{ fontSize: '0.85rem', color: 'var(--portal-text-muted)' }}>
                            {request.description}
                          </span>
                        </div>
                      )}
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">{t('formEngine.portalColPriority')}</span>
                        {getPriorityBadge(request.priority)}
                      </div>
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">{t('formEngine.portalColDate')}</span>
                        <span className="mobile-card-value">
                          {new Date(request.created_at).toLocaleDateString('tr-TR')}
                        </span>
                      </div>
                    </div>
                    {request.status === 'pending' && (
                      <div className="mobile-card-footer">
                        <button
                          className="btn btn-outline-primary btn-sm"
                          onClick={() => void handleCancel(request.id)}
                        >
                          {t('formEngine.portalCancel')}
                        </button>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </>
          ) : (
            <div className="empty-state">
              <BsInboxes size={64} className="text-muted mb-3" />
              <h3>{t('formEngine.portalRequestsEmpty')}</h3>
              <p>{t('formEngine.portalRequestsEmptyHint')}</p>
              <button className="btn btn-primary" onClick={() => setShowModal(true)}>
                <BsPlus /> {t('formEngine.portalRequestFirst')}
              </button>
            </div>
          )}
        </div>
      </div>

      <button className="fab" onClick={() => setShowModal(true)} aria-label={t('formEngine.portalRequestNew')}>
        <BsPlus size={24} />
      </button>

      {showModal && (
        <div className="modal-mobile open">
          <div className="modal-mobile-header">
            <h3 className="modal-mobile-title">{t('formEngine.portalRequestNew')}</h3>
            <button className="modal-mobile-close" onClick={() => setShowModal(false)}>
              <BsX size={24} />
            </button>
          </div>
          <div className="modal-mobile-body">
              <div className="mb-3">
                <label className="form-label">{t('formEngine.portalTypeLabel')}</label>
                <Select
                  value={formData.request_type_id}
                  onChange={(v) => setFormData({ ...formData, request_type_id: v })}
                  options={requestTypes.map((type) => ({
                    value: String(type.id),
                    label: type.name,
                  }))}
                  allowEmpty
                  placeholder={t('formEngine.selectPlaceholder')}
                  aria-label={t('formEngine.portalTypeLabel')}
                />
              </div>
              <div className="mb-3">
                <label className="form-label">{t('formEngine.portalTitleLabel')}</label>
                <input
                  type="text"
                  className="form-control"
                  value={formData.title}
                  onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                  placeholder={t('formEngine.portalTitlePlaceholder')}
                  required={!dynamicDefinition}
                />
              </div>
              <div className="mb-3">
                <label className="form-label">{t('formEngine.portalColPriority')}</label>
                <Select
                  value={formData.priority}
                  onChange={(v) => setFormData({ ...formData, priority: v || 'normal' })}
                  options={priorityLookups.map((opt) => ({
                    value: opt.value,
                    label: opt.label,
                    color: opt.color,
                  }))}
                  placeholder={t('formEngine.selectPlaceholder')}
                  aria-label={t('formEngine.portalColPriority')}
                />
              </div>
              <div className="mb-3">
                <label className="form-label">{t('formEngine.portalDescriptionLabel')}</label>
                <textarea
                  className="form-control"
                  rows={4}
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  placeholder={t('formEngine.portalDescriptionPlaceholder')}
                />
              </div>

              {dynamicDefinition ? (
                <FormEngine
                  definition={dynamicDefinition}
                  onSubmit={handleDynamicSubmit}
                  loading={submitting}
                  submitLabel={t('formEngine.portalSubmit')}
                  cancelLabel={t('formEngine.cancel')}
                  savingLabel={t('formEngine.saving')}
                  onCancel={() => setShowModal(false)}
                  messages={{
                    required: t('validation:required'),
                    email: t('validation:email'),
                    selectPlaceholder: t('formEngine.selectPlaceholder'),
                  }}
                />
              ) : (
                <div className="modal-mobile-footer" style={{ padding: 0, border: 'none' }}>
                  <button
                    type="button"
                    className="btn btn-outline-primary"
                    onClick={() => setShowModal(false)}
                  >
                    {t('formEngine.cancel')}
                  </button>
                  <button
                    type="button"
                    className="btn btn-primary"
                    disabled={submitting}
                    onClick={() => void submitRequest({})}
                  >
                    {submitting ? t('formEngine.saving') : t('formEngine.portalSubmit')}
                  </button>
                </div>
              )}
            </div>
        </div>
      )}
    </div>
  );
};

export default RequestsPage;
