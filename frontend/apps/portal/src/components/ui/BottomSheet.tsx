import React, { useEffect } from 'react';

export interface BottomSheetProps {
  open: boolean;
  onClose: () => void;
  title?: string;
  children: React.ReactNode;
}

const BottomSheet: React.FC<BottomSheetProps> = ({ open, onClose, title, children }) => {
  useEffect(() => {
    if (!open) return undefined;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = prev;
    };
  }, [open]);

  if (!open) return null;

  return (
    <>
      <button
        type="button"
        className="portal-sheet-backdrop"
        aria-label="Close"
        onClick={onClose}
      />
      <div className="portal-sheet" role="dialog" aria-modal="true">
        <div className="portal-sheet__handle" />
        {title && <h2 className="portal-sheet__title">{title}</h2>}
        {children}
      </div>
    </>
  );
};

export default BottomSheet;
