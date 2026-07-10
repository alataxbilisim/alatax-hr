import React, { useState, useEffect } from 'react';
import Modal from './Modal';
import { adminApi } from '@shared/services/api';
import toast from 'react-hot-toast';

interface LedgerModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  companyId: number;
  companyName: string;
  type: 'debit' | 'credit';
}

const LedgerModal: React.FC<LedgerModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
  companyId,
  companyName,
  type,
}) => {
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    amount: 0,
    description: '',
    payment_method: 'bank_transfer',
    payment_reference: '',
    payment_date: new Date().toISOString().split('T')[0],
    notes: '',
  });

  useEffect(() => {
    if (isOpen) {
      setFormData({
        amount: 0,
        description: '',
        payment_method: 'bank_transfer',
        payment_reference: '',
        payment_date: new Date().toISOString().split('T')[0],
        notes: '',
      });
    }
  }, [isOpen]);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const { name, value, type: inputType } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: inputType === 'number' ? parseFloat(value) || 0 : value,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      if (type === 'debit') {
        await adminApi.companies.addDebit(companyId, {
          amount: formData.amount,
          description: formData.description,
          notes: formData.notes,
        });
        toast.success('Borç kaydı eklendi');
      } else {
        await adminApi.companies.addCredit(companyId, {
          amount: formData.amount,
          description: formData.description,
          payment_method: formData.payment_method,
          payment_reference: formData.payment_reference,
          payment_date: formData.payment_date,
          notes: formData.notes,
        });
        toast.success('Ödeme kaydı eklendi');
      }
      onSuccess();
      onClose();
    } catch {
      toast.error('İşlem sırasında bir hata oluştu');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={type === 'debit' ? 'Borç Ekle' : 'Ödeme Kaydet'}
      size="md"
      footer={
        <>
          <button
            type="button"
            className="btn btn-secondary"
            onClick={onClose}
            disabled={loading}
          >
            İptal
          </button>
          <button
            type="submit"
            form="ledger-form"
            className={`btn ${type === 'debit' ? 'btn-danger' : 'btn-success'}`}
            disabled={loading}
          >
            {loading ? 'Kaydediliyor...' : type === 'debit' ? 'Borç Ekle' : 'Ödeme Kaydet'}
          </button>
        </>
      }
    >
      <form id="ledger-form" onSubmit={handleSubmit}>
        <div className="mb-3 p-3 rounded" style={{ background: 'var(--surface-glass)' }}>
          <div className="text-muted small">Firma</div>
          <div className="fw-semibold">{companyName}</div>
        </div>

        <div className="form-group">
          <label className="form-label">Tutar (₺) *</label>
          <input
            type="number"
            className="form-control"
            name="amount"
            value={formData.amount}
            onChange={handleChange}
            required
            min="0.01"
            step="0.01"
            placeholder="0.00"
          />
        </div>

        <div className="form-group">
          <label className="form-label">Açıklama *</label>
          <input
            type="text"
            className="form-control"
            name="description"
            value={formData.description}
            onChange={handleChange}
            required
            placeholder={type === 'debit' ? 'Örn: Ocak 2024 Lisans Ücreti' : 'Örn: Ocak 2024 Ödemesi'}
          />
        </div>

        {type === 'credit' && (
          <>
            <div className="form-group">
              <label className="form-label">Ödeme Yöntemi</label>
              <select
                className="form-select"
                name="payment_method"
                value={formData.payment_method}
                onChange={handleChange}
              >
                <option value="bank_transfer">Banka Havalesi</option>
                <option value="credit_card">Kredi Kartı</option>
                <option value="cash">Nakit</option>
                <option value="check">Çek</option>
                <option value="other">Diğer</option>
              </select>
            </div>

            <div className="row">
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Ödeme Referansı</label>
                  <input
                    type="text"
                    className="form-control"
                    name="payment_reference"
                    value={formData.payment_reference}
                    onChange={handleChange}
                    placeholder="Dekont No / İşlem No"
                  />
                </div>
              </div>
              <div className="col-md-6">
                <div className="form-group">
                  <label className="form-label">Ödeme Tarihi</label>
                  <input
                    type="date"
                    className="form-control"
                    name="payment_date"
                    value={formData.payment_date}
                    onChange={handleChange}
                  />
                </div>
              </div>
            </div>
          </>
        )}

        <div className="form-group">
          <label className="form-label">Notlar</label>
          <textarea
            className="form-control"
            name="notes"
            value={formData.notes}
            onChange={handleChange}
            rows={2}
            placeholder="Ek notlar..."
          />
        </div>
      </form>
    </Modal>
  );
};

export default LedgerModal;

