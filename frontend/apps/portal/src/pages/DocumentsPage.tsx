import React, { useEffect, useState } from 'react';
import { portalApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsFileEarmarkText, BsDownload } from 'react-icons/bs';

interface Document {
  id: number;
  title: string;
  category: string;
  category_label: string;
  file_name: string;
  file_size_formatted: string;
  issue_date: string | null;
  expiry_date: string | null;
  is_expired: boolean;
  created_at: string;
}

const DocumentsPage: React.FC = () => {
  const [documents, setDocuments] = useState<Document[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadDocuments();
  }, []);

  const loadDocuments = async () => {
    try {
      const response = await portalApi.documents.list();
      setDocuments(response.data.data.data || []);
    } catch {
      toast.error('Belgeler yüklenemedi');
    } finally {
      setLoading(false);
    }
  };

  const handleDownload = async (doc: Document) => {
    try {
      const response = await portalApi.documents.download(doc.id);
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', doc.file_name);
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch {
      toast.error('Belge indirilemedi');
    }
  };

  return (
    <div className="animate-fade-in">
      <div className="page-header">
        <div className="page-header-content">
          <h1 className="page-title">Belgelerim</h1>
          <p className="page-subtitle">Sizinle ilgili belgeleri görüntüleyin</p>
        </div>
      </div>

      <div className="card">
        <div className="card-body p-0">
          {loading ? (
            <div className="page-loading">
              <div className="loading-spinner"></div>
            </div>
          ) : documents.length > 0 ? (
            <div className="table-responsive">
              <table className="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Belge</th>
                    <th>Kategori</th>
                    <th>Boyut</th>
                    <th>Geçerlilik</th>
                    <th>İşlemler</th>
                  </tr>
                </thead>
                <tbody>
                  {documents.map((doc) => (
                    <tr key={doc.id}>
                      <td>
                        <div className="d-flex align-items-center gap-2">
                          <BsFileEarmarkText className="text-primary" />
                          <div>
                            <div className="fw-semibold">{doc.title}</div>
                            <div className="text-muted small">{doc.file_name}</div>
                          </div>
                        </div>
                      </td>
                      <td>{doc.category_label}</td>
                      <td>{doc.file_size_formatted}</td>
                      <td>
                        {doc.expiry_date ? (
                          <span className={doc.is_expired ? 'text-danger' : ''}>
                            {new Date(doc.expiry_date).toLocaleDateString('tr-TR')}
                            {doc.is_expired && ' (Süresi doldu)'}
                          </span>
                        ) : (
                          '-'
                        )}
                      </td>
                      <td>
                        <button
                          className="btn btn-sm btn-ghost"
                          onClick={() => handleDownload(doc)}
                          title="İndir"
                        >
                          <BsDownload />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="empty-state">
              <BsFileEarmarkText size={64} className="text-muted mb-3" />
              <h3>Henüz belge yok</h3>
              <p>İK tarafından yüklenen belgeler burada görünecektir</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default DocumentsPage;

