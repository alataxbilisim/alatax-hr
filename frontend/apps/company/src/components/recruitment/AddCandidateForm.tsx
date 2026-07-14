import React, { useEffect, useState } from 'react';
import { useTranslation } from '@shared/i18n';
import { recruitmentApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { Modal } from '../ui';

interface PositionOption {
  id: number;
  title: string;
}

interface AddCandidateFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const AddCandidateForm: React.FC<AddCandidateFormProps> = ({ isOpen, onClose, onSuccess }) => {
  const { t } = useTranslation('common');
  const [loading, setLoading] = useState(false);
  const [positions, setPositions] = useState<PositionOption[]>([]);
  const [form, setForm] = useState({
    job_position_id: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    notes: '',
    consent_kvkk: false,
  });

  useEffect(() => {
    if (!isOpen) return;

    recruitmentApi.positions
      .list({ per_page: 100 })
      .then((res) => {
        const data = res.data.data;
        const rows = Array.isArray(data) ? data : (data?.data ?? []);
        setPositions(
          (rows as PositionOption[]).map((p) => ({
            id: p.id,
            title: p.title,
          }))
        );
      })
      .catch(() => toast.error(t('recruitment.positionsLoadFailed')));
  }, [isOpen, t]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.consent_kvkk) {
      toast.error(t('recruitment.consentRequired'));
      return;
    }
    if (!form.job_position_id) {
      toast.error(t('recruitment.positionRequired'));
      return;
    }

    setLoading(true);
    try {
      await recruitmentApi.applications.create({
        job_position_id: Number(form.job_position_id),
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        email: form.email.trim(),
        phone: form.phone.trim() || null,
        notes: form.notes.trim() || null,
        consent_kvkk: true,
        source: 'manual',
      });
      toast.success(t('recruitment.candidateAdded'));
      setForm({
        job_position_id: '',
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        notes: '',
        consent_kvkk: false,
      });
      onSuccess();
      onClose();
    } catch {
      toast.error(t('recruitment.candidateAddFailed'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={t('recruitment.addCandidate')} size="md">
      <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 'var(--sp-3)' }}>
        <div>
          <label className="form-label" htmlFor="add-cand-position">
            {t('recruitment.position')} *
          </label>
          <select
            id="add-cand-position"
            className="form-input"
            value={form.job_position_id}
            onChange={(e) => setForm({ ...form, job_position_id: e.target.value })}
            required
          >
            <option value="">{t('recruitment.selectPosition')}</option>
            {positions.map((p) => (
              <option key={p.id} value={p.id}>
                {p.title}
              </option>
            ))}
          </select>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--sp-3)' }}>
          <div>
            <label className="form-label" htmlFor="add-cand-first">
              {t('recruitment.firstName')} *
            </label>
            <input
              id="add-cand-first"
              className="form-input"
              value={form.first_name}
              onChange={(e) => setForm({ ...form, first_name: e.target.value })}
              required
            />
          </div>
          <div>
            <label className="form-label" htmlFor="add-cand-last">
              {t('recruitment.lastName')} *
            </label>
            <input
              id="add-cand-last"
              className="form-input"
              value={form.last_name}
              onChange={(e) => setForm({ ...form, last_name: e.target.value })}
              required
            />
          </div>
        </div>

        <div>
          <label className="form-label" htmlFor="add-cand-email">
            {t('recruitment.email')} *
          </label>
          <input
            id="add-cand-email"
            type="email"
            className="form-input"
            value={form.email}
            onChange={(e) => setForm({ ...form, email: e.target.value })}
            required
          />
        </div>

        <div>
          <label className="form-label" htmlFor="add-cand-phone">
            {t('recruitment.phone')}
          </label>
          <input
            id="add-cand-phone"
            className="form-input"
            value={form.phone}
            onChange={(e) => setForm({ ...form, phone: e.target.value })}
          />
        </div>

        <div>
          <label className="form-label" htmlFor="add-cand-notes">
            {t('recruitment.notes')}
          </label>
          <textarea
            id="add-cand-notes"
            className="form-input"
            rows={3}
            value={form.notes}
            onChange={(e) => setForm({ ...form, notes: e.target.value })}
          />
        </div>

        <label style={{ display: 'flex', alignItems: 'flex-start', gap: 'var(--sp-2)', fontSize: 'var(--fs-body)' }}>
          <input
            type="checkbox"
            checked={form.consent_kvkk}
            onChange={(e) => setForm({ ...form, consent_kvkk: e.target.checked })}
            required
          />
          <span>{t('recruitment.kvkkConsent')}</span>
        </label>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 'var(--sp-2)', marginTop: 'var(--sp-2)' }}>
          <button type="button" className="btn btn-ghost" onClick={onClose} disabled={loading}>
            {t('cancel')}
          </button>
          <button type="submit" className="btn btn-primary" disabled={loading}>
            {loading ? t('loading') : t('recruitment.addCandidate')}
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default AddCandidateForm;
