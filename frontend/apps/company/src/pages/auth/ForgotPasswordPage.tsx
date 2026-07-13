import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from '@shared/i18n';
import { authApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsEnvelope, BsBuilding, BsArrowLeft } from 'react-icons/bs';

type ForgotForm = { email: string };

const ForgotPasswordPage: React.FC = () => {
  const { t } = useTranslation(['auth', 'validation']);
  const [sent, setSent] = useState(false);

  const forgotSchema = useMemo(
    () =>
      z.object({
        email: z
          .string()
          .min(1, t('validation:required_email'))
          .email(t('validation:email')),
      }),
    [t]
  );

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ForgotForm>({
    resolver: zodResolver(forgotSchema),
    defaultValues: { email: '' },
  });

  const onSubmit = async (data: ForgotForm) => {
    try {
      await authApi.forgotPassword({ email: data.email });
      setSent(true);
      toast.success(t('auth:forgot.sentToast'));
    } catch {
      // Hata interceptor tarafından gösterilir
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

          <h2 className="auth-title">{t('auth:forgot.title')}</h2>
          <p className="auth-subtitle">{t('auth:forgot.subtitle')}</p>

          {sent ? (
            <div style={{ textAlign: 'center' }}>
              <p style={{ color: 'var(--text-secondary)', marginBottom: '1.5rem' }}>
                {t('auth:forgot.sentBody')}
              </p>
              <Link to="/login" className="btn btn-primary btn-lg" style={{ width: '100%' }}>
                {t('auth:backToLoginPage')}
              </Link>
            </div>
          ) : (
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
                    placeholder={t('auth:emailPlaceholderCompany')}
                    autoComplete="email"
                    {...register('email')}
                  />
                </div>
                {errors.email && <div className="form-error">{errors.email.message}</div>}
              </div>

              <button
                type="submit"
                className="btn btn-primary btn-lg"
                disabled={isSubmitting}
                style={{ width: '100%', marginTop: '0.5rem' }}
              >
                {isSubmitting ? t('auth:forgot.submitting') : t('auth:forgot.submit')}
              </button>
            </form>
          )}

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

export default ForgotPasswordPage;
