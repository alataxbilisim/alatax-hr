import React from 'react';
import { combineReducers, configureStore, type Reducer } from '@reduxjs/toolkit';
import { Provider } from 'react-redux';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, type RenderOptions } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { I18nextProvider } from 'react-i18next';
import i18n from '../i18n';
import authReducer from '../store/slices/authSlice';
import themeReducer from '../store/slices/themeSlice';
import notificationReducer from '../store/slices/notificationSlice';

const baseReducers = {
  auth: authReducer,
  theme: themeReducer,
  notifications: notificationReducer,
};

type ReducerMap = Record<string, Reducer>;

/** Paylaşılan slice'lar + uygulamaya özel slice'larla (ör. branchContext) test store'u. */
export function createTestStore(extraReducers: ReducerMap = {}, preloadedState: Record<string, unknown> = {}) {
  const rootReducer = combineReducers({ ...baseReducers, ...extraReducers });
  return configureStore({
    reducer: rootReducer,
    preloadedState: preloadedState as Partial<ReturnType<typeof rootReducer>>,
  });
}

export type TestStore = ReturnType<typeof createTestStore>;

export function createTestQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: 0, staleTime: 0 },
      mutations: { retry: false },
    },
  });
}

interface ProvidersOptions {
  store?: TestStore;
  extraReducers?: ReducerMap;
  preloadedState?: Record<string, unknown>;
  /** MemoryRouter başlangıç adresi; `false` → router sarmalanmaz (bileşen kendi router'ını kuruyorsa). */
  route?: string | false;
  queryClient?: QueryClient;
}

/** Redux + Router + i18n (TR) + React Query sağlayıcılarıyla render. */
export function renderWithProviders(
  ui: React.ReactElement,
  { store, extraReducers, preloadedState, route = '/', queryClient, ...renderOptions }: ProvidersOptions & Omit<RenderOptions, 'wrapper'> = {}
) {
  const testStore = store ?? createTestStore(extraReducers, preloadedState);
  const client = queryClient ?? createTestQueryClient();

  const Wrapper: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const content = route === false ? children : <MemoryRouter initialEntries={[route]}>{children}</MemoryRouter>;
    return (
      <Provider store={testStore}>
        <QueryClientProvider client={client}>
          <I18nextProvider i18n={i18n}>{content}</I18nextProvider>
        </QueryClientProvider>
      </Provider>
    );
  };

  return {
    store: testStore,
    queryClient: client,
    user: userEvent.setup(),
    ...render(ui, { wrapper: Wrapper, ...renderOptions }),
  };
}
