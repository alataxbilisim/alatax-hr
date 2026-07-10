import React from 'react';
import Modal from './Modal';
import { BsExclamationTriangle, BsTrash, BsQuestionCircle } from 'react-icons/bs';

interface ConfirmDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  type?: 'danger' | 'warning' | 'info';
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
  type = 'danger',
  loading = false,
}) => {
  const getIcon = () => {
    switch (type) {
      case 'danger':
        return <BsTrash size={32} />;
      case 'warning':
        return <BsExclamationTriangle size={32} />;
      default:
        return <BsQuestionCircle size={32} />;
    }
  };

  const getIconClass = () => {
    switch (type) {
      case 'danger':
        return 'text-danger';
      case 'warning':
        return 'text-warning';
      default:
        return 'text-primary';
    }
  };

  const getButtonClass = () => {
    switch (type) {
      case 'danger':
        return 'btn-danger';
      case 'warning':
        return 'btn-warning';
      default:
        return 'btn-primary';
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={title}
      size="sm"
      footer={
        <>
          <button
            type="button"
            className="btn btn-secondary"
            onClick={onClose}
            disabled={loading}
          >
            {cancelText}
          </button>
          <button
            type="button"
            className={`btn ${getButtonClass()}`}
            onClick={onConfirm}
            disabled={loading}
          >
            {loading ? 'İşleniyor...' : confirmText}
          </button>
        </>
      }
    >
      <div className="text-center">
        <div className={`mb-4 ${getIconClass()}`}>{getIcon()}</div>
        <p className="mb-0">{message}</p>
      </div>
    </Modal>
  );
};

export default ConfirmDialog;

