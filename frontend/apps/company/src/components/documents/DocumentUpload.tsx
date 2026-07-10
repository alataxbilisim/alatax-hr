import React, { useState, useRef, useEffect } from 'react';
import { Modal } from '../ui';
import { documentsApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsCloudUpload, BsFileEarmark, BsX } from 'react-icons/bs';

interface Category {
  id: number;
  name: string;
}

interface DocumentUploadProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

const DocumentUpload: React.FC<DocumentUploadProps> = ({
  isOpen,
  onClose,
  onSuccess,
}) => {
  const [loading, setLoading] = useState(false);
  const [categories, setCategories] = useState<Category[]>([]);
  const [files, setFiles] = useState<File[]>([]);
  const [formData, setFormData] = useState({
    category_id: '',
    title: '',
    description: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [dragOver, setDragOver] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (isOpen) {
      loadCategories();
      setFiles([]);
      setFormData({ category_id: '', title: '', description: '' });
      setErrors({});
    }
  }, [isOpen]);

  const loadCategories = async () => {
    try {
      const response = await documentsApi.categories.list();
      setCategories(response.data.data || []);
    } catch {
      toast.error('Kategoriler yüklenemedi');
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(true);
  };

  const handleDragLeave = () => {
    setDragOver(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(false);
    
    const droppedFiles = Array.from(e.dataTransfer.files);
    addFiles(droppedFiles);
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files) {
      addFiles(Array.from(e.target.files));
    }
  };

  const addFiles = (newFiles: File[]) => {
    // Max 10 MB per file
    const validFiles = newFiles.filter((f) => f.size <= 10 * 1024 * 1024);
    if (validFiles.length < newFiles.length) {
      toast.error('Bazı dosyalar 10 MB limitini aşıyor');
    }
    setFiles((prev) => [...prev, ...validFiles]);
    
    // Auto-fill title from first file if empty
    if (!formData.title && validFiles.length > 0) {
      const name = validFiles[0].name.replace(/\.[^/.]+$/, '');
      setFormData((prev) => ({ ...prev, title: name }));
    }
  };

  const removeFile = (index: number) => {
    setFiles((prev) => prev.filter((_, i) => i !== index));
  };

  const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  };

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (files.length === 0) {
      newErrors.files = 'En az bir dosya seçin';
    }

    if (!formData.title.trim()) {
      newErrors.title = 'Başlık gerekli';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validate()) return;

    setLoading(true);
    try {
      const data = new FormData();
      data.append('title', formData.title);
      if (formData.category_id) data.append('category_id', formData.category_id);
      if (formData.description) data.append('description', formData.description);
      files.forEach((file) => {
        data.append('file', file);
      });

      await documentsApi.files.create(data);
      toast.success('Evrak yüklendi');
      onSuccess();
      onClose();
    } catch (error: unknown) {
      const err = error as { response?: { data?: { errors?: Record<string, string[]> } } };
      if (err.response?.data?.errors) {
        const backendErrors: Record<string, string> = {};
        Object.entries(err.response.data.errors).forEach(([key, msgs]) => {
          backendErrors[key] = msgs[0];
        });
        setErrors(backendErrors);
      } else {
        toast.error('Yükleme başarısız');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="Evrak Yükle"
      size="md"
      footer={
        <>
          <button className="btn btn-secondary" onClick={onClose} disabled={loading}>
            İptal
          </button>
          <button className="btn btn-primary" onClick={handleSubmit} disabled={loading || files.length === 0}>
            {loading ? 'Yükleniyor...' : 'Yükle'}
          </button>
        </>
      }
    >
      {/* Drop Zone */}
      <div
        onClick={() => fileInputRef.current?.click()}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
        style={{
          border: `2px dashed ${dragOver ? 'var(--primary)' : errors.files ? 'var(--danger)' : 'var(--border-primary)'}`,
          borderRadius: 'var(--radius-lg)',
          padding: '2rem',
          textAlign: 'center',
          cursor: 'pointer',
          background: dragOver ? 'var(--primary-soft)' : 'var(--surface-glass)',
          transition: 'all 0.15s ease',
          marginBottom: '1rem',
        }}
      >
        <BsCloudUpload size={32} style={{ color: dragOver ? 'var(--primary)' : 'var(--text-muted)', marginBottom: '0.5rem' }} />
        <p style={{ color: 'var(--text-secondary)', margin: '0.5rem 0 0', fontSize: '0.875rem' }}>
          Dosyaları sürükleyin veya tıklayın
        </p>
        <p style={{ color: 'var(--text-muted)', margin: '0.25rem 0 0', fontSize: '0.75rem' }}>
          Maks. 10 MB
        </p>
        <input
          ref={fileInputRef}
          type="file"
          multiple
          onChange={handleFileSelect}
          style={{ display: 'none' }}
        />
      </div>
      {errors.files && <div className="form-error" style={{ marginTop: '-0.75rem', marginBottom: '0.75rem' }}>{errors.files}</div>}

      {/* Selected Files */}
      {files.length > 0 && (
        <div style={{ marginBottom: '1rem' }}>
          <div style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--text-tertiary)', marginBottom: '0.5rem' }}>
            Seçilen Dosyalar ({files.length})
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
            {files.map((file, idx) => (
              <div
                key={idx}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  padding: '0.5rem 0.75rem',
                  background: 'var(--surface-glass)',
                  borderRadius: 'var(--radius-md)',
                  border: '1px solid var(--border-primary)',
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', minWidth: 0 }}>
                  <BsFileEarmark style={{ color: 'var(--primary)', flexShrink: 0 }} />
                  <span style={{ fontSize: '0.8125rem', color: 'var(--text-primary)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {file.name}
                  </span>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', flexShrink: 0 }}>
                  <span style={{ fontSize: '0.6875rem', color: 'var(--text-muted)' }}>
                    {formatFileSize(file.size)}
                  </span>
                  <button
                    type="button"
                    onClick={(e) => { e.stopPropagation(); removeFile(idx); }}
                    style={{ background: 'none', border: 'none', color: 'var(--danger)', cursor: 'pointer', padding: 2 }}
                  >
                    <BsX size={18} />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Title */}
      <div className="form-group">
        <label className="form-label">Başlık *</label>
        <input
          type="text"
          name="title"
          className={`form-control ${errors.title ? 'is-invalid' : ''}`}
          value={formData.title}
          onChange={handleChange}
          placeholder="Evrak başlığı"
        />
        {errors.title && <div className="form-error">{errors.title}</div>}
      </div>

      {/* Category */}
      <div className="form-group">
        <label className="form-label">Kategori</label>
        <select
          name="category_id"
          className="form-control"
          value={formData.category_id}
          onChange={handleChange}
        >
          <option value="">Kategori seçin (isteğe bağlı)</option>
          {categories.map((cat) => (
            <option key={cat.id} value={cat.id}>{cat.name}</option>
          ))}
        </select>
      </div>

      {/* Description */}
      <div className="form-group">
        <label className="form-label">Açıklama</label>
        <textarea
          name="description"
          className="form-control"
          value={formData.description}
          onChange={handleChange}
          rows={2}
          placeholder="Evrak hakkında açıklama (isteğe bağlı)"
        />
      </div>
    </Modal>
  );
};

export default DocumentUpload;

