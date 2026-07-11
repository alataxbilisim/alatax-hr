import React, { useState, useEffect, useCallback } from 'react';
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
} from 'react-icons/bs';
import { employeesApi } from '@shared/services/api';
import toast from 'react-hot-toast';

interface Employee {
  id: number;
  employee_code: string;
  position?: string;
  title?: string;
  user?: {
    id: number;
    name: string;
    email: string;
  };
  department?: {
    id: number;
    name: string;
  };
}

interface OrgNode {
  employee: Employee;
  children: OrgNode[];
  expanded: boolean;
}

const OrganizationChartPage: React.FC = () => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [orgData, setOrgData] = useState<OrgNode[]>([]);
  const [zoom, setZoom] = useState(1);
  const [expandAll, setExpandAll] = useState(true);

  const loadOrganizationData = useCallback(async () => {
    try {
      setLoading(true);
      const response = await employeesApi.getOrganizationChart();
      setOrgData(response.data.data || []);
    } catch {
      toast.error('Organizasyon şeması yüklenemedi');
      // Demo veri
      setOrgData([]);
    } finally {
      setLoading(false);
    }
  }, []);

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
    setExpandAll(!expandAll);
  };

  const EmployeeCard: React.FC<{ employee: Employee; isRoot?: boolean }> = ({ employee, isRoot }) => (
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
            color: isRoot ? 'white' : 'var(--text-secondary)',
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
            {employee.position || employee.title || 'Pozisyon belirtilmemiş'}
          </div>
          {employee.department && (
            <div style={{ fontSize: 'var(--fs-caption)', color: 'var(--text-tertiary)', display: 'flex', alignItems: 'center', gap: 2, marginTop: 2 }}>
              <BsBuilding /> {employee.department.name}
            </div>
          )}
        </div>
      </div>
    </div>
  );

  const OrgNodeComponent: React.FC<{ node: OrgNode; level: number; expandAll: boolean }> = ({
    node,
    level,
    expandAll,
  }) => {
    const [isExpanded, setIsExpanded] = useState(expandAll);
    const hasChildren = node.children && node.children.length > 0;

    return (
      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
        <div style={{ position: 'relative' }}>
          <EmployeeCard employee={node.employee} isRoot={level === 0} />
          {hasChildren && (
            <button
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
            >
              {isExpanded ? <BsChevronDown /> : <BsChevronRight />}
            </button>
          )}
        </div>

        {hasChildren && isExpanded && (
          <>
            {/* Dikey çizgi */}
            <div style={{ width: 2, height: 24, background: 'var(--border-color)' }} />
            
            {/* Yatay çizgi (birden fazla çocuk varsa) */}
            {node.children.length > 1 && (
              <div
                style={{
                  height: 2,
                  background: 'var(--border-color)',
                  width: `${(node.children.length - 1) * 220 + 40}px`,
                }}
              />
            )}

            {/* Alt elemanlar — expandAll değişince key ile remount */}
            <div style={{ display: 'flex', gap: '1rem', paddingTop: node.children.length > 1 ? 0 : '0.5rem' }}>
              {node.children.map((child) => (
                <div key={`${child.employee.id}-${expandAll}`} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                  {node.children.length > 1 && (
                    <div style={{ width: 2, height: 16, background: 'var(--border-color)' }} />
                  )}
                  <OrgNodeComponent node={child} level={level + 1} expandAll={expandAll} />
                </div>
              ))}
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
            <h1 className="page-title">Organizasyon Şeması</h1>
          </div>
        </div>
        <div className="card">
          <div className="card-body text-center py-5">
            <div className="spinner-border" role="status">
              <span className="visually-hidden">Yükleniyor...</span>
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
            className="btn btn-ghost btn-sm"
            onClick={() => navigate('/employees')}
            style={{ marginBottom: '0.5rem' }}
          >
            <BsArrowLeft /> Personel Listesi
          </button>
          <h1 className="page-title">Organizasyon Şeması</h1>
          <p className="page-subtitle">Şirket hiyerarşisini görsel olarak inceleyin</p>
        </div>
        <div className="page-header-actions">
          <div className="btn-group">
            <button className="btn btn-secondary" onClick={handleZoomOut} title="Uzaklaştır">
              <BsZoomOut />
            </button>
            <button
              className="btn btn-secondary"
              onClick={handleResetZoom}
              style={{ minWidth: 60 }}
            >
              {Math.round(zoom * 100)}%
            </button>
            <button className="btn btn-secondary" onClick={handleZoomIn} title="Yakınlaştır">
              <BsZoomIn />
            </button>
          </div>
          <button className="btn btn-secondary" onClick={toggleExpandAll}>
            <BsArrowsFullscreen /> {expandAll ? 'Daralt' : 'Genişlet'}
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
              <h4 className="text-muted mb-2">Organizasyon Şeması Boş</h4>
              <p className="text-muted mb-3">
                Organizasyon şeması oluşturmak için personellere yönetici ataması yapın.
              </p>
              <button className="btn btn-primary" onClick={() => navigate('/employees')}>
                Personel Listesine Git
              </button>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '0.5rem' }}>
              {orgData.map((node) => (
                <OrgNodeComponent key={`${node.employee.id}-${expandAll}`} node={node} level={0} expandAll={expandAll} />
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Bilgi Kartı */}
      <div className="card mt-3">
        <div className="card-body">
          <div style={{ display: 'flex', gap: '2rem', fontSize: '0.875rem', color: 'var(--text-secondary)' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <div
                style={{
                  width: 12,
                  height: 12,
                  borderRadius: '50%',
                  background: 'var(--primary)',
                }}
              />
              <span>Üst Yönetici</span>
            </div>
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
              <span>Çalışan</span>
            </div>
            <div style={{ marginLeft: 'auto', display: 'flex', gap: '0.5rem' }}>
              <span>Toplam {orgData.length} kök düğüm</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default OrganizationChartPage;

