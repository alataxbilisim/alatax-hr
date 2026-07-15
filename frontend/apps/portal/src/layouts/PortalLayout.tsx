import React, { useState, useEffect, useCallback, useRef, useImperativeHandle, forwardRef } from 'react';
import { Outlet, NavLink, useNavigate, useLocation } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import { RootState, AppDispatch } from '../store';
import { logout } from '@shared/store/slices/authSlice';
import { toggleTheme } from '@shared/store/slices/themeSlice';
import { NotificationBell } from '@shared/components';
import {
  BsSpeedometer2,
  BsPersonCircle,
  BsCalendarCheck,
  BsFileEarmarkText,
  BsCurrencyDollar,
  BsMegaphone,
  BsInboxes,
  BsBoxArrowRight,
  BsSun,
  BsMoon,
  BsList,
  BsMortarboard,
  BsGraphUp,
  BsClipboardData,
  BsX,
  BsHouseDoor,
  BsThreeDots,
  BsClock,
  BsReceipt,
  BsCashCoin,
} from 'react-icons/bs';
import { useTranslation } from '@shared/i18n';

type MenuItem = {
  path: string;
  icon: React.ElementType;
  label: string;
};

type PortalMobileNavHandle = {
  open: () => void;
  close: () => void;
  toggle: () => void;
};

type PortalMobileNavProps = {
  menuItems: MenuItem[];
  userName?: string;
  onLogout: () => void;
};

/** Mobil drawer — key={pathname} remount ile kapalı initial state. */
const PortalMobileNav = forwardRef<PortalMobileNavHandle, PortalMobileNavProps>(
  function PortalMobileNav({ menuItems, userName, onLogout }, ref) {
    const [open, setOpen] = useState(false);

    useImperativeHandle(ref, () => ({
      open: () => setOpen(true),
      close: () => setOpen(false),
      toggle: () => setOpen((prev) => !prev),
    }));

    useEffect(() => {
      if (open) {
        document.body.style.overflow = 'hidden';
      } else {
        document.body.style.overflow = '';
      }
      return () => {
        document.body.style.overflow = '';
      };
    }, [open]);

    return (
      <>
        <div
          className={`sidebar-overlay ${open ? 'visible' : ''}`}
          onClick={() => setOpen(false)}
        />

        <aside className={`sidebar ${open ? 'mobile-open' : ''}`}>
          <div className="sidebar-header">
            <div className="sidebar-brand">
              <h1>ALATAX</h1>
              <span className="sidebar-subtitle">Personel Portalı</span>
            </div>
            <button type="button" className="header-toggle" onClick={() => setOpen(false)}>
              <BsX size={24} />
            </button>
          </div>

          <nav className="sidebar-nav">
            {menuItems.map((item) => (
              <NavLink
                key={item.path}
                to={item.path}
                className={({ isActive }) =>
                  `sidebar-nav-item ${isActive ? 'active' : ''}`
                }
                onClick={() => setOpen(false)}
              >
                <item.icon size={20} />
                <span>{item.label}</span>
              </NavLink>
            ))}
          </nav>

          <div className="sidebar-footer">
            <div className="sidebar-user">
              <div className="avatar avatar-sm">
                {userName?.charAt(0).toUpperCase() || 'U'}
              </div>
              <div className="sidebar-user-info">
                <div className="sidebar-user-name">{userName}</div>
                <div className="sidebar-user-role">Personel</div>
              </div>
            </div>
            <div className="sidebar-mobile-actions" style={{ marginTop: '1rem' }}>
              <button
                type="button"
                className="btn btn-outline-primary btn-sm"
                style={{ width: '100%' }}
                onClick={onLogout}
              >
                <BsBoxArrowRight size={16} />
                <span style={{ marginLeft: '0.5rem' }}>Çıkış Yap</span>
              </button>
            </div>
          </div>
        </aside>
      </>
    );
  },
);

