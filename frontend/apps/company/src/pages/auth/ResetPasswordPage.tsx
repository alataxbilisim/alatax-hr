import React, { useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from '@shared/i18n';
import { authApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsLock, BsEnvelope, BsBuilding, BsEye, BsEyeSlash, BsArrowLeft } from 'react-icons/bs';

type ResetForm = {
  email: string;
  password: string;
  password_confirmation: string;
};

const ResetPasswordPage: React.FC = () => {
  const { t } = useTranslation(['auth', 'validation']);
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const emailFromUrl = searchParams.get('email') ?? '';
  const [showPassword, setShowPassword] = useState(false);

  const resetSchema = useMemo(
    () =>
      z
        .object({
          email: z
            .string()
            .min(1, t('validation:required_email'))
            .email(t('validation:email')),
          password: z
            .string()
            .min(8, t('validation:password_min', { min: 8 }))
            .regex(/[a-z]/, t('validation:password_lower'))
            .regex(/[A-Z]/, t('validation:password_upper'))
            .regex(/[0-9]/, t('validation:password_number'))
            .regex(/[^A-Za-z0-9]/, t('validation:password_symbol')),
          password_confirmation: z.string().min(1, t('validation:required_password_confirmation')),
        })
        .refine((data) => data.password === data.password_confirmation, {
          message: t('validation:password_mismatch'),
          path: ['password_confirmation'],
        }),
    [t]
  );

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ResetForm>({
    resolver: zodResolver(resetSchema),
    defaultValues: {
      email: emailFromUrl,
      password: '',
      password_confirmation: '',
    },
  });

  const onSubmit = async (data: ResetForm) => {
    if (!token) {
      toast.error(t('auth:reset.invalidTokenToast'));
      return;
    }

    try {
      await authApi.resetPassword({
        token,
        email: data.email,
        password: data.password,
        password_confirmation: data.password_confirmation,
      });
      toast.success(t('auth:reset.successToast'));
      navigate('/login', { replace: true });
    } catch {
      // Hata interceptor tarafından gösterilir
    }
  };

  if (!token) {
    return (
      <div className="auth-layout">
        <div className="auth-container animate-fade-in">
          <div className="auth-card">
            <h2 className="auth-title">{t('auth:reset.invalidLinkTitle')}</h2>
            <p className="auth-subtitle">{t('auth:reset.invalidLinkBody')}</p>
            <Link to="/forgot-password" className="btn btn-primary btn-lg" style={{ width: '100%' }}>
              {t('auth:reset.newRequest')}
            </Link>
            <div className="auth-footer">
              <p>
                <Link to="/login">{t('auth:backToLogin')}</Link>
              </p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="auth-layout">
      <div className="auth-container animate-fade-in">
        <div className="auth-card">
          <div className="auth-logo">
            <div
              style={{
                width: 48,
                height: 48,
                background: 'linear-gradient(135deg, #10b981 0%, #06b6d4 100%)',
                borderRadius: 10,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                margin: '0 auto 0.75rem',
              }}
            >
              <BsBuilding size={24} color="white" />
            </div>
            <h1>{t('auth:brandName')}</h1>
            <span>{t('auth:companyPanel')}</span>
          </div>

          <h2 className="auth-title">{t('auth:reset.title')}</h2>
          <p className="auth-subtitle">{t('auth:reset.subtitle')}</p>

          <form onSubmit={handleSubmit(onSubmit)} className="auth-form" noValidate>
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
                  className={`form-control ${errors.email ? 'is-invalid' : ''}`}
                  autoComplete="email"
                  {...register('email')}
                />
              </div>
              {errors.email && <div className="form-error">{errors.email.message}</div>}
            </div>

            <div className="form-group">
              <label htmlFor="password" className="form-label">
                {t('auth:reset.passwordLabel')}
              </label>
              <div className="input-group">
                <span className="input-icon">
                  <BsLock />
                </span>
                <input
                  type={showPassword ? 'text' : 'password'}
                  id="password"
                  className={`form-control ${errors.password ? 'is-invalid' : ''}`}
                  placeholder="••••••••"
                  autoComplete="new-password"
                  style={{ paddingRight: '2.5rem' }}
                  {...register('password')}
                />
                <button
                  type="button"
                  className="input-action"
                  onClick={() => setShowPassword((v) => !v)}
                  tabIndex={-1}
                >
                  {showPassword ? <BsEyeSlash /> : <BsEye />}
                </button>
              </div>
              {errors.password && <div className="form-error">{errors.password.message}</div>}
            </div>

            <div className="form-group">
              <label htmlFor="password_confirmation" className="form-label">
                {t('auth:reset.passwordConfirmLabel')}
              </label>
              <div className="input-group">
                <span className="input-icon">
                  <BsLock />
                </span>
                <input
                  type={showPassword ? 'text' : 'password'}
                  id="password_confirmation"
                  className={`form-control ${errors.password_confirmation ? 'is-invalid' : ''}`}
                  placeholder="••••••••"
                  autoComplete="new-password"
                  {...register('password_confirmation')}
                />
              </div>
              {errors.password_confirmation && (
                <div className="form-error">{errors.password_confirmation.message}</div>
              )}
            </div>

            <button
              type="submit"
              className="btn btn-primary btn-lg"
              disabled={isSubmitting}
              style={{ width: '100%', marginTop: '0.5rem' }}
            >
              {isSubmitting ? t('auth:reset.submitting') : t('auth:reset.submit')}
            </button>
          </form>

          <div className="auth-footer">
            <p>
              <Link to="/login">
                <BsArrowLeft style={{ marginRight: 4 }} />
                {t('auth:backToLogin')}
              </Link>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ResetPasswordPage;
