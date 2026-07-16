import React from 'react';

export interface SkeletonLoaderProps {
  height?: number | string;
  width?: number | string;
  count?: number;
  className?: string;
}

const SkeletonLoader: React.FC<SkeletonLoaderProps> = ({
  height = 16,
  width = '100%',
  count = 1,
  className = '',
}) => (
  <>
    {Array.from({ length: count }, (_, i) => (
      <div
        key={i}
        className={`portal-skeleton ${className}`.trim()}
        style={{
          height: typeof height === 'number' ? `${height}px` : height,
          width: typeof width === 'number' ? `${width}px` : width,
          marginBottom: i < count - 1 ? 'var(--sp-2)' : undefined,
        }}
      />
    ))}
  </>
);

export default SkeletonLoader;
