import React, { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { documentsApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { Select } from '@shared/components';
import toast from 'react-hot-toast';
import { ConfirmDialog, Modal } from '../../components/ui';
import {
  BsArrowLeft,
  BsDownload,
  BsPencil,
  BsTrash,
  BsFileEarmarkText,
  BsFileEarmarkPdf,
  BsFileEarmarkImage,
  BsFileEarmarkWord,
  BsFileEarmarkExcel,
  BsFileEarmarkZip,
  BsClockHistory,
  BsFolder,
  BsPerson,
  BsCalendar3,
} from 'react-icons/bs';

interface DocumentVersion {
  id: number;
  version_number: number;
  file_name: string;
  file_size: number;
  change_notes?: string;
  uploaded_by?: { id: number; name: string };
  created_at: string;
}

interface DocumentDetail {
  id: number;
  name: string;
  description?: string;
  file_name: string;
  file_path?: string;
  file_size: number;
  file_type: string;
  category?: { id: number; name: string };
  category_id?: number;
  uploaded_by?: { id: number; name: string };
  version: number;
  current_version?: number;
  validity_date?: string;
  approval_status?: string;
  requires_approval?: boolean;
  metadata?: Record<string, unknown>;
  versions?: DocumentVersion[];
  created_at: string;
  updated_at?: string;
}

interface Category {
  id: number;
  name: string;
}

const DocumentDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  
  const [document, setDocument] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [categories, setCategories] = useState<Category[]>([]);
  const [approvalStatusOptions, setApprovalStatusOptions] = useState<LookupItem[]>([]);
  
  // Edit modal
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [editFormData, setEditFormData] = useState({ name: '', description: '', category_id: '' });
  const [editLoading, setEditLoading] = useState(false);
  
  // Delete dialog
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);
  
  // Download state
  const [downloadingId, setDownloadingId] = useState<number | null>(null);
  const [downloadingVersionId, setDownloadingVersionId] = useState<number | null>(null);

  const loadDocument = useCallback(async () => {
    try {
      setLoading(true);
      const response = await documentsApi.files.get(parseInt(id!));
      setDocument(response.data.data);
    } catch {
      toast.error('Evrak yüklenemedi');
      navigate('/documents');
    } finally {
      setLoading(false);
    }
  }, [id, navigate]);

  const loadCategories = useCallback(async () => {
    try {
      const response = await documentsApi.categories.list();
      setCategories(response.data.data || []);
    } catch {
      // Kategoriler yüklenemezse sessizce devam et
    }
  }, []);

  const loadLookups = useCallback(async () => {
    try {
      const res = await lookupsApi.forType('document_approval_status');
      setApprovalStatusOptions(res.data.data ?? []);
    } catch {
      console.error('Onay durumu lookup yüklenemedi');
    }
  }, []);

  useEffect(() => {
    if (!id) return;
    void loadDocument();
    void loadCategories();
    void loadLookups();
  }, [id, loadDocument, loadCategories, loadLookups]);

  const handleDownload = async () => {
    if (!document) return;
    
    setDownloadingId(document.id);
    try {
      const response = await documentsApi.files.download(document.id);
      const blob = new Blob([response.data], { type: document.file_type });
      const url = window.URL.createObjectURL(blob);
      const link = window.document.createElement('a');
      link.href = url;
      link.download = document.file_name;
      window.document.body.appendChild(link);
      link.click();
      window.document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
      toast.success('İndirme başladı');
    } catch {
      toast.error('Dosya indirilemedi');
    } finally {
      setDownloadingId(null);
    }
  };

  const handleDownloadVersion = async (version: DocumentVersion) => {
    if (!document) return;
    
    setDownloadingVersionId(version.id);
    try {
      const response = await documentsApi.files.downloadVersion(document.id, version.id);
      const blob = new Blob([response.data]);
      const url = window.URL.createObjectURL(blob);
      const link = window.document.createElement('a');
      link.href = url;
      link.download = version.file_name;
      window.document.body.appendChild(link);
      link.click();
      window.document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
      toast.success('İndirme başladı');
    } catch {
      toast.error('Versiyon indirilemedi');
    } finally {
      setDownloadingVersionId(null);
    }
  };

  const handleEdit = () => {
    if (!document) return;
    setEditFormData({
      name: document.name,
      description: document.description || '',
      category_id: document.category_id?.toString() || document.category?.id?.toString() || '',
    });
    setEditModalOpen(true);
  };

  const handleSaveEdit = async () => {
    if (!document) return;
    
    setEditLoading(true);
    try {
      await documentsApi.files.update(document.id, {
        name: editFormData.name,
        description: editFormData.description || null,
        category_id: editFormData.category_id ? parseInt(editFormData.category_id) : null,
      });
      toast.success('Evrak güncellendi');
      setEditModalOpen(false);
      loadDocument();
    } catch {
      toast.error('Güncelleme başarısız');
    } finally {
      setEditLoading(false);
    }
  };

  const handleDelete = async () => {
    if (!document) return;
    
    setDeleteLoading(true);
    try {
      await documentsApi.files.delete(document.id);
      toast.success('Evrak silindi');
      navigate('/documents');
    } catch {
      toast.error('Silme başarısız');
    } finally {
      setDeleteLoading(false);
    }
  };

  const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  };

  const getFileIcon = (mimeType: string, size: number = 48) => {
    if (mimeType?.includes('pdf')) {
      return <BsFileEarmarkPdf size={size} style={{ color: '#ef4444' }} />;
    }
    if (mimeType?.includes('image')) {
      return <BsFileEarmarkImage size={size} style={{ color: '#8b5cf6' }} />;
    }
    if (mimeType?.includes('word') || mimeType?.includes('document')) {
      return <BsFileEarmarkWord size={size} style={{ color: '#3b82f6' }} />;
    }
    if (mimeType?.includes('excel') || mimeType?.includes('spreadsheet')) {
      return <BsFileEarmarkExcel size={size} style={{ color: '#22c55e' }} />;
    }
    if (mimeType?.includes('zip') || mimeType?.includes('rar') || mimeType?.includes('7z')) {
      return <BsFileEarmarkZip size={size} style={{ color: '#f59e0b' }} />;
    }
    return <BsFileEarmarkText size={size} style={{ color: 'var(--primary)' }} />;
  };

  const getApprovalStatusBadge = (status?: string) => {
    const value = status || 'approved';
    const lookup = approvalStatusOptions.find((o) => o.value === value);
    const label = lookup?.label || value;
    const classMap: Record<string, string> = {
      draft: 'badge-secondary',
      pending: 'badge-warning',
      approved: 'badge-success',
      rejected: 'badge-danger',
    };
    return <span className={`badge ${classMap[value] || 'badge-secondary'}`}>{label}</span>;
  };

  if (loading) {
    return (
      <div className="page-loading">
        <div className="loading-spinner" />
      </div>
    );
  }

  if (!document) {
    return (
      <div className="card">
        <div className="card-body empty-state">
          <BsFileEarmarkText size={48} style={{ color: 'var(--text-muted)' }} />
          <h3 className="empty-state-title mt-3">Evrak Bulunamadı</h3>
          <button className="btn btn-primary mt-2" onClick={() => navigate('/documents')}>
            Evraklara Dön
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="animate-fade-in">
      {/* Page Header */}
      <div className="page-header">
        <div className="page-header-content">
          <button
            className="btn btn-ghost btn-sm"
            onClick={() => navigate('/documents')}
            style={{ marginBottom: '0.5rem' }}
          >
            <BsArrowLeft /> Evraklara Dön
          </button>
          <h1 className="page-title">{document.name}</h1>
          <p className="page-subtitle">{document.file_name}</p>
        </div>
        <div className="page-header-actions">
          <button
            className="btn btn-secondary"
            onClick={handleDownload}
            disabled={downloadingId === document.id}
          >
            {downloadingId === document.id ? (
              <span className="loading-spinner" style={{ width: 16, height: 16 }} />
            ) : (
              <BsDownload />
            )}
            İndir
          </button>
          <button className="btn btn-secondary" onClick={handleEdit}>
            <BsPencil /> Düzenle
          </button>
          <button
            className="btn btn-danger"
            onClick={() => setDeleteDialogOpen(true)}
          >
            <BsTrash /> Sil
          </button>
        </div>
      </div>

      <div className="row" style={{ gap: '1.5rem' }}>
        {/* Main Content */}
        <div className="col-lg-8">
          {/* Document Preview Card */}
          <div className="card mb-4">
            <div className="card-body">
              <div style={{ display: 'flex', alignItems: 'center', gap: '1.5rem' }}>
                <div
                  style={{
                    width: 80,
                    height: 80,
                    borderRadius: 'var(--radius-lg)',
                    background: 'var(--surface-glass)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexShrink: 0,
                  }}
                >
                  {getFileIcon(document.file_type)}
                </div>
                <div style={{ flex: 1 }}>
                  <h3 style={{ margin: 0, fontSize: '1.25rem', fontWeight: 600 }}>{document.name}</h3>
                  <p style={{ margin: '0.5rem 0', color: 'var(--text-secondary)', fontSize: '0.875rem' }}>
                    {document.file_name}
                  </p>
                  <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap' }}>
                    <span className="badge badge-secondary">{formatFileSize(document.file_size)}</span>
                    <span className="badge badge-secondary">v{document.current_version || document.version}</span>
                    {getApprovalStatusBadge(document.approval_status)}
                  </div>
                </div>
              </div>
              
              {document.description && (
                <div style={{ marginTop: '1.5rem', padding: '1rem', background: 'var(--surface-glass)', borderRadius: 'var(--radius-md)' }}>
                  <h4 style={{ fontSize: '0.875rem', fontWeight: 600, marginBottom: '0.5rem', color: 'var(--text-secondary)' }}>
                    Açıklama
                  </h4>
                  <p style={{ margin: 0, color: 'var(--text-primary)', lineHeight: 1.6 }}>
                    {document.description}
                  </p>
                </div>
              )}
            </div>
          </div>

          {/* Version History */}
          {document.versions && document.versions.length > 0 && (
            <div className="card">
              <div className="card-header">
                <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <BsClockHistory /> Versiyon Geçmişi
                </h3>
              </div>
              <div className="card-body" style={{ padding: 0 }}>
                <div className="table-responsive">
                  <table className="table">
                    <thead>
                      <tr>
                        <th>Versiyon</th>
                        <th>Dosya</th>
                        <th>Boyut</th>
                        <th>Yükleyen</th>
                        <th>Tarih</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      {document.versions.map((version) => (
                        <tr key={version.id}>
                          <td>
                            <span className="badge badge-primary">v{version.version_number}</span>
                          </td>
                          <td>
                            <div>
                              <span style={{ fontWeight: 500 }}>{version.file_name}</span>
                              {version.change_notes && (
                                <p style={{ margin: '0.25rem 0 0', fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                                  {version.change_notes}
                                </p>
                              )}
                            </div>
                          </td>
                          <td>{formatFileSize(version.file_size)}</td>
                          <td>{version.uploaded_by?.name || '-'}</td>
                          <td>{new Date(version.created_at).toLocaleDateString('tr-TR')}</td>
                          <td>
                            <button
                              className="btn btn-ghost btn-icon btn-sm"
                              onClick={() => handleDownloadVersion(version)}
                              disabled={downloadingVersionId === version.id}
                              title="İndir"
                            >
                              {downloadingVersionId === version.id ? (
                                <span className="loading-spinner" style={{ width: 14, height: 14 }} />
                              ) : (
                                <BsDownload />
                              )}
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Sidebar */}
        <div className="col-lg-4">
          <div className="card">
            <div className="card-header">
              <h3 className="card-title">Detaylar</h3>
            </div>
            <div className="card-body">
              <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                  <div style={{ width: 32, height: 32, borderRadius: 'var(--radius-md)', background: 'var(--primary-soft)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <BsFolder style={{ color: 'var(--primary)' }} />
                  </div>
                  <div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Kategori</div>
                    <div style={{ fontWeight: 500 }}>{document.category?.name || 'Kategorisiz'}</div>
                  </div>
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                  <div style={{ width: 32, height: 32, borderRadius: 'var(--radius-md)', background: 'var(--success-soft)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <BsPerson style={{ color: 'var(--success)' }} />
                  </div>
                  <div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Yükleyen</div>
                    <div style={{ fontWeight: 500 }}>{document.uploaded_by?.name || '-'}</div>
                  </div>
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                  <div style={{ width: 32, height: 32, borderRadius: 'var(--radius-md)', background: 'var(--warning-soft)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <BsCalendar3 style={{ color: 'var(--warning)' }} />
                  </div>
                  <div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Yükleme Tarihi</div>
                    <div style={{ fontWeight: 500 }}>
                      {new Date(document.created_at).toLocaleDateString('tr-TR', {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                      })}
                    </div>
                  </div>
                </div>

                {document.validity_date && (
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                    <div style={{ width: 32, height: 32, borderRadius: 'var(--radius-md)', background: 'var(--danger-soft)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                      <BsCalendar3 style={{ color: 'var(--danger)' }} />
                    </div>
                    <div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>Geçerlilik Tarihi</div>
                      <div style={{ fontWeight: 500 }}>
                        {new Date(document.validity_date).toLocaleDateString('tr-TR')}
                      </div>
                    </div>
                  </div>
                )}

                {document.updated_at && document.updated_at !== document.created_at && (
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', paddingTop: '0.5rem', borderTop: '1px solid var(--border-primary)' }}>
                    Son güncelleme: {new Date(document.updated_at).toLocaleDateString('tr-TR', {
                      day: 'numeric',
                      month: 'long',
                      year: 'numeric',
                    })}
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Metadata */}
          {document.metadata && Object.keys(document.metadata).length > 0 && (
            <div className="card mt-4">
              <div className="card-header">
                <h3 className="card-title">Meta Veriler</h3>
              </div>
              <div className="card-body">
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                  {Object.entries(document.metadata).map(([key, value]) => (
                    <div key={key} style={{ display: 'flex', justifyContent: 'space-between' }}>
                      <span style={{ color: 'var(--text-tertiary)', fontSize: '0.875rem' }}>{key}</span>
                      <span style={{ fontWeight: 500, fontSize: '0.875rem' }}>{String(value)}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Edit Modal */}
      <Modal
        isOpen={editModalOpen}
        onClose={() => setEditModalOpen(false)}
        title="Evrak Düzenle"
        size="md"
        footer={
          <>
            <button className="btn btn-secondary" onClick={() => setEditModalOpen(false)} disabled={editLoading}>
              İptal
            </button>
            <button className="btn btn-primary" onClick={handleSaveEdit} disabled={editLoading}>
              {editLoading ? 'Kaydediliyor...' : 'Kaydet'}
            </button>
          </>
        }
      >
        <div className="form-group">
          <label className="form-label">Evrak Adı *</label>
          <input
            type="text"
            className="form-control"
            value={editFormData.name}
            onChange={(e) => setEditFormData({ ...editFormData, name: e.target.value })}
          />
        </div>
        <div className="form-group">
          <label className="form-label">Kategori</label>
          <Select
            value={editFormData.category_id}
            onChange={(v) => setEditFormData({ ...editFormData, category_id: v })}
            options={categories.map((cat) => ({
              value: String(cat.id),
              label: cat.name,
            }))}
            placeholder="Kategori seçin"
            allowEmpty
            emptyLabel="Kategori seçin"
            clearable
            aria-label="Kategori"
          />
        </div>
        <div className="form-group">
          <label className="form-label">Açıklama</label>
          <textarea
            className="form-control"
            rows={3}
            value={editFormData.description}
            onChange={(e) => setEditFormData({ ...editFormData, description: e.target.value })}
          />
        </div>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmDialog
        isOpen={deleteDialogOpen}
        onClose={() => setDeleteDialogOpen(false)}
        onConfirm={handleDelete}
        title="Evrak Sil"
        message={`"${document.name}" evrakını silmek istediğinize emin misiniz? Bu işlem geri alınamaz.`}
        confirmText="Sil"
        variant="danger"
        loading={deleteLoading}
      />
    </div>
  );
};

export default DocumentDetailPage;

