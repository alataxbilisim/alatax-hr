import React, { useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { useTranslation } from '@shared/i18n';
import { authApi } from '@shared/services/api';
import { fetchCurrentUser } from '@shared/store/slices/authSlice';
import toast from 'react-hot-toast';
import { BsShieldLock } from 'react-icons/bs';
import { RootState, AppDispatch } from '../../store';

const AccountSecurityPage: React.FC = () => {
  const { t } = useTranslation('common');
  const dispatch = useDispatch<AppDispatch>();
  const { user } = useSelector((state: RootState) => state.auth);

  const [passwordData, setPasswordData] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const [savingPassword, setSavingPassword] = useState(false);

  const [twoFactorLoading, setTwoFactorLoading] = useState(false);
  const [setupSecret, setSetupSecret] = useState<string | null>(null);
  const [qrSvg, setQrSvg] = useState<string | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [confirmCode, setConfirmCode] = useState('');
  const [disablePassword, setDisablePassword] = useState('');
  const [disableCode, setDisableCode] = useState('');
  const [regenPassword, setRegenPassword] = useState('');
  const [remainingCount, setRemainingCount] = useState<number | null>(null);

  const enabled = Boolean(user?.two_factor_enabled);

  const refreshUser = async () => {
    await dispatch(fetchCurrentUser());
  };

  const handlePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (passwordData.password !== passwordData.password_confirmation) {
      toast.error(t('account.passwordMismatch'));
      return;
    }
    setSavingPassword(true);
    try {
      await authApi.updatePassword(passwordData);
      toast.success(t('account.passwordSuccess'));
      setPasswordData({ current_password: '', password: '', password_confirmation: '' });
    } catch {
      toast.error(t('account.passwordError'));
    } finally {
      setSavingPassword(false);
    }
  };

  const handleEnable = async () => {
    setTwoFactorLoading(true);
    try {
      const res = await authApi.enableSelf2FA();
      const data = res.data.data;
      setSetupSecret(data.secret);
      setQrSvg(data.qr_code_svg ?? null);
      setRecoveryCodes(data.recovery_codes);
      setConfirmCode('');
    } catch {
      toast.error(t('account.twoFactorEnableError'));
    } finally {
      setTwoFactorLoading(false);
    }
  };

  const handleConfirm = async () => {
    setTwoFactorLoading(true);
    try {
      await authApi.confirmSelf2FA({ code: confirmCode });
      toast.success(t('account.twoFactorEnableSuccess'));
      setSetupSecret(null);
      setQrSvg(null);
      setConfirmCode('');
      await refreshUser();
      const status = await authApi.getSelfRecoveryCodes();
      setRemainingCount(status.data.data.remaining_count);
    } catch {
      toast.error(t('account.twoFactorEnableError'));
    } finally {
      setTwoFactorLoading(false);
    }
  };

  const handleDisable = async () => {
    if (!window.confirm(t('account.twoFactorDisableConfirm'))) return;
    setTwoFactorLoading(true);
    try {
      await authApi.disableSelf2FA(
        disableCode
          ? { code: disableCode }
          : { password: disablePassword }
      );
      toast.success(t('account.twoFactorDisableSuccess'));
      setDisableCode('');
      setDisablePassword('');
      setRecoveryCodes([]);
      setRemainingCount(null);
      await refreshUser();
    } catch {
      toast.error(t('account.twoFactorDisableError'));
    } finally {
      setTwoFactorLoading(false);
    }
  };

  const handleRegenerate = async () => {
    setTwoFactorLoading(true);
    try {
      const res = await authApi.regenerateSelfRecoveryCodes({
        password: regenPassword,
      });
      setRecoveryCodes(res.data.data.recovery_codes);
      setRemainingCount(res.data.data.recovery_codes.length);
      setRegenPassword('');
      toast.success(t('account.twoFactorRegenSuccess'));
    } catch {
      toast.error(t('error'));
    } finally {
      setTwoFactorLoading(false);
    }
  };

  const loadRemaining = async () => {
    try {
      const res = await authApi.getSelfRecoveryCodes();
      setRemainingCount(res.data.data.remaining_count);
    } catch {
      /* ignore */
    }
  };

  React.useEffect(() => {
    if (enabled) {
      void loadRemaining();
    }
  }, [enabled]);

  return (
    <div className="animate-fade-in">
      <div className="page-header" style={{ marginBottom: 'var(--space-4)' }}>
        <h1 className="page-title">{t('account.securityTitle')}</h1>
        <p className="page-subtitle">{t('account.securitySubtitle')}</p>
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-4)', maxWidth: 560 }}>
        <div className="card">
          <div className="card-header">
            <h3 className="card-title">{t('account.changePassword')}</h3>
          </div>
          <div className="card-body">
            <form onSubmit={handlePassword} style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-3)' }}>
              <div className="form-group">
                <label className="form-label" htmlFor="cur-pw">{t('account.currentPassword')}</label>
                <input
                  id="cur-pw"
                  type="password"
                  className="form-control"
                  value={passwordData.current_password}
                  onChange={(e) => setPasswordData({ ...passwordData, current_password: e.target.value })}
                  required
                  autoComplete="current-password"
                />
              </div>
              <div className="form-group">
                <label className="form-label" htmlFor="new-pw">{t('account.newPassword')}</label>
                <input
                  id="new-pw"
                  type="password"
                  className="form-control"
                  value={passwordData.password}
                  onChange={(e) => setPasswordData({ ...passwordData, password: e.target.value })}
                  required
                  autoComplete="new-password"
                />
              </div>
              <div className="form-group">
                <label className="form-label" htmlFor="conf-pw">{t('account.confirmPassword')}</label>
                <input
                  id="conf-pw"
                  type="password"
                  className="form-control"
                  value={passwordData.password_confirmation}
                  onChange={(e) => setPasswordData({ ...passwordData, password_confirmation: e.target.value })}
                  required
                  autoComplete="new-password"
                />
              </div>
              <button type="submit" className="btn btn-primary" disabled={savingPassword}>
                {savingPassword ? t('loading') : t('save')}
              </button>
            </form>
          </div>
        </div>

        <div className="card">
          <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <BsShieldLock />
              {t('account.twoFactor')}
            </h3>
            <span className={`badge ${enabled ? 'badge-success' : 'badge-secondary'}`}>
              {enabled ? t('account.twoFactorActive') : t('account.twoFactorInactive')}
            </span>
          </div>
          <div className="card-body" style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-3)' }}>
            {!enabled && !setupSecret && (
              <button
                type="button"
                className="btn btn-primary"
                onClick={handleEnable}
                disabled={twoFactorLoading}
              >
                {t('account.twoFactorEnable')}
              </button>
            )}

            {setupSecret && (
              <>
                {qrSvg && (
                  <div
                    style={{ display: 'flex', justifyContent: 'center' }}
                    dangerouslySetInnerHTML={{ __html: qrSvg }}
                  />
                )}
                <div className="form-group">
                  <label className="form-label">{t('account.twoFactorSecret')}</label>
                  <code style={{ wordBreak: 'break-all', fontSize: '0.8125rem' }}>{setupSecret}</code>
                </div>
                {recoveryCodes.length > 0 && (
                  <div>
                    <strong>{t('account.twoFactorRecoveryTitle')}</strong>
                    <p style={{ fontSize: '0.8125rem', color: 'var(--text-tertiary)' }}>
                      {t('account.twoFactorRecoveryHint')}
                    </p>
                    <ul style={{ fontFamily: 'monospace', fontSize: '0.875rem' }}>
                      {recoveryCodes.map((c) => (
                        <li key={c}>{c}</li>
                      ))}
                    </ul>
                  </div>
                )}
                <div className="form-group">
                  <label className="form-label" htmlFor="totp-confirm">{t('account.twoFactorCode')}</label>
                  <input
                    id="totp-confirm"
                    className="form-control"
                    value={confirmCode}
                    onChange={(e) => setConfirmCode(e.target.value)}
                    maxLength={6}
                    inputMode="numeric"
                  />
                </div>
                <button
                  type="button"
                  className="btn btn-primary"
                  onClick={handleConfirm}
                  disabled={twoFactorLoading || confirmCode.length !== 6}
                >
                  {t('account.twoFactorConfirm')}
                </button>
              </>
            )}

            {enabled && (
              <>
                {remainingCount != null && (
                  <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
                    {t('account.twoFactorRemaining', { count: remainingCount })}
                  </p>
                )}

                {recoveryCodes.length > 0 && (
                  <div>
                    <strong>{t('account.twoFactorRecoveryTitle')}</strong>
                    <ul style={{ fontFamily: 'monospace', fontSize: '0.875rem' }}>
                      {recoveryCodes.map((c) => (
                        <li key={c}>{c}</li>
                      ))}
                    </ul>
                  </div>
                )}

                <div className="form-group">
                  <label className="form-label" htmlFor="regen-pw">{t('account.twoFactorPasswordConfirm')}</label>
                  <input
                    id="regen-pw"
                    type="password"
                    className="form-control"
                    value={regenPassword}
                    onChange={(e) => setRegenPassword(e.target.value)}
                  />
                </div>
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  onClick={handleRegenerate}
                  disabled={twoFactorLoading || !regenPassword}
                >
                  {t('account.twoFactorRegenerate')}
                </button>

                <hr style={{ borderColor: 'var(--border-primary)', width: '100%' }} />

                <div className="form-group">
                  <label className="form-label" htmlFor="disable-pw">{t('account.twoFactorPasswordConfirm')}</label>
                  <input
                    id="disable-pw"
                    type="password"
                    className="form-control"
                    value={disablePassword}
                    onChange={(e) => setDisablePassword(e.target.value)}
                  />
                </div>
                <div className="form-group">
                  <label className="form-label" htmlFor="disable-code">{t('account.twoFactorCode')}</label>
                  <input
                    id="disable-code"
                    className="form-control"
                    value={disableCode}
                    onChange={(e) => setDisableCode(e.target.value)}
                    maxLength={6}
                  />
                </div>
                <button
                  type="button"
                  className="btn btn-danger"
                  onClick={handleDisable}
                  disabled={twoFactorLoading || (!disablePassword && !disableCode)}
                >
                  {t('account.twoFactorDisable')}
                </button>
              </>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default AccountSecurityPage;
