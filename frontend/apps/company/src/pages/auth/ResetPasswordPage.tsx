import React, { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { authApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsLock, BsEnvelope, BsBuilding, BsEye, BsEyeSlash, BsArrowLeft } from 'react-icons/bs';

// TODO(i18n): hardcode Türkçe — i18n turunda t()'ye çevrilecek
const resetSchema = z
  .object({
    email: z.string().min(1, 'E-posta adresi gerekli').email('Geçerli bir e-posta adresi girin'),
    password: z
      .string()
      .min(8, 'Şifre en az 8 karakter olmalı')
      .regex(/[a-z]/, 'En az bir küçük harf gerekli')
      .regex(/[A-Z]/, 'En az bir büyük harf gerekli')
      .regex(/[0-9]/, 'En az bir rakam gerekli')
      .regex(/[^A-Za-z0-9]/, 'En az bir özel karakter gerekli'),
    password_confirmation: z.string().min(1, 'Şifre tekrarı gerekli'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Şifreler eşleşmiyor',
    path: ['password_confirmation'],
  });

type ResetForm = z.infer<typeof resetSchema>;

const ResetPasswordPage: React.FC = () => {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const emailFromUrl = searchParams.get('email') ?? '';
  const [showPassword, setShowPassword] = useState(false);

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
      toast.error('Geçersiz veya eksik sıfırlama bağlantısı');
      return;
    }

    try {
      await authApi.resetPassword({
        token,
        email: data.email,
        password: data.password,
        password_confirmation: data.password_confirmation,
      });
      toast.success('Şifreniz başarıyla sıfırlandı');
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
            <h2 className="auth-title">Geçersiz Bağlantı</h2>
            <p className="auth-subtitle">
              Şifre sıfırlama bağlantısı eksik veya geçersiz. Lütfen yeni bir talep oluşturun.
            </p>
            <Link to="/forgot-password" className="btn btn-primary btn-lg" style={{ width: '100%' }}>
              Yeni sıfırlama talebi
            </Link>
            <div className="auth-footer">
              <p>
                <Link to="/login">Girişe dön</Link>
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
            <h1>ALATAX HR</h1>
            <span>Firma Yönetim Paneli</span>
          </div>

          <h2 className="auth-title">Yeni Şifre Belirle</h2>
          <p className="auth-subtitle">Hesabınız için yeni bir şifre oluşturun</p>

          <form onSubmit={handleSubmit(onSubmit)} className="auth-form" noValidate>
            <div className="form-group">
              <label htmlFor="email" className="form-label">
                E-posta Adresi
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
                Yeni Şifre
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
                Şifre Tekrar
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
              {isSubmitting ? 'Kaydediliyor...' : 'Şifreyi Güncelle'}
            </button>
          </form>

          <div className="auth-footer">
            <p>
              <Link to="/login">
                <BsArrowLeft style={{ marginRight: 4 }} />
                Girişe dön
              </Link>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ResetPasswordPage;
