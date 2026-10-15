import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterAll, afterEach, beforeAll, vi } from 'vitest';
import '../i18n';
import { server } from './server';

// jsdom'da olmayan tarayıcı API'leri (grafik, Radix ve sanal liste bileşenleri bunları çağırır)
class ResizeObserverStub {
  observe(): void {}
  unobserve(): void {}
  disconnect(): void {}
}

class IntersectionObserverStub {
  readonly root = null;
  readonly rootMargin = '';
  readonly thresholds: number[] = [];
  observe(): void {}
  unobserve(): void {}
  disconnect(): void {}
  takeRecords(): IntersectionObserverEntry[] {
    return [];
  }
}

vi.stubGlobal('ResizeObserver', ResizeObserverStub);
vi.stubGlobal('IntersectionObserver', IntersectionObserverStub);

if (!window.matchMedia) {
  window.matchMedia = (query: string): MediaQueryList => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  });
}

window.scrollTo = () => {};
Element.prototype.scrollIntoView = function scrollIntoView(): void {};
Element.prototype.scrollTo = function scrollTo(): void {};
Element.prototype.hasPointerCapture = function hasPointerCapture(): boolean {
  return false;
};
Element.prototype.releasePointerCapture = function releasePointerCapture(): void {};
Element.prototype.setPointerCapture = function setPointerCapture(): void {};

// Canvas (echarts / html2canvas): jsdom getContext uygulamaz, null döner.
HTMLCanvasElement.prototype.getContext = function getContext(): null {
  return null;
} as typeof HTMLCanvasElement.prototype.getContext;

if (!URL.createObjectURL) {
  URL.createObjectURL = () => 'blob:test';
}
if (!URL.revokeObjectURL) {
  URL.revokeObjectURL = () => {};
}

beforeAll(() => {
  server.listen({ onUnhandledFrame: 'error' });
});

afterEach(() => {
  cleanup();
  server.resetHandlers();
  localStorage.clear();
  sessionStorage.clear();
  vi.restoreAllMocks();
});

afterAll(() => {
  server.close();
});
