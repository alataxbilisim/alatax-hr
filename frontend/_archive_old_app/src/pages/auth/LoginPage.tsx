import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { AppDispatch, RootState } from '../../store';
import { login } from '../../store/slices/authSlice';
import { BsEnvelope, BsLock, BsEye, BsEyeSlash } from 'react-icons/bs';

const loginSchema = z.object({
  email: z.string().email('Geçerli bir e-posta adresi girin'),
  password: z.string().min(1, 'Şifre gerekli'),
});

type LoginFormData = z.infer<typeof loginSchema>;

const LoginPage: React.FC = () => {
  const dispatch = useDispatch<AppDispatch>();
  const { isLoading, error } = useSelector((state: RootState) => state.auth);
  const [showPassword, setShowPassword] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
  });

  const onSubmit = async (data: LoginFormData) => {
    dispatch(login(data));
  };

  return (
    <div className="auth-card animate-slide-up">
      <div className="auth-header">
        <div className="auth-logo">A</div>
        <h1 className="auth-title">Hoş Geldiniz</h1>
        <p className="auth-subtitle">Hesabınıza giriş yapın</p>
      </div>

      {error && (
        <div className="alert alert-danger" style={{
          background: 'var(--danger-soft)',
          color: 'var(--danger-text)',
          padding: '0.75rem 1rem',
          borderRadius: 'var(--radius-md)',
          marginBottom: '1.5rem',
          fontSize: '0.875rem',
        }}>
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)}>
        {/* Email */}
        <div className="form-group">
          <label className="form-label required">E-posta Adresi</label>
          <div style={{ position: 'relative' }}>
            <BsEnvelope style={{
              position: 'absolute',
              left: '1rem',
              top: '50%',
              transform: 'translateY(-50%)',
              color: 'var(--text-tertiary)',
            }} />
            <input
              type="email"
              className={`form-input ${errors.email ? 'is-invalid' : ''}`}
              placeholder="ornek@sirket.com"
              style={{ paddingLeft: '2.75rem' }}
              {...register('email')}
            />
          </div>
          {errors.email && <p className="form-error">{errors.email.message}</p>}
        </div>

        {/* Password */}
        <div className="form-group">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <label className="form-label required">Şifre</label>
            <Link to="/forgot-password" style={{ fontSize: '0.8125rem' }}>
              Şifremi Unuttum
            </Link>
          </div>
          <div style={{ position: 'relative' }}>
            <BsLock style={{
              position: 'absolute',
              left: '1rem',
              top: '50%',
              transform: 'translateY(-50%)',
              color: 'var(--text-tertiary)',
            }} />
            <input
              type={showPassword ? 'text' : 'password'}
              className={`form-input ${errors.password ? 'is-invalid' : ''}`}
              placeholder="••••••••"
              style={{ paddingLeft: '2.75rem', paddingRight: '2.75rem' }}
              {...register('password')}
            />
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              style={{
                position: 'absolute',
                right: '1rem',
                top: '50%',
                transform: 'translateY(-50%)',
                background: 'none',
                border: 'none',
                color: 'var(--text-tertiary)',
                cursor: 'pointer',
              }}
            >
              {showPassword ? <BsEyeSlash /> : <BsEye />}
            </button>
          </div>
          {errors.password && <p className="form-error">{errors.password.message}</p>}
        </div>

        {/* Submit */}
        <button
          type="submit"
          className="btn btn-primary"
          style={{ width: '100%', marginTop: '0.5rem' }}
          disabled={isLoading}
        >
          {isLoading ? (
            <>
              <span className="spinner" style={{ width: 16, height: 16 }} />
              Giriş yapılıyor...
            </>
          ) : (
            'Giriş Yap'
          )}
        </button>
      </form>

      <div className="auth-footer">
        Hesabınız yok mu?{' '}
        <Link to="/register">Ücretsiz Deneyin</Link>
      </div>
    </div>
  );
};

export default LoginPage;

