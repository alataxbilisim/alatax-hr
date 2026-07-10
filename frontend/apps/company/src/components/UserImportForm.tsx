import React, { useState, useRef } from 'react';
import { Modal } from './ui';
import { usersApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { BsUpload, BsFileEarmarkSpreadsheet } from 'react-icons/bs';

interface UserImportFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const UserImportForm: React.FC<UserImportFormProps> = ({ isOpen, onClose, onSuccess }) => {
  const [file, setFile] = useState<File | null>(null);
  const [loading, setLoading] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0];
    if (selectedFile) {
      // CSV veya TXT dosyası kontrolü
      const validExtensions = ['.csv', '.txt'];
      const fileExtension = selectedFile.name.toLowerCase().substring(selectedFile.name.lastIndexOf('.'));
      
      if (!validExtensions.includes(fileExtension)) {
        toast.error('Lütfen CSV veya TXT formatında bir dosya seçin');
        return;
      }

      setFile(selectedFile);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!file) {
      toast.error('Lütfen bir dosya seçin');
      return;
    }

    setLoading(true);
    try {
      const response = await usersApi.import(file);
      const result = response.data.data;
      
      if (result.success > 0) {
        toast.success(`${result.success} kullanıcı başarıyla import edildi`);
      }
      
      if (result.failed > 0) {
        const errorMessage = result.errors.slice(0, 5).join('\n');
        toast.error(`${result.failed} kullanıcı import edilemedi. İlk 5 hata:\n${errorMessage}`, {
          duration: 8000,
        });
      }

      if (result.success > 0) {
        onSuccess();
        onClose();
        setFile(null);
        if (fileInputRef.current) {
          fileInputRef.current.value = '';
        }
      }
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'Import başarısız'));
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    if (!loading) {
      setFile(null);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
      onClose();
    }
  };

  const downloadTemplate = () => {
    // CSV template oluştur
    const headers = ['name', 'email', 'phone', 'department', 'is_active', 'roles'];
    const csvContent = headers.join(',') + '\n' +
      'Örnek Kullanıcı,ornek@example.com,5551234567,IT,true,Admin,User';
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'kullanici_import_template.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      title="Kullanıcı Import"
      size="md"
      footer={
        <>
          <button className="btn btn-secondary" onClick={handleClose} disabled={loading}>
            İptal
          </button>
          <button className="btn btn-primary" onClick={handleSubmit} disabled={loading || !file}>
            {loading ? (
              <>
                <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                Import Ediliyor...
              </>
            ) : (
              <>
                <BsUpload style={{ marginRight: 6 }} /> Import Et
              </>
            )}
          </button>
        </>
      }
    >
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label className="form-label">CSV Dosyası Seç *</label>
          <div
            style={{
              border: '2px dashed var(--border-color)',
              borderRadius: 'var(--radius-md)',
              padding: '2rem',
              textAlign: 'center',
              cursor: 'pointer',
              transition: 'all 0.2s',
              background: file ? 'var(--bg-secondary)' : 'transparent',
            }}
            onClick={() => fileInputRef.current?.click()}
            onDragOver={(e) => {
              e.preventDefault();
              e.currentTarget.style.borderColor = 'var(--primary)';
            }}
            onDragLeave={(e) => {
              e.currentTarget.style.borderColor = 'var(--border-color)';
            }}
            onDrop={(e) => {
              e.preventDefault();
              e.currentTarget.style.borderColor = 'var(--border-color)';
              const droppedFile = e.dataTransfer.files[0];
              if (droppedFile) {
                const validExtensions = ['.csv', '.txt'];
                const fileExtension = droppedFile.name.toLowerCase().substring(droppedFile.name.lastIndexOf('.'));
                if (validExtensions.includes(fileExtension)) {
                  setFile(droppedFile);
                } else {
                  toast.error('Lütfen CSV veya TXT formatında bir dosya seçin');
                }
              }
            }}
          >
            {file ? (
              <div>
                <BsFileEarmarkSpreadsheet size={48} style={{ color: 'var(--primary)', marginBottom: '0.5rem' }} />
                <div style={{ fontWeight: 500, marginBottom: '0.25rem' }}>{file.name}</div>
                <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
                  {(file.size / 1024).toFixed(2)} KB
                </div>
              </div>
            ) : (
              <div>
                <BsUpload size={48} style={{ color: 'var(--text-tertiary)', marginBottom: '0.5rem' }} />
                <div style={{ fontWeight: 500, marginBottom: '0.25rem' }}>Dosya Seç veya Sürükle-Bırak</div>
                <div style={{ fontSize: '0.875rem', color: 'var(--text-tertiary)' }}>
                  CSV veya TXT formatında dosya yükleyin
                </div>
              </div>
            )}
          </div>
          <input
            ref={fileInputRef}
            type="file"
            accept=".csv,.txt"
            onChange={handleFileChange}
            style={{ display: 'none' }}
            disabled={loading}
          />
        </div>

        <div className="alert alert-info" style={{ marginTop: '1rem' }}>
          <div style={{ fontWeight: 500, marginBottom: '0.5rem' }}>CSV Formatı:</div>
          <div style={{ fontSize: '0.875rem', marginBottom: '0.5rem' }}>
            <strong>Gerekli kolonlar:</strong> name, email
          </div>
          <div style={{ fontSize: '0.875rem', marginBottom: '0.5rem' }}>
            <strong>Opsiyonel kolonlar:</strong> phone, department, is_active (true/false), roles (virgülle ayrılmış)
          </div>
          <button
            type="button"
            className="btn btn-link btn-sm"
            onClick={downloadTemplate}
            style={{ padding: 0, marginTop: '0.5rem' }}
          >
            Template İndir
          </button>
        </div>
      </form>
    </Modal>
  );
};

export default UserImportForm;

