import React from 'react';
import { NavLink } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { BsChevronLeft } from 'react-icons/bs';
import { type ModuleGroup, getFilteredMenuItems } from './moduleNav';
import { RootState } from '../../store';

interface ContextSidebarProps {
  module: ModuleGroup | null;
  isOpen: boolean;
  onClose: () => void;
}

const ContextSidebar: React.FC<ContextSidebarProps> = ({
  module,
  isOpen,
  onClose,
}) => {
  const { user } = useSelector((state: RootState) => state.auth);

  if (!module) return null;

  // Yetki bazlı filtrelenmiş menü öğeleri
  const filteredItems = getFilteredMenuItems(module, user as { type: string; permissions: string[] } | null);

  // Hiç görüntülenebilir öğe yoksa sidebar'ı gösterme
  if (filteredItems.length === 0) return null;

  const Icon = module.icon;

  return (
    <aside className={`context-sidebar ${isOpen ? 'open' : ''}`}>
      {/* Header with module info */}
      <div 
        className="context-sidebar-header"
        style={{ '--module-color': module.color } as React.CSSProperties}
      >
        <div className="context-header-content">
          <span className="context-header-icon">
            <Icon />
          </span>
          <span className="context-header-title">{module.label}</span>
        </div>
        <button 
          className="context-close-btn"
          onClick={onClose}
          title="Menüyü Kapat"
        >
          <BsChevronLeft />
        </button>
      </div>

      {/* Navigation Items - Only show items user has permission for */}
      <nav className="context-nav">
        {filteredItems.map((item) => (
          <NavLink
            key={item.path}
            to={item.path}
            className={({ isActive }) =>
              `context-nav-item ${isActive ? 'active' : ''}`
            }
            style={{ '--module-color': module.color } as React.CSSProperties}
          >
            <span className="context-nav-text">{item.label}</span>
            {item.badge && item.badge > 0 && (
              <span className="context-nav-badge">{item.badge}</span>
            )}
          </NavLink>
        ))}
      </nav>

      {/* Divider and Quick Actions (future) */}
      <div className="context-sidebar-footer">
        <div className="context-module-info">
          <span className="module-color-dot" style={{ background: module.color }} />
          <span>{filteredItems.length} alt menü</span>
        </div>
      </div>
    </aside>
  );
};

export default ContextSidebar;
