import React, { useState, useRef } from 'react';
import { employeesApi } from '@shared/services/api';
import { getErrorMessage } from '@shared/services/apiHelpers';
import toast from 'react-hot-toast';
import { Modal, ConfirmDialog } from '../../ui';
import {
  BsFileEarmark,
  BsPlus,
  BsDownload,
  BsTrash,
  BsExclamationTriangle,
} from 'react-icons/bs';

interface Document {
  id: number;
  title: string;
  description?: string;
  category: string;
  file_name: string;
  file_type?: string;
  file_size?: number;
  issue_date?: string;
  expiry_date?: string;
  is_expired: boolean;
  status: string;
  created_at: string;
  uploaded_by?: { id: number; name: string };
}

interface DocumentsTabProps {
  employeeId: number;
  documents: Document[];
  onRefresh: () => void;
}

const categoryLabels: Record<string, string> = {
  id_card: 'Kimlik',
  contract: 'Sözleşme',
  certificate: 'Sertifika',
  education: 'Eğitim',
  health: 'Sağlık',
  other: 'Diğer',
};

const DocumentsTab: React.FC<DocumentsTabProps> = ({ employeeId, documents, onRefresh }) => {
  const [uploadModalOpen, setUploadModalOpen] = useState(false);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [selectedDocument, setSelectedDocument] = useState<Document | null>(null);
  const [loading, setLoading] = useState(false);
  const [categoryFilter, setCategoryFilter] = useState('');
  
  // Form state
  const [file, setFile] = useState<File | null>(null);
  const [formData, setFormData] = useState({
    title: '',
    description: '',
    category: 'other',
    issue_date: '',
    expiry_date: '',
    is_visible_to_employee: true,
  });
  
  const fileInputRef = useRef<HTMLInputElement>(null);

  const filteredDocuments = categoryFilter
    ? documents.filter(d => d.category === categoryFilter)
    : documents;

  const formatDate = (date?: string) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('tr-TR');
  };

  const formatFileSize = (bytes?: number) => {
    if (!bytes) return '-';
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
    if (bytes >= 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${bytes} bytes`;
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files?.[0]) {
      const selectedFile = e.target.files[0];
      setFile(selectedFile);
      if (!formData.title) {
        setFormData({ ...formData, title: selectedFile.name.replace(/\.[^/.]+$/, '') });
      }
    }
  };

  const handleUpload = async () => {
    if (!file) {
      toast.error('Lütfen bir dosya seçin');
      return;
    }

    const data = new FormData();
    data.append('file', file);
    data.append('title', formData.title);
    data.append('description', formData.description);
    data.append('category', formData.category);
    if (formData.issue_date) data.append('issue_date', formData.issue_date);
    if (formData.expiry_date) data.append('expiry_date', formData.expiry_date);
    data.append('is_visible_to_employee', formData.is_visible_to_employee ? '1' : '0');

    try {
      setLoading(true);
      await employeesApi.documents.upload(employeeId, data);
      toast.success('Belge başarıyla yüklendi');
      setUploadModalOpen(false);
      resetForm();
      onRefresh();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, 'Belge yüklenemedi'));
    } finally {
      setLoading(false);
    }
  };

  const handleDownload = async (doc: Document) => {
    try {
      const response = await employeesApi.documents.download(employeeId, doc.id);
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', doc.file_name);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('Dosya indirilemedi');
    }
  };

  const handleDelete = async () => {
    if (!selectedDocument) return;
    
    try {
      await employeesApi.documents.delete(employeeId, selectedDocument.id);
      toast.success('Belge silindi');
      setDeleteDialogOpen(false);
      setSelectedDocument(null);
      onRefresh();
    } catch {
      toast.error('Belge silinemedi');
    }
  };

  const resetForm = () => {
    setFile(null);
    setFormData({
      title: '',
      description: '',
      category: 'other',
      issue_date: '',
      expiry_date: '',
      is_visible_to_employee: true,
    });
  };

  return (
    <div>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
        <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
          <select
            className="form-select"
            value={categoryFilter}
            onChange={(e) => setCategoryFilter(e.target.value)}
            style={{ width: 'auto' }}
          >
            <option value="">Tüm Kategoriler</option>
            {Object.entries(categoryLabels).map(([key, label]) => (
              <option key={key} value={key}>{label}</option>
            ))}
          </select>
          <span style={{ color: 'var(--text-tertiary)', fontSize: '0.875rem' }}>
            {filteredDocuments.length} belge
          </span>
        </div>
        <button className="btn btn-primary btn-sm" onClick={() => setUploadModalOpen(true)}>
          <BsPlus /> Belge Yükle
        </button>
      </div>

      {/* Belgeler Listesi */}
      {filteredDocuments.length === 0 ? (
        <div className="card">
          <div className="card-body" style={{ textAlign: 'center', padding: '3rem' }}>
            <BsFileEarmark size={48} style={{ color: 'var(--text-tertiary)', marginBottom: '1rem' }} />
            <p style={{ color: 'var(--text-tertiary)' }}>Henüz belge yüklenmemiş</p>
            <button className="btn btn-primary btn-sm" onClick={() => setUploadModalOpen(true)}>
              <BsPlus /> İlk Belgeyi Yükle
            </button>
          </div>
        </div>
      ) : (
        <div className="card">
          <div className="table-responsive">
            <table className="table">
              <thead>
                <tr>
                  <th>Başlık</th>
                  <th>Kategori</th>
                  <th>Dosya</th>
                  <th>Son Kullanma</th>
                  <th>Yüklenme</th>
                  <th className="text-end">İşlemler</th>
                </tr>
              </thead>
              <tbody>
                {filteredDocuments.map((doc) => (
                  <tr key={doc.id}>
                    <td>
                      <div style={{ fontWeight: 500 }}>{doc.title}</div>
                      {doc.description && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                          {doc.description.substring(0, 50)}...
                        </div>
                      )}
                    </td>
                    <td>
                      <span className="badge badge-secondary">{categoryLabels[doc.category] || doc.category}</span>
                    </td>
                    <td>
                      <div style={{ fontSize: '0.875rem' }}>{doc.file_name}</div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                        {formatFileSize(doc.file_size)}
                      </div>
                    </td>
                    <td>
                      {doc.expiry_date ? (
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                          {doc.is_expired && <BsExclamationTriangle style={{ color: 'var(--danger)' }} />}
                          <span style={{ color: doc.is_expired ? 'var(--danger)' : undefined }}>
                            {formatDate(doc.expiry_date)}
                          </span>
                        </div>
                      ) : (
                        '-'
                      )}
                    </td>
                    <td>
                      <div style={{ fontSize: '0.875rem' }}>{formatDate(doc.created_at)}</div>
                      {doc.uploaded_by && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                          {doc.uploaded_by.name}
                        </div>
                      )}
                    </td>
                    <td>
                      <div className="btn-group btn-group-sm">
                        <button
                          className="btn btn-secondary"
                          onClick={() => handleDownload(doc)}
                          title="İndir"
                        >
                          <BsDownload />
                        </button>
                        <button
                          className="btn btn-danger"
                          onClick={() => {
                            setSelectedDocument(doc);
                            setDeleteDialogOpen(true);
                          }}
                          title="Sil"
                        >
                          <BsTrash />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Yükleme Modal */}
      <Modal
        isOpen={uploadModalOpen}
        onClose={() => { setUploadModalOpen(false); resetForm(); }}
        title="Belge Yükle"
        size="md"
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          {/* Dosya Seçimi */}
          <div className="form-group">
            <label className="form-label">Dosya *</label>
            <input
              ref={fileInputRef}
              type="file"
              className="form-control"
              accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif"
              onChange={handleFileSelect}
            />
            {file && (
              <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', marginTop: '0.25rem' }}>
                {file.name} ({formatFileSize(file.size)})
              </div>
            )}
          </div>

          <div className="form-group">
            <label className="form-label">Başlık *</label>
            <input
              type="text"
              className="form-control"
              value={formData.title}
              onChange={(e) => setFormData({ ...formData, title: e.target.value })}
              placeholder="Belge başlığı"
            />
          </div>

          <div className="form-group">
            <label className="form-label">Kategori *</label>
            <select
              className="form-select"
              value={formData.category}
              onChange={(e) => setFormData({ ...formData, category: e.target.value })}
            >
              {Object.entries(categoryLabels).map(([key, label]) => (
                <option key={key} value={key}>{label}</option>
              ))}
            </select>
          </div>

          <div className="form-group">
            <label className="form-label">Açıklama</label>
            <textarea
              className="form-control"
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              rows={2}
            />
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
            <div className="form-group">
              <label className="form-label">Düzenlenme Tarihi</label>
              <input
                type="date"
                className="form-control"
                value={formData.issue_date}
                onChange={(e) => setFormData({ ...formData, issue_date: e.target.value })}
              />
            </div>
            <div className="form-group">
              <label className="form-label">Son Kullanma Tarihi</label>
              <input
                type="date"
                className="form-control"
                value={formData.expiry_date}
                onChange={(e) => setFormData({ ...formData, expiry_date: e.target.value })}
              />
            </div>
          </div>

          <div className="form-check">
            <input
              type="checkbox"
              className="form-check-input"
              id="is_visible"
              checked={formData.is_visible_to_employee}
              onChange={(e) => setFormData({ ...formData, is_visible_to_employee: e.target.checked })}
            />
            <label className="form-check-label" htmlFor="is_visible">
              Personel portalında görünsün
            </label>
          </div>

          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '1rem' }}>
            <button className="btn btn-secondary" onClick={() => { setUploadModalOpen(false); resetForm(); }}>
              İptal
            </button>
            <button
              className="btn btn-primary"
              onClick={handleUpload}
              disabled={!file || !formData.title || loading}
            >
              {loading ? 'Yükleniyor...' : 'Yükle'}
            </button>
          </div>
        </div>
      </Modal>

      {/* Silme Dialog */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => { setDeleteDialogOpen(false); setSelectedDocument(null); }}
        onConfirm={handleDelete}
        title="Belgeyi Sil"
        message={`"${selectedDocument?.title}" belgesini silmek istediğinizden emin misiniz?`}
        confirmText="Sil"
        variant="danger"
      />
    </div>
  );
};

export default DocumentsTab;

