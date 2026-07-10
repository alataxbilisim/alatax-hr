/**
 * Loading State Hook
 * Async operations için loading state yönetimi
 */

import { useState, useCallback, useRef, useEffect } from 'react';

export interface UseLoadingReturn {
  isLoading: boolean;
  error: Error | null;
  
  // Actions
  startLoading: () => void;
  stopLoading: () => void;
  setError: (error: Error | null) => void;
  reset: () => void;
  
  // Wrapper for async functions
  withLoading: <T>(fn: () => Promise<T>) => Promise<T>;
}

/**
 * Basic loading state hook
 */
export function useLoading(initialLoading = false): UseLoadingReturn {
  const [isLoading, setIsLoading] = useState(initialLoading);
  const [error, setErrorState] = useState<Error | null>(null);
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  const startLoading = useCallback(() => {
    if (mountedRef.current) {
      setIsLoading(true);
      setErrorState(null);
    }
  }, []);

  const stopLoading = useCallback(() => {
    if (mountedRef.current) {
      setIsLoading(false);
    }
  }, []);

  const setError = useCallback((error: Error | null) => {
    if (mountedRef.current) {
      setErrorState(error);
      setIsLoading(false);
    }
  }, []);

  const reset = useCallback(() => {
    if (mountedRef.current) {
      setIsLoading(false);
      setErrorState(null);
    }
  }, []);

  const withLoading = useCallback(async <T>(fn: () => Promise<T>): Promise<T> => {
    startLoading();
    try {
      const result = await fn();
      stopLoading();
      return result;
    } catch (err) {
      setError(err instanceof Error ? err : new Error(String(err)));
      throw err;
    }
  }, [startLoading, stopLoading, setError]);

  return {
    isLoading,
    error,
    startLoading,
    stopLoading,
    setError,
    reset,
    withLoading,
  };
}

// ============================================
// MULTIPLE LOADING STATES
// ============================================

export interface UseMultiLoadingReturn {
  isLoading: (key: string) => boolean;
  isAnyLoading: boolean;
  loadingKeys: string[];
  
  startLoading: (key: string) => void;
  stopLoading: (key: string) => void;
  reset: () => void;
  
  withLoading: <T>(key: string, fn: () => Promise<T>) => Promise<T>;
}

/**
 * Multiple loading states hook
 * Useful when you have multiple async operations
 */
export function useMultiLoading(): UseMultiLoadingReturn {
  const [loadingStates, setLoadingStates] = useState<Set<string>>(new Set());
  const mountedRef = useRef(true);

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  const startLoading = useCallback((key: string) => {
    if (mountedRef.current) {
      setLoadingStates(prev => new Set(prev).add(key));
    }
  }, []);

  const stopLoading = useCallback((key: string) => {
    if (mountedRef.current) {
      setLoadingStates(prev => {
        const newSet = new Set(prev);
        newSet.delete(key);
        return newSet;
      });
    }
  }, []);

  const reset = useCallback(() => {
    if (mountedRef.current) {
      setLoadingStates(new Set());
    }
  }, []);

  const isLoading = useCallback((key: string): boolean => {
    return loadingStates.has(key);
  }, [loadingStates]);

  const withLoading = useCallback(async <T>(key: string, fn: () => Promise<T>): Promise<T> => {
    startLoading(key);
    try {
      const result = await fn();
      stopLoading(key);
      return result;
    } catch (err) {
      stopLoading(key);
      throw err;
    }
  }, [startLoading, stopLoading]);

  return {
    isLoading,
    isAnyLoading: loadingStates.size > 0,
    loadingKeys: Array.from(loadingStates),
    startLoading,
    stopLoading,
    reset,
    withLoading,
  };
}

// ============================================
// ASYNC DATA HOOK
// ============================================

export interface UseAsyncDataOptions<T> {
  initialData?: T;
  onSuccess?: (data: T) => void;
  onError?: (error: Error) => void;
  autoFetch?: boolean;
}

export interface UseAsyncDataReturn<T> {
  data: T | undefined;
  isLoading: boolean;
  error: Error | null;
  
  fetch: () => Promise<void>;
  refetch: () => Promise<void>;
  setData: (data: T | undefined) => void;
  reset: () => void;
}

/**
 * Async data fetching hook
 * Useful for data loading patterns
 */
export function useAsyncData<T>(
  fetchFn: () => Promise<T>,
  options: UseAsyncDataOptions<T> = {}
): UseAsyncDataReturn<T> {
  const { initialData, onSuccess, onError, autoFetch = true } = options;

  const [data, setData] = useState<T | undefined>(initialData);
  const [isLoading, setIsLoading] = useState(autoFetch);
  const [error, setError] = useState<Error | null>(null);
  const mountedRef = useRef(true);
  const fetchRef = useRef(fetchFn);

  // Update fetchRef when fetchFn changes
  useEffect(() => {
    fetchRef.current = fetchFn;
  }, [fetchFn]);

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  const fetch = useCallback(async () => {
    if (!mountedRef.current) return;

    setIsLoading(true);
    setError(null);

    try {
      const result = await fetchRef.current();
      if (mountedRef.current) {
        setData(result);
        onSuccess?.(result);
      }
    } catch (err) {
      if (mountedRef.current) {
        const error = err instanceof Error ? err : new Error(String(err));
        setError(error);
        onError?.(error);
      }
    } finally {
      if (mountedRef.current) {
        setIsLoading(false);
      }
    }
  }, [onSuccess, onError]);

  const reset = useCallback(() => {
    if (mountedRef.current) {
      setData(initialData);
      setError(null);
      setIsLoading(false);
    }
  }, [initialData]);

  // Auto fetch on mount
  useEffect(() => {
    if (autoFetch) {
      fetch();
    }
  }, [autoFetch, fetch]);

  return {
    data,
    isLoading,
    error,
    fetch,
    refetch: fetch,
    setData,
    reset,
  };
}

export default useLoading;

