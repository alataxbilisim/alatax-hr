import React from 'react';
import { Outlet, NavLink, useNavigate } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import { RootState, AppDispatch } from '../store';
import { logout } from '@shared/store/slices/authSlice';
import { toggleTheme } from '@shared/store/slices/themeSlice';
import {
  BsSpeedometer2,
  BsBuilding,
  BsBox,
  BsPuzzle,
  BsPeople,
  BsJournalText,
  BsBoxArrowRight,
  BsSun,
  BsMoon,
  BsGear,
} from 'react-icons/bs';

const AdminLayout: React.FC = () => {
  const dispatch = useDispatch<AppDispatch>();
  const navigate = useNavigate();
  const { user } = useSelector((state: RootState) => state.auth);
  const { mode } = useSelector((state: RootState) => state.theme);

  const handleLogout = async () => {
    await dispatch(logout());
    navigate('/login');
  };

  const handleThemeToggle = () => {
    dispatch(toggleTheme());
  };

  const menuItems = [
    { path: '/dashboard', icon: BsSpeedometer2, label: 'Dashboard' },
    { path: '/companies', icon: BsBuilding, label: 'Firmalar' },
    { path: '/packages', icon: BsBox, label: 'Lisans Paketleri' },
    { path: '/modules', icon: BsPuzzle, label: 'Modüller' },
    { path: '/users', icon: BsPeople, label: 'Kullanıcılar' },
    { path: '/logs', icon: BsJournalText, label: 'Sistem Logları' },
  ];

  return (
    <div className="layout">
      {/* Sidebar */}
      <aside className="sidebar">
        <div className="sidebar-header">
          <div className="sidebar-brand">
            <h1 className="admin-brand">ALATAX</h1>
            <span className="sidebar-subtitle">SuperAdmin</span>
          </div>
        </div>

        <nav className="sidebar-nav">
          {menuItems.map((item) => (
            <NavLink
              key={item.path}
              to={item.path}
              className={({ isActive }) =>
                `sidebar-nav-item ${isActive ? 'active' : ''}`
              }
            >
              <item.icon size={20} />
              <span>{item.label}</span>
            </NavLink>
          ))}
        </nav>

        <div className="sidebar-footer">
          <div className="sidebar-user">
            <div className="avatar avatar-sm">
              {user?.name?.charAt(0).toUpperCase() || 'A'}
            </div>
            <div className="sidebar-user-info">
              <div className="sidebar-user-name">{user?.name}</div>
              <div className="sidebar-user-role">Super Admin</div>
            </div>
          </div>
        </div>
      </aside>

      {/* Main Content */}
      <div className="main">
        {/* Header */}
        <header className="header">
          <div className="header-left">
            <h2 className="header-title">SuperAdmin Panel</h2>
          </div>
          <div className="header-right">
            <button
              className="header-btn"
              onClick={handleThemeToggle}
              title="Tema Değiştir"
            >
              {mode === 'dark' ? <BsSun size={18} /> : <BsMoon size={18} />}
            </button>
            <button className="header-btn" title="Ayarlar">
              <BsGear size={18} />
            </button>
            <button
              className="header-btn danger"
              onClick={handleLogout}
              title="Çıkış Yap"
            >
              <BsBoxArrowRight size={18} />
            </button>
          </div>
        </header>

        {/* Page Content */}
        <main className="content">
          <Outlet />
        </main>
      </div>
    </div>
  );
};

export default AdminLayout;

