import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { lookupsApi, type LookupItem } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import { usePermission } from '@shared/hooks';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import {
  BsPlus,
  BsPencil,
  BsTrash,
  BsListUl,
  BsLock,
  BsShuffle,
} from 'react-icons/bs';
import { ConfirmDialog, DataTable, Modal } from '../../components/ui';
import type { Column } from '../../components/ui/DataTable';
import {
  DEFAULT_LOOKUP_TYPE,
  LOOKUP_TYPE_GROUPS,
  findLookupTypeDef,
  getLookupTypeKind,
  type LookupTypeDef,
  type LookupTypeKind,
} from './lookupTypeCatalog';

interface LookupFormState {
  label: string;
  value: string;
  color: string;
  sort_order: number;
  is_active: boolean;
}

const emptyForm = (): LookupFormState => ({
  label: '',
  value: '',
  color: '',
  sort_order: 100,
  is_active: true,
});

function kindBadgeClass(kind: LookupTypeKind): string {
  if (kind === 'system') return 'badge-secondary';
  if (kind === 'hybrid') return 'badge-warning';
  return 'badge-info';
}

const LookupsPage: React.FC = () => {
  const { t } = useTranslation('common');
  const { canCreate, canEdit, canDelete } = usePermission();

  const allowCreate = canCreate('management', 'lookups');
  const allowEdit = canEdit('management', 'lookups');
  const allowDelete = canDelete('management', 'lookups');

  const [selectedType, setSelectedType] = useState(DEFAULT_LOOKUP_TYPE);
  const [items, setItems] = useState<LookupItem[]>([]);
  const [loading, setLoading] = useState(true);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<LookupItem | null>(null);
  const [form, setForm] = useState<LookupFormState>(emptyForm);
  const [formSaving, setFormSaving] = useState(false);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [toDelete, setToDelete] = useState<LookupItem | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const typeDef = useMemo(
    () => findLookupTypeDef(selectedType),
    [selectedType]
  );
  const typeKind = typeDef?.kind ?? getLookupTypeKind(selectedType);
  const isSystemType = typeKind === 'system';
  const isHybridType = typeKind === 'hybrid';
  const canAddValue = allowCreate && typeKind === 'firm';

  const loadItems = useCallback(async () => {
    try {
      setLoading(true);
      const response = await lookupsApi.manageList(selectedType, false);
      const data = response.data.data;
      setItems(Array.isArray(data) ? data : []);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('lookups.loadError')));
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [selectedType, t]);

  useEffect(() => {
    void loadItems();
  }, [loadItems]);

  const openCreate = useCallback(() => {
    if (isSystemType) {
      toast.error(t('lookups.systemCreateBlocked'));
      return;
    }
    if (isHybridType) {
      toast.error(t('lookups.hybridCreateBlocked'));
      return;
    }
    setEditing(null);
    setForm(emptyForm());
    setFormOpen(true);
  }, [isSystemType, isHybridType, t]);

  const openEdit = useCallback(
    (item: LookupItem) => {
      if (item.is_system || isSystemType) {
        toast.error(t('lookups.systemReadOnly'));
        return;
      }
      setEditing(item);
      setForm({
        label: item.label,
        value: item.value,
        color: item.color ?? '',
        sort_order: item.sort_order,
        is_active: item.is_active,
      });
      setFormOpen(true);
    },
    [isSystemType, t]
  );

  const closeForm = useCallback(() => {
    setFormOpen(false);
    setEditing(null);
    setForm(emptyForm());
  }, []);

  const handleSave = async () => {
    if (!form.label.trim()) {
      toast.error(t('lookups.labelRequired'));
      return;
    }

    try {
      setFormSaving(true);
      if (editing) {
        await lookupsApi.update(editing.id, {
          label: form.label.trim(),
          color: form.color.trim() || null,
          sort_order: form.sort_order,
          is_active: form.is_active,
        });
        toast.success(t('lookups.updateSuccess'));
      } else {
        const payload: {
          lookup_type: string;
          label: string;
          color: string | null;
          sort_order: number;
          value?: string;
        } = {
          lookup_type: selectedType,
          label: form.label.trim(),
          color: form.color.trim() || null,
          sort_order: form.sort_order,
        };
        const slug = form.value.trim();
        if (slug) payload.value = slug;
        await lookupsApi.create(payload);
        toast.success(t('lookups.createSuccess'));
      }
      closeForm();
      await loadItems();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('lookups.saveError')));
    } finally {
      setFormSaving(false);
    }
  };

  const askDelete = useCallback(
    (item: LookupItem) => {
      if (item.is_system || isSystemType) {
        toast.error(t('lookups.systemReadOnly'));
        return;
      }
      if (item.is_hybrid || isHybridType) {
        toast.error(t('lookups.hybridDeleteBlocked'));
        return;
      }
      setToDelete(item);
      setDeleteOpen(true);
    },
    [isSystemType, isHybridType, t]
  );

  const confirmDelete = async () => {
    if (!toDelete) return;
    try {
      setDeleteLoading(true);
      const response = await lookupsApi.delete(toDelete.id);
      const message =
        typeof response.data.message === 'string' && response.data.message
          ? response.data.message
          : t('lookups.deleteSuccess');
      // K-B: kullanımda → pasifleştirildi mesajı API'den gelir
      toast.success(message);
      setDeleteOpen(false);
      setToDelete(null);
      await loadItems();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('lookups.deleteError')));
    } finally {
      setDeleteLoading(false);
    }
  };

  const columns: Column<LookupItem>[] = useMemo(
    () => [
      {
        key: 'value',
        title: t('lookups.colValue'),
        render: (row) => (
          <code style={{ fontSize: '0.8rem' }}>{row.value}</code>
        ),
      },
      {
        key: 'label',
        title: t('lookups.colLabel'),
        render: (row) => <span style={{ fontWeight: 500 }}>{row.label}</span>,
      },
      {
        key: 'color',
        title: t('lookups.colColor'),
        width: '80px',
        align: 'center',
        render: (row) =>
          row.color ? (
            <span
              title={row.color}
              style={{
                display: 'inline-block',
                width: 18,
                height: 18,
                borderRadius: 4,
                background: row.color,
                border: '1px solid var(--border-color)',
                verticalAlign: 'middle',
              }}
            />
          ) : (
            <span style={{ color: 'var(--text-tertiary)' }}>—</span>
          ),
      },
      {
        key: 'sort_order',
        title: t('lookups.colSort'),
        width: '90px',
        align: 'center',
        render: (row) => row.sort_order,
      },
      {
        key: 'is_active',
        title: t('lookups.colActive'),
        width: '100px',
        align: 'center',
        render: (row) => (
          <span className={`badge ${row.is_active ? 'badge-success' : 'badge-secondary'}`}>
            {row.is_active ? t('lookups.active') : t('lookups.inactive')}
          </span>
        ),
      },
      {
        key: 'badges',
        title: t('lookups.colKind'),
        width: '120px',
        render: (row) => {
          if (row.is_system) {
            return (
              <span className={`badge ${kindBadgeClass('system')}`}>
                {t('lookups.kindSystem')}
              </span>
            );
          }
          if (row.is_hybrid || isHybridType) {
            return (
              <span className={`badge ${kindBadgeClass('hybrid')}`}>
                {t('lookups.kindHybrid')}
              </span>
            );
          }
          return (
            <span className={`badge ${kindBadgeClass('firm')}`}>
              {t('lookups.kindFirm')}
            </span>
          );
        },
      },
      {
        key: 'actions',
        title: t('lookups.colActions'),
        width: '120px',
        align: 'right',
        render: (row) => {
          const readOnly = row.is_system || isSystemType;
          const noDelete = readOnly || row.is_hybrid || isHybridType;
          return (
            <div className="d-flex gap-1" style={{ justifyContent: 'flex-end' }}>
              {allowEdit && !readOnly && (
                <button
                  type="button"
                  className="btn btn-sm btn-ghost"
                  title={t('lookups.edit')}
                  onClick={() => openEdit(row)}
                >
                  <BsPencil />
                </button>
              )}
              {allowDelete && !noDelete && (
                <button
                  type="button"
                  className="btn btn-sm btn-ghost text-danger"
                  title={t('lookups.delete')}
                  onClick={() => askDelete(row)}
                >
                  <BsTrash />
                </button>
              )}
              {readOnly && (
                <span
                  className="text-muted"
                  title={t('lookups.systemReadOnly')}
                  style={{ display: 'inline-flex', alignItems: 'center', padding: '0 0.35rem' }}
                >
                  <BsLock size={14} />
                </span>
              )}
            </div>
          );
        },
      },
    ],
    [t, allowEdit, allowDelete, isSystemType, isHybridType, openEdit, askDelete]
  );

  const renderTypeButton = (def: LookupTypeDef) => {
    const active = selectedType === def.type;
    return (
      <button
        key={def.type}
        type="button"
        className={`lookup-type-item${active ? ' active' : ''}`}
        onClick={() => setSelectedType(def.type)}
      >
        <span className="lookup-type-label">{def.label}</span>
        <span className={`badge badge-sm ${kindBadgeClass(def.kind)}`}>
          {def.kind === 'system'
            ? t('lookups.kindSystemShort')
            : def.kind === 'hybrid'
              ? t('lookups.kindHybridShort')
              : t('lookups.kindFirmShort')}
        </span>
      </button>
    );
  };

  return (
    <div className="page-container lookups-page">
      <div className="page-header">
        <div>
          <h1 className="page-title">
            <BsListUl style={{ marginRight: '0.5rem' }} />
            {t('lookups.title')}
          </h1>
          <p className="page-subtitle">{t('lookups.subtitle')}</p>
        </div>
      </div>

      <div className="lookups-layout">
        <aside className="lookups-sidebar card">
          <div className="card-header">
            <strong>{t('lookups.typeList')}</strong>
          </div>
          <div className="lookups-sidebar-body">
            {LOOKUP_TYPE_GROUPS.map((group) => (
              <div key={group.id} className="lookup-group">
                <div className="lookup-group-title">{group.label}</div>
                {group.types.map(renderTypeButton)}
              </div>
            ))}
          </div>
        </aside>

        <section className="lookups-main">
          <div className="lookups-toolbar">
            <div>
              <h2 className="lookups-type-title">
                {typeDef?.label ?? selectedType}
              </h2>
              <div className="lookups-type-meta">
                <code>{selectedType}</code>
                <span className={`badge ${kindBadgeClass(typeKind)}`}>
                  {typeKind === 'system'
                    ? t('lookups.kindSystem')
                    : typeKind === 'hybrid'
                      ? t('lookups.kindHybrid')
                      : t('lookups.kindFirm')}
                </span>
                {isSystemType && (
                  <span className="text-muted" style={{ fontSize: '0.8rem' }}>
                    {t('lookups.systemHint')}
                  </span>
                )}
                {isHybridType && (
                  <span className="text-muted" style={{ fontSize: '0.8rem' }}>
                    {t('lookups.hybridHint')}
                  </span>
                )}
              </div>
            </div>
            {canAddValue && (
              <button type="button" className="btn btn-primary" onClick={openCreate}>
                <BsPlus /> {t('lookups.addValue')}
              </button>
            )}
            {allowCreate && isSystemType && (
              <button
                type="button"
                className="btn btn-secondary"
                disabled
                title={t('lookups.systemCreateBlocked')}
              >
                <BsLock /> {t('lookups.addValue')}
              </button>
            )}
            {allowCreate && isHybridType && (
              <button
                type="button"
                className="btn btn-secondary"
                disabled
                title={t('lookups.hybridCreateBlocked')}
              >
                <BsShuffle /> {t('lookups.addValue')}
              </button>
            )}
          </div>

          <DataTable<LookupItem>
            columns={columns}
            data={items}
            loading={loading}
            emptyMessage={t('lookups.empty')}
            getRowKey={(row) => row.id}
          />
        </section>
      </div>

      <Modal
        isOpen={formOpen}
        onClose={closeForm}
        title={editing ? t('lookups.editTitle') : t('lookups.createTitle')}
        size="md"
        footer={
          <>
            <button type="button" className="btn btn-secondary" onClick={closeForm} disabled={formSaving}>
              {t('cancel')}
            </button>
            <button type="button" className="btn btn-primary" onClick={() => void handleSave()} disabled={formSaving}>
              {formSaving ? t('loading') : t('save')}
            </button>
          </>
        }
      >
        <div className="form-group">
          <label className="form-label" htmlFor="lookup-label">
            {t('lookups.fieldLabel')} *
          </label>
          <input
            id="lookup-label"
            type="text"
            className="form-control"
            value={form.label}
            onChange={(e) => setForm((f) => ({ ...f, label: e.target.value }))}
            maxLength={255}
          />
        </div>

        {!editing && (
          <div className="form-group">
            <label className="form-label" htmlFor="lookup-value">
              {t('lookups.fieldValue')}
            </label>
            <input
              id="lookup-value"
              type="text"
              className="form-control"
              value={form.value}
              onChange={(e) =>
                setForm((f) => ({
                  ...f,
                  value: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''),
                }))
              }
              placeholder={t('lookups.fieldValueHint')}
              maxLength={100}
            />
          </div>
        )}

        {editing && (
          <div className="form-group">
            <label className="form-label">{t('lookups.fieldValue')}</label>
            <input type="text" className="form-control" value={form.value} disabled />
            {(editing.is_hybrid || isHybridType) && (
              <small className="form-hint">{t('lookups.valueLockedHint')}</small>
            )}
          </div>
        )}

        <div className="form-group">
          <label className="form-label" htmlFor="lookup-color">
            {t('lookups.fieldColor')}
          </label>
          <div className="d-flex gap-2" style={{ alignItems: 'center' }}>
            <input
              id="lookup-color"
              type="color"
              value={form.color || '#64748b'}
              onChange={(e) => setForm((f) => ({ ...f, color: e.target.value }))}
              style={{ width: 42, height: 36, padding: 2, cursor: 'pointer' }}
            />
            <input
              type="text"
              className="form-control"
              value={form.color}
              onChange={(e) => setForm((f) => ({ ...f, color: e.target.value }))}
              placeholder="#10b981"
              maxLength={32}
            />
            {form.color && (
              <button
                type="button"
                className="btn btn-sm btn-secondary"
                onClick={() => setForm((f) => ({ ...f, color: '' }))}
              >
                {t('lookups.clearColor')}
              </button>
            )}
          </div>
        </div>

        <div className="form-group">
          <label className="form-label" htmlFor="lookup-sort">
            {t('lookups.fieldSort')}
          </label>
          <input
            id="lookup-sort"
            type="number"
            className="form-control"
            min={0}
            value={form.sort_order}
            onChange={(e) =>
              setForm((f) => ({ ...f, sort_order: Number(e.target.value) || 0 }))
            }
          />
        </div>

        {editing && (
          <div className="form-group">
            <label className="form-check">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked }))}
              />
              <span>{t('lookups.fieldActive')}</span>
            </label>
          </div>
        )}
      </Modal>

      <ConfirmDialog
        isOpen={deleteOpen}
        onClose={() => {
          setDeleteOpen(false);
          setToDelete(null);
        }}
        onConfirm={() => void confirmDelete()}
        title={t('lookups.deleteTitle')}
        message={t('lookups.deleteConfirm', { label: toDelete?.label ?? '' })}
        confirmText={t('lookups.delete')}
        cancelText={t('cancel')}
        variant="danger"
        loading={deleteLoading}
      />

      <style>{`
        .lookups-layout {
          display: grid;
          grid-template-columns: minmax(220px, 280px) 1fr;
          gap: 1rem;
          align-items: start;
        }
        .lookups-sidebar {
          position: sticky;
          top: 1rem;
          max-height: calc(100vh - 8rem);
          display: flex;
          flex-direction: column;
          overflow: hidden;
        }
        .lookups-sidebar-body {
          overflow-y: auto;
          padding: 0.5rem 0.75rem 1rem;
        }
        .lookup-group {
          margin-bottom: 1rem;
        }
        .lookup-group-title {
          font-size: 0.7rem;
          font-weight: 600;
          text-transform: uppercase;
          letter-spacing: 0.04em;
          color: var(--text-tertiary);
          padding: 0.35rem 0.5rem;
        }
        .lookup-type-item {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 0.5rem;
          width: 100%;
          text-align: left;
          border: none;
          background: transparent;
          color: var(--text-primary);
          padding: 0.45rem 0.5rem;
          border-radius: var(--radius-sm, 6px);
          cursor: pointer;
          font-size: 0.875rem;
        }
        .lookup-type-item:hover {
          background: var(--bg-secondary);
        }
        .lookup-type-item.active {
          background: var(--primary-soft, rgba(99, 102, 241, 0.12));
          color: var(--primary);
          font-weight: 500;
        }
        .lookup-type-label {
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }
        .badge-sm {
          font-size: 0.65rem;
          padding: 0.1rem 0.35rem;
          flex-shrink: 0;
        }
        .lookups-toolbar {
          display: flex;
          align-items: flex-start;
          justify-content: space-between;
          gap: 1rem;
          margin-bottom: 1rem;
        }
        .lookups-type-title {
          font-size: 1.125rem;
          font-weight: 600;
          margin: 0 0 0.35rem;
          color: var(--text-primary);
        }
        .lookups-type-meta {
          display: flex;
          flex-wrap: wrap;
          align-items: center;
          gap: 0.5rem;
        }
        .lookups-type-meta code {
          font-size: 0.75rem;
          background: var(--bg-secondary);
          padding: 0.15rem 0.4rem;
          border-radius: 4px;
        }
        .form-hint {
          display: block;
          margin-top: 0.35rem;
          color: var(--text-tertiary);
          font-size: 0.8rem;
        }
        .form-check {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          cursor: pointer;
        }
        @media (max-width: 900px) {
          .lookups-layout {
            grid-template-columns: 1fr;
          }
          .lookups-sidebar {
            position: static;
            max-height: 240px;
          }
        }
      `}</style>
    </div>
  );
};

export default LookupsPage;
