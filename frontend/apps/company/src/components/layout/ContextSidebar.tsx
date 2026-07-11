import React from 'react';
import { NavLink } from 'react-router-dom';
import { useSelector } from 'react-redux';
import { BsChevronLeft, BsChevronRight } from 'react-icons/bs';
import { type ModuleGroup, getFilteredMenuItems } from './moduleNav';
import { RootState } from '../../store';

interface ContextSidebarProps {
  module: ModuleGroup | null;
  /** true = 216px geniş; false = 48px daraltılmış */
  expanded: boolean;
  onToggle: () => void;
}

const ContextSidebar: React.FC<ContextSidebarProps> = ({
  module,
  expanded,
  onToggle,
}) => {
  const { user } = useSelector((state: RootState) => state.auth);

  if (!module) return null;

  const filteredItems = getFilteredMenuItems(module, user as { type: string; permissions: string[] } | null);

  if (filteredItems.length === 0) return null;

  const Icon = module.icon;
  const collapsed = !expanded;

  return (
    <aside
      className={`context-sidebar ${expanded ? 'expanded' : 'collapsed'}`}
      aria-expanded={expanded}
    >
      <div
        className="context-sidebar-header"
        style={{ '--module-color': module.color } as React.CSSProperties}
      >
        <div className="context-header-content" title={module.label}>
          <span className="context-header-icon" aria-hidden>
            <Icon />
          </span>
          {!collapsed && <span className="context-header-title">{module.label}</span>}
        </div>
        <button
          type="button"
          className="context-close-btn"
          onClick={onToggle}
          title={expanded ? 'Menüyü Daralt' : 'Menüyü Genişlet'}
          aria-label={expanded ? 'Menüyü Daralt' : 'Menüyü Genişlet'}
        >
          {expanded ? <BsChevronLeft /> : <BsChevronRight />}
        </button>
      </div>

      <nav className="context-nav">
        {filteredItems.map((item) => {
          const initial = item.label.trim().charAt(0).toLocaleUpperCase('tr-TR');
          return (
            <NavLink
              key={item.path}
              to={item.path}
              title={item.label}
              className={({ isActive }) =>
                `context-nav-item ${isActive ? 'active' : ''}`
              }
              style={{ '--module-color': module.color } as React.CSSProperties}
            >
              {collapsed ? (
                <span className="context-nav-icon" aria-hidden>
                  {initial}
                </span>
              ) : (
                <>
                  <span className="context-nav-text">{item.label}</span>
                  {item.badge != null && item.badge > 0 && (
                    <span className="context-nav-badge">{item.badge}</span>
                  )}
                </>
              )}
            </NavLink>
          );
        })}
      </nav>

      {!collapsed && (
        <div className="context-sidebar-footer">
          <div className="context-module-info">
            <span className="module-color-dot" style={{ background: module.color }} />
            <span>{filteredItems.length} alt menü</span>
          </div>
        </div>
      )}
    </aside>
  );
};

export default ContextSidebar;
