import React, { useState, useEffect, useCallback, useRef } from 'react';
import toast from 'react-hot-toast';
import api from '../../services/api';

interface Document {
  id: number;
  name: string;
  file_name: string;
  file_path: string;
  file_size: number;
  file_type: string;
  category: Category | null;
  category_id: number | null;
  description: string | null;
  version: number;
  uploaded_by: { id: number; name: string } | null;
  created_at: string;
}

interface Category {
  id: number;
  name: string;
  slug: string;
  icon: string | null;
  color: string | null;
  documents_count: number;
}

const DocumentsPage: React.FC = () => {
  const [documents, setDocuments] = useState<Document[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [showCategoryModal, setShowCategoryModal] = useState(false);
  const [newCategoryName, setNewCategoryName] = useState('');
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [uploadData, setUploadData] = useState({
    name: '',
    description: '',
    category_id: '',
    file: null as File | null,
  });

  const loadDocuments = useCallback(async () => {
    try {
      setLoading(true);
      const params: Record<string, string | number> = {};
      if (searchQuery) params.search = searchQuery;
      if (selectedCategory) params.category_id = selectedCategory;
      
      const response = await api.get('/api/v1/documents', { params });
      setDocuments(response.data.data || []);
    } catch (error) {
      console.error('Dokümanlar yüklenemedi:', error);
    } finally {
      setLoading(false);
    }
  }, [searchQuery, selectedCategory]);

  const loadCategories = useCallback(async () => {
    try {
      const response = await api.get('/api/v1/documents/categories');
      setCategories(response.data.data || []);
    } catch (error) {
      console.error('Kategoriler yüklenemedi:', error);
    }
  }, []);

  useEffect(() => {
    loadDocuments();
    loadCategories();
  }, [loadDocuments, loadCategories]);

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setUploadData({
        ...uploadData,
        file,
        name: uploadData.name || file.name.replace(/\.[^/.]+$/, ''),
      });
    }
  };

  const handleUpload = async () => {
    if (!uploadData.file) {
      toast.error('Lütfen bir dosya seçin');
      return;
    }

    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('file', uploadData.file);
      formData.append('name', uploadData.name || uploadData.file.name);
      if (uploadData.description) formData.append('description', uploadData.description);
      if (uploadData.category_id) formData.append('category_id', uploadData.category_id);

      await api.post('/api/v1/documents', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      toast.success('Dosya yüklendi');
      setShowUploadModal(false);
      setUploadData({ name: '', description: '', category_id: '', file: null });
      loadDocuments();
    } catch (error) {
      console.error('Yükleme hatası:', error);
      toast.error('Dosya yüklenemedi');
    } finally {
      setUploading(false);
    }
  };

  const handleCreateCategory = async () => {
    if (!newCategoryName.trim()) return;
    
    try {
      await api.post('/api/v1/documents/categories', { name: newCategoryName.trim() });
      toast.success('Kategori oluşturuldu');
      setShowCategoryModal(false);
      setNewCategoryName('');
      loadCategories();
    } catch (error) {
      toast.error('Kategori oluşturulamadı');
    }
  };

  const handleDeleteDocument = async (doc: Document) => {
    if (!confirm(`"${doc.name}" dosyasını silmek istediğinize emin misiniz?`)) return;
    
    try {
      await api.delete(`/api/v1/documents/${doc.id}`);
      toast.success('Dosya silindi');
      loadDocuments();
    } catch (error) {
      toast.error('Dosya silinemedi');
    }
  };

  const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  };

  const getFileIcon = (type: string) => {
    if (type.includes('pdf')) return 'bi-file-pdf text-red-500';
    if (type.includes('word') || type.includes('doc')) return 'bi-file-word text-blue-500';
    if (type.includes('excel') || type.includes('sheet')) return 'bi-file-excel text-green-500';
    if (type.includes('image')) return 'bi-file-image text-purple-500';
    return 'bi-file-earmark text-gray-500';
  };

  const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('tr-TR', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Evrak Yönetimi</h1>
          <p className="page-subtitle">{documents.length} doküman</p>
        </div>
        <div className="flex gap-2">
          <button className="btn btn-outline-secondary" onClick={() => setShowCategoryModal(true)}>
            <i className="bi bi-folder-plus me-2"></i>
            Kategori
          </button>
          <button className="btn btn-primary" onClick={() => setShowUploadModal(true)}>
            <i className="bi bi-upload me-2"></i>
            Yükle
          </button>
        </div>
      </div>

      <div className="row">
        {/* Sidebar - Categories */}
        <div className="col-md-3">
          <div className="card mb-4">
            <div className="card-header">
              <h3 className="card-title text-sm">Kategoriler</h3>
            </div>
            <div className="card-body p-0">
              <div className="list-group list-group-flush">
                <button
                  className={`list-group-item list-group-item-action d-flex justify-content-between align-items-center ${
                    selectedCategory === null ? 'active' : ''
                  }`}
                  onClick={() => setSelectedCategory(null)}
                >
                  <span>
                    <i className="bi bi-folder me-2"></i>
                    Tümü
                  </span>
                  <span className="badge bg-secondary">{documents.length}</span>
                </button>
                {categories.map((cat) => (
                  <button
                    key={cat.id}
                    className={`list-group-item list-group-item-action d-flex justify-content-between align-items-center ${
                      selectedCategory === cat.id ? 'active' : ''
                    }`}
                    onClick={() => setSelectedCategory(cat.id)}
                  >
                    <span>
                      <i className={`bi bi-${cat.icon || 'folder'} me-2`}></i>
                      {cat.name}
                    </span>
                    <span className="badge bg-secondary">{cat.documents_count}</span>
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Main - Documents */}
        <div className="col-md-9">
          {/* Search */}
          <div className="card mb-4">
            <div className="card-body">
              <div className="input-icon">
                <i className="bi bi-search"></i>
                <input
                  type="text"
                  className="form-control"
                  placeholder="Dosya adı ile ara..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
            </div>
          </div>

          {/* Documents Grid */}
          {loading ? (
            <div className="text-center py-8">
              <div className="spinner-border text-primary" role="status">
                <span className="visually-hidden">Yükleniyor...</span>
              </div>
            </div>
          ) : documents.length === 0 ? (
            <div className="card">
              <div className="card-body text-center py-12">
                <div className="text-6xl mb-4">📁</div>
                <h3 className="text-xl font-semibold mb-2">Henüz doküman yok</h3>
                <p className="text-[var(--text-secondary)] mb-4">
                  Dosyalarınızı yükleyerek başlayın
                </p>
                <button className="btn btn-primary" onClick={() => setShowUploadModal(true)}>
                  <i className="bi bi-upload me-2"></i>
                  İlk Dosyayı Yükle
                </button>
              </div>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {documents.map((doc) => (
                <div key={doc.id} className="card">
                  <div className="card-body">
                    <div className="flex items-start gap-3">
                      <div className="w-12 h-12 rounded-lg bg-[var(--surface-secondary)] flex items-center justify-center text-2xl">
                        <i className={`bi ${getFileIcon(doc.file_type)}`}></i>
                      </div>
                      <div className="flex-1 min-w-0">
                        <h4 className="font-semibold truncate">{doc.name}</h4>
                        <p className="text-sm text-[var(--text-secondary)] truncate">
                          {doc.file_name}
                        </p>
                        <div className="flex items-center gap-3 mt-2 text-xs text-[var(--text-muted)]">
                          <span>{formatFileSize(doc.file_size)}</span>
                          <span>v{doc.version}</span>
                          <span>{formatDate(doc.created_at)}</span>
                        </div>
                        {doc.category && (
                          <span className="badge badge-info mt-2">{doc.category.name}</span>
                        )}
                      </div>
                      <div className="dropdown">
                        <button className="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                          <i className="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul className="dropdown-menu dropdown-menu-end">
                          <li>
                            <a
                              className="dropdown-item"
                              href={doc.file_path}
                              target="_blank"
                              rel="noopener noreferrer"
                            >
                              <i className="bi bi-download me-2"></i>İndir
                            </a>
                          </li>
                          <li><hr className="dropdown-divider" /></li>
                          <li>
                            <button
                              className="dropdown-item text-danger"
                              onClick={() => handleDeleteDocument(doc)}
                            >
                              <i className="bi bi-trash me-2"></i>Sil
                            </button>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Upload Modal */}
      {showUploadModal && (
        <div className="modal-backdrop show" onClick={() => setShowUploadModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">Dosya Yükle</h5>
                  <button type="button" className="btn-close" onClick={() => setShowUploadModal(false)}></button>
                </div>
                <div className="modal-body">
                  <div
                    className="border-2 border-dashed border-[var(--border-primary)] rounded-lg p-8 text-center cursor-pointer hover:bg-[var(--surface-secondary)] transition-colors"
                    onClick={() => fileInputRef.current?.click()}
                  >
                    <input
                      type="file"
                      ref={fileInputRef}
                      className="hidden"
                      onChange={handleFileSelect}
                      accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                    />
                    {uploadData.file ? (
                      <div>
                        <i className="bi bi-file-check text-4xl text-[var(--primary)]"></i>
                        <p className="mt-2 font-semibold">{uploadData.file.name}</p>
                        <p className="text-sm text-[var(--text-muted)]">
                          {formatFileSize(uploadData.file.size)}
                        </p>
                      </div>
                    ) : (
                      <div>
                        <i className="bi bi-cloud-upload text-4xl text-[var(--text-secondary)]"></i>
                        <p className="mt-2">Dosya seçmek için tıklayın</p>
                        <p className="text-sm text-[var(--text-muted)]">
                          PDF, Word, Excel, Resim (max 10MB)
                        </p>
                      </div>
                    )}
                  </div>

                  <div className="mt-4 space-y-3">
                    <div>
                      <label className="form-label">Dosya Adı</label>
                      <input
                        type="text"
                        className="form-control"
                        value={uploadData.name}
                        onChange={(e) => setUploadData({ ...uploadData, name: e.target.value })}
                        placeholder="Dosya için bir isim girin"
                      />
                    </div>
                    <div>
                      <label className="form-label">Kategori</label>
                      <select
                        className="form-select"
                        value={uploadData.category_id}
                        onChange={(e) => setUploadData({ ...uploadData, category_id: e.target.value })}
                      >
                        <option value="">Kategori Seçin</option>
                        {categories.map((cat) => (
                          <option key={cat.id} value={cat.id}>
                            {cat.name}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="form-label">Açıklama</label>
                      <textarea
                        className="form-control"
                        rows={2}
                        value={uploadData.description}
                        onChange={(e) => setUploadData({ ...uploadData, description: e.target.value })}
                        placeholder="İsteğe bağlı açıklama"
                      />
                    </div>
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowUploadModal(false)}>
                    İptal
                  </button>
                  <button
                    type="button"
                    className="btn btn-primary"
                    onClick={handleUpload}
                    disabled={!uploadData.file || uploading}
                  >
                    {uploading ? (
                      <>
                        <span className="spinner-border spinner-border-sm me-2"></span>
                        Yükleniyor...
                      </>
                    ) : (
                      <>
                        <i className="bi bi-upload me-2"></i>
                        Yükle
                      </>
                    )}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Category Modal */}
      {showCategoryModal && (
        <div className="modal-backdrop show" onClick={() => setShowCategoryModal(false)}>
          <div className="modal show d-block" tabIndex={-1}>
            <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
              <div className="modal-content">
                <div className="modal-header">
                  <h5 className="modal-title">Yeni Kategori</h5>
                  <button type="button" className="btn-close" onClick={() => setShowCategoryModal(false)}></button>
                </div>
                <div className="modal-body">
                  <input
                    type="text"
                    className="form-control"
                    placeholder="Kategori adı"
                    value={newCategoryName}
                    onChange={(e) => setNewCategoryName(e.target.value)}
                    onKeyPress={(e) => e.key === 'Enter' && handleCreateCategory()}
                  />
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowCategoryModal(false)}>
                    İptal
                  </button>
                  <button
                    type="button"
                    className="btn btn-primary"
                    onClick={handleCreateCategory}
                    disabled={!newCategoryName.trim()}
                  >
                    <i className="bi bi-plus-lg me-2"></i>
                    Oluştur
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      <style>{`
        .modal-backdrop.show {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.5);
          backdrop-filter: blur(4px);
          z-index: 1050;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .modal.show { position: relative; z-index: 1051; }
        .modal-dialog { margin: 0; }
        .modal-content {
          background: var(--surface-primary);
          border: 1px solid var(--border-primary);
          border-radius: 12px;
        }
        .modal-header { border-bottom: 1px solid var(--border-primary); }
        .modal-footer { border-top: 1px solid var(--border-primary); }
        .list-group-item {
          background: var(--surface-primary);
          border-color: var(--border-primary);
          color: var(--text-primary);
        }
        .list-group-item:hover {
          background: var(--surface-secondary);
        }
        .list-group-item.active {
          background: var(--primary);
          border-color: var(--primary);
        }
        .dropdown-menu {
          background: var(--surface-primary);
          border-color: var(--border-primary);
        }
        .dropdown-item { color: var(--text-primary); }
        .dropdown-item:hover { background: var(--surface-secondary); }
      `}</style>
    </div>
  );
};

export default DocumentsPage;