const PortalLayout: React.FC = () => {
  const dispatch = useDispatch<AppDispatch>();
  const navigate = useNavigate();
  const location = useLocation();
  const { t } = useTranslation('common');
  const { user } = useSelector((state: RootState) => state.auth);
  const { mode } = useSelector((state: RootState) => state.theme);
  const [desktopSidebarOpen, setDesktopSidebarOpen] = useState(false);
  const [isMobile, setIsMobile] = useState(false);
  const mobileNavRef = useRef<PortalMobileNavHandle>(null);

  useEffect(() => {
    const checkMobile = () => {
      setIsMobile(window.innerWidth <= 768);
    };
    checkMobile();
    window.addEventListener('resize', checkMobile);
    return () => window.removeEventListener('resize', checkMobile);
  }, []);

  const handleLogout = async () => {
    await dispatch(logout());
    navigate('/login');
  };

  const handleThemeToggle = () => {
    dispatch(toggleTheme());
  };

  const toggleSidebar = useCallback(() => {
    if (isMobile) {
      mobileNavRef.current?.toggle();
    } else {
      setDesktopSidebarOpen((prev) => !prev);
    }
  }, [isMobile]);

  const menuItems: MenuItem[] = [
    { path: '/dashboard', icon: BsSpeedometer2, label: 'Ana Sayfa' },
    { path: '/profile', icon: BsPersonCircle, label: 'Profilim' },
    { path: '/leaves', icon: BsCalendarCheck, label: 'İzinlerim' },
    { path: '/payslips', icon: BsCurrencyDollar, label: 'Bordrolarım' },
    { path: '/salary', icon: BsCashCoin, label: t('nav.mySalary') },
    { path: '/documents', icon: BsFileEarmarkText, label: 'Belgelerim' },
    { path: '/training', icon: BsMortarboard, label: 'Eğitimlerim' },
    { path: '/performance', icon: BsGraphUp, label: 'Performansım' },
    { path: '/surveys', icon: BsClipboardData, label: 'Anketler' },
    { path: '/timesheet', icon: BsClock, label: 'Puantaj' },
    { path: '/expenses', icon: BsReceipt, label: 'Masraflar' },
    { path: '/announcements', icon: BsMegaphone, label: 'Duyurular' },
    { path: '/requests', icon: BsInboxes, label: 'Taleplerim' },
  ];

  const bottomNavItems = [
    { path: '/dashboard', icon: BsHouseDoor, label: 'Ana Sayfa' },
    { path: '/leaves', icon: BsCalendarCheck, label: 'İzinler' },
    { path: '/requests', icon: BsInboxes, label: 'Talepler' },
    { path: '/announcements', icon: BsMegaphone, label: 'Duyurular' },
    { path: '/more', icon: BsThreeDots, label: 'Daha Fazla', isMore: true },
  ];

  const handleMoreClick = () => {
    mobileNavRef.current?.open();
  };

  return (
    <div className={`layout ${!isMobile && desktopSidebarOpen ? '' : 'sidebar-collapsed'}`}>
      {/* Desktop sidebar — mobil drawer'dan ayrı (route remount desktop state'i bozmasın) */}
      {!isMobile && (
        <aside className="sidebar">
          <div className="sidebar-header">
            <div className="sidebar-brand">
              <h1>ALATAX</h1>
              <span className="sidebar-subtitle">Personel Portalı</span>
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
                {user?.name?.charAt(0).toUpperCase() || 'U'}
              </div>
              <div className="sidebar-user-info">
                <div className="sidebar-user-name">{user?.name}</div>
                <div className="sidebar-user-role">Personel</div>
              </div>
            </div>
          </div>
        </aside>
      )}

      {isMobile && (
        <PortalMobileNav
          key={location.pathname}
          ref={mobileNavRef}
          menuItems={menuItems}
          userName={user?.name}
          onLogout={handleLogout}
        />
      )}

      <div className="main">
        <header className="header">
          <div className="header-left">
            <button
              type="button"
              className="header-toggle"
              onClick={toggleSidebar}
              aria-label="Toggle menu"
            >
              <BsList size={20} />
            </button>
            {isMobile && (
              <span style={{ marginLeft: '0.75rem', fontWeight: 600, color: 'var(--portal-primary)' }}>
                ALATAX
              </span>
            )}
          </div>
          <div className="header-right">
            <button
              type="button"
              className="header-btn"
              onClick={handleThemeToggle}
              title="Tema Değiştir"
              aria-label="Toggle theme"
            >
              {mode === 'dark' ? <BsSun size={18} /> : <BsMoon size={18} />}
            </button>
            <NotificationBell />
            {!isMobile && (
              <button
                type="button"
                className="header-btn danger"
                onClick={handleLogout}
                title="Çıkış Yap"
                aria-label="Logout"
              >
                <BsBoxArrowRight size={18} />
              </button>
            )}
          </div>
        </header>

        <main className="content">
          <Outlet />
        </main>
      </div>

      <nav className="bottom-nav">
        <div className="bottom-nav-container">
          {bottomNavItems.map((item) => {
            if (item.isMore) {
              return (
                <button
                  key="more"
                  type="button"
                  className="bottom-nav-item"
                  onClick={handleMoreClick}
                  aria-label="More options"
                >
                  <item.icon size={20} />
                  <span className="bottom-nav-label">{item.label}</span>
                </button>
              );
            }
            return (
              <NavLink
                key={item.path}
                to={item.path}
                className={({ isActive }) =>
                  `bottom-nav-item ${isActive ? 'active' : ''}`
                }
              >
                <item.icon size={20} />
                <span className="bottom-nav-label">{item.label}</span>
              </NavLink>
            );
          })}
        </div>
      </nav>
    </div>
  );
};

export default PortalLayout;
