import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { documentsApi, lookupsApi, type LookupItem } from '@shared/services/api';
import { Select } from '@shared/components';
import toast from 'react-hot-toast';
import { DataTable, ConfirmDialog, Modal } from '../../components/ui';
import DocumentUpload from '../../components/documents/DocumentUpload';
import CategoryForm from '../../components/documents/CategoryForm';
import {
  BsPlus,
  BsFileEarmarkText,
  BsFolder,
  BsDownload,
  BsTrash,
  BsPencil,
  BsEye,
  BsFilter,
  BsX,
  BsFileEarmarkPdf,
  BsFileEarmarkImage,
  BsFileEarmarkWord,
  BsFileEarmarkExcel,
  BsFileEarmarkZip,
} from 'react-icons/bs';

interface Category {
  id: number;
  name: string;
  description?: string;
  documents_count?: number;
}

interface Document {
  id: number;
  name: string;
  description?: string;
  file_name: string;
  file_size: number;
  file_type: string;
  category?: { id: number; name: string };
  category_id?: number;
  user?: { id: number; name: string };
  uploaded_by?: { id: number; name: string };
  created_at: string;
  updated_at?: string;
  approval_status?: string;
  version?: number;
  current_version?: number;
}

type TabType = 'documents' | 'categories';

interface FilterState {
  category_id: string;
  file_type: string;
  date_from: string;
  date_to: string;
}

