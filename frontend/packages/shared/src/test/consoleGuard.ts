import { vi } from 'vitest';

export interface ConsoleErrorCapture {
  errors: string[];
  restore: () => void;
}

function stringify(arg: unknown): string {
  if (arg instanceof Error) return `${arg.name}: ${arg.message}`;
  if (typeof arg === 'string') return arg;
  try {
    return JSON.stringify(arg);
  } catch {
    return String(arg);
  }
}

/**
 * console.error çağrılarını ve yakalanmamış hata olaylarını toplar.
 * `ignore`: bilinçli olarak yok sayılan (gerekçeli) kalıplar.
 */
export function captureConsoleErrors(ignore: RegExp[] = []): ConsoleErrorCapture {
  const errors: string[] = [];
  const record = (message: string) => {
    if (!ignore.some((re) => re.test(message))) errors.push(message);
  };

  const spy = vi.spyOn(console, 'error').mockImplementation((...args: unknown[]) => {
    record(args.map(stringify).join(' '));
  });
  const onError = (event: ErrorEvent) => record(`window.onerror: ${event.message}`);
  const onRejection = (event: PromiseRejectionEvent) => record(`unhandledrejection: ${stringify(event.reason)}`);
  window.addEventListener('error', onError);
  window.addEventListener('unhandledrejection', onRejection);

  return {
    errors,
    restore: () => {
      spy.mockRestore();
      window.removeEventListener('error', onError);
      window.removeEventListener('unhandledrejection', onRejection);
    },
  };
}
