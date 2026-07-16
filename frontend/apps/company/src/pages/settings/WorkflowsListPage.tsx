import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from '@shared/i18n';
import { Select } from '@shared/components';
import { workflowsApi, type WorkflowPayload } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { usePermission } from '@shared/hooks';
import toast from 'react-hot-toast';
import { BsPlus, BsPencil, BsTrash } from 'react-icons/bs';

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function toStringRecord(value: unknown): Record<string, string> {
  if (!isRecord(value)) return {};
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(value)) {
    if (typeof v === 'string') out[k] = v;
  }
  return out;
}

function isWorkflowPayload(value: unknown): value is WorkflowPayload {
  if (!isRecord(value)) return false;
  return typeof value.name === 'string' && typeof value.entity_type === 'string';
}

const WorkflowsListPage: React.FC = () => {
  const { t } = useTranslation('common');
  const { canCreate, canEdit, canDelete } = usePermission();
  const canCreateWf = canCreate('management', 'workflows');
  const canEditWf = canEdit('management', 'workflows');
  const canDeleteWf = canDelete('management', 'workflows');

  const [items, setItems] = useState<WorkflowPayload[]>([]);
  const [entityTypes, setEntityTypes] = useState<Record<string, string>>({});
  const [entityFilter, setEntityFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [seeding, setSeeding] = useState(false);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const [listRes, typesRes] = await Promise.all([
        workflowsApi.list(entityFilter ? { entity_type: entityFilter } : undefined),
        workflowsApi.entityTypes(),
      ]);
      const listData = listRes.data.data;
      setItems(Array.isArray(listData) ? listData.filter(isWorkflowPayload) : []);
      setEntityTypes(toStringRecord(typesRes.data.data));
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('workflows.loadError')));
    } finally {
      setLoading(false);
    }
  }, [entityFilter, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const entityOptions = useMemo(
    () => Object.entries(entityTypes).map(([value, label]) => ({ value, label })),
    [entityTypes]
  );

  const handleSeed = async () => {
    try {
      setSeeding(true);
      await workflowsApi.seedDefaultLeave();
      toast.success(t('workflows.seedSuccess'));
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('workflows.seedError')));
    } finally {
      setSeeding(false);
    }
  };

  const handleDelete = async (id: number | undefined) => {
    if (!id) return;
    if (!window.confirm(t('workflows.deleteConfirm'))) return;
    try {
      await workflowsApi.delete(id);
      toast.success(t('workflows.deleteSuccess'));
      await load();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('workflows.deleteError')));
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">{t('workflows.listTitle')}</h1>
          <p className="page-subtitle">{t('workflows.listSubtitle')}</p>
        </div>
        <div className="page-header-actions" style={{ display: 'flex', gap: 'var(--space-2)' }}>
          {canCreateWf ? (
            <Link to="/settings/workflows/new" className="btn btn-primary btn-sm">
              <BsPlus /> {t('workflows.new')}
            </Link>
          ) : null}
        </div>
      </div>

      <div className="card" style={{ marginBottom: 'var(--space-4)' }}>
        <div className="card-body" style={{ display: 'flex', gap: 'var(--space-3)', alignItems: 'end' }}>
          <div className="form-group" style={{ margin: 0, minWidth: 220 }}>
            <label className="form-label">{t('workflows.entityFilter')}</label>
            <Select
              value={entityFilter}
              onChange={setEntityFilter}
              options={entityOptions}
              allowEmpty
              emptyLabel={t('workflows.allEntities')}
              placeholder={t('workflows.allEntities')}
            />
          </div>
        </div>
      </div>

      {loading ? (
        <div className="card">
          <div className="card-body text-center py-5">
            <div className="spinner-border" role="status" />
          </div>
        </div>
      ) : items.length === 0 ? (
        <div className="card">
          <div className="card-body" style={{ textAlign: 'center', padding: 'var(--space-6)' }}>
            <h3 style={{ marginBottom: 'var(--space-2)' }}>{t('workflows.emptyTitle')}</h3>
            <p style={{ color: 'var(--text-tertiary)', marginBottom: 'var(--space-4)' }}>
              {t('workflows.emptyHint')}
            </p>
            {canCreateWf ? (
              <div style={{ display: 'flex', gap: 'var(--space-2)', justifyContent: 'center' }}>
                <button
                  type="button"
                  className="btn btn-secondary"
                  disabled={seeding}
                  onClick={() => void handleSeed()}
                >
                  {t('workflows.seedDefault')}
                </button>
                <Link to="/settings/workflows/new" className="btn btn-primary">
                  {t('workflows.new')}
                </Link>
              </div>
            ) : null}
          </div>
        </div>
      ) : (
        <div className="card">
          <div className="card-body" style={{ padding: 0 }}>
            <table className="table" style={{ margin: 0 }}>
              <thead>
                <tr>
                  <th>{t('workflows.name')}</th>
                  <th>{t('workflows.entityType')}</th>
                  <th>{t('workflows.steps')}</th>
                  <th>{t('workflows.active')}</th>
                  <th style={{ width: 120 }} />
                </tr>
              </thead>
              <tbody>
                {items.map((wf) => (
                  <tr key={wf.id}>
                    <td>
                      {wf.name}
                      {wf.is_default ? (
                        <span
                          style={{
                            marginLeft: 'var(--space-2)',
                            fontSize: '0.75rem',
                            color: 'var(--text-tertiary)',
                          }}
                        >
                          ({t('workflows.default')})
                        </span>
                      ) : null}
                    </td>
                    <td>{entityTypes[wf.entity_type] ?? wf.entity_type}</td>
                    <td>{wf.steps_count ?? wf.steps?.length ?? 0}</td>
                    <td>{wf.is_active ? t('workflows.active') : t('workflows.inactive')}</td>
                    <td>
                      <div style={{ display: 'flex', gap: 'var(--space-1)' }}>
                        {canEditWf && wf.id ? (
                          <Link
                            to={`/settings/workflows/${wf.id}`}
                            className="btn btn-ghost btn-sm"
                            title={t('workflows.edit')}
                          >
                            <BsPencil />
                          </Link>
                        ) : null}
                        {canDeleteWf && wf.id ? (
                          <button
                            type="button"
                            className="btn btn-ghost btn-sm"
                            title={t('workflows.delete')}
                            onClick={() => void handleDelete(wf.id)}
                          >
                            <BsTrash />
                          </button>
                        ) : null}
                      </div>
                    </td>
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

export default WorkflowsListPage;
