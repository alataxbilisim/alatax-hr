import React, { useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import { AppDispatch, RootState } from '../store';
import { login } from '@shared/store/slices/authSlice';
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

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    try {
      const result = await dispatch(login(formData)).unwrap();
      
      // Check if user is super_admin
      if (result.user.type !== 'super_admin') {
        toast.error('Bu panel sadece SuperAdmin kullanıcıları içindir.');
        return;
      }
      
      toast.success('Giriş başarılı!');
      navigate('/dashboard');
    } catch {
      // Error is handled by the API interceptor
    }
  };

  return (
    <div className="auth-card">
      <div className="auth-header">
        <h2>SuperAdmin Girişi</h2>
        <p>Yönetim paneline erişmek için giriş yapın</p>
      </div>

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
              placeholder="admin@alataxbilisim.com"
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

        <button
          type="submit"
          className="btn btn-primary btn-block"
          disabled={isLoading}
        >
          {isLoading ? 'Giriş yapılıyor...' : 'Giriş Yap'}
        </button>
      </form>
    </div>
  );
};

export default LoginPage;

