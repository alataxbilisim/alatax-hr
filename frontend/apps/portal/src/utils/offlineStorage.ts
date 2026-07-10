/**
 * Offline Storage Utilities
 * IndexedDB wrapper for offline data persistence
 */

const DB_NAME = 'alatax-offline-db';
const DB_VERSION = 1;

interface OfflineRecord {
  id?: number;
  type: string;
  data: Record<string, unknown>;
  token: string;
  createdAt: Date;
}

class OfflineStorage {
  private db: IDBDatabase | null = null;
  private dbPromise: Promise<IDBDatabase> | null = null;

  async open(): Promise<IDBDatabase> {
    if (this.db) return this.db;
    if (this.dbPromise) return this.dbPromise;

    this.dbPromise = new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onerror = () => {
        reject(request.error);
      };

      request.onsuccess = () => {
        this.db = request.result;
        resolve(this.db);
      };

      request.onupgradeneeded = (event) => {
        const db = (event.target as IDBOpenDBRequest).result;

        // Attendance store
        if (!db.objectStoreNames.contains('attendance')) {
          db.createObjectStore('attendance', { keyPath: 'id', autoIncrement: true });
        }

        // Requests store
        if (!db.objectStoreNames.contains('requests')) {
          db.createObjectStore('requests', { keyPath: 'id', autoIncrement: true });
        }

        // Cache store for read data
        if (!db.objectStoreNames.contains('cache')) {
          const cacheStore = db.createObjectStore('cache', { keyPath: 'key' });
          cacheStore.createIndex('expiry', 'expiry');
        }
      };
    });

    return this.dbPromise;
  }

  async saveAttendance(type: 'in' | 'out', data: Record<string, unknown>, token: string): Promise<void> {
    const db = await this.open();
    const tx = db.transaction('attendance', 'readwrite');
    const store = tx.objectStore('attendance');

    await new Promise<void>((resolve, reject) => {
      const record: OfflineRecord = {
        type,
        data,
        token,
        createdAt: new Date(),
      };
      const request = store.add(record);
      request.onerror = () => reject(request.error);
      request.onsuccess = () => resolve();
    });

    // Request background sync
    if ('serviceWorker' in navigator && 'sync' in (navigator.serviceWorker as unknown as { sync: unknown })) {
      try {
        const registration = await navigator.serviceWorker.ready;
        await (registration as unknown as { sync: { register: (tag: string) => Promise<void> } }).sync.register('sync-attendance');
      } catch {
        console.log('Background sync not available');
      }
    }
  }

  async saveRequest(data: Record<string, unknown>, token: string): Promise<void> {
    const db = await this.open();
    const tx = db.transaction('requests', 'readwrite');
    const store = tx.objectStore('requests');

    await new Promise<void>((resolve, reject) => {
      const record: OfflineRecord = {
        type: 'request',
        data,
        token,
        createdAt: new Date(),
      };
      const request = store.add(record);
      request.onerror = () => reject(request.error);
      request.onsuccess = () => resolve();
    });

    // Request background sync
    if ('serviceWorker' in navigator && 'sync' in (navigator.serviceWorker as unknown as { sync: unknown })) {
      try {
        const registration = await navigator.serviceWorker.ready;
        await (registration as unknown as { sync: { register: (tag: string) => Promise<void> } }).sync.register('sync-requests');
      } catch {
        console.log('Background sync not available');
      }
    }
  }

  async getPendingCount(): Promise<{ attendance: number; requests: number }> {
    const db = await this.open();
    
    const getCount = (storeName: string): Promise<number> => {
      return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readonly');
        const store = tx.objectStore(storeName);
        const request = store.count();
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
      });
    };

    const [attendance, requests] = await Promise.all([
      getCount('attendance'),
      getCount('requests'),
    ]);

    return { attendance, requests };
  }

  async cacheData(key: string, data: unknown, ttlMinutes: number = 60): Promise<void> {
    const db = await this.open();
    const tx = db.transaction('cache', 'readwrite');
    const store = tx.objectStore('cache');

    await new Promise<void>((resolve, reject) => {
      const expiry = new Date(Date.now() + ttlMinutes * 60 * 1000);
      const request = store.put({ key, data, expiry });
      request.onerror = () => reject(request.error);
      request.onsuccess = () => resolve();
    });
  }

  async getCachedData<T>(key: string): Promise<T | null> {
    const db = await this.open();
    const tx = db.transaction('cache', 'readonly');
    const store = tx.objectStore('cache');

    return new Promise((resolve, reject) => {
      const request = store.get(key);
      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        const result = request.result;
        if (!result) {
          resolve(null);
          return;
        }
        
        // Check expiry
        if (new Date(result.expiry) < new Date()) {
          // Expired, delete it
          const deleteTx = db.transaction('cache', 'readwrite');
          deleteTx.objectStore('cache').delete(key);
          resolve(null);
          return;
        }
        
        resolve(result.data as T);
      };
    });
  }

  async clearExpiredCache(): Promise<void> {
    const db = await this.open();
    const tx = db.transaction('cache', 'readwrite');
    const store = tx.objectStore('cache');
    const index = store.index('expiry');
    const now = new Date();

    const request = index.openCursor(IDBKeyRange.upperBound(now));
    request.onsuccess = () => {
      const cursor = request.result;
      if (cursor) {
        cursor.delete();
        cursor.continue();
      }
    };
  }
}

export const offlineStorage = new OfflineStorage();

// Check if we're online
export const isOnline = (): boolean => navigator.onLine;

// Online/Offline event hooks
export const onOnlineStatusChange = (callback: (isOnline: boolean) => void): (() => void) => {
  const handleOnline = () => callback(true);
  const handleOffline = () => callback(false);

  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);

  return () => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
  };
};

