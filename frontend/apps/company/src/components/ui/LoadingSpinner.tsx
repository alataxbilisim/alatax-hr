import React from 'react';

interface LoadingSpinnerProps {
  size?: 'sm' | 'md' | 'lg';
  text?: string;
  fullPage?: boolean;
  overlay?: boolean;
  className?: string;
}

const sizeClasses = {
  sm: { spinner: 24, text: '0.75rem' },
  md: { spinner: 32, text: '0.875rem' },
  lg: { spinner: 48, text: '1rem' },
};

/**
 * Reusable Loading Spinner Component
 */
const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({
  size = 'md',
  text,
  fullPage = false,
  overlay = false,
  className = '',
}) => {
  const { spinner: spinnerSize, text: textSize } = sizeClasses[size];

  const content = (
    <div className={`loading-spinner-container ${className}`}>
      <div 
        className="loading-spinner" 
        style={{ width: spinnerSize, height: spinnerSize }}
      />
      {text && (
        <p 
          className="loading-text" 
          style={{ fontSize: textSize }}
        >
          {text}
        </p>
      )}
    </div>
  );

  if (fullPage) {
    return (
      <div className="loading-screen">
        {content}
      </div>
    );
  }

  if (overlay) {
    return (
      <div className="loading-overlay">
        {content}
      </div>
    );
  }

  return content;
};

/**
 * Page Loading Component
 * For use within page content areas
 */
export const PageLoading: React.FC<{ text?: string }> = ({ text = 'Yükleniyor...' }) => (
  <div className="page-loading">
    <LoadingSpinner size="md" text={text} />
  </div>
);

/**
 * Inline Loading Component
 * For use within buttons or small areas
 */
export const InlineLoading: React.FC<{ className?: string }> = ({ className }) => (
  <span className={`inline-loading ${className || ''}`}>
    <LoadingSpinner size="sm" />
  </span>
);

/**
 * Button Loading Component
 * For use within buttons
 */
export const ButtonLoading: React.FC = () => (
  <span className="button-loading">
    <LoadingSpinner size="sm" />
  </span>
);

/**
 * Skeleton Loading Components
 */
export const Skeleton: React.FC<{
  width?: string | number;
  height?: string | number;
  borderRadius?: string | number;
  className?: string;
}> = ({ width = '100%', height = 20, borderRadius = 4, className = '' }) => (
  <div 
    className={`skeleton ${className}`}
    style={{ 
      width: typeof width === 'number' ? `${width}px` : width,
      height: typeof height === 'number' ? `${height}px` : height,
      borderRadius: typeof borderRadius === 'number' ? `${borderRadius}px` : borderRadius,
    }}
  />
);

export const SkeletonText: React.FC<{ lines?: number; className?: string }> = ({ 
  lines = 3, 
  className = '' 
}) => (
  <div className={`skeleton-text ${className}`}>
    {Array.from({ length: lines }).map((_, i) => (
      <Skeleton 
        key={i} 
        height={16} 
        width={i === lines - 1 ? '70%' : '100%'} 
        className="skeleton-line"
      />
    ))}
  </div>
);

export const SkeletonCard: React.FC<{ className?: string }> = ({ className = '' }) => (
  <div className={`skeleton-card card ${className}`}>
    <div className="card-body">
      <Skeleton height={24} width="60%" className="mb-3" />
      <SkeletonText lines={2} />
    </div>
  </div>
);

export const SkeletonTable: React.FC<{ rows?: number; cols?: number; className?: string }> = ({ 
  rows = 5, 
  cols = 4,
  className = '' 
}) => (
  <div className={`skeleton-table ${className}`}>
    <div className="skeleton-table-header">
      {Array.from({ length: cols }).map((_, i) => (
        <Skeleton key={i} height={16} width="80%" />
      ))}
    </div>
    {Array.from({ length: rows }).map((_, rowIndex) => (
      <div key={rowIndex} className="skeleton-table-row">
        {Array.from({ length: cols }).map((_, colIndex) => (
          <Skeleton key={colIndex} height={14} width={colIndex === 0 ? '60%' : '80%'} />
        ))}
      </div>
    ))}
  </div>
);

export default LoadingSpinner;

// Add styles
const styles = `
.loading-spinner-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
}

.loading-text {
  color: var(--text-secondary);
  margin: 0;
}

.loading-overlay {
  position: absolute;
  inset: 0;
  background: rgba(var(--bg-primary-rgb, 15, 23, 42), 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
  backdrop-filter: blur(2px);
}

.inline-loading {
  display: inline-flex;
  align-items: center;
  margin-left: 0.5rem;
}

.button-loading {
  display: inline-flex;
  align-items: center;
  margin-right: 0.5rem;
}

.skeleton-line {
  margin-bottom: 0.5rem;
}

.skeleton-line:last-child {
  margin-bottom: 0;
}

.skeleton-table-header,
.skeleton-table-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
  gap: 1rem;
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--border-primary);
}

.skeleton-table-header {
  padding-top: 0;
}

.skeleton-table-row:last-child {
  border-bottom: none;
}
`;

// Inject styles
if (typeof document !== 'undefined') {
  const styleElement = document.createElement('style');
  styleElement.textContent = styles;
  if (!document.querySelector('[data-loading-spinner-styles]')) {
    styleElement.setAttribute('data-loading-spinner-styles', '');
    document.head.appendChild(styleElement);
  }
}

