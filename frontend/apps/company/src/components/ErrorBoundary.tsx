import React, { Component, ErrorInfo, ReactNode } from 'react';
import { BsExclamationTriangle, BsArrowClockwise, BsHouseDoor } from 'react-icons/bs';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
  onError?: (error: Error, errorInfo: ErrorInfo) => void;
}

interface State {
  hasError: boolean;
  error: Error | null;
  errorInfo: ErrorInfo | null;
}

/**
 * Error Boundary Component
 * Catches JavaScript errors anywhere in the child component tree
 */
class ErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null,
    };
  }

  static getDerivedStateFromError(error: Error): Partial<State> {
    return {
      hasError: true,
      error,
    };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
    this.setState({ errorInfo });

    // Call custom error handler if provided
    this.props.onError?.(error, errorInfo);

    // Log error to console in development
    if (process.env.NODE_ENV === 'development') {
      console.error('Error caught by ErrorBoundary:', error);
      console.error('Component stack:', errorInfo.componentStack);
    }

    // In production, you might want to send this to an error tracking service
    // Example: Sentry.captureException(error, { extra: errorInfo });
  }

  handleReload = (): void => {
    window.location.reload();
  };

  handleGoHome = (): void => {
    window.location.href = '/dashboard';
  };

  handleRetry = (): void => {
    this.setState({
      hasError: false,
      error: null,
      errorInfo: null,
    });
  };

  render(): ReactNode {
    const { hasError, error, errorInfo } = this.state;
    const { children, fallback } = this.props;

    if (hasError) {
      // Custom fallback UI
      if (fallback) {
        return fallback;
      }

      // Default error UI
      return (
        <ErrorFallback
          error={error}
          errorInfo={errorInfo}
          onReload={this.handleReload}
          onGoHome={this.handleGoHome}
          onRetry={this.handleRetry}
        />
      );
    }

    return children;
  }
}

/**
 * Default Error Fallback UI
 */
interface ErrorFallbackProps {
  error: Error | null;
  errorInfo: ErrorInfo | null;
  onReload: () => void;
  onGoHome: () => void;
  onRetry: () => void;
}

const ErrorFallback: React.FC<ErrorFallbackProps> = ({
  error,
  errorInfo,
  onReload,
  onGoHome,
  onRetry,
}) => {
  const isDev = process.env.NODE_ENV === 'development';

  return (
    <div className="error-boundary">
      <div className="error-boundary-content">
        <div className="error-icon">
          <BsExclamationTriangle />
        </div>
        
        <h1>Bir Hata Oluştu</h1>
        <p className="error-message">
          Beklenmeyen bir hata oluştu. Lütfen sayfayı yenileyin veya ana sayfaya dönün.
        </p>

        {isDev && error && (
          <div className="error-details">
            <div className="error-name">{error.name}: {error.message}</div>
            {errorInfo && (
              <pre className="error-stack">
                {errorInfo.componentStack}
              </pre>
            )}
          </div>
        )}

        <div className="error-actions">
          <button className="btn btn-primary" onClick={onRetry}>
            <BsArrowClockwise /> Tekrar Dene
          </button>
          <button className="btn btn-secondary" onClick={onReload}>
            <BsArrowClockwise /> Sayfayı Yenile
          </button>
          <button className="btn btn-ghost" onClick={onGoHome}>
            <BsHouseDoor /> Ana Sayfaya Dön
          </button>
        </div>
      </div>

      <style>{`
        .error-boundary {
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 2rem;
          background: var(--bg-primary, #0f172a);
        }

        .error-boundary-content {
          text-align: center;
          max-width: 500px;
        }

        .error-icon {
          width: 80px;
          height: 80px;
          background: rgba(239, 68, 68, 0.1);
          color: #ef4444;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          margin: 0 auto 1.5rem;
          font-size: 2.5rem;
        }

        .error-boundary h1 {
          font-size: 1.5rem;
          font-weight: 600;
          color: var(--text-primary, #f8fafc);
          margin-bottom: 0.75rem;
        }

        .error-message {
          color: var(--text-secondary, #94a3b8);
          margin-bottom: 1.5rem;
        }

        .error-details {
          background: var(--bg-secondary, #1e293b);
          border: 1px solid var(--border-primary, rgba(255, 255, 255, 0.06));
          border-radius: 8px;
          padding: 1rem;
          margin-bottom: 1.5rem;
          text-align: left;
        }

        .error-name {
          font-family: monospace;
          color: #ef4444;
          font-size: 0.875rem;
          margin-bottom: 0.5rem;
        }

        .error-stack {
          font-family: monospace;
          font-size: 0.75rem;
          color: var(--text-tertiary, #64748b);
          overflow-x: auto;
          white-space: pre-wrap;
          word-break: break-word;
          max-height: 200px;
          margin: 0;
        }

        .error-actions {
          display: flex;
          flex-wrap: wrap;
          gap: 0.75rem;
          justify-content: center;
        }

        .error-actions .btn {
          display: inline-flex;
          align-items: center;
          gap: 0.5rem;
        }

        @media (max-width: 480px) {
          .error-actions {
            flex-direction: column;
          }

          .error-actions .btn {
            width: 100%;
            justify-content: center;
          }
        }
      `}</style>
    </div>
  );
};

export default ErrorBoundary;

