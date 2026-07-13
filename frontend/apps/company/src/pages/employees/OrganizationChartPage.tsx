import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  BsArrowLeft,
  BsZoomIn,
  BsZoomOut,
  BsArrowsFullscreen,
  BsPersonBadge,
  BsBuilding,
  BsChevronDown,
  BsChevronRight,
  BsDiagram3,
} from 'react-icons/bs';
import { employeesApi } from '@shared/services/api';
import { Select } from '@shared/components';
import { useTranslation } from '@shared/i18n';
import toast from 'react-hot-toast';

type OrgChartMode = 'people' | 'department' | 'hybrid';

interface EmployeeData {
  id: number;
  employee_code: string;
  position?: string | null;
  title?: string | null;
  user?: {
    id: number;
    name: string;
    email: string;
  } | null;
  department?: {
    id: number;
    name: string;
  } | null;
}

interface DepartmentData {
  id: number;
  name: string | null;
  code?: string | null;
  manager?: {
    id: number;
    name: string;
    email: string;
  } | null;
  is_unassigned?: boolean;
}

interface OrgChartNode {
  type: 'employee' | 'department';
  employee?: EmployeeData;
  department?: DepartmentData;
  children: OrgChartNode[];
  expanded?: boolean;
}

interface OrgChartResponse {
  mode: OrgChartMode;
  tree: OrgChartNode[];
}

