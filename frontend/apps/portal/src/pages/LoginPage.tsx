import React, { useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { Link, useNavigate } from 'react-router-dom';
import { AppDispatch, RootState } from '../store';
import { login } from '@shared/store/slices/authSlice';
import { TwoFactorChallenge } from '@shared/components/TwoFactorChallenge';
import { isTwoFactorChallenge, type AuthResponse, type TwoFactorChallenge as TwoFactorChallengeData } from '@shared/types';
import toast from 'react-hot-toast';
import { BsEnvelope, BsLock, BsEye, BsEyeSlash } from 'react-icons/bs';

const LoginPage: React.FC = () => {
  const dispatch = useDispatch<AppDispatch>();
  const navigate = useNavigate();
  const { isLoading } = useSelector((state: RootState) => state.auth);

  const [formData, setFormData] = useState({
    email: '',
    password: '',
  });
  const [showPassword, setShowPassword] = useState(false);
  const [challenge, setChallenge] = useState<TwoFactorChallengeData | null>(null);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const completeLogin = (_result: AuthResponse) => {
    toast.success('Portal girişi başarılı!');
    navigate('/dashboard');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
      const result = await dispatch(login({ ...formData, portal_login: true })).unwrap();

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
    <div className="auth-card">
      <div className="auth-header">
        <h2>Personel Girişi</h2>
        <p>Self-servis portalına erişmek için giriş yapın</p>
      </div>

      {challenge ? (
        <TwoFactorChallenge
          challengeToken={challenge.challenge_token}
          onSuccess={completeLogin}
          onCancel={() => setChallenge(null)}
        />
      ) : (
        <>
          <form onSubmit={handleSubmit} className="auth-form">
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
                  name="email"
                  className="form-control"
                  placeholder="personel@firma.com"
                  value={formData.email}
                  onChange={handleChange}
                  required
                />
              </div>
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
                  className="form-control"
                  placeholder="••••••••"
                  value={formData.password}
                  onChange={handleChange}
                  required
                />
                <button
                  type="button"
                  className="input-action"
                  onClick={() => setShowPassword(!showPassword)}
                >
                  {showPassword ? <BsEyeSlash /> : <BsEye />}
                </button>
              </div>
            </div>

            <button type="submit" className="btn btn-primary btn-block" disabled={isLoading}>
              {isLoading ? 'Giriş yapılıyor...' : 'Giriş Yap'}
            </button>
          </form>

          <div className="auth-footer mt-3 text-center">
            <p>
              Şifrenizi mi unuttunuz? <Link to="/forgot-password">Şifre Sıfırla</Link>
            </p>
          </div>
        </>
      )}
    </div>
  );
};

export default LoginPage;
