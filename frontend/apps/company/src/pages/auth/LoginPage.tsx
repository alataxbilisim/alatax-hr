import React, { useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { Link, useNavigate } from 'react-router-dom';
import { AppDispatch, RootState } from '../../store';
import { login } from '@shared/store/slices/authSlice';
import { TwoFactorChallenge } from '@shared/components/TwoFactorChallenge';
import { isTwoFactorChallenge, type AuthResponse, type TwoFactorChallenge as TwoFactorChallengeData } from '@shared/types';
import { useTranslation } from '@shared/i18n';
import { hasPanelAccess } from '@shared/constants/permissions';
import toast from 'react-hot-toast';
import { BsEnvelope, BsLock, BsEye, BsEyeSlash, BsBuilding } from 'react-icons/bs';

const PORTAL_LOGIN_URL =
  (import.meta.env.VITE_PORTAL_URL as string | undefined)?.replace(/\/$/, '') ||
  'http://localhost:3003';

type LoginRejectPayload = {
  message?: string;
  code?: string;
  portal_url?: string;
};

const LoginPage: React.FC = () => {
  const { t } = useTranslation(['auth', 'validation']);
  const dispatch = useDispatch<AppDispatch>();
  const navigate = useNavigate();
  const { isLoading } = useSelector((state: RootState) => state.auth);

  const [formData, setFormData] = useState({
    email: '',
    password: '',
  });
  const [showPassword, setShowPassword] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [challenge, setChallenge] = useState<TwoFactorChallengeData | null>(null);

  const validateForm = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.email) {
      newErrors.email = t('validation:required_email');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = t('validation:email');
    }

    if (!formData.password) {
      newErrors.password = t('validation:required_password');
    } else if (formData.password.length < 6) {
      newErrors.password = t('validation:password_min', { min: 6 });
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData({ ...formData, [name]: value });
    if (errors[name]) {
      setErrors({ ...errors, [name]: '' });
    }
  };

  const redirectToPortal = (portalUrl?: string) => {
    toast.error(t('auth:login.panelAccessDenied'));
    const base = (portalUrl || PORTAL_LOGIN_URL).replace(/\/$/, '');
    window.location.href = `${base}/login`;
  };

  const completeLogin = (result: AuthResponse) => {
    if (result.user.type === 'super_admin') {
      toast.success(t('auth:login.welcome'));
      window.location.href = 'http://localhost:3001/dashboard';
      return;
    }

    if (!['company_admin', 'user'].includes(result.user.type)) {
      toast.error(t('auth:login.panelAccessDeniedShort'));
      return;
    }

    if (!hasPanelAccess(result.user)) {
      redirectToPortal();
      return;
    }

    toast.success(t('auth:login.welcome'));
    navigate('/dashboard');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm()) return;

    try {
      const result = await dispatch(login(formData)).unwrap();

      if (isTwoFactorChallenge(result)) {
        setChallenge(result);
        return;
      }

      completeLogin(result);
    } catch (err: unknown) {
      const payload = err as LoginRejectPayload;
      if (payload?.code === 'panel_access_denied') {
        redirectToPortal(payload.portal_url);
      }
      // Diğer hatalar API interceptor toast'ında
    }
  };

  return (
    <div className="auth-layout">
      <div className="auth-container animate-fade-in">
        <div className="auth-card">
          <div className="auth-logo">
            <div className="auth-logo-mark" aria-hidden>
              <BsBuilding size={24} />
            </div>
            <h1>{t('auth:brandName')}</h1>
            <span>{t('auth:companyPanel')}</span>
          </div>

          {challenge ? (
            <TwoFactorChallenge
              challengeToken={challenge.challenge_token}
              onSuccess={completeLogin}
              onCancel={() => setChallenge(null)}
            />
          ) : (
            <>
              <h2 className="auth-title">{t('auth:login.welcome')}</h2>
              <p className="auth-subtitle">{t('auth:login.subtitleCompany')}</p>

              <form onSubmit={handleSubmit} className="auth-form">
                <div className="form-group">
                  <label htmlFor="email" className="form-label">
                    {t('auth:emailLabel')}
                  </label>
                  <div className="input-group">
                    <span className="input-icon">
                      <BsEnvelope />
                    </span>
                    <input
                      type="email"
                      id="email"
                      name="email"
                      className={`form-control ${errors.email ? 'is-invalid' : ''}`}
                      placeholder={t('auth:emailPlaceholderCompany')}
                      value={formData.email}
                      onChange={handleChange}
                      autoComplete="email"
                    />
                  </div>
                  {errors.email && <div className="form-error">{errors.email}</div>}
                </div>

                <div className="form-group">
                  <label htmlFor="password" className="form-label">
                    Şifre
                  </label>
                  <div className="input-group">
                    <span className="input-icon">
                      <BsLock />
                    </span>
                    <input
                      type={showPassword ? 'text' : 'password'}
                      id="password"
                      name="password"
                      className={`form-control ${errors.password ? 'is-invalid' : ''}`}
                      placeholder="••••••••"
                      value={formData.password}
                      onChange={handleChange}
                      autoComplete="current-password"
                    />
                    <button
                      type="button"
                      className="input-action"
                      onClick={() => setShowPassword(!showPassword)}
                      tabIndex={-1}
                      aria-label={showPassword ? 'Şifreyi gizle' : 'Şifreyi göster'}
                    >
                      {showPassword ? <BsEyeSlash /> : <BsEye />}
                    </button>
                  </div>
                  {errors.password && <div className="form-error">{errors.password}</div>}
                </div>

                <button
                  type="submit"
                  className="btn btn-primary btn-lg"
                  disabled={isLoading}
                >
                  {isLoading ? (
                    <>
                      <span
                        className="loading-spinner"
                        style={{ width: 16, height: 16, borderWidth: 2 }}
                      ></span>
                      {t('auth:login.submitting')}
                    </>
                  ) : (
                    t('auth:login.submit')
                  )}
                </button>
              </form>

              <div className="auth-footer">
                <p>
                  {t('auth:login.forgotPrompt')}{' '}
                  <Link to="/forgot-password">{t('auth:login.resetLink')}</Link>
                </p>
              </div>
            </>
          )}
        </div>

        {!challenge && (
          <div className="auth-demo-hint">
            <strong>{t('auth:login.demoTitle')}</strong>
            <div>{t('auth:login.demoCredentials')}</div>
          </div>
        )}
      </div>
    </div>
  );
};

export default LoginPage;
