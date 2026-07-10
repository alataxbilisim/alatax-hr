import React, { useState, useRef, useEffect } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import { RootState, AppDispatch } from '../../store';
import { logout } from '../../store/slices/authSlice';
import { toggleTheme, toggleSidebar } from '../../store/slices/themeSlice';
import { notificationsApi } from '../../services/api';
import {
  BsList,
  BsSearch,
  BsBell,
  BsMoon,
  BsSun,
  BsPersonCircle,
  BsGear,
  BsBoxArrowRight,
  BsChevronDown,
  BsCheck2All,
  BsInfoCircle,
} from 'react-icons/bs';

interface HeaderProps {
  title?: string;
}

const Header: React.FC<HeaderProps> = ({ title }) => {
  const dispatch = useDispatch<AppDispatch>();
  const navigate = useNavigate();
  const { user } = useSelector((state: RootState) => state.auth);
  const { mode } = useSelector((state: RootState) => state.theme);
  const { unreadCount } = useSelector((state: RootState) => state.notifications);
  
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const [notifMenuOpen, setNotifMenuOpen] = useState(false);
  const [notifications, setNotifications] = useState<Array<{ id: string; data: { title: string; message: string }; read_at: string | null; created_at: string }>>([]);
  const [localUnreadCount, setLocalUnreadCount] = useState(0);
  const userMenuRef = useRef<HTMLDivElement>(null);
  const notifMenuRef = useRef<HTMLDivElement>(null);

  // Load notifications
  useEffect(() => {
    const loadNotifications = async () => {
      try {
        const response = await notificationsApi.list({ per_page: 5 });
        setNotifications(response.data.data?.notifications || []);
        setLocalUnreadCount(response.data.data?.unread_count || 0);
      } catch (error) {
        console.error('Bildirimler yüklenemedi:', error);
      }
    };
    loadNotifications();
  }, []);

  // Click outside to close
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (userMenuRef.current && !userMenuRef.current.contains(event.target as Node)) {
        setUserMenuOpen(false);
      }
      if (notifMenuRef.current && !notifMenuRef.current.contains(event.target as Node)) {
        setNotifMenuOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleMarkAllAsRead = async () => {
    try {
      await notificationsApi.markAllAsRead();
      setLocalUnreadCount(0);
      setNotifications(prev => prev.map(n => ({ ...n, read_at: new Date().toISOString() })));
    } catch (error) {
      console.error('Bildirimler işaretlenemedi:', error);
    }
  };

  const handleLogout = async () => {
    await dispatch(logout());
    navigate('/login');
  };

  const handleThemeToggle = () => {
    dispatch(toggleTheme());
  };

  const handleSidebarToggle = () => {
    dispatch(toggleSidebar());
  };

  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map(n => n[0])
      .join('')
      .toUpperCase()
      .slice(0, 2);
  };

  const getUserRole = () => {
    switch (user?.type) {
      case 'super_admin':
        return 'Super Admin';
      case 'company_admin':
        return 'Yönetici';
      default:
        return user?.roles?.[0] || 'Kullanıcı';
    }
  };

  return (
    <header className="header">
      {/* Left Section */}
      <div className="header-left">
        <button className="header-toggle" onClick={handleSidebarToggle}>
          <BsList size={20} />
        </button>
        {title && <h1 className="header-title">{title}</h1>}
      </div>

      {/* Right Section */}
      <div className="header-right">
        {/* Search */}
        <div className="header-search">
          <BsSearch className="header-search-icon" />
          <input
            type="text"
            className="header-search-input"
            placeholder="Ara..."
          />
        </div>

        {/* Theme Toggle */}
        <button className="header-btn" onClick={handleThemeToggle} title="Tema Değiştir">
          {mode === 'dark' ? <BsSun size={18} /> : <BsMoon size={18} />}
        </button>

        {/* Notifications */}
        <div className={`dropdown ${notifMenuOpen ? 'open' : ''}`} ref={notifMenuRef}>
          <button className="header-btn" title="Bildirimler" onClick={() => setNotifMenuOpen(!notifMenuOpen)}>
            <BsBell size={18} />
            {(unreadCount > 0 || localUnreadCount > 0) && <span className="header-btn-badge" />}
          </button>

          <div className="dropdown-menu dropdown-menu-notifications" style={{ width: '320px', right: 0 }}>
            <div className="dropdown-header d-flex justify-content-between align-items-center px-3 py-2">
              <strong>Bildirimler</strong>
              {localUnreadCount > 0 && (
                <button className="btn btn-sm btn-link p-0" onClick={handleMarkAllAsRead}>
                  <BsCheck2All /> Tümünü Okundu İşaretle
                </button>
              )}
            </div>
            <div className="dropdown-divider" />
            {notifications.length > 0 ? (
              <>
                {notifications.slice(0, 5).map((notif) => (
                  <div 
                    key={notif.id} 
                    className={`dropdown-item notification-item ${!notif.read_at ? 'unread' : ''}`}
                    style={{ 
                      padding: '0.75rem 1rem',
                      backgroundColor: !notif.read_at ? 'var(--primary-soft)' : 'transparent',
                      borderLeft: !notif.read_at ? '3px solid var(--primary)' : 'none',
                    }}
                  >
                    <div className="notification-title" style={{ fontWeight: 500, marginBottom: '0.25rem' }}>
                      {notif.data?.title || 'Bildirim'}
                    </div>
                    <div className="notification-message" style={{ fontSize: '0.85rem', color: 'var(--text-secondary)' }}>
                      {notif.data?.message || ''}
                    </div>
                    <div className="notification-time" style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginTop: '0.25rem' }}>
                      {new Date(notif.created_at).toLocaleString('tr-TR')}
                    </div>
                  </div>
                ))}
                <div className="dropdown-divider" />
                <div className="dropdown-item text-center" onClick={() => { navigate('/notifications'); setNotifMenuOpen(false); }}>
                  Tüm Bildirimleri Gör
                </div>
              </>
            ) : (
              <div className="dropdown-item text-center py-4" style={{ color: 'var(--text-tertiary)' }}>
                <BsInfoCircle size={24} className="mb-2" />
                <div>Henüz bildirim yok</div>
              </div>
            )}
          </div>
        </div>

        {/* User Menu */}
        <div className={`dropdown ${userMenuOpen ? 'open' : ''}`} ref={userMenuRef}>
          <div className="header-user" onClick={() => setUserMenuOpen(!userMenuOpen)}>
            <div className="avatar avatar-sm">
              {user?.avatar ? (
                <img src={user.avatar} alt={user.name} />
              ) : (
                getInitials(user?.name || 'U')
              )}
            </div>
            <div className="header-user-info">
              <div className="header-user-name">{user?.name}</div>
              <div className="header-user-role">{getUserRole()}</div>
            </div>
            <BsChevronDown size={12} style={{ color: 'var(--text-tertiary)' }} />
          </div>

          <div className="dropdown-menu">
            <div className="dropdown-item" onClick={() => { navigate('/profile'); setUserMenuOpen(false); }}>
              <BsPersonCircle size={16} />
              <span>Profil</span>
            </div>
            <div className="dropdown-item" onClick={() => { navigate('/settings'); setUserMenuOpen(false); }}>
              <BsGear size={16} />
              <span>Ayarlar</span>
            </div>
            <div className="dropdown-divider" />
            <div className="dropdown-item danger" onClick={handleLogout}>
              <BsBoxArrowRight size={16} />
              <span>Çıkış Yap</span>
            </div>
          </div>
        </div>
      </div>
    </header>
  );
};

export default Header;

