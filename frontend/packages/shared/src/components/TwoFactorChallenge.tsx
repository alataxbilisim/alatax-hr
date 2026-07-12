import React, { useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import type { ThunkDispatch, UnknownAction } from '@reduxjs/toolkit';
import { useTranslation } from '../i18n';
import {
  verifyTwoFactor,
  type VerifyTwoFactorReject,
} from '../store/slices/authSlice';
import type { AuthResponse } from '../types';
import toast from 'react-hot-toast';
import { BsShieldLock } from 'react-icons/bs';

type AuthRootState = {
  auth: { isLoading: boolean };
};

type AppDispatch = ThunkDispatch<AuthRootState, unknown, UnknownAction>;

export type TwoFactorChallengeProps = {
  challengeToken: string;
  onSuccess: (result: AuthResponse) => void;
  onCancel: () => void;
};

/**
 * Login sonrası 2FA challenge ekranı (TOTP 6 hane veya recovery kod).
 * BE: POST /auth/2fa/verify + Bearer challenge_token
 */
export const TwoFactorChallenge: React.FC<TwoFactorChallengeProps> = ({
  challengeToken,
  onSuccess,
  onCancel,
}) => {
  const { t } = useTranslation('auth');
  const dispatch = useDispatch<AppDispatch>();
  const isLoading = useSelector((s: AuthRootState) => s.auth.isLoading);

  const [mode, setMode] = useState<'totp' | 'recovery'>('totp');
  const [code, setCode] = useState('');
  const [recoveryCode, setRecoveryCode] = useState('');
  const [fieldError, setFieldError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFieldError('');

    if (mode === 'totp') {
      const digits = code.replace(/\D/g, '');
      if (digits.length !== 6) {
        setFieldError(t('twoFactor.codeRequired'));
        return;
      }
    } else if (!recoveryCode.trim()) {
      setFieldError(t('twoFactor.recoveryRequired'));
      return;
    }

    try {
      const result = await dispatch(
        verifyTwoFactor(
          mode === 'totp'
            ? { challenge_token: challengeToken, code: code.replace(/\D/g, '') }
            : { challenge_token: challengeToken, recovery_code: recoveryCode.trim() }
        )
      ).unwrap();
      onSuccess(result);
    } catch (err: unknown) {
      const reject = err as VerifyTwoFactorReject;
      if (reject?.status === 429) {
        const msg = t('twoFactor.throttled');
        setFieldError(msg);
        toast.error(msg);
        return;
      }
      if (reject?.status === 401) {
        const msg = t('twoFactor.invalidCode');
        setFieldError(msg);
        toast.error(msg);
        return;
      }
      const msg = reject?.message || t('twoFactor.genericError');
      setFieldError(msg);
      toast.error(msg);
    }
  };

  return (
    <>
      <h2 className="auth-title">{t('twoFactor.title')}</h2>
      <p className="auth-subtitle">
        {mode === 'totp' ? t('twoFactor.subtitle') : t('twoFactor.recoverySubtitle')}
      </p>

      <form onSubmit={handleSubmit} className="auth-form">
        {mode === 'totp' ? (
          <div className="form-group">
            <label htmlFor="totp-code" className="form-label">
              {t('twoFactor.codeLabel')}
            </label>
            <div className="input-group">
              <span className="input-icon">
                <BsShieldLock />
              </span>
              <input
                type="text"
                id="totp-code"
                name="code"
                className={`form-control ${fieldError ? 'is-invalid' : ''}`}
                placeholder={t('twoFactor.codePlaceholder')}
                value={code}
                onChange={(e) => {
                  const next = e.target.value.replace(/\D/g, '').slice(0, 6);
                  setCode(next);
                  if (fieldError) setFieldError('');
                }}
                inputMode="numeric"
                autoComplete="one-time-code"
                autoFocus
                maxLength={6}
              />
            </div>
            {fieldError && <div className="form-error">{fieldError}</div>}
          </div>
        ) : (
          <div className="form-group">
            <label htmlFor="recovery-code" className="form-label">
              {t('twoFactor.recoveryLabel')}
            </label>
            <div className="input-group">
              <span className="input-icon">
                <BsShieldLock />
              </span>
              <input
                type="text"
                id="recovery-code"
                name="recovery_code"
                className={`form-control ${fieldError ? 'is-invalid' : ''}`}
                placeholder={t('twoFactor.recoveryPlaceholder')}
                value={recoveryCode}
                onChange={(e) => {
                  setRecoveryCode(e.target.value);
                  if (fieldError) setFieldError('');
                }}
                autoComplete="off"
                autoFocus
              />
            </div>
            {fieldError && <div className="form-error">{fieldError}</div>}
          </div>
        )}

        <button
          type="submit"
          className="btn btn-primary btn-lg"
          disabled={isLoading}
          style={{ width: '100%', marginTop: '0.5rem' }}
        >
          {isLoading ? t('twoFactor.submitting') : t('twoFactor.submit')}
        </button>
      </form>

      <div className="auth-footer" style={{ marginTop: '1rem', textAlign: 'center' }}>
        <p>
          <button
            type="button"
            className="btn btn-link"
            onClick={() => {
              setMode(mode === 'totp' ? 'recovery' : 'totp');
              setFieldError('');
              setCode('');
              setRecoveryCode('');
            }}
            style={{
              background: 'none',
              border: 'none',
              color: 'var(--primary)',
              cursor: 'pointer',
              padding: 0,
              textDecoration: 'underline',
              font: 'inherit',
            }}
          >
            {mode === 'totp' ? t('twoFactor.useRecovery') : t('twoFactor.useTotp')}
          </button>
        </p>
        <p style={{ marginTop: '0.5rem' }}>
          <button
            type="button"
            onClick={onCancel}
            style={{
              background: 'none',
              border: 'none',
              color: 'var(--text-secondary)',
              cursor: 'pointer',
              padding: 0,
              textDecoration: 'underline',
              font: 'inherit',
            }}
          >
            {t('twoFactor.backToPassword')}
          </button>
        </p>
      </div>
    </>
  );
};

export default TwoFactorChallenge;
