import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from '@shared/i18n';
import { portalApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';

interface NoticePayload {
  id: number;
  title: string;
  body: string;
  version: number;
}

function isNotice(v: unknown): v is NoticePayload {
  return typeof v === 'object' && v !== null && 'id' in v && 'body' in v && 'title' in v;
}

/**
 * D2a — Portal aydınlatma modalı.
 * Onay zorunlu değil (zorlamak rızayı sakatlar); reddedilince "görüntülendi/onaylanmadı" kaydı.
 */
const PortalPrivacyModal: React.FC = () => {
  const { t } = useTranslation('common');
  const [open, setOpen] = useState(false);
  const [notice, setNotice] = useState<NoticePayload | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await portalApi.privacy.status();
      const data: unknown = res.data.data;
      if (typeof data !== 'object' || data === null) return;
      const needs =
        'needs_attention' in data && typeof data.needs_attention === 'boolean'
          ? data.needs_attention
          : false;
      const active =
        'active_notice' in data && isNotice(data.active_notice) ? data.active_notice : null;
      if (needs && active) {
        setNotice(active);
        setOpen(true);
      }
    } catch {
      // sessiz — portal ana akışı kırılmasın
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const respond = async (granted: boolean) => {
    if (!notice) return;
    try {
      setBusy(true);
      await portalApi.privacy.acknowledge({ notice_id: notice.id, granted });
      setOpen(false);
      if (!granted) {
        toast(t('portalPrivacy.declineHint'));
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, t('portalPrivacy.loadError')));
    } finally {
      setBusy(false);
    }
  };

  if (!open || !notice) return null;

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="portal-privacy-title"
      style={{
        position: 'fixed',
        inset: 0,
        background: 'rgba(0,0,0,0.45)',
        zIndex: 1000,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: 'var(--sp-4)',
      }}
    >
      <div
        style={{
          background: 'var(--color-surface, #fff)',
          maxWidth: '36rem',
          width: '100%',
          maxHeight: '80vh',
          overflow: 'auto',
          padding: 'var(--sp-5)',
          borderRadius: 'var(--radius-md, 8px)',
        }}
      >
        <h2 id="portal-privacy-title">{t('portalPrivacy.title')}</h2>
        <p className="text-muted">{t('portalPrivacy.subtitle')}</p>
        <h3 style={{ fontSize: 'var(--fs-md)' }}>{notice.title}</h3>
        <div style={{ whiteSpace: 'pre-wrap', margin: 'var(--sp-3) 0' }}>{notice.body}</div>
        <div style={{ display: 'flex', gap: 'var(--sp-2)', flexWrap: 'wrap' }}>
          <button
            type="button"
            className="btn btn-primary"
            disabled={busy}
            onClick={() => void respond(true)}
          >
            {t('portalPrivacy.acknowledge')}
          </button>
          <button
            type="button"
            className="btn btn-outline-secondary"
            disabled={busy}
            onClick={() => void respond(false)}
          >
            {t('portalPrivacy.decline')}
          </button>
        </div>
      </div>
    </div>
  );
};

export default PortalPrivacyModal;
