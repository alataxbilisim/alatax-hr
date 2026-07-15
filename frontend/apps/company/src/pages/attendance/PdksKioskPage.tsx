import React, { useCallback, useEffect, useRef, useState } from 'react';
import QRCode from 'qrcode';
import { useTranslation } from '@shared/i18n';
import { attendanceApi, branchesApi } from '@shared/services/api';
import { Select } from '@shared/components';
import toast from 'react-hot-toast';

interface BranchOption {
  id: number;
  name: string;
}

interface TokenPayload {
  token: string;
  expires_at: string;
  expires_in: number;
  company_id: number;
  branch_id: number | null;
}

const REFRESH_MS = 25_000;

/**
 * PDKS kiosk: tam ekran, kısa ömürlü QR. Sayfa reload yok.
 */
const PdksKioskPage: React.FC = () => {
  const { t } = useTranslation('common');
  const [branches, setBranches] = useState<BranchOption[]>([]);
  const [branchId, setBranchId] = useState<string>('');
  const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);
  const [expiresAt, setExpiresAt] = useState<string | null>(null);
  const [secondsLeft, setSecondsLeft] = useState(30);
  const [offline, setOffline] = useState(false);
  const [loading, setLoading] = useState(true);
  const branchRef = useRef<string>('');
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const countdownRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    branchRef.current = branchId;
  }, [branchId]);

  const refreshToken = useCallback(async () => {
    try {
      const bid = branchRef.current ? Number(branchRef.current) : undefined;
      const res = await attendanceApi.issueKioskToken(
        bid && !Number.isNaN(bid) ? { branch_id: bid } : {}
      );
      const data = (res.data as { data: TokenPayload }).data;
      const url = await QRCode.toDataURL(data.token, {
        width: 420,
        margin: 2,
        errorCorrectionLevel: 'M',
      });
      setQrDataUrl(url);
      setExpiresAt(data.expires_at);
      setSecondsLeft(data.expires_in);
      setOffline(false);
      setLoading(false);
    } catch {
      setOffline(true);
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void (async () => {
      try {
        const res = await branchesApi.list({ per_page: 100, is_active: true });
        const raw = res.data.data;
        const list = Array.isArray(raw) ? raw : (raw as { data?: BranchOption[] })?.data ?? [];
        setBranches(
          (list as BranchOption[]).map((b) => ({ id: b.id, name: b.name }))
        );
      } catch {
        toast.error(t('pdks.branchesLoadFailed'));
      }
    })();
  }, [t]);

  useEffect(() => {
    void refreshToken();
    timerRef.current = setInterval(() => {
      void refreshToken();
    }, REFRESH_MS);

    countdownRef.current = setInterval(() => {
      setSecondsLeft((s) => (s > 0 ? s - 1 : 0));
    }, 1000);

    const onOnline = () => {
      setOffline(false);
      void refreshToken();
    };
    const onOffline = () => setOffline(true);
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);

    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
      if (countdownRef.current) clearInterval(countdownRef.current);
      window.removeEventListener('online', onOnline);
      window.removeEventListener('offline', onOffline);
    };
  }, [refreshToken]);

  const onBranchChange = (value: string) => {
    setBranchId(value);
    branchRef.current = value;
    void refreshToken();
  };

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: '1.25rem',
        padding: '1.5rem',
        background: 'var(--bg-primary)',
        color: 'var(--text-primary)',
      }}
    >
      <h1 style={{ fontSize: 'var(--fs-xl)', margin: 0 }}>{t('pdks.kioskTitle')}</h1>
      <p style={{ color: 'var(--text-secondary)', margin: 0, textAlign: 'center', maxWidth: 480 }}>
        {t('pdks.kioskHint')}
      </p>

      <div style={{ width: 'min(320px, 90vw)' }}>
        <label className="form-label">{t('pdks.branchLabel')}</label>
        <Select
          value={branchId}
          onChange={onBranchChange}
          allowEmpty
          emptyLabel={t('pdks.branchNone')}
          options={branches.map((b) => ({ value: String(b.id), label: b.name }))}
        />
      </div>

      {offline && (
        <div
          role="alert"
          style={{
            padding: '0.75rem 1rem',
            borderRadius: 'var(--radius-sm)',
            background: 'var(--danger-bg, #fef2f2)',
            color: 'var(--danger)',
            border: '1px solid var(--danger)',
          }}
        >
          {t('pdks.connectionLost')}
        </div>
      )}

      <div
        style={{
          padding: '1rem',
          background: '#fff',
          borderRadius: 'var(--radius-md)',
          boxShadow: 'var(--shadow-sm)',
          minWidth: 280,
          minHeight: 280,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
        }}
      >
        {loading && !qrDataUrl ? (
          <span style={{ color: 'var(--text-tertiary)' }}>{t('pdks.loading')}</span>
        ) : qrDataUrl ? (
          <img src={qrDataUrl} alt={t('pdks.qrAlt')} width={420} height={420} />
        ) : (
          <span style={{ color: 'var(--danger)' }}>{t('pdks.qrFailed')}</span>
        )}
      </div>

      <div style={{ fontSize: 'var(--fs-lg)', fontWeight: 600 }}>
        {t('pdks.refreshIn', { seconds: secondsLeft })}
      </div>
      {expiresAt && (
        <div style={{ fontSize: 'var(--fs-sm)', color: 'var(--text-tertiary)' }}>
          {t('pdks.expiresAt', { time: new Date(expiresAt).toLocaleTimeString('tr-TR') })}
        </div>
      )}
    </div>
  );
};

export default PdksKioskPage;