const OrganizationChartPage: React.FC = () => {
  const navigate = useNavigate();
  const { t } = useTranslation('common');
  const [loading, setLoading] = useState(true);
  const [mode, setMode] = useState<OrgChartMode>('people');
  const [orgData, setOrgData] = useState<OrgChartNode[]>([]);
  const [zoom, setZoom] = useState(1);
  const [expandAll, setExpandAll] = useState(true);

  const modeOptions = useMemo(
    () => [
      { value: 'people', label: t('organizationChart.modePeople') },
      { value: 'department', label: t('organizationChart.modeDepartment') },
      { value: 'hybrid', label: t('organizationChart.modeHybrid') },
    ],
    [t]
  );

  const loadOrganizationData = useCallback(async () => {
    try {
      setLoading(true);
      const response = await employeesApi.getOrganizationChart({ mode });
      const payload = response.data.data as OrgChartResponse | OrgChartNode[] | undefined;

      if (Array.isArray(payload)) {
        setOrgData(payload);
      } else if (payload && Array.isArray(payload.tree)) {
        setOrgData(payload.tree);
      } else {
        setOrgData([]);
      }
    } catch {
      toast.error(t('organizationChart.loadError'));
      setOrgData([]);
    } finally {
      setLoading(false);
    }
  }, [mode, t]);

  useEffect(() => {
    loadOrganizationData();
  }, [loadOrganizationData]);

  const handleZoomIn = () => {
    setZoom((prev) => Math.min(prev + 0.1, 2));
  };

  const handleZoomOut = () => {
    setZoom((prev) => Math.max(prev - 0.1, 0.5));
  };

  const handleResetZoom = () => {
    setZoom(1);
  };

  const toggleExpandAll = () => {
    setExpandAll((prev) => !prev);
  };

  const emptyMessage = useMemo(() => {
    if (mode === 'department') return t('organizationChart.emptyDepartment');
    if (mode === 'hybrid') return t('organizationChart.emptyHybrid');
    return t('organizationChart.emptyPeople');
  }, [mode, t]);

  const departmentLabel = (department: DepartmentData): string => {
    if (department.is_unassigned) {
      return t('organizationChart.unassignedDepartment');
    }
    return department.name ?? department.code ?? `#${department.id}`;
  };

  const EmployeeCard: React.FC<{ employee: EmployeeData; isRoot?: boolean }> = ({
    employee,
    isRoot,
  }) => (
    <div
      onClick={() => navigate(`/employees/${employee.id}`)}
      style={{
        padding: 'var(--sp-2) var(--sp-3)',
        background: isRoot ? 'var(--primary-soft)' : 'var(--bg-primary)',
        border: `1px solid ${isRoot ? 'var(--primary)' : 'var(--border-primary)'}`,
        borderRadius: 'var(--radius-md)',
        minWidth: 180,
        cursor: 'pointer',
        transition: 'all var(--transition-fast)',
        boxShadow: 'var(--shadow-sm)',
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.boxShadow = 'var(--shadow-md)';
        e.currentTarget.style.transform = 'translateY(-1px)';
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.boxShadow = 'var(--shadow-sm)';
        e.currentTarget.style.transform = 'translateY(0)';
      }}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-2)' }}>
        <div
          style={{
            width: 32,
            height: 32,
            borderRadius: '50%',
            background: isRoot ? 'var(--primary)' : 'var(--bg-tertiary)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            color: isRoot ? 'var(--bg-primary)' : 'var(--text-secondary)',
            fontWeight: 600,
            fontSize: 'var(--fs-body)',
            flexShrink: 0,
          }}
        >
          {employee.user?.name?.charAt(0) || <BsPersonBadge />}
        </div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontWeight: 600, fontSize: 'var(--fs-body)' }}>
            {employee.user?.name || employee.employee_code}
          </div>
          <div style={{ fontSize: 'var(--fs-caption)', color: 'var(--text-tertiary)' }}>
            {employee.position || employee.title || t('organizationChart.positionUnset')}
          </div>
          {employee.department && mode !== 'department' && (
            <div
              style={{
                fontSize: 'var(--fs-caption)',
                color: 'var(--text-tertiary)',
                display: 'flex',
                alignItems: 'center',
                gap: 2,
                marginTop: 2,
              }}
            >
              <BsBuilding /> {employee.department.name}
            </div>
          )}
        </div>
      </div>
    </div>
  );

  const DepartmentCard: React.FC<{ department: DepartmentData; isRoot?: boolean }> = ({
    department,
    isRoot,
  }) => (
    <div
      style={{
        padding: 'var(--sp-2) var(--sp-3)',
        background: isRoot ? 'var(--primary-soft)' : 'var(--bg-secondary)',
        border: `1px solid ${isRoot ? 'var(--primary)' : 'var(--border-primary)'}`,
        borderRadius: 'var(--radius-md)',
        minWidth: 200,
        boxShadow: 'var(--shadow-sm)',
      }}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--sp-2)' }}>
        <div
          style={{
            width: 32,
            height: 32,
            borderRadius: 'var(--radius-sm)',
            background: department.is_unassigned ? 'var(--bg-tertiary)' : 'var(--primary)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            color: department.is_unassigned
              ? 'var(--text-secondary)'
              : 'var(--bg-primary)',
            flexShrink: 0,
          }}
        >
          {department.is_unassigned ? <BsPersonBadge /> : <BsDiagram3 />}
        </div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontWeight: 600, fontSize: 'var(--fs-body)' }}>
            {departmentLabel(department)}
          </div>
          {department.code && !department.is_unassigned && (
            <div style={{ fontSize: 'var(--fs-caption)', color: 'var(--text-tertiary)' }}>
              {department.code}
            </div>
          )}
          {department.manager?.name && (
            <div style={{ fontSize: 'var(--fs-caption)', color: 'var(--text-tertiary)', marginTop: 2 }}>
              {t('organizationChart.departmentManager', { name: department.manager.name })}
            </div>
          )}
        </div>
      </div>
    </div>
  );

  const OrgNodeComponent: React.FC<{
    node: OrgChartNode;
    level: number;
    expandAllFlag: boolean;
  }> = ({ node, level, expandAllFlag }) => {
    const [isExpanded, setIsExpanded] = useState(expandAllFlag);
    const hasChildren = node.children.length > 0;
    const nodeKey =
      node.type === 'employee'
        ? `e-${node.employee?.id ?? level}`
        : `d-${node.department?.id ?? 'u'}-${node.department?.code ?? ''}`;

    return (
      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
        <div style={{ position: 'relative' }}>
          {node.type === 'department' && node.department ? (
            <DepartmentCard department={node.department} isRoot={level === 0} />
          ) : node.employee ? (
            <EmployeeCard employee={node.employee} isRoot={level === 0} />
          ) : null}
          {hasChildren && (
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                setIsExpanded(!isExpanded);
              }}
              style={{
                position: 'absolute',
                bottom: -12,
                left: '50%',
                transform: 'translateX(-50%)',
                width: 24,
                height: 24,
                borderRadius: '50%',
                background: 'var(--bg-primary)',
                border: '2px solid var(--border-color)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                cursor: 'pointer',
                fontSize: '0.75rem',
                zIndex: 10,
              }}
              aria-expanded={isExpanded}
            >
              {isExpanded ? <BsChevronDown /> : <BsChevronRight />}
            </button>
          )}
        </div>

        {hasChildren && isExpanded && (
          <>
            <div style={{ width: 2, height: 24, background: 'var(--border-color)' }} />

            {node.children.length > 1 && (
              <div
                style={{
                  height: 2,
                  background: 'var(--border-color)',
                  width: `${(node.children.length - 1) * 220 + 40}px`,
                }}
              />
            )}

            <div
              style={{
                display: 'flex',
                gap: '1rem',
                paddingTop: node.children.length > 1 ? 0 : '0.5rem',
              }}
            >
              {node.children.map((child, index) => {
                const childKey =
                  child.type === 'employee'
                    ? `e-${child.employee?.id ?? index}-${expandAllFlag}`
                    : `d-${child.department?.id ?? index}-${child.department?.code ?? ''}-${expandAllFlag}`;
                return (
                  <div
                    key={`${nodeKey}-${childKey}`}
                    style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}
                  >
                    {node.children.length > 1 && (
                      <div style={{ width: 2, height: 16, background: 'var(--border-color)' }} />
                    )}
                    <OrgNodeComponent
                      node={child}
                      level={level + 1}
                      expandAllFlag={expandAllFlag}
                    />
                  </div>
                );
              })}
            </div>
          </>
        )}
      </div>
    );
  };

  if (loading) {
    return (
      <div className="animate-fade-in">
        <div className="page-header">
          <div className="page-header-content">
            <h1 className="page-title">{t('organizationChart.title')}</h1>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center py-5">
            <div className="spinner-border" role="status">
              <span className="visually-hidden">{t('organizationChart.loading')}</span>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <button
            type="button"
            className="btn btn-ghost btn-sm"
            onClick={() => navigate('/employees')}
            style={{ marginBottom: '0.5rem' }}
          >
            <BsArrowLeft /> {t('organizationChart.backToList')}
          </button>
          <h1 className="page-title">{t('organizationChart.title')}</h1>
          <p className="page-subtitle">{t('organizationChart.subtitle')}</p>
        </div>
        <div className="page-header-actions" style={{ display: 'flex', gap: 'var(--sp-2)', alignItems: 'center', flexWrap: 'wrap' }}>
          <div style={{ minWidth: 220 }}>
            <Select
              aria-label={t('organizationChart.modeLabel')}
              value={mode}
              onChange={(value) => {
                if (value === 'people' || value === 'department' || value === 'hybrid') {
                  setMode(value);
                }
              }}
              options={modeOptions}
            />
          </div>
          <div className="btn-group">
            <button
              type="button"
              className="btn btn-secondary"
              onClick={handleZoomOut}
              title={t('organizationChart.zoomOut')}
            >
              <BsZoomOut />
            </button>
            <button
              type="button"
              className="btn btn-secondary"
              onClick={handleResetZoom}
              style={{ minWidth: 60 }}
            >
              {Math.round(zoom * 100)}%
            </button>
            <button
              type="button"
              className="btn btn-secondary"
              onClick={handleZoomIn}
              title={t('organizationChart.zoomIn')}
            >
              <BsZoomIn />
            </button>
          </div>
          <button type="button" className="btn btn-secondary" onClick={toggleExpandAll}>
            <BsArrowsFullscreen />{' '}
            {expandAll ? t('organizationChart.collapse') : t('organizationChart.expand')}
          </button>
        </div>
      </div>

      <div
        className="card"
        style={{
          minHeight: 'calc(100vh - 250px)',
          overflow: 'auto',
        }}
      >
        <div
          className="card-body"
          style={{
            display: 'flex',
            justifyContent: 'center',
            padding: '2rem',
            transform: `scale(${zoom})`,
            transformOrigin: 'top center',
            transition: 'transform 0.2s ease',
          }}
        >
          {orgData.length === 0 ? (
            <div className="text-center py-5">
              <BsBuilding size={64} className="text-muted mb-3" />
              <h4 className="text-muted mb-2">{t('organizationChart.emptyTitle')}</h4>
              <p className="text-muted mb-3">{emptyMessage}</p>
              <button
                type="button"
                className="btn btn-primary"
                onClick={() => navigate('/employees')}
              >
                {t('organizationChart.goToEmployees')}
              </button>
            </div>
          ) : (
            <div
              style={{
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                gap: '0.5rem',
              }}
            >
              {orgData.map((node, index) => {
                const key =
                  node.type === 'employee'
                    ? `root-e-${node.employee?.id ?? index}-${expandAll}`
                    : `root-d-${node.department?.id ?? index}-${node.department?.code ?? ''}-${expandAll}`;
                return (
                  <OrgNodeComponent
                    key={key}
                    node={node}
                    level={0}
                    expandAllFlag={expandAll}
                  />
                );
              })}
            </div>
          )}
        </div>
      </div>

      <div className="card mt-3">
        <div className="card-body">
          <div
            style={{
              display: 'flex',
              gap: '2rem',
              fontSize: '0.875rem',
              color: 'var(--text-secondary)',
              flexWrap: 'wrap',
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <div
                style={{
                  width: 12,
                  height: 12,
                  borderRadius: '50%',
                  background: 'var(--primary)',
                }}
              />
              <span>{t('organizationChart.legendRoot')}</span>
            </div>
            {(mode === 'people' || mode === 'hybrid') && (
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <div
                  style={{
                    width: 12,
                    height: 12,
                    borderRadius: '50%',
                    background: 'var(--bg-tertiary)',
                    border: '2px solid var(--border-color)',
                  }}
                />
                <span>{t('organizationChart.legendEmployee')}</span>
              </div>
            )}
            {(mode === 'department' || mode === 'hybrid') && (
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <div
                  style={{
                    width: 12,
                    height: 12,
                    borderRadius: 'var(--radius-sm)',
                    background: 'var(--bg-secondary)',
                    border: '2px solid var(--border-color)',
                  }}
                />
                <span>{t('organizationChart.legendDepartment')}</span>
              </div>
            )}
            <div style={{ marginLeft: 'auto' }}>
              <span>{t('organizationChart.rootCount', { count: orgData.length })}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default OrganizationChartPage;
