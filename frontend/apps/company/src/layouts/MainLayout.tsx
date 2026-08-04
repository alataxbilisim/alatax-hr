import React, { useState, useEffect, useRef, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { Outlet, useNavigate, useLocation } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import { RootState, AppDispatch } from '../store';
import { logout } from '@shared/store/slices/authSlice';
import { toggleTheme, toggleSidebar, setSidebarOpen } from '@shared/store/slices/themeSlice';
import { useTranslation } from '@shared/i18n';
import { Select, NotificationBell } from '@shared/components';
import {
  BsBoxArrowRight,
  BsSun,
  BsMoon,
  BsList,
  BsX,
  BsChevronRight,
  BsPersonGear,
} from 'react-icons/bs';
import ModuleRail from '../components/layout/ModuleRail';
import {
  moduleGroups,
  operationalModuleGroups,
  pinnedModuleGroups,
  getFilteredMenuItems,
  findModuleIdByPath,
  type ModuleGroup,
} from '../components/layout/moduleNav';
import ContextSidebar from '../components/layout/ContextSidebar';
import {
  BRANCH_ALL,
  fetchBranchContext,
  resetBranchContext,
  setSelectedBranchId,
} from '../store/branchContextSlice';
import {
  fetchCompanyContext,
  resetCompanyContext,
  setSelectedCompanyId,
} from '../store/companyContextSlice';

/** Mobil drawer — key={pathname} ile remount edilince kapalı initial state'e döner. */
const CompanyMobileDrawer: React.FC<{
  activeModules: string[];
  toggleHost: HTMLElement | null;
}> = ({ activeModules, toggleHost }) => {
  const [open, setOpen] = useState(false);
  const location = useLocation();
  const navigate = useNavigate();
  const { t } = useTranslation('common');
  const { user } = useSelector((state: RootState) => state.auth);

  const hasModuleAccess = (module: ModuleGroup): boolean => {
    if (!user) return false;
    if (user.type === 'company_admin' || user.type === 'super_admin') return true;
    if (!module.permissionModule) return true;
    const permissions = user.permissions || [];
    const permModule = module.permissionModule;
    if (permissions.includes('*') || permissions.includes(`${permModule}.*`)) return true;
    return permissions.some((p: string) => p.startsWith(`${permModule}.`));
  };

  const allModules = [
    ...operationalModuleGroups.filter((m) => {
      if (m.hidden) return false;
      if (m.moduleKey && !activeModules.includes(m.moduleKey)) return false;
      if (!hasModuleAccess(m)) return false;
      return (
        getFilteredMenuItems(
          m,
          user
            ? { type: user.type, permissions: user.permissions || [] }
            : null,
          activeModules
        ).length > 0
      );
    }),
    ...pinnedModuleGroups.filter((m) => {
      if (m.hidden) return false;
      if (m.id === 'account') return !!user;
      if (!hasModuleAccess(m)) return false;
      return (
        getFilteredMenuItems(
          m,
          user
            ? { type: user.type, permissions: user.permissions || [] }
            : null,
          activeModules
        ).length > 0
      );
    }),
  ];

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
          <span>{t('nav.menu')}</span>
          <button type="button" onClick={() => setOpen(false)}>
            <BsX size={24} />
          </button>
        </div>
        <div className="mobile-sheet-content">
          {allModules.map((module) => {
            const items = getFilteredMenuItems(
              module,
              user
                ? { type: user.type, permissions: user.permissions || [] }
                : null,
              activeModules
            );
            return (
              <div key={module.id} className="mobile-module-group">
                <div
                  className="mobile-module-header"
                  style={{ '--module-color': module.color } as React.CSSProperties}
                >
                  <module.icon />
                  <span>{t(module.labelKey)}</span>
                </div>
                <div className="mobile-module-items">
                  {items.map((item) => (
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
                      {t(item.labelKey)}
                    </a>
                  ))}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </>
  );
};

const MainLayout: React.FC = () => {
  const dispatch = useDispatch<AppDispatch>();
  const navigate = useNavigate();
  const location = useLocation();
  const { t } = useTranslation('common');
  const { user } = useSelector((state: RootState) => state.auth);
  const { mode, sidebarOpen } = useSelector((state: RootState) => state.theme);
  const branchContext = useSelector((state: RootState) => state.branchContext);
  const companyContext = useSelector((state: RootState) => state.companyContext);

  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const userMenuRef = useRef<HTMLDivElement>(null);
  const mobileToggleHostRef = useRef<HTMLDivElement>(null);
  const [mobileToggleHost, setMobileToggleHost] = useState<HTMLElement | null>(null);

  const activeModules = user?.company?.active_modules || [];

  const activeModule = useMemo(
    () => findModuleIdByPath(location.pathname),
    [location.pathname]
  );

  const currentModule: ModuleGroup | null =
    moduleGroups.find((m) => m.id === activeModule) || null;

  const branchOptions = useMemo(() => {
    const opts = branchContext.branches.map((b) => ({
      value: String(b.id),
      label: b.name,
    }));
    if (branchContext.canSelectAll) {
      return [{ value: BRANCH_ALL, label: t('nav.allBranches') }, ...opts];
    }
    return opts;
  }, [branchContext.branches, branchContext.canSelectAll, t]);

  const companyOptions = useMemo(
    () =>
      companyContext.companies.map((c) => ({
        value: String(c.id),
        label: c.name,
      })),
    [companyContext.companies]
  );

  const activeCompanyName = useMemo(() => {
    const active = companyContext.companies.find(
      (c) => String(c.id) === companyContext.selectedCompanyId
    );
    return active?.name ?? user?.company?.name;
  }, [companyContext.companies, companyContext.selectedCompanyId, user?.company?.name]);

  useEffect(() => {
    setMobileToggleHost(mobileToggleHostRef.current);
  }, []);

  useEffect(() => {
    if (user) {
      void dispatch(fetchCompanyContext());
      void dispatch(fetchBranchContext());
    }
  }, [dispatch, user]);

  const handleCompanyChange = (value: string) => {
    if (!value || value === companyContext.selectedCompanyId) return;
    dispatch(setSelectedCompanyId(value));
    // Şirket app'inde QueryClientProvider kurulu değil (yalnız Redux + version pattern);
    // listeler branchContext.version / companyContext.version değişimiyle yeniden yüklenir.
    dispatch(resetBranchContext());
    void dispatch(fetchBranchContext());
    navigate('/dashboard');
  };

  const handleModuleClick = (moduleId: string) => {
    if (moduleId === activeModule) {
      dispatch(toggleSidebar());
    } else {
      dispatch(setSidebarOpen(true));
      const module = moduleGroups.find((m) => m.id === moduleId);
      if (module) {
        const visible = getFilteredMenuItems(
          module,
          user
            ? { type: user.type, permissions: user.permissions || [] }
            : null,
          activeModules
        );
        if (visible.length > 0) {
          navigate(visible[0].path);
        }
      }
    }
  };

  const handleContextToggle = () => {
    dispatch(toggleSidebar());
  };

  const getBreadcrumb = (): string[] => {
    if (!currentModule) {
      return [t('nav.dashboard')];
    }

    const crumbs = [t(currentModule.labelKey)];
    const activeItem = [...currentModule.items]
      .sort((a, b) => b.path.length - a.path.length)
      .find(
        (item) =>
          location.pathname === item.path ||
          location.pathname.startsWith(`${item.path}/`)
      );

    if (activeItem && activeItem.labelKey !== currentModule.labelKey) {
      crumbs.push(t(activeItem.labelKey));
    }

    return crumbs;
  };

  const handleLogout = async () => {
    dispatch(resetBranchContext());
    dispatch(resetCompanyContext());
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
              title={sidebarOpen ? t('nav.collapseMenu') : t('nav.expandMenu')}
              aria-label={sidebarOpen ? t('nav.collapseMenu') : t('nav.expandMenu')}
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
            <div className="header-branch-context" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <span
                className="header-company-label"
                style={{
                  fontSize: '0.8125rem',
                  color: 'var(--text-secondary)',
                  whiteSpace: 'nowrap',
                }}
                title={activeCompanyName ?? t('nav.companyLabel')}
              >
                {t('nav.companyLabel')}
                {activeCompanyName ? `: ${activeCompanyName}` : ''}
              </span>
              {companyOptions.length > 1 && (
                <div style={{ minWidth: '10rem' }}>
                  <Select
                    id="header-company-selector"
                    value={companyContext.selectedCompanyId}
                    onChange={handleCompanyChange}
                    options={companyOptions}
                    placeholder={t('nav.companySelector')}
                    aria-label={t('nav.switchCompany')}
                  />
                </div>
              )}
              {branchOptions.length > 0 && (
                <div style={{ minWidth: '10rem' }}>
                  <Select
                    id="header-branch-selector"
                    value={branchContext.selectedBranchId}
                    onChange={(v) => dispatch(setSelectedBranchId(v || BRANCH_ALL))}
                    options={branchOptions}
                    disabled={!branchContext.canSelectAll && branchContext.lockedBranchId !== null}
                    placeholder={t('nav.branchSelector')}
                  />
                </div>
              )}
            </div>

            <button
              type="button"
              className="header-btn"
              onClick={handleThemeToggle}
              title={mode === 'dark' ? t('account.themeLight') : t('account.themeDark')}
            >
              {mode === 'dark' ? <BsSun size={18} /> : <BsMoon size={18} />}
            </button>

            <NotificationBell />

            <div className="dropdown" ref={userMenuRef}>
              <button
                type="button"
                className="header-btn user-btn"
                onClick={() => setUserMenuOpen(!userMenuOpen)}
                title={t('account.settingsMenu')}
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
                  <div
                    className="dropdown-item"
                    onClick={() => {
                      setUserMenuOpen(false);
                      navigate('/account/profile');
                    }}
                  >
                    <BsPersonGear /> {t('account.settingsMenu')}
                  </div>
                  <div className="dropdown-divider" />
                  <div className="dropdown-item danger" onClick={handleLogout}>
                    <BsBoxArrowRight /> {t('nav.logout')}
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
