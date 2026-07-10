import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from '@shared/i18n';
import { authApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsEnvelope } from 'react-icons/bs';

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
    <div className="auth-card">
      <div className="auth-header">
        <h2>{t('auth:forgot.title')}</h2>
        <p>{t('auth:forgot.subtitle')}</p>
      </div>

      {sent ? (
        <div>
          <p className="text-muted mb-3">{t('auth:forgot.sentBody')}</p>
          <Link to="/login" className="btn btn-primary btn-block">
            {t('auth:backToLoginPage')}
          </Link>
        </div>
      ) : (
        <form onSubmit={handleSubmit(onSubmit)} className="auth-form" noValidate>
          <div className="form-group">
            <label htmlFor="email" className="form-label">
              {t('auth:email')}
            </label>
            <div className="input-group">
              <span className="input-icon">
                <BsEnvelope />
              </span>
              <input
                type="email"
                id="email"
                className={`form-control ${errors.email ? 'is-invalid' : ''}`}
                placeholder={t('auth:emailPlaceholderPortal')}
                autoComplete="email"
                {...register('email')}
              />
            </div>
            {errors.email && <div className="form-error text-danger small">{errors.email.message}</div>}
          </div>

          <button type="submit" className="btn btn-primary btn-block" disabled={isSubmitting}>
            {isSubmitting ? t('auth:forgot.submitting') : t('auth:forgot.submit')}
          </button>
        </form>
      )}

      <div className="auth-footer mt-3 text-center">
        <Link to="/login">{t('auth:backToLogin')}</Link>
      </div>
    </div>
  );
};

export default ForgotPasswordPage;
