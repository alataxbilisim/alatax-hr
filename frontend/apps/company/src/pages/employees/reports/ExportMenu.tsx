import React, { useState, useRef, useEffect } from 'react';
import { FiDownload, FiFileText, FiFile, FiChevronDown } from 'react-icons/fi';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';
import { employeesApi } from '@alatax/shared';
import toast from 'react-hot-toast';

interface ExportMenuProps {
  dashboardId?: number;
  dashboardName: string;
  containerRef: React.RefObject<HTMLElement>;
}

const ExportMenu: React.FC<ExportMenuProps> = ({
  dashboardId,
  dashboardName,
  containerRef,
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [exporting, setExporting] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleExportPDF = async () => {
    if (!containerRef.current) {
      toast.error('Dashboard bulunamadı');
      return;
    }

    setExporting(true);
    setIsOpen(false);

    try {
      // Dashboard'u canvas'a çevir
      const canvas = await html2canvas(containerRef.current, {
        scale: 2,
        useCORS: true,
        logging: false,
        backgroundColor: '#0f1117',
      });

      // PDF oluştur
      const imgData = canvas.toDataURL('image/png');
      const pdf = new jsPDF({
        orientation: canvas.width > canvas.height ? 'landscape' : 'portrait',
        unit: 'px',
        format: [canvas.width, canvas.height],
      });

      pdf.addImage(imgData, 'PNG', 0, 0, canvas.width, canvas.height);
      pdf.save(`${dashboardName || 'dashboard'}_${new Date().toISOString().split('T')[0]}.pdf`);

      toast.success('PDF başarıyla indirildi');
    } catch (error) {
      console.error('PDF export error:', error);
      toast.error('PDF oluşturulamadı');
    } finally {
      setExporting(false);
    }
  };

  const handleExportExcel = async () => {
    if (!dashboardId) {
      toast.error('Dashboard kaydedilmeden Excel export yapılamaz');
      return;
    }

    setExporting(true);
    setIsOpen(false);

    try {
      const response = await employeesApi.dashboards.exportExcel(dashboardId);
      
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `${dashboardName || 'dashboard'}_${new Date().toISOString().split('T')[0]}.xlsx`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);

      toast.success('Excel başarıyla indirildi');
    } catch (error) {
      console.error('Excel export error:', error);
      toast.error('Excel oluşturulamadı');
    } finally {
      setExporting(false);
    }
  };

  return (
    <div className="export-menu" ref={menuRef}>
      <button
        className="btn btn-secondary export-trigger"
        onClick={() => setIsOpen(!isOpen)}
        disabled={exporting}
      >
        <FiDownload />
        <span>{exporting ? 'Dışa Aktarılıyor...' : 'Dışa Aktar'}</span>
        <FiChevronDown className={`chevron ${isOpen ? 'open' : ''}`} />
      </button>

      {isOpen && (
        <div className="export-dropdown">
          <button className="export-option" onClick={handleExportPDF}>
            <FiFile />
            <div className="export-option-info">
              <span className="export-option-title">PDF olarak indir</span>
              <span className="export-option-desc">Dashboard görüntüsünü PDF olarak kaydet</span>
            </div>
          </button>
          <button 
            className="export-option" 
            onClick={handleExportExcel}
            disabled={!dashboardId}
          >
            <FiFileText />
            <div className="export-option-info">
              <span className="export-option-title">Excel olarak indir</span>
              <span className="export-option-desc">Widget verilerini Excel olarak kaydet</span>
            </div>
          </button>
        </div>
      )}
    </div>
  );
};

export default ExportMenu;

