import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { expensesApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { Select } from '@shared/components';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import { DataTable, ConfirmDialog, Modal } from '../../components/ui';
import { BsCheck, BsX, BsCashCoin, BsPlus, BsPencil, BsTrash } from 'react-icons/bs';

interface ExpenseClaimRow {
  id: number;
  claim_number?: string;
  title: string;
  total_amount: number | string;
  currency?: string;
  status: string;
  expense_date?: string;
  user?: { id: number; name: string };
  items_count?: number;
}

interface ExpenseCategoryRow {
  id: number;
  name: string;
  code?: string | null;
  max_amount?: number | string | null;
  requires_receipt: boolean;
  is_active: boolean;
}

type TabType = 'queue' | 'all' | 'categories';

const ExpensesPage: React.FC = () => {
  const { t } = useTranslation('common');
  const location = useLocation();
  const navigate = useNavigate();

  const activeTab: TabType = useMemo(() => {
    if (location.pathname.includes('/expenses/categories')) return 'categories';
    if (location.pathname.includes('/expenses/all')) return 'all';
    return 'queue';
  }, [location.pathname]);

  const [claims, setClaims] = useState<ExpenseClaimRow[]>([]);
  const [claimsLoading, setClaimsLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [statusFilter, setStatusFilter] = useState('');
  const [statusOptions, setStatusOptions] = useState<LookupItem[]>([]);
  const [actionLoading, setActionLoading] = useState<number | null>(null);

  const [rejectOpen, setRejectOpen] = useState(false);
  const [rejectTarget, setRejectTarget] = useState<ExpenseClaimRow | null>(null);
  const [rejectReason, setRejectReason] = useState('');

  const [paidOpen, setPaidOpen] = useState(false);
  const [paidTarget, setPaidTarget] = useState<ExpenseClaimRow | null>(null);
  const [paymentReference, setPaymentReference] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('');

  const [categories, setCategories] = useState<ExpenseCategoryRow[]>([]);
  const [categoriesLoading, setCategoriesLoading] = useState(false);
  const [categoryModalOpen, setCategoryModalOpen] = useState(false);
  const [editingCategory, setEditingCategory] = useState<ExpenseCategoryRow | null>(null);
  const [categoryForm, setCategoryForm] = useState({
    name: '',
    code: '',
    max_amount: '',
    requires_receipt: false,
    is_active: true,
  });
  const [deleteCategoryOpen, setDeleteCategoryOpen] = useState(false);
  const [categoryToDelete, setCategoryToDelete] = useState<ExpenseCategoryRow | null>(null);

  const handleTabChange = (tab: TabType) => {
    const paths: Record<TabType, string> = {
      queue: '/expenses',
      all: '/expenses/all',
      categories: '/expenses/categories',
    };
    navigate(paths[tab]);
  };

  const loadClaims = useCallback(async () => {
    try {
      setClaimsLoading(true);
      const params: Record<string, unknown> = { page, per_page: 15 };
      if (activeTab === 'queue') {
        params.status = 'submitted';
      } else if (statusFilter) {
        params.status = statusFilter;
      }
      const response = await expensesApi.claims.list(params);
      const data = response.data.data;
      setClaims(data.data || []);
      setTotalPages(data.last_page || 1);
    } catch {
      toast.error(t('expenses.loadFailed'));
    } finally {
      setClaimsLoading(false);
    }
  }, [page, statusFilter, activeTab, t]);

  const loadCategories = useCallback(async () => {
    try {
      setCategoriesLoading(true);
      const response = await expensesApi.categories.list({ per_page: 100 });
      setCategories(response.data.data || []);
    } catch {
      toast.error(t('expenses.loadFailed'));
    } finally {
      setCategoriesLoading(false);
    }
  }, [t]);

  useEffect(() => {
    lookupsApi
      .forType('expense_claim_status')
      .then((res) => setStatusOptions(res.data.data ?? []))
      .catch(() => undefined);
  }, []);

  useEffect(() => {
    if (activeTab === 'categories') {
      void loadCategories();
    } else {
      void loadClaims();
    }
  }, [activeTab, loadClaims, loadCategories]);

  const handleApprove = async (claim: ExpenseClaimRow) => {
    try {
      setActionLoading(claim.id);
      await expensesApi.claims.approve(claim.id);
      toast.success(t('expenses.approved'));
      await loadClaims();
    } catch {
      toast.error(t('expenses.actionFailed'));
    } finally {
      setActionLoading(null);
    }
  };

  const handleReject = async () => {
    if (!rejectTarget || rejectReason.trim().length < 3) {
      toast.error(t('expenses.rejectReasonRequired'));
      return;
    }
    try {
      setActionLoading(rejectTarget.id);
      await expensesApi.claims.reject(rejectTarget.id, { reason: rejectReason.trim() });
      toast.success(t('expenses.rejected'));
      setRejectOpen(false);
      setRejectTarget(null);
      setRejectReason('');
      await loadClaims();
    } catch {
      toast.error(t('expenses.actionFailed'));
    } finally {
      setActionLoading(null);
    }
  };

  const handleMarkPaid = async () => {
    if (!paidTarget) return;
    try {
      setActionLoading(paidTarget.id);
      await expensesApi.claims.markPaid(paidTarget.id, {
        payment_reference: paymentReference || undefined,
        payment_method: paymentMethod || undefined,
      });
      toast.success(t('expenses.paid'));
      setPaidOpen(false);
      setPaidTarget(null);
      setPaymentReference('');
      setPaymentMethod('');
      await loadClaims();
    } catch {
      toast.error(t('expenses.actionFailed'));
    } finally {
      setActionLoading(null);
    }
  };

  const openCategoryModal = (category?: ExpenseCategoryRow) => {
    if (category) {
      setEditingCategory(category);
      setCategoryForm({
        name: category.name,
        code: category.code || '',
        max_amount: category.max_amount != null ? String(category.max_amount) : '',
        requires_receipt: category.requires_receipt,
        is_active: category.is_active,
      });
    } else {
      setEditingCategory(null);
      setCategoryForm({
        name: '',
        code: '',
        max_amount: '',
        requires_receipt: false,
        is_active: true,
      });
    }
    setCategoryModalOpen(true);
  };

  const saveCategory = async () => {
    if (!categoryForm.name.trim()) return;
    try {
      const payload = {
        name: categoryForm.name.trim(),
        code: categoryForm.code.trim() || null,
        max_amount: categoryForm.max_amount ? Number(categoryForm.max_amount) : null,
        requires_receipt: categoryForm.requires_receipt,
        is_active: categoryForm.is_active,
      };
      if (editingCategory) {
        await expensesApi.categories.update(editingCategory.id, payload);
        toast.success(t('expenses.categoryUpdated'));
      } else {
        await expensesApi.categories.create(payload);
        toast.success(t('expenses.categoryCreated'));
      }
      setCategoryModalOpen(false);
      await loadCategories();
    } catch {
      toast.error(t('expenses.categorySaveFailed'));
    }
  };

  const deleteCategory = async () => {
    if (!categoryToDelete) return;
    try {
      await expensesApi.categories.delete(categoryToDelete.id);
      toast.success(t('expenses.categoryDeleted'));
      setDeleteCategoryOpen(false);
      setCategoryToDelete(null);
      await loadCategories();
    } catch {
      toast.error(t('expenses.categoryDeleteFailed'));
    }
  };

  const statusLabel = (value: string) =>
    statusOptions.find((o) => o.value === value)?.label || value;

  const claimColumns = [
    {
      key: 'claim_number',
      title: t('expenses.claimNumber'),
      render: (row: ExpenseClaimRow) => row.claim_number || `#${row.id}`,
    },
    {
      key: 'user',
      title: t('expenses.employee'),
      render: (row: ExpenseClaimRow) => row.user?.name || '—',
    },
    {
      key: 'title',
      title: t('expenses.titleCol'),
      render: (row: ExpenseClaimRow) => row.title,
    },
    {
      key: 'total_amount',
      title: t('expenses.amount'),
      render: (row: ExpenseClaimRow) =>
        `${Number(row.total_amount).toLocaleString('tr-TR', { minimumFractionDigits: 2 })} ${row.currency || 'TRY'}`,
    },
    {
      key: 'status',
      title: t('expenses.status'),
      render: (row: ExpenseClaimRow) => statusLabel(row.status),
    },
    {
      key: 'actions',
      title: '',
      render: (row: ExpenseClaimRow) => (
        <div style={{ display: 'flex', gap: '0.35rem' }}>
          {row.status === 'submitted' && (
            <>
              <button
                type="button"
                className="btn btn-sm btn-success"
                disabled={actionLoading === row.id}
                onClick={() => void handleApprove(row)}
                title={t('expenses.approve')}
              >
                <BsCheck />
              </button>
              <button
                type="button"
                className="btn btn-sm btn-danger"
                disabled={actionLoading === row.id}
                onClick={() => {
                  setRejectTarget(row);
                  setRejectReason('');
                  setRejectOpen(true);
                }}
                title={t('expenses.reject')}
              >
                <BsX />
              </button>
            </>
          )}
          {row.status === 'approved' && (
            <button
              type="button"
              className="btn btn-sm btn-primary"
              disabled={actionLoading === row.id}
              onClick={() => {
                setPaidTarget(row);
                setPaymentReference('');
                setPaymentMethod('');
                setPaidOpen(true);
              }}
              title={t('expenses.markPaid')}
            >
              <BsCashCoin />
            </button>
          )}
        </div>
      ),
    },
  ];

  const categoryColumns = [
    { key: 'name', title: t('expenses.categoryName'), render: (row: ExpenseCategoryRow) => row.name },
    { key: 'code', title: t('expenses.categoryCode'), render: (row: ExpenseCategoryRow) => row.code || '—' },
    {
      key: 'max_amount',
      title: t('expenses.maxAmount'),
      render: (row: ExpenseCategoryRow) =>
        row.max_amount != null ? Number(row.max_amount).toLocaleString('tr-TR') : '—',
    },
    {
      key: 'requires_receipt',
      title: t('expenses.requiresReceipt'),
      render: (row: ExpenseCategoryRow) => (row.requires_receipt ? t('attendance.yes') : t('attendance.no')),
    },
    {
      key: 'is_active',
      title: t('expenses.active'),
      render: (row: ExpenseCategoryRow) => (row.is_active ? t('attendance.yes') : t('attendance.no')),
    },
    {
      key: 'actions',
      title: '',
      render: (row: ExpenseCategoryRow) => (
        <div style={{ display: 'flex', gap: '0.35rem' }}>
          <button type="button" className="btn btn-sm btn-secondary" onClick={() => openCategoryModal(row)}>
            <BsPencil />
          </button>
          <button
            type="button"
            className="btn btn-sm btn-danger"
            onClick={() => {
              setCategoryToDelete(row);
              setDeleteCategoryOpen(true);
            }}
          >
            <BsTrash />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="page-container">
      <div className="page-header" style={{ marginBottom: '1rem' }}>
        <h1 style={{ margin: 0 }}>{t('expenses.title')}</h1>
      </div>

      <div className="tabs" style={{ display: 'flex', gap: '0.5rem', marginBottom: '1rem' }}>
        {(
          [
            ['queue', 'expenses.queue'],
            ['all', 'expenses.all'],
            ['categories', 'expenses.categories'],
          ] as const
        ).map(([key, labelKey]) => (
          <button
            key={key}
            type="button"
            className={`btn btn-sm ${activeTab === key ? 'btn-primary' : 'btn-secondary'}`}
            onClick={() => handleTabChange(key)}
          >
            {t(labelKey)}
          </button>
        ))}
      </div>

      {activeTab !== 'categories' && (
        <>
          {activeTab === 'all' && (
            <div style={{ marginBottom: '1rem', maxWidth: 240 }}>
              <Select
                allowEmpty
                emptyLabel={t('expenses.status')}
                options={statusOptions.map((o) => ({ value: o.value, label: o.label }))}
                value={statusFilter}
                onChange={(v) => {
                  setStatusFilter(v);
                  setPage(1);
                }}
              />
            </div>
          )}
          <DataTable
            columns={claimColumns}
            data={claims}
            loading={claimsLoading}
            emptyMessage={t('expenses.emptyClaims')}
            currentPage={page}
            totalPages={totalPages}
            onPageChange={setPage}
          />
        </>
      )}

      {activeTab === 'categories' && (
        <>
          <div style={{ marginBottom: '1rem' }}>
            <button type="button" className="btn btn-primary" onClick={() => openCategoryModal()}>
              <BsPlus /> {t('expenses.newCategory')}
            </button>
          </div>
          <DataTable
            columns={categoryColumns}
            data={categories}
            loading={categoriesLoading}
            emptyMessage={t('expenses.emptyCategories')}
          />
        </>
      )}

      <Modal
        isOpen={rejectOpen}
        onClose={() => setRejectOpen(false)}
        title={t('expenses.reject')}
      >
        <div className="form-group">
          <label className="form-label">{t('expenses.rejectReason')}</label>
          <textarea
            className="form-control"
            rows={3}
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
          />
        </div>
        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1rem' }}>
          <button type="button" className="btn btn-secondary" onClick={() => setRejectOpen(false)}>
            {t('cancel')}
          </button>
          <button type="button" className="btn btn-danger" onClick={() => void handleReject()}>
            {t('expenses.reject')}
          </button>
        </div>
      </Modal>

      <Modal isOpen={paidOpen} onClose={() => setPaidOpen(false)} title={t('expenses.markPaid')}>
        <div className="form-group">
          <label className="form-label">{t('expenses.paymentMethod')}</label>
          <input
            className="form-control"
            value={paymentMethod}
            onChange={(e) => setPaymentMethod(e.target.value)}
          />
        </div>
        <div className="form-group">
          <label className="form-label">{t('expenses.paymentReference')}</label>
          <input
            className="form-control"
            value={paymentReference}
            onChange={(e) => setPaymentReference(e.target.value)}
          />
        </div>
        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1rem' }}>
          <button type="button" className="btn btn-secondary" onClick={() => setPaidOpen(false)}>
            {t('cancel')}
          </button>
          <button type="button" className="btn btn-primary" onClick={() => void handleMarkPaid()}>
            {t('expenses.markPaid')}
          </button>
        </div>
      </Modal>

      <Modal
        isOpen={categoryModalOpen}
        onClose={() => setCategoryModalOpen(false)}
        title={editingCategory ? t('expenses.editCategory') : t('expenses.newCategory')}
      >
        <div className="form-group">
          <label className="form-label">{t('expenses.categoryName')}</label>
          <input
            className="form-control"
            value={categoryForm.name}
            onChange={(e) => setCategoryForm((f) => ({ ...f, name: e.target.value }))}
          />
        </div>
        <div className="form-group">
          <label className="form-label">{t('expenses.categoryCode')}</label>
          <input
            className="form-control"
            value={categoryForm.code}
            onChange={(e) => setCategoryForm((f) => ({ ...f, code: e.target.value }))}
          />
        </div>
        <div className="form-group">
          <label className="form-label">{t('expenses.maxAmount')}</label>
          <input
            className="form-control"
            type="number"
            value={categoryForm.max_amount}
            onChange={(e) => setCategoryForm((f) => ({ ...f, max_amount: e.target.value }))}
          />
        </div>
        <label style={{ display: 'flex', gap: '0.5rem', alignItems: 'center', marginBottom: '0.5rem' }}>
          <input
            type="checkbox"
            checked={categoryForm.requires_receipt}
            onChange={(e) => setCategoryForm((f) => ({ ...f, requires_receipt: e.target.checked }))}
          />
          {t('expenses.requiresReceipt')}
        </label>
        <label style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
          <input
            type="checkbox"
            checked={categoryForm.is_active}
            onChange={(e) => setCategoryForm((f) => ({ ...f, is_active: e.target.checked }))}
          />
          {t('expenses.active')}
        </label>
        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1rem' }}>
          <button type="button" className="btn btn-secondary" onClick={() => setCategoryModalOpen(false)}>
            {t('cancel')}
          </button>
          <button type="button" className="btn btn-primary" onClick={() => void saveCategory()}>
            {t('save')}
          </button>
        </div>
      </Modal>

      <ConfirmDialog
        isOpen={deleteCategoryOpen}
        onClose={() => setDeleteCategoryOpen(false)}
        onConfirm={() => void deleteCategory()}
        title={t('expenses.editCategory')}
        message={t('expenses.deleteCategory')}
      />
    </div>
  );
};

export default ExpensesPage;
