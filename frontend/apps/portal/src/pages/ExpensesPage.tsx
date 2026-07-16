import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { portalApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { Select } from '@shared/components';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import { BsPlus, BsReceipt, BsX, BsTrash } from 'react-icons/bs';

interface ExpenseCategory {
  id: number;
  name: string;
  code: string | null;
  max_amount: number | null;
  requires_receipt: boolean;
}

interface ExpenseClaim {
  id: number;
  title: string;
  claim_number: string;
  expense_date: string;
  total_amount: number;
  currency: string;
  status: string;
  items_count: number;
  created_at: string;
}

interface ExpenseItem {
  expense_category_id: string;
  description: string;
  item_date: string;
  amount: string;
  vendor_name: string;
}

interface ExpenseSummary {
  pending_count: number;
  pending_amount: number;
  approved_this_month: number;
  paid_this_month: number;
}

const statusClassMap: Record<string, string> = {
  draft: 'cancelled',
  submitted: 'pending',
  approved: 'approved',
  rejected: 'rejected',
  paid: 'approved',
};

const ExpensesPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [claims, setClaims] = useState<ExpenseClaim[]>([]);
  const [categories, setCategories] = useState<ExpenseCategory[]>([]);
  const [statusLookups, setStatusLookups] = useState<LookupItem[]>([]);
  const [summary, setSummary] = useState<ExpenseSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const [formData, setFormData] = useState({
    title: '',
    description: '',
    expense_date: new Date().toISOString().split('T')[0],
  });
  const [items, setItems] = useState<ExpenseItem[]>([{
    expense_category_id: '',
    description: '',
    item_date: new Date().toISOString().split('T')[0],
    amount: '',
    vendor_name: '',
  }]);

  useEffect(() => {
    void loadData();
  }, []);

  const loadData = async () => {
    try {
      const [claimsRes, categoriesRes, summaryRes, statusRes] = await Promise.all([
        portalApi.expenses.list(),
        portalApi.expenses.categories(),
        portalApi.expenses.summary(),
        lookupsApi.forType('expense_claim_status'),
      ]);
      setClaims(claimsRes.data.data.data || []);
      setCategories(categoriesRes.data.data || []);
      setSummary(summaryRes.data.data);
      setStatusLookups(statusRes.data.data ?? []);
    } catch {
      toast.error('Veriler yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const getStatusBadge = (status: string) => {
    const label = statusLookups.find((o) => o.value === status)?.label || status;
    const className = statusClassMap[status] || '';
    return <span className={`request-status ${className}`}>{label}</span>;
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
  };

  const addItem = () => {
    setItems([...items, {
      expense_category_id: '',
      description: '',
      item_date: new Date().toISOString().split('T')[0],
      amount: '',
      vendor_name: '',
    }]);
  };

  const removeItem = (index: number) => {
    if (items.length > 1) {
      setItems(items.filter((_, i) => i !== index));
    }
  };

  const updateItem = (index: number, field: keyof ExpenseItem, value: string) => {
    const newItems = [...items];
    newItems[index] = { ...newItems[index], [field]: value };
    setItems(newItems);
  };

  const handleSubmit = async (e: React.FormEvent, asDraft: boolean = true) => {
    e.preventDefault();
    if (!formData.title || !formData.expense_date) {
      toast.error('Lütfen başlık ve tarih alanlarını doldurun');
      return;
    }

    const validItems = items.filter((item) =>
      item.expense_category_id && item.description && item.amount
    );

    if (validItems.length === 0) {
      toast.error('En az bir masraf kalemi eklemelisiniz');
      return;
    }

    setSubmitting(true);
    try {
      const data = {
        ...formData,
        items: validItems.map((item) => ({
          ...item,
          expense_category_id: Number(item.expense_category_id),
          amount: parseFloat(item.amount),
        })),
      };

      const response = await portalApi.expenses.create(data);

      if (!asDraft) {
        await portalApi.expenses.submit(response.data.data.id);
      }

      toast.success(asDraft ? 'Masraf talebi kaydedildi' : 'Masraf talebi gönderildi');
      setShowModal(false);
      resetForm();
      void loadData();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err.response?.data?.message || 'Masraf talebi oluşturulamadı');
    } finally {
      setSubmitting(false);
    }
  };

  const resetForm = () => {
    setFormData({
      title: '',
      description: '',
      expense_date: new Date().toISOString().split('T')[0],
    });
    setItems([{
      expense_category_id: '',
      description: '',
      item_date: new Date().toISOString().split('T')[0],
      amount: '',
      vendor_name: '',
    }]);
  };

  const handleCancel = async (id: number) => {
    if (!confirm('Bu masraf talebini iptal etmek istediğinize emin misiniz?')) return;
    try {
      await portalApi.expenses.cancel(id);
      toast.success('Masraf talebi iptal edildi');
      void loadData();
    } catch {
      toast.error('İptal işlemi başarısız');
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Masraflarım</h1>
          <p className="page-subtitle">Masraf taleplerinizi oluşturun ve takip edin</p>
        </div>
        <div className="page-header-actions">
          <button className="btn btn-primary" onClick={() => setShowModal(true)}>
            <BsPlus size={20} /> Yeni Masraf
          </button>
          <Link to="/expenses/form-engine" className="btn btn-outline-secondary btn-sm ms-2">
            {t('formEngine.openFormEngine')}
          </Link>
        </div>
      </div>

      {summary && (
        <div className="row mb-4">
          <div className="col-6 col-lg-3 mb-3">
            <div className="balance-card">
              <div className="balance-info">
                <div className="balance-type">Bekleyen</div>
                <div className="balance-value">{summary.pending_count}</div>
                <div className="balance-label">{formatCurrency(summary.pending_amount)}</div>
              </div>
            </div>
          </div>
          <div className="col-6 col-lg-3 mb-3">
            <div className="balance-card">
              <div className="balance-info">
                <div className="balance-type">Bu Ay Onaylanan</div>
                <div className="balance-value" style={{ fontSize: '1rem' }}>{formatCurrency(summary.approved_this_month)}</div>
              </div>
            </div>
          </div>
          <div className="col-6 col-lg-3 mb-3">
            <div className="balance-card">
              <div className="balance-info">
                <div className="balance-type">Bu Ay Ödenen</div>
                <div className="balance-value" style={{ fontSize: '1rem' }}>{formatCurrency(summary.paid_this_month)}</div>
              </div>
            </div>
          </div>
        </div>
      )}

      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="page-loading">
              <div className="loading-spinner"></div>
            </div>
          ) : claims.length > 0 ? (
            <>
              <div className="table-responsive desktop-only">
                <table className="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>Talep No</th>
                      <th>Başlık</th>
                      <th>Tarih</th>
                      <th>Tutar</th>
                      <th>Kalem</th>
                      <th>Durum</th>
                      <th>İşlem</th>
                    </tr>
                  </thead>
                  <tbody>
                    {claims.map((claim) => (
                      <tr key={claim.id}>
                        <td><code>{claim.claim_number}</code></td>
                        <td>{claim.title}</td>
                        <td>{new Date(claim.expense_date).toLocaleDateString('tr-TR')}</td>
                        <td className="fw-semibold">{formatCurrency(claim.total_amount)}</td>
                        <td>{claim.items_count}</td>
                        <td>{getStatusBadge(claim.status)}</td>
                        <td>
                          {(claim.status === 'draft' || claim.status === 'submitted') && (
                            <button
                              className="btn btn-sm btn-ghost text-danger"
                              onClick={() => void handleCancel(claim.id)}
                            >
                              İptal
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="mobile-card-list has-data">
                {claims.map((claim) => (
                  <div key={claim.id} className="mobile-card">
                    <div className="mobile-card-header">
                      <div>
                        <div className="mobile-card-title">{claim.title}</div>
                        <div className="mobile-card-subtitle">{claim.claim_number}</div>
                      </div>
                      {getStatusBadge(claim.status)}
                    </div>
                    <div className="mobile-card-body">
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">Tutar</span>
                        <span className="mobile-card-value text-primary fw-semibold">
                          {formatCurrency(claim.total_amount)}
                        </span>
                      </div>
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">Tarih</span>
                        <span className="mobile-card-value">
                          {new Date(claim.expense_date).toLocaleDateString('tr-TR')}
                        </span>
                      </div>
                      <div className="mobile-card-row">
                        <span className="mobile-card-label">Kalem Sayısı</span>
                        <span className="mobile-card-value">{claim.items_count}</span>
                      </div>
                    </div>
                    {(claim.status === 'draft' || claim.status === 'submitted') && (
                      <div className="mobile-card-footer">
                        <button
                          className="btn btn-outline-primary btn-sm"
                          onClick={() => void handleCancel(claim.id)}
                        >
                          İptal Et
                        </button>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </>
          ) : (
            <div className="empty-state">
              <BsReceipt size={64} className="text-muted mb-3" />
              <h3>Henüz masraf talebiniz yok</h3>
              <p>Yeni bir masraf talebi oluşturarak başlayın</p>
              <button className="btn btn-primary" onClick={() => setShowModal(true)}>
                <BsPlus /> İlk Masraf Talebinizi Oluşturun
              </button>
            </div>
          )}
        </div>
      </div>

      <button className="fab" onClick={() => setShowModal(true)} aria-label="Yeni Masraf">
        <BsPlus size={24} />
      </button>

      {showModal && (
        <div className="modal-mobile open">
          <div className="modal-mobile-header">
            <h3 className="modal-mobile-title">Yeni Masraf Talebi</h3>
            <button className="modal-mobile-close" onClick={() => setShowModal(false)}>
              <BsX size={24} />
            </button>
          </div>
          <form onSubmit={(e) => void handleSubmit(e, true)}>
            <div className="modal-mobile-body">
              <div className="mb-3">
                <label className="form-label">Başlık *</label>
                <input
                  type="text"
                  className="form-control"
                  value={formData.title}
                  onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                  placeholder="Örn: Ocak 2025 Seyahat Masrafları"
                  required
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Tarih *</label>
                <input
                  type="date"
                  className="form-control"
                  value={formData.expense_date}
                  onChange={(e) => setFormData({ ...formData, expense_date: e.target.value })}
                  required
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Açıklama</label>
                <textarea
                  className="form-control"
                  rows={2}
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  placeholder="Opsiyonel açıklama"
                />
              </div>

              <hr />
              <h4 style={{ fontSize: '1rem', marginBottom: '1rem' }}>Masraf Kalemleri</h4>

              {items.map((item, index) => (
                <div key={index} className="card mb-3" style={{ background: 'var(--portal-bg)' }}>
                  <div className="card-body" style={{ padding: '0.75rem' }}>
                    <div className="d-flex justify-content-between align-items-center mb-2">
                      <strong>Kalem {index + 1}</strong>
                      {items.length > 1 && (
                        <button
                          type="button"
                          className="btn btn-sm btn-ghost text-danger"
                          onClick={() => removeItem(index)}
                        >
                          <BsTrash />
                        </button>
                      )}
                    </div>
                    <div className="mb-2">
                      <Select
                        value={item.expense_category_id}
                        onChange={(v) => updateItem(index, 'expense_category_id', v)}
                        options={categories.map((cat) => ({
                          value: String(cat.id),
                          label: cat.name,
                        }))}
                        allowEmpty
                        placeholder="Kategori Seçin *"
                        aria-label={`Masraf kategorisi ${index + 1}`}
                      />
                    </div>
                    <div className="mb-2">
                      <input
                        type="text"
                        className="form-control"
                        value={item.description}
                        onChange={(e) => updateItem(index, 'description', e.target.value)}
                        placeholder="Açıklama *"
                        required
                      />
                    </div>
                    <div className="row">
                      <div className="col-6 mb-2">
                        <input
                          type="date"
                          className="form-control"
                          value={item.item_date}
                          onChange={(e) => updateItem(index, 'item_date', e.target.value)}
                        />
                      </div>
                      <div className="col-6 mb-2">
                        <input
                          type="number"
                          step="0.01"
                          min="0.01"
                          className="form-control"
                          value={item.amount}
                          onChange={(e) => updateItem(index, 'amount', e.target.value)}
                          placeholder="Tutar *"
                          required
                        />
                      </div>
                    </div>
                    <div>
                      <input
                        type="text"
                        className="form-control"
                        value={item.vendor_name}
                        onChange={(e) => updateItem(index, 'vendor_name', e.target.value)}
                        placeholder="Satıcı/Tedarikçi"
                      />
                    </div>
                  </div>
                </div>
              ))}

              <button
                type="button"
                className="btn btn-outline-primary btn-sm btn-block"
                onClick={addItem}
              >
                <BsPlus /> Kalem Ekle
              </button>
            </div>
            <div className="modal-mobile-footer">
              <button
                type="button"
                className="btn btn-outline-primary"
                onClick={() => setShowModal(false)}
              >
                İptal
              </button>
              <button
                type="submit"
                className="btn btn-outline-primary"
                disabled={submitting}
              >
                Taslak Kaydet
              </button>
              <button
                type="button"
                className="btn btn-primary"
                disabled={submitting}
                onClick={(e) => void handleSubmit(e, false)}
              >
                {submitting ? 'Gönderiliyor...' : 'Gönder'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
};

export default ExpensesPage;
