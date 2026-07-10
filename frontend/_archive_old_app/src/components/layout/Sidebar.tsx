import React, { useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { RootState } from '../../store';
import {
  BsSpeedometer2,
  BsPeople,
  BsBuilding,
  BsPersonBadge,
  BsFileEarmarkText,
  BsPersonCheck,
  BsCalendarCheck,
  BsGear,
  BsJournalText,
  BsBox,
  BsGraphUp,
  BsShieldCheck,
  BsChevronDown,
  BsChevronRight,
  BsClipboardData,
  BsPersonLinesFill,
  BsFileText,
  BsCreditCard,
} from 'react-icons/bs';

interface MenuItem {
  label: string;
  path: string;
  icon: React.ReactNode;
  module?: string;
  badge?: number;
  children?: MenuItem[];
}

interface MenuGroup {
  title: string;
  items: MenuItem[];
}

const Sidebar: React.FC<{ collapsed: boolean }> = ({ collapsed }) => {
  const location = useLocation();
  const { user } = useSelector((state: RootState) => state.auth);
  const [expandedMenus, setExpandedMenus] = useState<string[]>(['recruitment']);
  
  const isSuperAdmin = user?.type === 'super_admin';
  const activeModules = user?.company?.active_modules || [];

  const toggleMenu = (path: string) => {
    setExpandedMenus(prev => 
      prev.includes(path) 
        ? prev.filter(p => p !== path)
        : [...prev, path]
    );
  };

  // SuperAdmin menüsü
  const superAdminMenu: MenuGroup[] = [
    {
      title: 'Genel',
      items: [
        { label: 'Dashboard', path: '/admin', icon: <BsSpeedometer2 /> },
        { label: 'Firmalar', path: '/admin/companies', icon: <BsBuilding /> },
        { label: 'Modüller', path: '/admin/modules', icon: <BsBox /> },
        { label: 'Kullanıcılar', path: '/admin/users', icon: <BsPeople /> },
        { label: 'Lisanslar', path: '/admin/licenses', icon: <BsCreditCard /> },
        { label: 'Paketler', path: '/admin/packages', icon: <BsBox /> },
        { label: 'Sistem Logları', path: '/admin/logs', icon: <BsJournalText /> },
      ],
    },
  ];

  // Firma Admin/Kullanıcı menüsü
  const companyMenu: MenuGroup[] = [
    {
      title: 'Genel',
      items: [
        { label: 'Dashboard', path: '/dashboard', icon: <BsSpeedometer2 /> },
      ],
    },
    {
      title: 'Yönetim',
      items: [
        { label: 'Kullanıcılar', path: '/users', icon: <BsPeople /> },
        { label: 'Roller', path: '/roles', icon: <BsShieldCheck /> },
        { label: 'Firma Ayarları', path: '/company', icon: <BsBuilding /> },
      ],
    },
    {
      title: 'İK Modülleri',
      items: [
        { 
          label: 'İşe Alım', 
          path: '/recruitment', 
          icon: <BsPersonBadge />,
          module: 'job-applications',
          children: [
            { label: 'İş Pozisyonları', path: '/recruitment/positions', icon: <BsClipboardData /> },
            { label: 'Form Builder', path: '/recruitment/forms', icon: <BsFileText /> },
            { label: 'Başvurular', path: '/recruitment/applications', icon: <BsPersonBadge /> },
            { label: 'CV Havuzu', path: '/recruitment/cv-pool', icon: <BsPersonLinesFill /> },
          ],
        },
        { 
          label: 'Evrak Yönetimi', 
          path: '/documents', 
          icon: <BsFileEarmarkText />,
          module: 'document-management',
        },
        { 
          label: 'Onboarding', 
          path: '/onboarding', 
          icon: <BsPersonCheck />,
          module: 'onboarding',
        },
        { 
          label: 'İzin Yönetimi', 
          path: '/leaves', 
          icon: <BsCalendarCheck />,
          module: 'leave-management',
        },
      ],
    },
    {
      title: 'Diğer',
      items: [
        { label: 'Raporlar', path: '/reports', icon: <BsGraphUp /> },
        { label: 'Ayarlar', path: '/settings', icon: <BsGear /> },
      ],
    },
  ];

  // Modül erişim kontrolü
  const filterMenuByModules = (menu: MenuGroup[]): MenuGroup[] => {
    return menu.map(group => ({
      ...group,
      items: group.items.filter(item => {
        if (!item.module) return true;
        return activeModules.includes(item.module);
      }),
    })).filter(group => group.items.length > 0);
  };

  const menu = isSuperAdmin ? superAdminMenu : filterMenuByModules(companyMenu);

  const isPathActive = (path: string) => {
    return location.pathname === path || location.pathname.startsWith(path + '/');
  };

  const renderMenuItem = (item: MenuItem, depth = 0) => {
    const hasChildren = item.children && item.children.length > 0;
    const isExpanded = expandedMenus.includes(item.path);
    const isActive = isPathActive(item.path);

    if (hasChildren) {
      return (
        <div key={item.path} className="sidebar-item-group">
          <button
            className={`sidebar-item sidebar-item-expandable ${isActive ? 'active' : ''}`}
            onClick={() => toggleMenu(item.path)}
          >
            <span className="sidebar-item-icon">{item.icon}</span>
            <span className="sidebar-item-text">{item.label}</span>
            <span className="sidebar-item-expand">
              {isExpanded ? <BsChevronDown /> : <BsChevronRight />}
            </span>
          </button>
          {isExpanded && (
            <div className="sidebar-submenu">
              {item.children!.map((child) => (
                <NavLink
                  key={child.path}
                  to={child.path}
                  className={({ isActive }) => 
                    `sidebar-item sidebar-item-child ${isActive ? 'active' : ''}`
                  }
                >
                  <span className="sidebar-item-icon">{child.icon}</span>
                  <span className="sidebar-item-text">{child.label}</span>
                </NavLink>
              ))}
            </div>
          )}
        </div>
      );
    }

    return (
      <NavLink
        key={item.path}
        to={item.path}
        className={({ isActive }) => 
          `sidebar-item ${isActive ? 'active' : ''}`
        }
        end={item.path === '/dashboard' || item.path === '/admin'}
      >
        <span className="sidebar-item-icon">{item.icon}</span>
        <span className="sidebar-item-text">{item.label}</span>
        {item.badge && item.badge > 0 && (
          <span className="sidebar-item-badge">{item.badge}</span>
        )}
      </NavLink>
    );
  };

  return (
    <aside className={`sidebar ${collapsed ? 'collapsed' : ''}`}>
      {/* Header */}
      <div className="sidebar-header">
        <div className="sidebar-logo">A</div>
        <span className="sidebar-brand">Alatax HR</span>
      </div>

      {/* Navigation */}
      <nav className="sidebar-content hide-scrollbar">
        {menu.map((group, groupIndex) => (
          <div key={groupIndex} className="sidebar-nav-group">
            <div className="sidebar-nav-title">{group.title}</div>
            <div className="sidebar-nav">
              {group.items.map((item) => renderMenuItem(item))}
            </div>
          </div>
        ))}
      </nav>

      {/* Footer */}
      <div className="sidebar-footer">
        <div className="sidebar-item" style={{ opacity: 0.6, cursor: 'default' }}>
          <span className="sidebar-item-icon">
            <BsBuilding />
          </span>
          <span className="sidebar-item-text" style={{ fontSize: '0.75rem' }}>
            {user?.company?.name || 'Alatax Bilişim'}
          </span>
        </div>
      </div>

      <style>{`
        .sidebar-item-group {
          width: 100%;
        }
        .sidebar-item-expandable {
          width: 100%;
          background: none;
          border: none;
          text-align: left;
          display: flex;
          align-items: center;
          padding: 0.625rem 1rem;
          color: var(--text-secondary);
          transition: all 0.2s;
          cursor: pointer;
        }
        .sidebar-item-expandable:hover {
          background: var(--surface-secondary);
          color: var(--text-primary);
        }
        .sidebar-item-expandable.active {
          color: var(--primary);
        }
        .sidebar-item-expand {
          margin-left: auto;
          font-size: 0.75rem;
          opacity: 0.7;
        }
        .sidebar-submenu {
          padding-left: 0.5rem;
        }
        .sidebar-item-child {
          padding-left: 1.5rem !important;
          font-size: 0.875rem;
        }
        .sidebar-item-child .sidebar-item-icon {
          font-size: 0.875rem;
        }
      `}</style>
    </aside>
  );
};

export default Sidebar;
