import React, { useState, useEffect, useRef, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { Outlet, useNavigate, useLocation } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import { RootState, AppDispatch } from '../store';
import { logout } from '@shared/store/slices/authSlice';
import { toggleTheme, toggleSidebar, setSidebarOpen } from '@shared/store/slices/themeSlice';
import {
  BsBoxArrowRight,
  BsSun,
  BsMoon,
  BsBell,
  BsList,
  BsX,
  BsChevronRight,
  BsGear,
} from 'react-icons/bs';
import ModuleRail from '../components/layout/ModuleRail';
import { moduleGroups, type ModuleGroup } from '../components/layout/moduleNav';
import ContextSidebar from '../components/layout/ContextSidebar';

function findModuleIdByPath(pathname: string): string {
  const byBasePath = moduleGroups
    .filter((module) => module.basePath && pathname.startsWith(module.basePath))
    .sort((a, b) => (b.basePath?.length ?? 0) - (a.basePath?.length ?? 0));
  if (byBasePath[0]) {
    return byBasePath[0].id;
  }

  const byItem = moduleGroups.find((module) =>
    module.items.some(
      (item) => pathname === item.path || pathname.startsWith(`${item.path}/`),
    ),
  );
  return byItem?.id ?? 'dashboard';
}

/** Mobil drawer — key={pathname} ile remount edilince kapalı initial state'e döner. */
const CompanyMobileDrawer: React.FC<{
  activeModules: string[];
  toggleHost: HTMLElement | null;
}> = ({ activeModules, toggleHost }) => {
  const [open, setOpen] = useState(false);
  const location = useLocation();
  const navigate = useNavigate();

  const toggleButton = (
    <button
      type="button"
      className="header-toggle mobile-only"
      onClick={() => setOpen((prev) => !prev)}
    >
      {open ? <BsX size={20} /> : <BsList size={20} />}
    </button>
  );

  return (
    <>
      {toggleHost ? createPortal(toggleButton, toggleHost) : null}

      <div
        className={`sidebar-overlay ${open ? 'active' : ''}`}
        onClick={() => setOpen(false)}
      />

      <div
        className={`mobile-sheet-overlay ${open ? 'active' : ''}`}
        onClick={() => setOpen(false)}
      />

      <div className={`mobile-sidebar-sheet ${open ? 'open' : ''}`}>
        <div className="mobile-sheet-header">
          <span>Menü</span>
          <button type="button" onClick={() => setOpen(false)}>
            <BsX size={24} />
          </button>
        </div>
        <div className="mobile-sheet-content">
          {moduleGroups
            .filter((m) => !m.moduleKey || activeModules.includes(m.moduleKey))
            .map((module) => (
              <div key={module.id} className="mobile-module-group">
                <div
                  className="mobile-module-header"
                  style={{ '--module-color': module.color } as React.CSSProperties}
                >
                  <module.icon />
                  <span>{module.label}</span>
                </div>
                <div className="mobile-module-items">
                  {module.items.map((item) => (
                    <a
                      key={item.path}
                      href={item.path}
                      className={`mobile-module-item ${location.pathname === item.path ? 'active' : ''}`}
                      onClick={(e) => {
                        e.preventDefault();
                        navigate(item.path);
                        setOpen(false);
                      }}
                    >
                      {item.label}
                    </a>
                  ))}
                </div>
              </div>
            ))}
        </div>
      </div>
    </>
  );
};

const MainLayout: React.FC = () => {
  const dispatch = useDispatch<AppDispatch>();
  const navigate = useNavigate();
  const location = useLocation();
  const { user } = useSelector((state: RootState) => state.auth);
  const { mode, sidebarOpen } = useSelector((state: RootState) => state.theme);

  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const userMenuRef = useRef<HTMLDivElement>(null);
  const mobileToggleHostRef = useRef<HTMLDivElement>(null);
  const [mobileToggleHost, setMobileToggleHost] = useState<HTMLElement | null>(null);

  const activeModules = user?.company?.active_modules || [];

  const activeModule = useMemo(
    () => findModuleIdByPath(location.pathname),
    [location.pathname],
  );

  const currentModule: ModuleGroup | null =
    moduleGroups.find((m) => m.id === activeModule) || null;

  useEffect(() => {
    setMobileToggleHost(mobileToggleHostRef.current);
  }, []);

  const handleModuleClick = (moduleId: string) => {
    if (moduleId === activeModule) {
      dispatch(toggleSidebar());
    } else {
      dispatch(setSidebarOpen(true));
      const module = moduleGroups.find((m) => m.id === moduleId);
      if (module && module.items.length > 0) {
        navigate(module.items[0].path);
      }
    }
  };

  const handleContextToggle = () => {
    dispatch(toggleSidebar());
  };

  const getBreadcrumb = () => {
    const pathSegments = location.pathname.split('/').filter(Boolean);
    const labels: Record<string, string> = {
      dashboard: 'Dashboard',
      users: 'Kullanıcılar',
      roles: 'Roller',
      leaves: 'İzin Yönetimi',
      types: 'Türler',
      balances: 'Bakiyeler',
      documents: 'Evrak Yönetimi',
      categories: 'Kategoriler',
      recruitment: 'İşe Alım',
      positions: 'Pozisyonlar',
      applications: 'Başvurular',
      interviews: 'Mülakatlar',
      'cv-pool': 'CV Havuzu',
      reports: 'Raporlar',
      'custom-fields': 'Özel Alanlar',
      onboarding: 'Onboarding',
      templates: 'Şablonlar',
      performance: 'Performans',
      periods: 'Dönemler',
      criteria: 'Kriterler',
      training: 'Eğitim',
      sessions: 'Oturumlar',
      assets: 'Varlık Yönetimi',
      assignments: 'Atamalar',
      surveys: 'Anketler',
      analytics: 'Analitik',
      settings: 'Ayarlar',
      'audit-logs': 'Log & Denetim',
    };

    return pathSegments.map((segment) => labels[segment] || segment);
  };

  const handleLogout = async () => {
    await dispatch(logout());
    navigate('/login');
  };

  const handleThemeToggle = () => {
    dispatch(toggleTheme());
  };

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (userMenuRef.current && !userMenuRef.current.contains(event.target as Node)) {
        setUserMenuOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <div className={`company-portal app-layout dual-sidebar-layout ${sidebarOpen ? 'context-expanded' : 'context-collapsed'}`}>
      <ModuleRail
        activeModule={activeModule}
        onModuleClick={handleModuleClick}
        activeModules={activeModules}
        companyName={user?.company?.name}
      />

      <ContextSidebar
        module={currentModule}
        expanded={sidebarOpen}
        onToggle={handleContextToggle}
      />

      <div className="main-content">
        <header className="main-header">
          <div className="header-left">
            <div ref={mobileToggleHostRef} className="mobile-only" />

            <button
              type="button"
              className="header-toggle desktop-only"
              onClick={handleContextToggle}
              title={sidebarOpen ? 'Menüyü Daralt' : 'Menüyü Genişlet'}
              aria-label={sidebarOpen ? 'Menüyü Daralt' : 'Menüyü Genişlet'}
            >
              <BsList size={20} />
            </button>

            <div className="breadcrumb">
              {getBreadcrumb().map((item, idx, arr) => (
                <React.Fragment key={idx}>
                  <span className={`breadcrumb-item ${idx === arr.length - 1 ? 'active' : ''}`}>
                    {item}
                  </span>
                  {idx < arr.length - 1 && (
                    <span className="breadcrumb-separator"><BsChevronRight size={10} /></span>
                  )}
                </React.Fragment>
              ))}
            </div>
          </div>

          <div className="header-right">
            <button
              type="button"
              className="header-btn"
              onClick={handleThemeToggle}
              title={mode === 'dark' ? 'Açık Tema' : 'Koyu Tema'}
            >
              {mode === 'dark' ? <BsSun size={18} /> : <BsMoon size={18} />}
            </button>

            <button type="button" className="header-btn has-notification" title="Bildirimler">
              <BsBell size={18} />
            </button>

            <div className="dropdown" ref={userMenuRef}>
              <button
                type="button"
                className="header-btn user-btn"
                onClick={() => setUserMenuOpen(!userMenuOpen)}
                title="Hesap"
              >
                <div className="user-avatar-small">
                  {user?.name?.charAt(0).toUpperCase() || 'U'}
                </div>
                <span className="user-name-text">{user?.name?.split(' ')[0]}</span>
              </button>

              {userMenuOpen && (
                <div className="dropdown-menu" style={{ opacity: 1, visibility: 'visible', transform: 'translateY(4px)' }}>
                  <div style={{ padding: '0.5rem 0.75rem', borderBottom: '1px solid var(--border-primary)', marginBottom: '0.25rem' }}>
                    <div style={{ fontWeight: 600, fontSize: '0.8125rem', color: 'var(--text-primary)' }}>{user?.name}</div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>{user?.email}</div>
                  </div>
                  <div className="dropdown-item" onClick={() => navigate('/settings')}>
                    <BsGear /> Ayarlar
                  </div>
                  <div className="dropdown-divider" />
                  <div className="dropdown-item danger" onClick={handleLogout}>
                    <BsBoxArrowRight /> Çıkış Yap
                  </div>
                </div>
              )}
            </div>
          </div>
        </header>

        <main className="page-content">
          <Outlet />
        </main>
      </div>

      <CompanyMobileDrawer
        key={location.pathname}
        activeModules={activeModules}
        toggleHost={mobileToggleHost}
      />

      <style>{`
        @media (max-width: 768px) {
          .desktop-only { display: none !important; }
          .mobile-only { display: flex !important; }
        }
        @media (min-width: 769px) {
          .mobile-only { display: none !important; }
        }
      `}</style>
    </div>
  );
};

export default MainLayout;
