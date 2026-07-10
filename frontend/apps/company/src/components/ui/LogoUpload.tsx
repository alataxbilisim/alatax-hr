import React, { useState, useRef } from 'react';
import { companyApi } from '@shared/services/api';
import toast from 'react-hot-toast';
import { BsUpload, BsTrash, BsImage } from 'react-icons/bs';

interface LogoUploadProps {
  currentLogo?: string | null;
  onUploadSuccess?: (logoUrl: string) => void;
  onDeleteSuccess?: () => void;
}

const LogoUpload: React.FC<LogoUploadProps> = ({
  currentLogo,
  onUploadSuccess,
  onDeleteSuccess,
}) => {
  const [uploading, setUploading] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [preview, setPreview] = useState<string | null>(currentLogo || null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Validate file type
    if (!file.type.startsWith('image/')) {
      toast.error('Lütfen bir resim dosyası seçin');
      return;
    }

    // Validate file size (2MB)
    if (file.size > 2 * 1024 * 1024) {
      toast.error('Dosya boyutu 2MB\'dan küçük olmalıdır');
      return;
    }

    // Create preview
    const reader = new FileReader();
    reader.onloadend = () => {
      setPreview(reader.result as string);
    };
    reader.readAsDataURL(file);

    // Upload file
    handleUpload(file);
  };

  const handleUpload = async (file: File) => {
    try {
      setUploading(true);
      const response = await companyApi.uploadLogo(file);
      const logoUrl = response.data.data.logo;
      setPreview(logoUrl);
      toast.success('Logo başarıyla yüklendi');
      onUploadSuccess?.(logoUrl);
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      toast.error(err?.response?.data?.message || 'Logo yüklenemedi');
      setPreview(currentLogo || null);
    } finally {
      setUploading(false);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  const handleDelete = async () => {
    if (!confirm('Logoyu silmek istediğinize emin misiniz?')) {
      return;
    }

    try {
      setDeleting(true);
      await companyApi.deleteLogo();
      setPreview(null);
      toast.success('Logo silindi');
      onDeleteSuccess?.();
    } catch {
      toast.error('Logo silinemedi');
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
      {/* Preview */}
      <div
        style={{
          width: '100%',
          maxWidth: 200,
          aspectRatio: '1',
          border: '2px dashed var(--border-primary)',
          borderRadius: 'var(--radius-md)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          background: preview ? 'transparent' : 'var(--surface-glass)',
          overflow: 'hidden',
          position: 'relative',
        }}
      >
        {preview ? (
          <img
            src={preview}
            alt="Company Logo"
            style={{
              width: '100%',
              height: '100%',
              objectFit: 'contain',
            }}
          />
        ) : (
          <div style={{ textAlign: 'center', color: 'var(--text-tertiary)' }}>
            <BsImage size={48} style={{ marginBottom: '0.5rem', opacity: 0.5 }} />
            <div style={{ fontSize: '0.875rem' }}>Logo yok</div>
          </div>
        )}
      </div>

      {/* Actions */}
      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
        <input
          ref={fileInputRef}
          type="file"
          accept="image/jpeg,image/jpg,image/png"
          onChange={handleFileSelect}
          style={{ display: 'none' }}
        />
        <button
          className="btn btn-primary btn-sm"
          onClick={() => fileInputRef.current?.click()}
          disabled={uploading || deleting}
        >
          {uploading ? (
            <>
              <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
              Yükleniyor...
            </>
          ) : (
            <>
              <BsUpload /> {preview ? 'Değiştir' : 'Logo Yükle'}
            </>
          )}
        </button>
        {preview && (
          <button
            className="btn btn-ghost btn-sm"
            onClick={handleDelete}
            disabled={uploading || deleting}
            style={{ color: 'var(--danger)' }}
          >
            {deleting ? (
              <>
                <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                Siliniyor...
              </>
            ) : (
              <>
                <BsTrash /> Sil
              </>
            )}
          </button>
        )}
      </div>

      <div style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
        JPG, PNG formatında, maksimum 2MB. Önerilen boyut: 800x800px
      </div>
    </div>
  );
};

export default LogoUpload;

