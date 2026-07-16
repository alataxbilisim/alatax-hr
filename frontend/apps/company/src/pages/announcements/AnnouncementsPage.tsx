import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from '@shared/i18n';
import { announcementsApi, departmentsApi, branchesApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { DataTable, ConfirmDialog, Modal, type Column } from '../../components/ui';
import { BsPlus, BsMegaphone, BsPencil, BsTrash, BsBroadcast } from 'react-icons/bs';

interface AnnouncementRow {
  id: number;
  title: string;
  summary?: string | null;
  type: string;
  is_published: boolean;
  is_for_all: boolean;
  published_at?: string | null;
  created_at: string;
}

const emptyForm = {
  title: '',
  content: '',
  summary: '',
  type: 'general',
  is_for_all: true,
  target_departments: [] as number[],
  target_branches: [] as number[],
  requires_acknowledgment: false,
  is_pinned: false,
};

const AnnouncementsPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [rows, setRows] = useState<AnnouncementRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [showModal, setShowModal] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [submitting, setSubmitting] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<AnnouncementRow | null>(null);
  const [departments, setDepartments] = useState<Array<{ id: number; name: string }>>([]);
  const [branches, setBranches] = useState<Array<{ id: number; name: string }>>([]);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await announcementsApi.list({ page, per_page: 20 });
      const data = res.data.data;
      setRows(Array.isArray(data) ? data : []);
      setTotalPages(res.data.meta?.last_page ?? 1);
    } catch {
      toast.error(t('announcementsAdmin.loadError'));
    } finally {
      setLoading(false);
    }
  }, [page, t]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    void (async () => {
      try {
        const [d, b] = await Promise.all([departmentsApi.getAll(), branchesApi.list()]);
        const deps = d.data.data;
        setDepartments(Array.isArray(deps) ? deps : []);
        const br = b.data.data;
        setBranches(Array.isArray(br) ? br : []);
      } catch {
        /* opsiyonel */
      }
    })();
  }, []);

  const openCreate = () => {
    setEditingId(null);
    setForm(emptyForm);
    setShowModal(true);
  };

  const openEdit = async (row: AnnouncementRow) => {
    try {
      const res = await announcementsApi.get(row.id);
      const a = res.data.data;
      setEditingId(row.id);
      setForm({
        title: a.title ?? '',
        content: a.content ?? '',
        summary: a.summary ?? '',
        type: a.type ?? 'general',
        is_for_all: a.is_for_all !== false,
        target_departments: Array.isArray(a.target_departments) ? a.target_departments : [],
        target_branches: Array.isArray(a.target_branches) ? a.target_branches : [],
        requires_acknowledgment: !!a.requires_acknowledgment,
        is_pinned: !!a.is_pinned,
      });
      setShowModal(true);
    } catch {
      toast.error(t('announcementsAdmin.loadError'));
    }
  };

  const handleSave = async () => {
    setSubmitting(true);
    try {
      const payload = {
        ...form,
        target_departments: form.is_for_all ? null : form.target_departments,
        target_branches: form.is_for_all ? null : form.target_branches,
      };
      if (editingId) {
        await announcementsApi.update(editingId, payload);
      } else {
        await announcementsApi.create(payload);
      }
      toast.success(t('announcementsAdmin.saveSuccess'));
      setShowModal(false);
      await load();
    } catch {
      toast.error(t('announcementsAdmin.saveError'));
    } finally {
      setSubmitting(false);
    }
  };

  const handlePublish = async (row: AnnouncementRow) => {
    try {
      await announcementsApi.publish(row.id);
      toast.success(t('announcementsAdmin.publishSuccess'));
      await load();
    } catch {
      toast.error(t('announcementsAdmin.publishError'));
    }
  };

  const columns: Column<AnnouncementRow>[] = [
    { key: 'title', title: t('announcementsAdmin.colTitle') },
    {
      key: 'status',
      title: t('announcementsAdmin.colStatus'),
      render: (row) =>
        row.is_published ? t('announcementsAdmin.published') : t('announcementsAdmin.draft'),
    },
    { key: 'type', title: t('announcementsAdmin.colType') },
    {
      key: 'actions',
      title: t('announcementsAdmin.colActions'),
      render: (row) => (
        <div style={{ display: 'flex', gap: 'var(--space-2)' }}>
          <button type="button" className="btn btn-ghost btn-sm" onClick={() => void openEdit(row)}>
            <BsPencil />
          </button>
          {!row.is_published ? (
            <button type="button" className="btn btn-secondary btn-sm" onClick={() => void handlePublish(row)}>
              <BsBroadcast /> {t('announcementsAdmin.publish')}
            </button>
          ) : null}
          <button type="button" className="btn btn-ghost btn-sm" onClick={() => setDeleteTarget(row)}>
            <BsTrash />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="animate-fade-in">
      <div className="page-header" style={{ marginBottom: 'var(--space-4)', display: 'flex', justifyContent: 'space-between' }}>
        <div>
          <h1 className="page-title">{t('announcementsAdmin.title')}</h1>
          <p className="page-subtitle">{t('announcementsAdmin.subtitle')}</p>
        </div>
        <button type="button" className="btn btn-primary" onClick={openCreate}>
          <BsPlus /> {t('announcementsAdmin.new')}
        </button>
      </div>

      <DataTable
        columns={columns}
        data={rows}
        loading={loading}
        emptyIcon={<BsMegaphone />}
        emptyMessage={t('announcementsAdmin.empty')}
        currentPage={page}
        totalPages={totalPages}
        onPageChange={setPage}
      />

      <Modal
        isOpen={showModal}
        onClose={() => setShowModal(false)}
        title={editingId ? t('announcementsAdmin.edit') : t('announcementsAdmin.new')}
        size="lg"
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-3)' }}>
          <input
            className="form-control"
            placeholder={t('announcementsAdmin.colTitle')}
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
          />
          <textarea
            className="form-control"
            rows={3}
            placeholder={t('announcementsAdmin.summary')}
            value={form.summary}
            onChange={(e) => setForm({ ...form, summary: e.target.value })}
          />
          <textarea
            className="form-control"
            rows={8}
            placeholder={t('announcementsAdmin.content')}
            value={form.content}
            onChange={(e) => setForm({ ...form, content: e.target.value })}
          />
          <select
            className="form-control"
            value={form.type}
            onChange={(e) => setForm({ ...form, type: e.target.value })}
          >
            <option value="general">{t('announcementsAdmin.typeGeneral')}</option>
            <option value="important">{t('announcementsAdmin.typeImportant')}</option>
            <option value="urgent">{t('announcementsAdmin.typeUrgent')}</option>
            <option value="info">{t('announcementsAdmin.typeInfo')}</option>
          </select>
          <label style={{ display: 'flex', gap: 'var(--space-2)', alignItems: 'center' }}>
            <input
              type="checkbox"
              checked={form.is_for_all}
              onChange={(e) => setForm({ ...form, is_for_all: e.target.checked })}
            />
            {t('announcementsAdmin.forAll')}
          </label>
          {!form.is_for_all ? (
            <>
              <label className="form-label">{t('announcementsAdmin.targetDepartments')}</label>
              <select
                className="form-control"
                multiple
                value={form.target_departments.map(String)}
                onChange={(e) =>
                  setForm({
                    ...form,
                    target_departments: Array.from(e.target.selectedOptions).map((o) => Number(o.value)),
                  })
                }
              >
                {departments.map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
              <label className="form-label">{t('announcementsAdmin.targetBranches')}</label>
              <select
                className="form-control"
                multiple
                value={form.target_branches.map(String)}
                onChange={(e) =>
                  setForm({
                    ...form,
                    target_branches: Array.from(e.target.selectedOptions).map((o) => Number(o.value)),
                  })
                }
              >
                {branches.map((b) => (
                  <option key={b.id} value={b.id}>{b.name}</option>
                ))}
              </select>
            </>
          ) : null}
          <button type="button" className="btn btn-primary" disabled={submitting} onClick={() => void handleSave()}>
            {t('save')}
          </button>
        </div>
      </Modal>

      <ConfirmDialog
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => {
          void (async () => {
            if (!deleteTarget) return;
            try {
              await announcementsApi.delete(deleteTarget.id);
              toast.success(t('announcementsAdmin.deleteSuccess'));
              setDeleteTarget(null);
              await load();
            } catch {
              toast.error(t('announcementsAdmin.deleteError'));
            }
          })();
        }}
        title={t('announcementsAdmin.deleteTitle')}
        message={t('announcementsAdmin.deleteConfirm')}
      />
    </div>
  );
};

export default AnnouncementsPage;
