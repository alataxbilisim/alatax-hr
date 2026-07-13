import React, { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { useDispatch, useSelector } from 'react-redux';
import { useTranslation } from '@shared/i18n';
import { authApi } from '@shared/services/api';
import { setUser } from '@shared/store/slices/authSlice';
import type { User } from '@shared/types';
import toast from 'react-hot-toast';
import { BsLock, BsBuilding, BsEye, BsEyeSlash } from 'react-icons/bs';

type ForceForm = {
  current_password: string;
  password: string;
  password_confirmation: string;
};

type RootAuth = {
  auth: { user: User | null };
};

type ForcedPasswordChangePageProps = {
  panelLabelKey: 'companyPanel' | 'portalPanel';
  afterSuccessPath?: string;
};

const ForcedPasswordChangePage: React.FC<ForcedPasswordChangePageProps> = ({
  panelLabelKey,
  afterSuccessPath = '/dashboard',
}) => {
  const { t } = useTranslation(['auth', 'validation']);
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const user = useSelector((s: RootAuth) => s.auth.user);
  const [showPassword, setShowPassword] = useState(false);

  const schema = useMemo(
    () =>
      z
        .object({
          current_password: z.string().min(1, t('validation:required_password')),
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
  } = useForm<ForceForm>({
    resolver: zodResolver(schema),
    defaultValues: {
      current_password: '',
      password: '',
      password_confirmation: '',
    },
  });

  const onSubmit = async (data: ForceForm) => {
    try {
      await authApi.updatePassword(data);
      if (user) {
        const updated = { ...user, must_change_password: false };
        dispatch(setUser(updated));
        localStorage.setItem('user', JSON.stringify(updated));
      }
      toast.success(t('auth:invite.mustChangeSuccess'));
      navigate(afterSuccessPath, { replace: true });
    } catch {
      // interceptor
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
            <span>{t(`auth:${panelLabelKey}`)}</span>
          </div>

          <h2 className="auth-title">{t('auth:invite.mustChangeTitle')}</h2>
          <p className="auth-subtitle">{t('auth:invite.mustChangeSubtitle')}</p>

          <form onSubmit={handleSubmit(onSubmit)} className="auth-form" noValidate>
            <div className="form-group">
              <label htmlFor="current_password" className="form-label">
                {t('auth:invite.currentPasswordLabel')}
              </label>
              <div className="input-group">
                <span className="input-icon">
                  <BsLock />
                </span>
                <input
                  type={showPassword ? 'text' : 'password'}
                  id="current_password"
                  className={`form-control ${errors.current_password ? 'is-invalid' : ''}`}
                  autoComplete="current-password"
                  {...register('current_password')}
                />
              </div>
              {errors.current_password && (
                <div className="form-error">{errors.current_password.message}</div>
              )}
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
              {isSubmitting ? t('auth:invite.submitting') : t('auth:invite.mustChangeSubmit')}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
};

export default ForcedPasswordChangePage;
