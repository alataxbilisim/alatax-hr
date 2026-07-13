import React, { useCallback, useEffect, useState } from 'react';
import { usersApi } from '@shared/services/api';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';
import { Modal } from './ui';
import { getErrorMessage } from '@shared/services/apiHelpers';

const PANEL_ROLES = [
  { value: 'hr_specialist', labelKey: 'users.panelRoles.hr_specialist' },
  { value: 'hr_manager', labelKey: 'users.panelRoles.hr_manager' },
  { value: 'manager', labelKey: 'users.panelRoles.manager' },
  { value: 'admin', labelKey: 'users.panelRoles.admin' },
] as const;

interface Candidate {
  id: number;
  name: string;
  email: string;
  employee?: { id: number; employee_code?: string } | null;
}

interface GrantPanelAccessModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const GrantPanelAccessModal: React.FC<GrantPanelAccessModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
}) => {
  const { t } = useTranslation('common');
  const [candidates, setCandidates] = useState<Candidate[]>([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [search, setSearch] = useState('');
  const [selectedUserId, setSelectedUserId] = useState<number | null>(null);
  const [role, setRole] = useState<string>('hr_specialist');

  const loadCandidates = useCallback(async () => {
    setLoading(true);
    try {
      const params: Record<string, unknown> = { per_page: 50 };
      if (search.trim()) params.search = search.trim();
      const response = await usersApi.portalCandidates(params);
      const resData = response.data.data as { data?: Candidate[] } | Candidate[];
      if (Array.isArray(resData)) {
        setCandidates(resData);
      } else if (resData?.data) {
        setCandidates(resData.data);
      } else {
        setCandidates([]);
      }
    } catch {
      toast.error(t('users.panelGrantLoadError'));
      setCandidates([]);
    } finally {
      setLoading(false);
    }
  }, [search, t]);

  useEffect(() => {
    if (!isOpen) return;
    setSelectedUserId(null);
    setRole('hr_specialist');
    setSearch('');
    void loadCandidates();
    // yalnızca modal açılışında yükle; arama butonla
    // eslint-disable-next-line react-hooks/exhaustive-deps -- open trigger
  }, [isOpen]);

  const handleSubmit = async () => {
    if (!selectedUserId) {
      toast.error(t('users.panelGrantSelectUser'));
      return;
    }
    if (!role) {
      toast.error(t('users.panelGrantSelectRole'));
      return;
    }

    setSaving(true);
    try {
      await usersApi.grantPanelAccess(selectedUserId, { role });
      toast.success(t('users.panelGrantSuccess'));
      onSuccess();
      onClose();
    } catch (err) {
      toast.error(getErrorMessage(err, t('users.panelGrantError')));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={t('users.panelGrantTitle')}
      size="md"
      footer={
        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 'var(--sp-2)' }}>
          <button type="button" className="btn btn-secondary" onClick={onClose} disabled={saving}>
            {t('cancel')}
          </button>
          <button
            type="button"
            className="btn btn-primary"
            onClick={() => void handleSubmit()}
            disabled={saving || !selectedUserId}
          >
            {saving ? t('loading') : t('users.panelGrantSubmit')}
          </button>
        </div>
      }
    >
      <p style={{ color: 'var(--text-secondary)', fontSize: 'var(--fs-body)', marginBottom: 'var(--sp-3)' }}>
        {t('users.panelGrantHint')}
      </p>

      <div className="form-group" style={{ marginBottom: 'var(--sp-3)' }}>
        <label className="form-label" htmlFor="panel-grant-search">
          {t('users.panelGrantSearch')}
        </label>
        <div style={{ display: 'flex', gap: 'var(--sp-2)' }}>
          <input
            id="panel-grant-search"
            className="form-control"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t('users.panelGrantSearchPlaceholder')}
            onKeyDown={(e) => {
              if (e.key === 'Enter') void loadCandidates();
            }}
          />
          <button type="button" className="btn btn-secondary" onClick={() => void loadCandidates()}>
            {t('users.panelGrantSearchBtn')}
          </button>
        </div>
      </div>

      <div className="form-group" style={{ marginBottom: 'var(--sp-3)' }}>
        <label className="form-label" htmlFor="panel-grant-role">
          {t('users.panelGrantRole')}
        </label>
        <select
          id="panel-grant-role"
          className="form-control"
          value={role}
          onChange={(e) => setRole(e.target.value)}
        >
          {PANEL_ROLES.map((r) => (
            <option key={r.value} value={r.value}>
              {t(r.labelKey)}
            </option>
          ))}
        </select>
      </div>

      <div className="form-group">
        <label className="form-label">{t('users.panelGrantPick')}</label>
        <div
          style={{
            maxHeight: 240,
            overflowY: 'auto',
            border: '1px solid var(--border-primary)',
            borderRadius: 'var(--radius-md)',
            background: 'var(--surface-secondary)',
          }}
        >
          {loading ? (
            <div style={{ padding: 'var(--sp-4)', textAlign: 'center', color: 'var(--text-tertiary)' }}>
              {t('loading')}
            </div>
          ) : candidates.length === 0 ? (
            <div style={{ padding: 'var(--sp-4)', textAlign: 'center', color: 'var(--text-tertiary)' }}>
              {t('users.panelGrantEmpty')}
            </div>
          ) : (
            candidates.map((c) => (
              <label
                key={c.id}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 'var(--sp-2)',
                  padding: 'var(--sp-2) var(--sp-3)',
                  borderBottom: '1px solid var(--border-primary)',
                  cursor: 'pointer',
                  background: selectedUserId === c.id ? 'var(--primary-soft)' : 'transparent',
                }}
              >
                <input
                  type="radio"
                  name="panel-candidate"
                  checked={selectedUserId === c.id}
                  onChange={() => setSelectedUserId(c.id)}
                />
                <span style={{ minWidth: 0 }}>
                  <span style={{ display: 'block', fontWeight: 500, color: 'var(--text-primary)' }}>
                    {c.name}
                  </span>
                  <span style={{ display: 'block', fontSize: 'var(--fs-caption)', color: 'var(--text-tertiary)' }}>
                    {c.email}
                    {c.employee?.employee_code ? ` · ${c.employee.employee_code}` : ''}
                  </span>
                </span>
              </label>
            ))
          )}
        </div>
      </div>
    </Modal>
  );
};

export default GrantPanelAccessModal;
