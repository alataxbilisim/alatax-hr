import React from 'react';

interface SkeletonProps {
  width?: string | number;
  height?: string | number;
  circle?: boolean;
  className?: string;
  style?: React.CSSProperties;
}

const Skeleton: React.FC<SkeletonProps> = ({
  width,
  height,
  circle = false,
  className = '',
  style = {},
}) => {
  const skeletonStyle: React.CSSProperties = {
    width: width || '100%',
    height: height || '1rem',
    borderRadius: circle ? '50%' : 'var(--radius-sm)',
    background: 'linear-gradient(90deg, var(--surface-glass) 25%, var(--surface-primary) 50%, var(--surface-glass) 75%)',
    backgroundSize: '200% 100%',
    animation: 'skeleton-loading 1.5s ease-in-out infinite',
    ...style,
  };

  return <div className={`skeleton ${className}`} style={skeletonStyle} />;
};

export default Skeleton;

