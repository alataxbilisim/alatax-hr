import React from 'react';
import Modal from './Modal';
import { BsExclamationTriangle } from 'react-icons/bs';

interface ConfirmDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  variant?: 'danger' | 'warning' | 'info';
  loading?: boolean;
}

const ConfirmDialog: React.FC<ConfirmDialogProps> = ({
  isOpen,
  onClose,
  onConfirm,
  title,
  message,
  confirmText = 'Onayla',
  cancelText = 'İptal',
  variant = 'danger',
  loading = false,
}) => {
  const variantColors = {
    danger: 'var(--danger)',
    warning: 'var(--warning)',
    info: 'var(--info)',
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={title}
      size="sm"
      footer={
        <>
          <button className="btn btn-secondary" onClick={onClose} disabled={loading}>
            {cancelText}
          </button>
          <button
            className={`btn btn-${variant}`}
            onClick={onConfirm}
            disabled={loading}
          >
            {loading ? (
              <>
                <span className="loading-spinner" style={{ width: 14, height: 14, borderWidth: 2 }} />
                İşleniyor...
              </>
            ) : (
              confirmText
            )}
          </button>
        </>
      }
    >
      <div style={{ textAlign: 'center', padding: '1rem 0' }}>
        <div
          style={{
            width: 56,
            height: 56,
            borderRadius: '50%',
            background: `${variantColors[variant]}15`,
            color: variantColors[variant],
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            margin: '0 auto 1rem',
          }}
        >
          <BsExclamationTriangle size={28} />
        </div>
        <p style={{ color: 'var(--text-secondary)', margin: 0, fontSize: '0.875rem' }}>
          {message}
        </p>
      </div>
    </Modal>
  );
};

export default ConfirmDialog;

