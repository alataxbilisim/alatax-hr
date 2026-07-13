import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  FiArrowLeft,
  FiPlus,
  FiSave,
  FiFolder,
  FiEdit3,
  FiCheck,
  FiZoomIn,
  FiZoomOut,
  FiMaximize,
} from 'react-icons/fi';
import toast from 'react-hot-toast';
import { employeesApi } from '@alatax/shared';
import type { Dashboard, DashboardWidget, ReportMetadata } from './reports/types';
import DashboardGrid from './reports/DashboardGrid';
import AddWidgetModal from './reports/AddWidgetModal';
import WidgetSettingsPanel from './reports/WidgetSettingsPanel';
import SaveDashboardModal from './reports/SaveDashboardModal';
import DashboardList from './reports/DashboardList';
import ExportMenu from './reports/ExportMenu';
import './reports/dashboard.css';

// Unique ID generator
const generateId = () => `widget_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

const EmployeeReportsPage: React.FC = () => {
  const navigate = useNavigate();
  const dashboardRef = useRef<HTMLDivElement>(null);

  // Rapor state
  const [dashboard, setDashboard] = useState<Dashboard>({
    name: 'Yeni Rapor',
    widgets: [],
  });
  const [savedDashboards, setSavedDashboards] = useState<Dashboard[]>([]);
  const [metadata, setMetadata] = useState<ReportMetadata | null>(null);

  // UI state
  const [isEditing, setIsEditing] = useState(true);
  const [loading, setLoading] = useState(true);
  const [isDirty, setIsDirty] = useState(false);

  // Modal state
  const [showAddWidget, setShowAddWidget] = useState(false);
  const [showSaveModal, setShowSaveModal] = useState(false);
  const [showDashboardList, setShowDashboardList] = useState(false);
  const [editingWidget, setEditingWidget] = useState<DashboardWidget | null>(null);

  // Grid width
  const [gridWidth, setGridWidth] = useState(1200);

  // Zoom state
  const [zoomLevel, setZoomLevel] = useState(100);
  const ZOOM_LEVELS = [50, 75, 100, 125, 150, 175, 200];
  const MIN_ZOOM = 50;
  const MAX_ZOOM = 200;

  // Zoom functions
  const handleZoomIn = () => {
    const currentIndex = ZOOM_LEVELS.indexOf(zoomLevel);
    if (currentIndex < ZOOM_LEVELS.length - 1) {
      setZoomLevel(ZOOM_LEVELS[currentIndex + 1]);
    } else if (zoomLevel < MAX_ZOOM) {
      setZoomLevel(Math.min(zoomLevel + 25, MAX_ZOOM));
    }
  };

  const handleZoomOut = () => {
    const currentIndex = ZOOM_LEVELS.indexOf(zoomLevel);
    if (currentIndex > 0) {
      setZoomLevel(ZOOM_LEVELS[currentIndex - 1]);
    } else if (zoomLevel > MIN_ZOOM) {
      setZoomLevel(Math.max(zoomLevel - 25, MIN_ZOOM));
    }
  };

  const handleZoomReset = () => {
    setZoomLevel(100);
  };

  // Load metadata and saved dashboards
  useEffect(() => {
    const fetchData = async () => {
      try {
        const [metadataRes, dashboardsRes] = await Promise.all([
          employeesApi.reports.getMetadata(),
          employeesApi.dashboards.getAll(),
        ]);
        setMetadata(metadataRes.data.data);
        setSavedDashboards(dashboardsRes.data.data || []);
      } catch (error) {
        console.error('Error loading data:', error);
        toast.error('Veriler yüklenemedi');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []);

  // Handle window resize
  useEffect(() => {
    const updateWidth = () => {
      if (dashboardRef.current) {
        setGridWidth(dashboardRef.current.offsetWidth - 32);
      }
    };

    updateWidth();
    window.addEventListener('resize', updateWidth);
    return () => window.removeEventListener('resize', updateWidth);
  }, []);

  // Add widget
  const handleAddWidget = useCallback((widgetData: Omit<DashboardWidget, 'id'>) => {
    const newWidget: DashboardWidget = {
      ...widgetData,
      id: generateId(),
    };
    setDashboard((prev) => ({
      ...prev,
      widgets: [...prev.widgets, newWidget],
    }));
    setIsDirty(true);
  }, []);

  // Update widget
  const handleUpdateWidget = useCallback((updatedWidget: DashboardWidget) => {
    setDashboard((prev) => ({
      ...prev,
      widgets: prev.widgets.map((w) =>
        w.id === updatedWidget.id ? updatedWidget : w
      ),
    }));
    setIsDirty(true);
    setEditingWidget(null);
  }, []);

  // Delete widget
  const handleDeleteWidget = useCallback((widgetId: string) => {
    setDashboard((prev) => ({
      ...prev,
      widgets: prev.widgets.filter((w) => w.id !== widgetId),
    }));
    setIsDirty(true);
  }, []);

  // Layout change
  const handleLayoutChange = useCallback((widgets: DashboardWidget[]) => {
    setDashboard((prev) => ({ ...prev, widgets }));
    setIsDirty(true);
  }, []);

  // Save dashboard
  const handleSaveDashboard = async (data: { name: string; description?: string; is_shared: boolean }) => {
    try {
      const payload = {
        name: data.name,
        description: data.description,
        widgets: dashboard.widgets,
        is_shared: data.is_shared,
      };

      if (dashboard.id) {
        await employeesApi.dashboards.update(dashboard.id, payload);
        toast.success('Rapor güncellendi');
      } else {
        const response = await employeesApi.dashboards.create(payload);
        setDashboard((prev) => ({ ...prev, ...response.data.data }));
        toast.success('Rapor kaydedildi');
      }

      // Refresh list
      const dashboardsRes = await employeesApi.dashboards.getAll();
      setSavedDashboards(dashboardsRes.data.data || []);
      setIsDirty(false);
      setShowSaveModal(false);
    } catch (error) {
      console.error('Save error:', error);
      toast.error('Kayıt başarısız');
    }
  };

  // Load dashboard
  const handleLoadDashboard = useCallback((loadedDashboard: Dashboard) => {
    setDashboard(loadedDashboard);
    setIsDirty(false);
    setIsEditing(false);
  }, []);

  // Delete dashboard
  const handleDeleteDashboard = async (id: number) => {
    if (!confirm('Rapor silinecek. Emin misiniz?')) return;

    try {
      await employeesApi.dashboards.delete(id);
      setSavedDashboards((prev) => prev.filter((d) => d.id !== id));
      if (dashboard.id === id) {
        setDashboard({ name: 'Yeni Rapor', widgets: [] });
      }
      toast.success('Rapor silindi');
    } catch {
      toast.error('Silme başarısız');
    }
  };

  // Toggle favorite
  const handleToggleFavorite = async (id: number) => {
    try {
      await employeesApi.dashboards.toggleFavorite(id);
      setSavedDashboards((prev) =>
        prev.map((d) =>
          d.id === id ? { ...d, is_favorite: !d.is_favorite } : d
        )
      );
    } catch {
      toast.error('İşlem başarısız');
    }
  };

  // New dashboard
  const handleNewDashboard = () => {
    if (isDirty && !confirm('Kaydedilmemiş değişiklikler kaybolacak. Devam etmek istiyor musunuz?')) {
      return;
    }
    setDashboard({ name: 'Yeni Rapor', widgets: [] });
    setIsDirty(false);
    setIsEditing(true);
  };

  if (loading) {
    return (
      <div className="page-fill dashboard-page loading">
        <div className="loading-spinner"></div>
        <span>Yükleniyor...</span>
      </div>
    );
  }

  return (
    <div className="page-fill dashboard-page">
      {/* Header */}
      <div className="dashboard-header">
        <div className="header-left">
          <button className="btn btn-ghost" onClick={() => navigate('/employees')}>
            <FiArrowLeft />
          </button>
          <div className="dashboard-title-section">
            <h1>{dashboard.name}</h1>
            {isDirty && <span className="unsaved-badge">Kaydedilmedi</span>}
          </div>
        </div>

        <div className="header-actions">
          {isEditing ? (
            <>
              <button className="btn btn-secondary" onClick={() => setShowAddWidget(true)}>
                <FiPlus /> Widget Ekle
              </button>
              <button
                className="btn btn-secondary"
                onClick={() => setIsEditing(false)}
              >
                <FiCheck /> Düzenlemeyi Bitir
              </button>
            </>
          ) : (
            <button className="btn btn-secondary" onClick={() => setIsEditing(true)}>
              <FiEdit3 /> Düzenle
            </button>
          )}

          <button className="btn btn-secondary" onClick={() => setShowDashboardList(true)}>
            <FiFolder /> Raporlar
          </button>

          <button className="btn btn-secondary" onClick={handleNewDashboard}>
            <FiPlus /> Yeni
          </button>

          <button
            className="btn btn-primary"
            onClick={() => setShowSaveModal(true)}
            disabled={dashboard.widgets.length === 0}
          >
            <FiSave /> Kaydet
          </button>

          <ExportMenu
            dashboardId={dashboard.id}
            dashboardName={dashboard.name}
            containerRef={dashboardRef as React.RefObject<HTMLElement>}
          />

          {/* Zoom Controls */}
          <div className="zoom-controls">
            <button
              className="btn btn-ghost zoom-btn"
              onClick={handleZoomOut}
              disabled={zoomLevel <= MIN_ZOOM}
              title="Uzaklaştır"
            >
              <FiZoomOut />
            </button>
            <button
              className="zoom-level-btn"
              onClick={handleZoomReset}
              title="Varsayılana Sıfırla"
            >
              {zoomLevel}%
            </button>
            <button
              className="btn btn-ghost zoom-btn"
              onClick={handleZoomIn}
              disabled={zoomLevel >= MAX_ZOOM}
              title="Yakınlaştır"
            >
              <FiZoomIn />
            </button>
            <button
              className="btn btn-ghost zoom-btn"
              onClick={handleZoomReset}
              title="Sığdır"
            >
              <FiMaximize />
            </button>
          </div>
        </div>
      </div>

      {/* Dashboard Grid */}
      <div className="dashboard-content" ref={dashboardRef}>
        <div 
          className="dashboard-zoom-container"
          style={{ 
            transform: `scale(${zoomLevel / 100})`,
            transformOrigin: 'top left',
            width: `${100 / (zoomLevel / 100)}%`,
          }}
        >
          <DashboardGrid
            widgets={dashboard.widgets}
            onLayoutChange={handleLayoutChange}
            onWidgetEdit={(id) => {
              const widget = dashboard.widgets.find((w) => w.id === id);
              if (widget) setEditingWidget(widget);
            }}
            onWidgetDelete={handleDeleteWidget}
            isEditing={isEditing}
            width={gridWidth * (100 / zoomLevel)}
            cols={12}
            rowHeight={42}
          />
        </div>
      </div>

      {/* Modals */}
      <AddWidgetModal
        isOpen={showAddWidget}
        onClose={() => setShowAddWidget(false)}
        onAdd={handleAddWidget}
        metadata={metadata}
      />

      <WidgetSettingsPanel
        key={editingWidget?.id ?? 'closed'}
        widget={editingWidget}
        isOpen={!!editingWidget}
        onClose={() => setEditingWidget(null)}
        onSave={handleUpdateWidget}
        metadata={metadata}
      />

      <SaveDashboardModal
        key={dashboard.id ?? (showSaveModal ? 'new' : 'closed')}
        isOpen={showSaveModal}
        onClose={() => setShowSaveModal(false)}
        onSave={handleSaveDashboard}
        dashboard={dashboard}
        isUpdate={!!dashboard.id}
      />

      <DashboardList
        isOpen={showDashboardList}
        onClose={() => setShowDashboardList(false)}
        dashboards={savedDashboards}
        onSelect={handleLoadDashboard}
        onDelete={handleDeleteDashboard}
        onToggleFavorite={handleToggleFavorite}
        currentDashboardId={dashboard.id}
      />
    </div>
  );
};

export default EmployeeReportsPage;
