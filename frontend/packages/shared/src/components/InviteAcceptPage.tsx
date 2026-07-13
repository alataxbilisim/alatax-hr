import React, { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from '@shared/i18n';
import { authApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsLock, BsEnvelope, BsBuilding, BsEye, BsEyeSlash, BsArrowLeft } from 'react-icons/bs';

type InviteForm = {
  email: string;
  password: string;
  password_confirmation: string;
};

type InvitePreview = {
  email: string;
  name: string;
  company_name?: string | null;
};

type InviteAcceptPageProps = {
  panelLabelKey: 'companyPanel' | 'portalPanel';
};

const InviteAcceptPage: React.FC<InviteAcceptPageProps> = ({ panelLabelKey }) => {
  const { t } = useTranslation(['auth', 'validation']);
  const navigate = useNavigate();
  const { token = '' } = useParams<{ token: string }>();
  const [showPassword, setShowPassword] = useState(false);
  const [preview, setPreview] = useState<InvitePreview | null>(null);
  const [previewError, setPreviewError] = useState<string | null>(null);
  const [loadingPreview, setLoadingPreview] = useState(true);

  const inviteSchema = useMemo(
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
    reset,
    formState: { errors, isSubmitting },
  } = useForm<InviteForm>({
    resolver: zodResolver(inviteSchema),
    defaultValues: {
      email: '',
      password: '',
      password_confirmation: '',
    },
  });

  useEffect(() => {
    if (!token) {
      setPreviewError(t('auth:invite.invalidLinkBody'));
      setLoadingPreview(false);
      return;
    }

    let cancelled = false;
    (async () => {
      try {
        const res = await authApi.showInvitation(token);
        const data = res.data.data as InvitePreview;
        if (!cancelled) {
          setPreview(data);
          reset({
            email: data.email,
            password: '',
            password_confirmation: '',
          });
        }
      } catch {
        if (!cancelled) {
          setPreviewError(t('auth:invite.invalidLinkBody'));
        }
      } finally {
        if (!cancelled) {
          setLoadingPreview(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [token, t, reset]);

  const onSubmit = async (data: InviteForm) => {
    if (!token) {
      toast.error(t('auth:invite.invalidTokenToast'));
      return;
    }

    try {
      await authApi.acceptInvitation({
        token,
        email: data.email,
        password: data.password,
        password_confirmation: data.password_confirmation,
      });
      toast.success(t('auth:invite.successToast'));
      navigate('/login', { replace: true });
    } catch {
      // interceptor
    }
  };

  if (loadingPreview) {
    return (
      <div className="auth-layout">
        <div className="auth-container animate-fade-in">
          <div className="auth-card">
            <p className="auth-subtitle">{t('auth:invite.loading')}</p>
          </div>
        </div>
      </div>
    );
  }

  if (previewError || !preview) {
    return (
      <div className="auth-layout">
        <div className="auth-container animate-fade-in">
          <div className="auth-card">
            <h2 className="auth-title">{t('auth:invite.invalidLinkTitle')}</h2>
            <p className="auth-subtitle">{previewError ?? t('auth:invite.invalidLinkBody')}</p>
            <Link to="/login" className="btn btn-primary btn-lg" style={{ width: '100%' }}>
              {t('auth:backToLogin')}
            </Link>
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
            <div className="auth-logo-mark" aria-hidden>
              <BsBuilding size={24} />
            </div>
            <h1>{t('auth:brandName')}</h1>
            <span>{t(`auth:${panelLabelKey}`)}</span>
          </div>

          <h2 className="auth-title">{t('auth:invite.title')}</h2>
          <p className="auth-subtitle">
            {preview.company_name
              ? t('auth:invite.subtitleWithCompany', { company: preview.company_name, name: preview.name })
              : t('auth:invite.subtitle', { name: preview.name })}
          </p>

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
                  readOnly
                  {...register('email')}
                />
              </div>
              {errors.email && <div className="form-error">{errors.email.message}</div>}
            </div>

            <div className="form-group">
              <label htmlFor="password" className="form-label">
                {t('auth:invite.passwordLabel')}
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
                {t('auth:invite.passwordConfirmLabel')}
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
              {isSubmitting ? t('auth:invite.submitting') : t('auth:invite.submit')}
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

export default InviteAcceptPage;