const DocumentsPage: React.FC = () => {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState<TabType>('documents');
  const [search, setSearch] = useState('');

  // Filter state
  const [showFilters, setShowFilters] = useState(false);
  const [filters, setFilters] = useState<FilterState>({
    category_id: '',
    file_type: '',
    date_from: '',
    date_to: '',
  });
  const [appliedFilters, setAppliedFilters] = useState<FilterState>({
    category_id: '',
    file_type: '',
    date_from: '',
    date_to: '',
  });

  // Documents state
  const [documents, setDocuments] = useState<Document[]>([]);
  const [docsLoading, setDocsLoading] = useState(true);
  const [docsPage, setDocsPage] = useState(1);
  const [docsTotalPages, setDocsTotalPages] = useState(1);
  const [docsTotal, setDocsTotal] = useState(0);
  const [uploadOpen, setUploadOpen] = useState(false);
  const [deleteDocDialogOpen, setDeleteDocDialogOpen] = useState(false);
  const [docToDelete, setDocToDelete] = useState<Document | null>(null);
  const [deleteDocLoading, setDeleteDocLoading] = useState(false);

  // Edit modal state
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [docToEdit, setDocToEdit] = useState<Document | null>(null);
  const [editFormData, setEditFormData] = useState({ name: '', description: '', category_id: '' });
  const [editLoading, setEditLoading] = useState(false);

  // Categories state
  const [categories, setCategories] = useState<Category[]>([]);
  const [catsLoading, setCatsLoading] = useState(true);
  const [catFormOpen, setCatFormOpen] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<Category | undefined>();
  const [deleteCatDialogOpen, setDeleteCatDialogOpen] = useState(false);
  const [catToDelete, setCatToDelete] = useState<Category | null>(null);
  const [deleteCatLoading, setDeleteCatLoading] = useState(false);

  // Download state
  const [downloadingId, setDownloadingId] = useState<number | null>(null);

  const [fileTypeOptions, setFileTypeOptions] = useState<LookupItem[]>([]);

  const loadLookups = useCallback(async () => {
    try {
      const fileTypeRes = await lookupsApi.forType('document_file_type');
      setFileTypeOptions(fileTypeRes.data.data ?? []);
    } catch {
      console.error('Evrak lookup listeleri yüklenemedi');
    }
  }, []);

  const loadDocuments = useCallback(async () => {
    try {
      setDocsLoading(true);
      const params: Record<string, unknown> = { page: docsPage, per_page: 15 };
      if (search) params.search = search;
      if (appliedFilters.category_id) params.category_id = appliedFilters.category_id;
      if (appliedFilters.file_type) params.file_type = appliedFilters.file_type;
      if (appliedFilters.date_from) params.date_from = appliedFilters.date_from;
      if (appliedFilters.date_to) params.date_to = appliedFilters.date_to;

      const response = await documentsApi.files.list(params);
      const data = response.data.data;

      if (Array.isArray(data)) {
        setDocuments(data);
        setDocsTotalPages(1);
        setDocsTotal(data.length);
      } else if (data?.data) {
        setDocuments(data.data);
        setDocsTotalPages(data.meta?.last_page || data.last_page || 1);
        setDocsTotal(data.meta?.total || data.total || 0);
      }
    } catch {
      toast.error('Evraklar yüklenemedi');
    } finally {
      setDocsLoading(false);
    }
  }, [docsPage, search, appliedFilters]);

  const loadCategories = useCallback(async () => {
    try {
      setCatsLoading(true);
      const response = await documentsApi.categories.list();
      setCategories(response.data.data || []);
    } catch {
      toast.error('Kategoriler yüklenemedi');
    } finally {
      setCatsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadCategories();
    void loadLookups();
  }, [loadCategories, loadLookups]);

  useEffect(() => {
    if (activeTab === 'documents') {
      loadDocuments();
    } else {
      loadCategories();
    }
  }, [activeTab, loadDocuments, loadCategories]);

  const handleDeleteDocument = (doc: Document) => {
    setDocToDelete(doc);
    setDeleteDocDialogOpen(true);
  };

  const confirmDeleteDocument = async () => {
    if (!docToDelete) return;

    setDeleteDocLoading(true);
    try {
      await documentsApi.files.delete(docToDelete.id);
      toast.success('Evrak silindi');
      setDeleteDocDialogOpen(false);
      setDocToDelete(null);
      loadDocuments();
    } catch {
      toast.error('Evrak silinemedi');
    } finally {
      setDeleteDocLoading(false);
    }
  };

  const handleDownloadDocument = async (doc: Document) => {
    setDownloadingId(doc.id);
    try {
      const response = await documentsApi.files.download(doc.id);
      const blob = new Blob([response.data], { type: doc.file_type });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = doc.file_name;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
      toast.success('İndirme başladı');
    } catch {
      toast.error('Dosya indirilemedi');
    } finally {
      setDownloadingId(null);
    }
  };

  const handleEditDocument = (doc: Document) => {
    setDocToEdit(doc);
    setEditFormData({
      name: doc.name,
      description: doc.description || '',
      category_id: doc.category_id?.toString() || doc.category?.id?.toString() || '',
    });
    setEditModalOpen(true);
  };

  const handleSaveEdit = async () => {
    if (!docToEdit) return;

    setEditLoading(true);
    try {
      await documentsApi.files.update(docToEdit.id, {
        name: editFormData.name,
        description: editFormData.description || null,
        category_id: editFormData.category_id ? parseInt(editFormData.category_id) : null,
      });
      toast.success('Evrak güncellendi');
      setEditModalOpen(false);
      setDocToEdit(null);
      loadDocuments();
    } catch {
      toast.error('Güncelleme başarısız');
    } finally {
      setEditLoading(false);
    }
  };

  const handleDeleteCategory = (cat: Category) => {
    setCatToDelete(cat);
    setDeleteCatDialogOpen(true);
  };

  const confirmDeleteCategory = async () => {
    if (!catToDelete) return;

    setDeleteCatLoading(true);
    try {
      await documentsApi.categories.delete(catToDelete.id);
      toast.success('Kategori silindi');
      setDeleteCatDialogOpen(false);
      setCatToDelete(null);
      loadCategories();
    } catch {
      toast.error('Kategori silinemedi');
    } finally {
      setDeleteCatLoading(false);
    }
  };

  const applyFilters = () => {
    setAppliedFilters(filters);
    setDocsPage(1);
    setShowFilters(false);
  };

  const clearFilters = () => {
    const emptyFilters = { category_id: '', file_type: '', date_from: '', date_to: '' };
    setFilters(emptyFilters);
    setAppliedFilters(emptyFilters);
    setDocsPage(1);
  };

  const hasActiveFilters = Object.values(appliedFilters).some((v) => v !== '');

  const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  };

  const getFileIcon = (mimeType: string) => {
    if (mimeType?.includes('pdf')) {
      return <BsFileEarmarkPdf size={20} style={{ color: '#ef4444' }} />;
    }
    if (mimeType?.includes('image')) {
      return <BsFileEarmarkImage size={20} style={{ color: '#8b5cf6' }} />;
    }
    if (mimeType?.includes('word') || mimeType?.includes('document')) {
      return <BsFileEarmarkWord size={20} style={{ color: '#3b82f6' }} />;
    }
    if (mimeType?.includes('excel') || mimeType?.includes('spreadsheet')) {
      return <BsFileEarmarkExcel size={20} style={{ color: '#22c55e' }} />;
    }
    if (mimeType?.includes('zip') || mimeType?.includes('rar') || mimeType?.includes('7z')) {
      return <BsFileEarmarkZip size={20} style={{ color: '#f59e0b' }} />;
    }
    return <BsFileEarmarkText size={20} style={{ color: 'var(--primary)' }} />;
  };

  const documentColumns = [
    {
      key: 'title',
      title: 'Evrak',
      render: (doc: Document) => (
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.625rem' }}>
          <div
            style={{
              width: 36,
              height: 36,
              borderRadius: 'var(--radius-md)',
              background: 'var(--surface-glass)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              flexShrink: 0,
            }}
          >
            {getFileIcon(doc.file_type)}
          </div>
          <div style={{ minWidth: 0 }}>
            <div style={{ fontWeight: 500, color: 'var(--text-primary)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
              {doc.name}
            </div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
              {doc.file_name} • {formatFileSize(doc.file_size)}
            </div>
          </div>
        </div>
      ),
    },
    {
      key: 'category',
      title: 'Kategori',
      render: (doc: Document) => (
        doc.category ? (
          <span className="badge badge-secondary">{doc.category.name}</span>
        ) : (
          <span style={{ color: 'var(--text-muted)', fontSize: '0.8125rem' }}>-</span>
        )
      ),
    },
    {
      key: 'user',
      title: 'Yükleyen',
      render: (doc: Document) => (
        <span style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)' }}>
          {doc.uploaded_by?.name || doc.user?.name || '-'}
        </span>
      ),
    },
    {
      key: 'created_at',
      title: 'Tarih',
      render: (doc: Document) => (
        <span style={{ fontSize: '0.8125rem', color: 'var(--text-tertiary)' }}>
          {new Date(doc.created_at).toLocaleDateString('tr-TR')}
        </span>
      ),
    },
    {
      key: 'actions',
      title: 'İşlemler',
      width: '140px',
      align: 'right' as const,
      render: (doc: Document) => (
        <div style={{ display: 'flex', gap: '0.25rem', justifyContent: 'flex-end' }}>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => navigate(`/documents/${doc.id}`)}
            title="Detay"
          >
            <BsEye />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => handleDownloadDocument(doc)}
            title="İndir"
            disabled={downloadingId === doc.id}
          >
            {downloadingId === doc.id ? (
              <span className="loading-spinner" style={{ width: 14, height: 14 }} />
            ) : (
              <BsDownload />
            )}
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => handleEditDocument(doc)}
            title="Düzenle"
          >
            <BsPencil />
          </button>
          <button
            className="btn btn-ghost btn-icon btn-sm"
            onClick={() => handleDeleteDocument(doc)}
            title="Sil"
            style={{ color: 'var(--danger)' }}
          >
            <BsTrash />
          </button>
        </div>
      ),
    },
  ];

  return (
    <div className="animate-fade-in list-page">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Evraklar</h1>
        </div>
        <div className="page-header-actions">
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={() => {
              if (activeTab === 'documents') {
                setUploadOpen(true);
              } else {
                setSelectedCategory(undefined);
                setCatFormOpen(true);
              }
            }}
          >
            <BsPlus />
            {activeTab === 'documents' ? 'Evrak Yükle' : 'Yeni Kategori'}
          </button>
        </div>
      </div>

      <div className="tabs">
        <button
          type="button"
          className={`tab ${activeTab === 'documents' ? 'active' : ''}`}
          onClick={() => setActiveTab('documents')}
        >
          Evraklar
        </button>
        <button
          type="button"
          className={`tab ${activeTab === 'categories' ? 'active' : ''}`}
          onClick={() => setActiveTab('categories')}
        >
          Kategoriler
        </button>
      </div>

      {activeTab === 'documents' ? (
        <>
          <div className="list-filter-bar">
            <div className="list-filter-search input-group">
              <input
                type="text"
                className="form-control"
                placeholder="Evrak ara..."
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setDocsPage(1);
                }}
              />
            </div>
            <button
              type="button"
              className={`btn btn-sm ${hasActiveFilters || showFilters ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => setShowFilters(!showFilters)}
            >
              <BsFilter /> Filtrele
              {hasActiveFilters && (
                <span className="badge badge-light" style={{ marginLeft: 6 }}>
                  {Object.values(appliedFilters).filter((v) => v !== '').length}
                </span>
              )}
            </button>
            {hasActiveFilters && (
              <button type="button" className="btn btn-ghost btn-sm" onClick={clearFilters}>
                <BsX /> Temizle
              </button>
            )}
          </div>

          {showFilters && (
            <div className="list-filter-bar list-filter-advanced" style={{ flexDirection: 'column', alignItems: 'stretch' }}>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 'var(--sp-2)', alignItems: 'flex-end' }}>
                <div style={{ minWidth: 140, flex: '1 1 140px' }}>
                  <label className="form-label">Kategori</label>
                  <Select
                    value={filters.category_id}
                    onChange={(v) => setFilters({ ...filters, category_id: v })}
                    options={categories.map((cat) => ({
                      value: String(cat.id),
                      label: cat.name,
                    }))}
                    placeholder="Tümü"
                    allowEmpty
                    emptyLabel="Tümü"
                    clearable
                    aria-label="Kategori filtresi"
                  />
                </div>
                <div style={{ minWidth: 140, flex: '1 1 140px' }}>
                  <label className="form-label">Dosya Tipi</label>
                  <Select
                    value={filters.file_type}
                    onChange={(v) => setFilters({ ...filters, file_type: v })}
                    options={fileTypeOptions.map((opt) => ({
                      value: opt.value,
                      label: opt.label,
                      color: opt.color,
                    }))}
                    placeholder="Tümü"
                    allowEmpty
                    emptyLabel="Tümü"
                    clearable
                    aria-label="Dosya tipi filtresi"
                  />
                </div>
                <div style={{ minWidth: 130, flex: '1 1 130px' }}>
                  <label className="form-label">Başlangıç</label>
                  <input
                    type="date"
                    className="form-control"
                    value={filters.date_from}
                    onChange={(e) => setFilters({ ...filters, date_from: e.target.value })}
                  />
                </div>
                <div style={{ minWidth: 130, flex: '1 1 130px' }}>
                  <label className="form-label">Bitiş</label>
                  <input
                    type="date"
                    className="form-control"
                    value={filters.date_to}
                    onChange={(e) => setFilters({ ...filters, date_to: e.target.value })}
                  />
                </div>
                <button type="button" className="btn btn-primary btn-sm" onClick={applyFilters}>
                  Uygula
                </button>
              </div>
            </div>
          )}

          <DataTable
            columns={documentColumns}
            data={documents}
            loading={docsLoading}
            emptyMessage="Evrak bulunamadı"
            emptyIcon={<BsFileEarmarkText size={32} />}
            currentPage={docsPage}
            totalPages={docsTotalPages}
            total={docsTotal}
            onPageChange={setDocsPage}
          />
        </>
      ) : (
        <>
          {catsLoading ? (
            <div className="page-loading">
              <div className="loading-spinner" />
            </div>
          ) : categories.length === 0 ? (
            <div className="card">
              <div className="card-body empty-state">
                <BsFolder size={48} style={{ color: 'var(--text-muted)' }} />
                <h3 className="empty-state-title mt-3">Kategori Bulunamadı</h3>
                <p className="empty-state-text">
                  Evrakları düzenlemek için kategoriler oluşturun.
                </p>
                <button
                  className="btn btn-primary mt-2"
                  onClick={() => {
                    setSelectedCategory(undefined);
                    setCatFormOpen(true);
                  }}
                >
                  <BsPlus /> Kategori Oluştur
                </button>
              </div>
            </div>
          ) : (
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '1rem' }}>
              {categories.map((cat) => (
                <div key={cat.id} className="card">
                  <div className="card-body">
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.625rem' }}>
                        <div
                          style={{
                            width: 36,
                            height: 36,
                            borderRadius: 'var(--radius-md)',
                            background: 'var(--primary-soft)',
                            color: 'var(--primary)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                          }}
                        >
                          <BsFolder size={16} />
                        </div>
                        <div>
                          <h4 style={{ fontSize: '0.9375rem', fontWeight: 600, color: 'var(--text-primary)', margin: 0 }}>
                            {cat.name}
                          </h4>
                          <span style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
                            {cat.documents_count || 0} evrak
                          </span>
                        </div>
                      </div>
                      
                      <div style={{ display: 'flex', gap: '0.25rem' }}>
                        <button
                          className="btn btn-ghost btn-icon btn-sm"
                          onClick={() => {
                            setSelectedCategory(cat);
                            setCatFormOpen(true);
                          }}
                          title="Düzenle"
                        >
                          <BsPencil />
                        </button>
                        <button
                          className="btn btn-ghost btn-icon btn-sm"
                          onClick={() => handleDeleteCategory(cat)}
                          title="Sil"
                          style={{ color: 'var(--danger)' }}
                        >
                          <BsTrash />
                        </button>
                      </div>
                    </div>
                    
                    {cat.description && (
                      <p style={{ fontSize: '0.8125rem', color: 'var(--text-tertiary)', margin: '0.75rem 0 0', lineHeight: 1.4 }}>
                        {cat.description}
                      </p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </>
      )}

      {/* Modals */}
      <DocumentUpload
        isOpen={uploadOpen}
        onClose={() => setUploadOpen(false)}
        onSuccess={loadDocuments}
      />

      <CategoryForm
        isOpen={catFormOpen}
        onClose={() => setCatFormOpen(false)}
        onSuccess={loadCategories}
        category={selectedCategory}
      />

      {/* Edit Document Modal */}
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

      <ConfirmDialog
        isOpen={deleteDocDialogOpen}
        onClose={() => setDeleteDocDialogOpen(false)}
        onConfirm={confirmDeleteDocument}
        title="Evrak Sil"
        message={`"${docToDelete?.name}" evrakını silmek istediğinize emin misiniz?`}
        confirmText="Sil"
        variant="danger"
        loading={deleteDocLoading}
      />

      <ConfirmDialog
        isOpen={deleteCatDialogOpen}
        onClose={() => setDeleteCatDialogOpen(false)}
        onConfirm={confirmDeleteCategory}
        title="Kategori Sil"
        message={`"${catToDelete?.name}" kategorisini silmek istediğinize emin misiniz?`}
        confirmText="Sil"
        variant="danger"
        loading={deleteCatLoading}
      />
    </div>
  );
};

export default DocumentsPage;
