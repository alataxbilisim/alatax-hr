import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { authApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsEnvelope, BsBuilding, BsArrowLeft } from 'react-icons/bs';

// TODO(i18n): hardcode Türkçe — i18n turunda t()'ye çevrilecek
const forgotSchema = z.object({
  email: z.string().min(1, 'E-posta adresi gerekli').email('Geçerli bir e-posta adresi girin'),
});

type ForgotForm = z.infer<typeof forgotSchema>;

const ForgotPasswordPage: React.FC = () => {
  const [sent, setSent] = useState(false);
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
      toast.success('Sıfırlama linki e-postanıza gönderildi');
    } catch {
      // Hata interceptor tarafından gösterilir
    }
  };

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

          <h2 className="auth-title">Şifremi Unuttum</h2>
          <p className="auth-subtitle">
            E-posta adresinize şifre sıfırlama bağlantısı göndereceğiz
          </p>

          {sent ? (
            <div style={{ textAlign: 'center' }}>
              <p style={{ color: 'var(--text-secondary)', marginBottom: '1.5rem' }}>
                Sıfırlama linki e-postanıza gönderildi. Gelen kutunuzu (ve spam klasörünü) kontrol edin.
              </p>
              <Link to="/login" className="btn btn-primary btn-lg" style={{ width: '100%' }}>
                Giriş sayfasına dön
              </Link>
            </div>
          ) : (
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
                    placeholder="ornek@sirket.com"
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
                {isSubmitting ? 'Gönderiliyor...' : 'Sıfırlama Linki Gönder'}
              </button>
            </form>
          )}

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

export default ForgotPasswordPage;
