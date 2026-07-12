import React, { useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { Link, useNavigate } from 'react-router-dom';
import { AppDispatch, RootState } from '../../store';
import { login } from '@shared/store/slices/authSlice';
import { TwoFactorChallenge } from '@shared/components/TwoFactorChallenge';
import { isTwoFactorChallenge, type AuthResponse, type TwoFactorChallenge as TwoFactorChallengeData } from '@shared/types';
import toast from 'react-hot-toast';
import { BsEnvelope, BsLock, BsEye, BsEyeSlash, BsBuilding } from 'react-icons/bs';

const LoginPage: React.FC = () => {
  const dispatch = useDispatch<AppDispatch>();
  const navigate = useNavigate();
  const { isLoading } = useSelector((state: RootState) => state.auth);

  const [formData, setFormData] = useState({
    email: '',
    password: '',
  });
  const [showPassword, setShowPassword] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [challenge, setChallenge] = useState<TwoFactorChallengeData | null>(null);

  const validateForm = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.email) {
      newErrors.email = 'E-posta adresi gerekli';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = 'Geçerli bir e-posta adresi girin';
    }

    if (!formData.password) {
      newErrors.password = 'Şifre gerekli';
    } else if (formData.password.length < 6) {
      newErrors.password = 'Şifre en az 6 karakter olmalı';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData({ ...formData, [name]: value });
    if (errors[name]) {
      setErrors({ ...errors, [name]: '' });
    }
  };

  const completeLogin = (result: AuthResponse) => {
    if (result.user.type === 'super_admin') {
      toast.success('SuperAdmin girişi başarılı!');
      window.location.href = 'http://localhost:3001/dashboard';
      return;
    }

    if (!['company_admin', 'user'].includes(result.user.type)) {
      toast.error('Bu panel firma kullanıcıları içindir.');
      return;
    }

    toast.success('Giriş başarılı!');
    navigate('/dashboard');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm()) return;

    try {
      const result = await dispatch(login(formData)).unwrap();

      if (isTwoFactorChallenge(result)) {
        setChallenge(result);
        return;
      }

      completeLogin(result);
    } catch {
      // Error handled by API interceptor
    }
  };

  return (
    <div className="auth-layout">
      <div className="auth-container animate-fade-in">
        <div className="auth-card">
          {/* Logo */}
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

          {challenge ? (
            <TwoFactorChallenge
              challengeToken={challenge.challenge_token}
              onSuccess={completeLogin}
              onCancel={() => setChallenge(null)}
            />
          ) : (
            <>
              <h2 className="auth-title">Hoş Geldiniz</h2>
              <p className="auth-subtitle">Firma panelinize erişmek için giriş yapın</p>

              <form onSubmit={handleSubmit} className="auth-form">
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
                      name="email"
                      className={`form-control ${errors.email ? 'is-invalid' : ''}`}
                      placeholder="ornek@sirket.com"
                      value={formData.email}
                      onChange={handleChange}
                      autoComplete="email"
                    />
                  </div>
                  {errors.email && <div className="form-error">{errors.email}</div>}
                </div>

                <div className="form-group">
                  <label htmlFor="password" className="form-label">
                    Şifre
                  </label>
                  <div className="input-group">
                    <span className="input-icon">
                      <BsLock />
                    </span>
                    <input
                      type={showPassword ? 'text' : 'password'}
                      id="password"
                      name="password"
                      className={`form-control ${errors.password ? 'is-invalid' : ''}`}
                      placeholder="••••••••"
                      value={formData.password}
                      onChange={handleChange}
                      autoComplete="current-password"
                      style={{ paddingRight: '2.5rem' }}
                    />
                    <button
                      type="button"
                      className="input-action"
                      onClick={() => setShowPassword(!showPassword)}
                      tabIndex={-1}
                    >
                      {showPassword ? <BsEyeSlash /> : <BsEye />}
                    </button>
                  </div>
                  {errors.password && <div className="form-error">{errors.password}</div>}
                </div>

                <button
                  type="submit"
                  className="btn btn-primary btn-lg"
                  disabled={isLoading}
                  style={{ width: '100%', marginTop: '0.5rem' }}
                >
                  {isLoading ? (
                    <>
                      <span
                        className="loading-spinner"
                        style={{ width: 16, height: 16, borderWidth: 2 }}
                      ></span>
                      Giriş yapılıyor...
                    </>
                  ) : (
                    'Giriş Yap'
                  )}
                </button>
              </form>

              <div className="auth-footer">
                <p>
                  Şifrenizi mi unuttunuz? <Link to="/forgot-password">Şifre Sıfırla</Link>
                </p>
              </div>
            </>
          )}
        </div>

        {!challenge && (
          <div
            style={{
              marginTop: '1rem',
              padding: '0.75rem 1rem',
              background: 'rgba(16, 185, 129, 0.1)',
              border: '1px solid rgba(16, 185, 129, 0.2)',
              borderRadius: 8,
              fontSize: '0.75rem',
              color: 'var(--text-secondary)',
            }}
          >
            <strong style={{ color: 'var(--primary)' }}>Demo Bilgileri:</strong>
            <div style={{ marginTop: '0.25rem' }}>Firma Admin: test@test.com / password123</div>
          </div>
        )}
      </div>
    </div>
  );
};

export default LoginPage;
