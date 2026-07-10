import React, { useEffect } from 'react';
import { Outlet, useNavigate } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { RootState } from '../../store';

const AuthLayout: React.FC = () => {
  const navigate = useNavigate();
  const { isAuthenticated, user } = useSelector((state: RootState) => state.auth);

  useEffect(() => {
    if (isAuthenticated && user) {
      // Kullanıcı tipine göre yönlendir
      if (user.type === 'super_admin') {
        navigate('/admin');
      } else {
        navigate('/dashboard');
      }
    }
  }, [isAuthenticated, user, navigate]);

  // Tema ayarla
  useEffect(() => {
    document.documentElement.setAttribute('data-theme', 'dark');
  }, []);

  if (isAuthenticated) {
    return null;
  }

  return (
    <div className="auth-layout">
      <div className="auth-background" />
      <div className="auth-container">
        <Outlet />
      </div>
    </div>
  );
};

export default AuthLayout;

