import React, { useEffect } from 'react';
import { Outlet, useNavigate } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import { RootState, AppDispatch } from '../../store';
import { fetchCurrentUser } from '../../store/slices/authSlice';
import { setTheme } from '../../store/slices/themeSlice';
import Sidebar from './Sidebar';
import Header from './Header';

const MainLayout: React.FC = () => {
  const dispatch = useDispatch<AppDispatch>();
  const navigate = useNavigate();
  const { isAuthenticated, user } = useSelector((state: RootState) => state.auth);
  const { sidebarOpen, mode } = useSelector((state: RootState) => state.theme);

  // Auth check
  useEffect(() => {
    if (!isAuthenticated) {
      navigate('/login');
      return;
    }

    // Kullanıcı bilgisini yenile
    dispatch(fetchCurrentUser());
  }, [isAuthenticated, navigate, dispatch]);

  // Tema ayarla
  useEffect(() => {
    document.documentElement.setAttribute('data-theme', mode);
  }, [mode]);

  // Kullanıcı tercihlerine göre tema ayarla
  useEffect(() => {
    if (user?.preferences?.theme) {
      dispatch(setTheme(user.preferences.theme));
    }
  }, [user?.preferences?.theme, dispatch]);

  if (!isAuthenticated) {
    return null;
  }

  return (
    <div className={`app-layout ${!sidebarOpen ? 'sidebar-collapsed' : ''}`}>
      <Sidebar collapsed={!sidebarOpen} />
      <div className="app-main">
        <Header />
        <main className="app-content">
          <Outlet />
        </main>
      </div>
    </div>
  );
};

export default MainLayout;

