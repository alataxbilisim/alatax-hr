import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { AppDispatch, RootState } from '../../store';
import { register as registerAction } from '../../store/slices/authSlice';
import { BsBuilding, BsPerson, BsEnvelope, BsLock, BsEye, BsEyeSlash } from 'react-icons/bs';

const registerSchema = z.object({
  company_name: z.string().min(2, 'Şirket adı en az 2 karakter olmalı'),
  name: z.string().min(2, 'İsim en az 2 karakter olmalı'),
  email: z.string().email('Geçerli bir e-posta adresi girin'),
  password: z
    .string()
    .min(8, 'Şifre en az 8 karakter olmalı')
    .regex(/[A-Z]/, 'En az bir büyük harf içermeli')
    .regex(/[a-z]/, 'En az bir küçük harf içermeli')
    .regex(/[0-9]/, 'En az bir rakam içermeli'),
  password_confirmation: z.string(),
}).refine((data) => data.password === data.password_confirmation, {
  message: 'Şifreler eşleşmiyor',
  path: ['password_confirmation'],
});

type RegisterFormData = z.infer<typeof registerSchema>;

const RegisterPage: React.FC = () => {
  const dispatch = useDispatch<AppDispatch>();
  const { isLoading, error } = useSelector((state: RootState) => state.auth);
  const [showPassword, setShowPassword] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RegisterFormData>({
    resolver: zodResolver(registerSchema),
  });

  const onSubmit = async (data: RegisterFormData) => {
    dispatch(registerAction(data));
  };

  return (
    <div className="auth-card animate-slide-up">
      <div className="auth-header">
        <div className="auth-logo">A</div>
        <h1 className="auth-title">Ücretsiz Deneyin</h1>
        <p className="auth-subtitle">14 gün ücretsiz deneme süresi</p>
      </div>

      {error && (
        <div style={{
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
        {/* Company Name */}
        <div className="form-group">
          <label className="form-label required">Şirket Adı</label>
          <div style={{ position: 'relative' }}>
            <BsBuilding style={{
              position: 'absolute',
              left: '1rem',
              top: '50%',
              transform: 'translateY(-50%)',
              color: 'var(--text-tertiary)',
            }} />
            <input
              type="text"
              className={`form-input ${errors.company_name ? 'is-invalid' : ''}`}
              placeholder="Şirketinizin adı"
              style={{ paddingLeft: '2.75rem' }}
              {...register('company_name')}
            />
          </div>
          {errors.company_name && <p className="form-error">{errors.company_name.message}</p>}
        </div>

        {/* Name */}
        <div className="form-group">
          <label className="form-label required">Adınız Soyadınız</label>
          <div style={{ position: 'relative' }}>
            <BsPerson style={{
              position: 'absolute',
              left: '1rem',
              top: '50%',
              transform: 'translateY(-50%)',
              color: 'var(--text-tertiary)',
            }} />
            <input
              type="text"
              className={`form-input ${errors.name ? 'is-invalid' : ''}`}
              placeholder="Adınız Soyadınız"
              style={{ paddingLeft: '2.75rem' }}
              {...register('name')}
            />
          </div>
          {errors.name && <p className="form-error">{errors.name.message}</p>}
        </div>

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
          <label className="form-label required">Şifre</label>
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
          <p className="form-hint">En az 8 karakter, 1 büyük harf, 1 küçük harf, 1 rakam</p>
        </div>

        {/* Password Confirmation */}
        <div className="form-group">
          <label className="form-label required">Şifre Tekrar</label>
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
              className={`form-input ${errors.password_confirmation ? 'is-invalid' : ''}`}
              placeholder="••••••••"
              style={{ paddingLeft: '2.75rem' }}
              {...register('password_confirmation')}
            />
          </div>
          {errors.password_confirmation && <p className="form-error">{errors.password_confirmation.message}</p>}
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
              Kayıt oluşturuluyor...
            </>
          ) : (
            'Kayıt Ol'
          )}
        </button>
      </form>

      <div className="auth-footer">
        Zaten hesabınız var mı?{' '}
        <Link to="/login">Giriş Yap</Link>
      </div>
    </div>
  );
};

export default RegisterPage;

