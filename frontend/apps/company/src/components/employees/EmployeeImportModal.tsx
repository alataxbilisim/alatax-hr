import React, { useState, useRef } from 'react';
import { Modal } from '../ui';
import { employeesApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import {
  BsUpload,
  BsFileEarmarkSpreadsheet,
  BsDownload,
  BsCheckCircle,
  BsXCircle,
  BsExclamationTriangle,
} from 'react-icons/bs';

interface ImportResult {
  total: number;
  success_count: number;
  failed_count: number;
  success_rows: Array<{ row: number; employee_code: string; action: 'created' | 'updated' }>;
  failed_rows: Array<{ row: number; data: Record<string, unknown>; error: string }>;
}

interface EmployeeImportModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const EmployeeImportModal: React.FC<EmployeeImportModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
}) => {
  const [step, setStep] = useState<'upload' | 'result'>('upload');
  const [file, setFile] = useState<File | null>(null);
  const [loading, setLoading] = useState(false);
  const [downloadingTemplate, setDownloadingTemplate] = useState(false);
  const [result, setResult] = useState<ImportResult | null>(null);
  const [dragOver, setDragOver] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleClose = () => {
    setStep('upload');
    setFile(null);
    setResult(null);
    onClose();
  };

  const handleFileSelect = (selectedFile: File) => {
    const extension = selectedFile.name.split('.').pop()?.toLowerCase();
    const validExtensions = ['csv', 'xls', 'xlsx'];

    if (!validExtensions.includes(extension || '')) {
      toast.error('Geçersiz dosya formatı. CSV, XLS veya XLSX dosyası seçin.');
      return;
    }

    setFile(selectedFile);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(false);
    
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFileSelect(e.dataTransfer.files[0]);
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(true);
  };

  const handleDragLeave = () => {
    setDragOver(false);
  };

  const handleDownloadTemplate = async () => {
    try {
      setDownloadingTemplate(true);
      const response = await employeesApi.importTemplate();
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', 'personel_import_sablonu.csv');
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      toast.success('Şablon indirildi');
    } catch {
      toast.error('Şablon indirilemedi');
    } finally {
      setDownloadingTemplate(false);
    }
  };

  const handleImport = async () => {
    if (!file) return;

    try {
      setLoading(true);
      const response = await employeesApi.import(file);
      setResult(response.data.data);
      setStep('result');
      
      if (response.data.data.success_count > 0) {
        toast.success(`${response.data.data.success_count} personel başarıyla işlendi`);
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'Import işlemi başarısız oldu'));
    } finally {
      setLoading(false);
    }
  };

  const handleFinish = () => {
    if (result && result.success_count > 0) {
      onSuccess();
    }
    handleClose();
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      title="Personel Import"
      size="lg"
    >
      {step === 'upload' && (
        <div>
          <p style={{ color: 'var(--text-secondary)', marginBottom: '1.5rem' }}>
            CSV veya Excel dosyasından personel bilgilerini toplu olarak içe aktarabilirsiniz.
            Mevcut sicil numaralarına sahip personeller güncellenir, yeni sicil numaraları için yeni kayıt oluşturulur.
          </p>

          {/* Şablon İndirme */}
          <div
            style={{
              padding: '1rem',
              background: 'var(--bg-tertiary)',
              borderRadius: 'var(--radius-md)',
              marginBottom: '1.5rem',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <BsFileEarmarkSpreadsheet size={24} style={{ color: 'var(--success)' }} />
              <div>
                <div style={{ fontWeight: 500 }}>Import Şablonu</div>
                <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
                  Doğru format için şablonu indirin
                </div>
              </div>
            </div>
            <button
              className="btn btn-secondary btn-sm"
              onClick={handleDownloadTemplate}
              disabled={downloadingTemplate}
            >
              {downloadingTemplate ? (
                <>
                  <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                  İndiriliyor...
                </>
              ) : (
                <>
                  <BsDownload /> Şablonu İndir
                </>
              )}
            </button>
          </div>

          {/* Dosya Yükleme Alanı */}
          <div
            onClick={() => fileInputRef.current?.click()}
            onDrop={handleDrop}
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            style={{
              border: `2px dashed ${dragOver ? 'var(--primary)' : 'var(--border-color)'}`,
              borderRadius: 'var(--radius-md)',
              padding: '3rem 2rem',
              textAlign: 'center',
              cursor: 'pointer',
              background: dragOver ? 'var(--primary-soft)' : 'var(--bg-secondary)',
              transition: 'all 0.2s ease',
            }}
          >
            <input
              ref={fileInputRef}
              type="file"
              accept=".csv,.xls,.xlsx"
              onChange={(e) => e.target.files?.[0] && handleFileSelect(e.target.files[0])}
              style={{ display: 'none' }}
            />
            
            {file ? (
              <div>
                <BsFileEarmarkSpreadsheet size={48} style={{ color: 'var(--success)', marginBottom: '1rem' }} />
                <div style={{ fontWeight: 500, marginBottom: '0.5rem' }}>{file.name}</div>
                <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
                  {(file.size / 1024).toFixed(1)} KB
                </div>
                <button
                  className="btn btn-ghost btn-sm"
                  onClick={(e) => {
                    e.stopPropagation();
                    setFile(null);
                  }}
                  style={{ marginTop: '1rem' }}
                >
                  Dosyayı Değiştir
                </button>
              </div>
            ) : (
              <div>
                <BsUpload size={48} style={{ color: 'var(--text-tertiary)', marginBottom: '1rem' }} />
                <div style={{ fontWeight: 500, marginBottom: '0.5rem' }}>
                  Dosyayı sürükleyip bırakın
                </div>
                <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
                  veya dosya seçmek için tıklayın
                </div>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginTop: '0.5rem' }}>
                  CSV, XLS veya XLSX (maks. 10MB)
                </div>
              </div>
            )}
          </div>

          {/* Butonlar */}
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1.5rem' }}>
            <button className="btn btn-secondary" onClick={handleClose}>
              İptal
            </button>
            <button
              className="btn btn-primary"
              onClick={handleImport}
              disabled={!file || loading}
            >
              {loading ? (
                <>
                  <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                  İşleniyor...
                </>
              ) : (
                <>
                  <BsUpload /> Import Et
                </>
              )}
            </button>
          </div>
        </div>
      )}

      {step === 'result' && result && (
        <div>
          {/* Özet */}
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(3, 1fr)',
              gap: '1rem',
              marginBottom: '1.5rem',
            }}
          >
            <div
              style={{
                padding: '1rem',
                background: 'var(--bg-tertiary)',
                borderRadius: 'var(--radius-md)',
                textAlign: 'center',
              }}
            >
              <div style={{ fontSize: '2rem', fontWeight: 700 }}>{result.total}</div>
              <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>Toplam Satır</div>
            </div>
            <div
              style={{
                padding: '1rem',
                background: 'var(--success-soft, rgba(34, 197, 94, 0.1))',
                borderRadius: 'var(--radius-md)',
                textAlign: 'center',
              }}
            >
              <div style={{ fontSize: '2rem', fontWeight: 700, color: 'var(--success)' }}>
                {result.success_count}
              </div>
              <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>Başarılı</div>
            </div>
            <div
              style={{
                padding: '1rem',
                background: result.failed_count > 0 ? 'var(--danger-soft, rgba(239, 68, 68, 0.1))' : 'var(--bg-tertiary)',
                borderRadius: 'var(--radius-md)',
                textAlign: 'center',
              }}
            >
              <div
                style={{
                  fontSize: '2rem',
                  fontWeight: 700,
                  color: result.failed_count > 0 ? 'var(--danger)' : 'inherit',
                }}
              >
                {result.failed_count}
              </div>
              <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>Hatalı</div>
            </div>
          </div>

          {/* Başarılı Kayıtlar */}
          {result.success_rows.length > 0 && (
            <div style={{ marginBottom: '1.5rem' }}>
              <h4 style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.75rem' }}>
                <BsCheckCircle style={{ color: 'var(--success)' }} />
                Başarılı İşlemler
              </h4>
              <div
                style={{
                  maxHeight: '150px',
                  overflowY: 'auto',
                  background: 'var(--bg-tertiary)',
                  borderRadius: 'var(--radius-md)',
                  padding: '0.75rem',
                }}
              >
                {result.success_rows.map((row, index) => (
                  <div
                    key={index}
                    style={{
                      display: 'flex',
                      justifyContent: 'space-between',
                      padding: '0.5rem',
                      borderBottom: index < result.success_rows.length - 1 ? '1px solid var(--border-color)' : 'none',
                    }}
                  >
                    <span>
                      Satır {row.row}: <strong>{row.employee_code}</strong>
                    </span>
                    <span className={`badge ${row.action === 'created' ? 'badge-success' : 'badge-info'}`}>
                      {row.action === 'created' ? 'Oluşturuldu' : 'Güncellendi'}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Hatalı Kayıtlar */}
          {result.failed_rows.length > 0 && (
            <div style={{ marginBottom: '1.5rem' }}>
              <h4 style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.75rem' }}>
                <BsXCircle style={{ color: 'var(--danger)' }} />
                Hatalı Satırlar
              </h4>
              <div
                style={{
                  maxHeight: '200px',
                  overflowY: 'auto',
                  background: 'var(--danger-soft, rgba(239, 68, 68, 0.05))',
                  borderRadius: 'var(--radius-md)',
                  padding: '0.75rem',
                }}
              >
                {result.failed_rows.map((row, index) => (
                  <div
                    key={index}
                    style={{
                      padding: '0.75rem',
                      borderBottom: index < result.failed_rows.length - 1 ? '1px solid var(--border-color)' : 'none',
                    }}
                  >
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
                      <BsExclamationTriangle style={{ color: 'var(--danger)' }} />
                      <strong>Satır {row.row}</strong>
                    </div>
                    <div style={{ fontSize: '0.875rem', color: 'var(--danger)' }}>{row.error}</div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Butonlar */}
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem' }}>
            <button className="btn btn-secondary" onClick={() => { setStep('upload'); setFile(null); }}>
              Yeni Import
            </button>
            <button className="btn btn-primary" onClick={handleFinish}>
              Kapat
            </button>
          </div>
        </div>
      )}
    </Modal>
  );
};

export default EmployeeImportModal;

