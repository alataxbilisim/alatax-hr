import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { authApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsEnvelope } from 'react-icons/bs';

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
    <div className="auth-card">
      <div className="auth-header">
        <h2>Şifremi Unuttum</h2>
        <p>E-posta adresinize şifre sıfırlama bağlantısı göndereceğiz</p>
      </div>

      {sent ? (
        <div>
          <p style={{ marginBottom: '1rem', color: 'var(--text-secondary)' }}>
            Sıfırlama linki e-postanıza gönderildi. Gelen kutunuzu (ve spam klasörünü) kontrol edin.
          </p>
          <Link to="/login" className="btn btn-primary btn-block">
            Giriş sayfasına dön
          </Link>
        </div>
      ) : (
        <form onSubmit={handleSubmit(onSubmit)} className="auth-form" noValidate>
          <div className="form-group">
            <label htmlFor="email" className="form-label">
              E-posta
            </label>
            <div className="input-group">
              <span className="input-icon">
                <BsEnvelope />
              </span>
              <input
                type="email"
                id="email"
                className={`form-control ${errors.email ? 'is-invalid' : ''}`}
                placeholder="admin@alataxbilisim.com"
                autoComplete="email"
                {...register('email')}
              />
            </div>
            {errors.email && <div className="form-error">{errors.email.message}</div>}
          </div>

          <button type="submit" className="btn btn-primary btn-block" disabled={isSubmitting}>
            {isSubmitting ? 'Gönderiliyor...' : 'Sıfırlama Linki Gönder'}
          </button>
        </form>
      )}

      <div className="auth-footer" style={{ marginTop: '1rem', textAlign: 'center' }}>
        <Link to="/login">Girişe dön</Link>
      </div>
    </div>
  );
};

export default ForgotPasswordPage;
