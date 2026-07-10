import React, { useState, useRef } from 'react';
import { BsUpload, BsTrash, BsPencil } from 'react-icons/bs';
import toast from 'react-hot-toast';

interface AvatarUploadProps {
  currentAvatarUrl?: string | null;
  onUpload: (file: File) => Promise<void>;
  onDelete: () => Promise<void>;
  userId: number;
  userName: string;
}

const AvatarUpload: React.FC<AvatarUploadProps> = ({
  currentAvatarUrl,
  onUpload,
  onDelete,
  userName,
}) => {
  const [previewUrl, setPreviewUrl] = useState<string | null>(currentAvatarUrl || null);
  const [uploading, setUploading] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    // Dosya tipi kontrolü
    if (!file.type.startsWith('image/')) {
      toast.error('Lütfen bir resim dosyası seçin');
      return;
    }

    // Dosya boyutu kontrolü (2MB)
    if (file.size > 2 * 1024 * 1024) {
      toast.error('Dosya boyutu 2MB\'dan küçük olmalıdır');
      return;
    }

    // Preview oluştur
    const reader = new FileReader();
    reader.onloadend = () => {
      setPreviewUrl(reader.result as string);
    };
    reader.readAsDataURL(file);

    // Upload
    try {
      setUploading(true);
      await onUpload(file);
      toast.success('Profil fotoğrafı başarıyla yüklendi');
    } catch {
      toast.error('Profil fotoğrafı yüklenemedi');
      setPreviewUrl(currentAvatarUrl || null);
    } finally {
      setUploading(false);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  const handleDeleteAvatar = async () => {
    if (!confirm('Profil fotoğrafını silmek istediğinize emin misiniz?')) {
      return;
    }

    try {
      setDeleting(true);
      await onDelete();
      setPreviewUrl(null);
      toast.success('Profil fotoğrafı silindi');
    } catch {
      toast.error('Profil fotoğrafı silinemedi');
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div className="avatar-upload-container">
      <div style={{ position: 'relative', display: 'inline-block' }}>
        <div
          style={{
            width: 120,
            height: 120,
            borderRadius: '50%',
            background: previewUrl ? `url(${previewUrl})` : 'var(--gradient-primary)',
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            color: previewUrl ? 'transparent' : 'white',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: '3rem',
            fontWeight: 600,
            border: '3px solid var(--border-primary)',
            cursor: 'pointer',
            transition: 'all 0.2s',
          }}
          onClick={() => fileInputRef.current?.click()}
          title="Fotoğraf değiştir"
        >
          {!previewUrl && userName.charAt(0).toUpperCase()}
        </div>
        <div
          style={{
            position: 'absolute',
            bottom: 0,
            right: 0,
            width: 36,
            height: 36,
            borderRadius: '50%',
            background: 'var(--primary)',
            color: 'white',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            cursor: 'pointer',
            border: '2px solid var(--bg-primary)',
            boxShadow: '0 2px 8px rgba(0,0,0,0.15)',
          }}
          onClick={() => fileInputRef.current?.click()}
          title="Fotoğraf değiştir"
        >
          <BsPencil size={16} />
        </div>
      </div>

      <input
        ref={fileInputRef}
        type="file"
        accept="image/jpeg,image/jpg,image/png"
        onChange={handleFileChange}
        style={{ display: 'none' }}
        disabled={uploading || deleting}
      />

      <div style={{ display: 'flex', gap: '0.5rem', marginTop: '1rem' }}>
        <button
          className="btn btn-ghost btn-sm"
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
              <BsUpload size={14} /> {previewUrl ? 'Değiştir' : 'Yükle'}
            </>
          )}
        </button>
        {previewUrl && (
          <button
            className="btn btn-ghost btn-sm"
            onClick={handleDeleteAvatar}
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
                <BsTrash size={14} /> Sil
              </>
            )}
          </button>
        )}
      </div>

      <div style={{ marginTop: '0.5rem', fontSize: '0.75rem', color: 'var(--text-tertiary)' }}>
        JPG, PNG (Max: 2MB)
      </div>
    </div>
  );
};

export default AvatarUpload;

