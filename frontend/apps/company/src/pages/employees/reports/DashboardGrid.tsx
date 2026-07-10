import React, { useCallback } from 'react';
import GridLayout, { type Layout, type LayoutItem } from 'react-grid-layout/legacy';
import 'react-grid-layout/css/styles.css';
import type { DashboardWidget } from './types';
import WidgetFactory from './WidgetFactory';

interface DashboardGridProps {
  widgets: DashboardWidget[];
  onLayoutChange: (widgets: DashboardWidget[]) => void;
  onWidgetEdit: (widgetId: string) => void;
  onWidgetDelete: (widgetId: string) => void;
  isEditing?: boolean;
  width?: number;
  cols?: number;
  rowHeight?: number;
}

const DashboardGrid: React.FC<DashboardGridProps> = ({
  widgets,
  onLayoutChange,
  onWidgetEdit,
  onWidgetDelete,
  isEditing = false,
  width = 1200,
  cols = 12,
  rowHeight = 80,
}) => {
  // Widget'lardan layout oluştur (Layout = LayoutItem[], LayoutItem = tek hücre)
  const layout: Layout = widgets.map((widget): LayoutItem => ({
    i: widget.id,
    x: widget.layout.x,
    y: widget.layout.y,
    w: widget.layout.w,
    h: widget.layout.h,
    minW: widget.layout.minW || 1, // Minimum 1 kolon genişlik
    minH: widget.layout.minH || 1, // Minimum 1 satır yükseklik
    maxW: widget.layout.maxW || 12,
    maxH: widget.layout.maxH, // Maksimum yükseklik sınırı kaldırıldı
    static: false, // static'i false bırak, isDraggable ve isResizable ile kontrol et
    isResizable: isEditing,
  }));

  // Layout değiştiğinde widget'ları güncelle
  const handleLayoutChange = useCallback(
    (newLayout: Layout) => {
      const updatedWidgets = widgets.map((widget) => {
        const layoutItem = newLayout.find((l) => l.i === widget.id);
        if (layoutItem) {
          return {
            ...widget,
            layout: {
              ...widget.layout,
              x: layoutItem.x,
              y: layoutItem.y,
              w: layoutItem.w,
              h: layoutItem.h,
            },
          };
        }
        return widget;
      });
      onLayoutChange(updatedWidgets);
    },
    [widgets, onLayoutChange]
  );

  if (widgets.length === 0) {
    return (
      <div className="dashboard-empty-state">
        <div className="empty-icon">📊</div>
        <h3>Rapor Boş</h3>
        <p>Widget eklemek için "Widget Ekle" butonuna tıklayın</p>
      </div>
    );
  }

  return (
    <div className="dashboard-grid-container">
      <GridLayout
        className="dashboard-grid"
        layout={layout}
        cols={cols}
        rowHeight={rowHeight}
        width={width}
        onLayoutChange={handleLayoutChange}
        isDraggable={isEditing}
        isResizable={isEditing}
        draggableHandle=".widget-drag-handle"
        resizeHandles={['se', 's', 'e', 'sw', 'nw', 'n', 'w', 'ne']}
        margin={[16, 16]}
        containerPadding={[0, 0]}
        useCSSTransforms={true}
        preventCollision={false}
        compactType={null}
        autoSize={true}
      >
        {widgets.map((widget) => (
          <div key={widget.id} className="dashboard-widget-wrapper">
            <WidgetFactory
              widget={widget}
              isEditing={isEditing}
              onEdit={() => onWidgetEdit(widget.id)}
              onDelete={() => onWidgetDelete(widget.id)}
            />
          </div>
        ))}
      </GridLayout>
    </div>
  );
};

export default DashboardGrid;

