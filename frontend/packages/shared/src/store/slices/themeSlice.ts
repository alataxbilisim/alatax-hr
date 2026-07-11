import { createSlice, PayloadAction } from '@reduxjs/toolkit';

type ThemeMode = 'dark' | 'light';
export type DensityMode = 'comfortable' | 'compact';

interface ThemeState {
  mode: ThemeMode;
  density: DensityMode;
  /** ContextSidebar geniş (true=216px) / daraltılmış (false=48px) */
  sidebarOpen: boolean;
}

const getInitialTheme = (): ThemeMode => {
  const savedTheme = localStorage.getItem('theme') as ThemeMode;
  if (savedTheme) {
    return savedTheme;
  }

  const userStr = localStorage.getItem('user');
  if (userStr) {
    try {
      const user = JSON.parse(userStr);
      if (user.preferences?.theme) {
        return user.preferences.theme;
      }
    } catch {
      // JSON parse hatası
    }
  }

  if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    return 'dark';
  }

  return 'dark';
};

/** Tema ile aynı desen: localStorage → user.preferences → varsayılan comfortable */
const getInitialDensity = (): DensityMode => {
  const saved = localStorage.getItem('density') as DensityMode | null;
  if (saved === 'comfortable' || saved === 'compact') {
    return saved;
  }

  const userStr = localStorage.getItem('user');
  if (userStr) {
    try {
      const user = JSON.parse(userStr);
      if (user.preferences?.density === 'comfortable' || user.preferences?.density === 'compact') {
        return user.preferences.density;
      }
    } catch {
      // ignore
    }
  }

  return 'comfortable';
};

/** ContextSidebar: localStorage → user.preferences → varsayılan açık (geniş) */
const getInitialSidebarOpen = (): boolean => {
  const saved = localStorage.getItem('contextSidebarExpanded');
  if (saved === 'true') return true;
  if (saved === 'false') return false;

  const userStr = localStorage.getItem('user');
  if (userStr) {
    try {
      const user = JSON.parse(userStr);
      if (typeof user.preferences?.contextSidebarExpanded === 'boolean') {
        return user.preferences.contextSidebarExpanded;
      }
    } catch {
      // ignore
    }
  }

  return true;
};

const persistSidebarOpen = (open: boolean) => {
  localStorage.setItem('contextSidebarExpanded', String(open));
  const userStr = localStorage.getItem('user');
  if (userStr) {
    try {
      const user = JSON.parse(userStr);
      user.preferences = { ...(user.preferences || {}), contextSidebarExpanded: open };
      localStorage.setItem('user', JSON.stringify(user));
    } catch {
      // ignore
    }
  }
};

const applyDensityToDom = (density: DensityMode) => {
  document.documentElement.setAttribute('data-density', density);
};

const initialState: ThemeState = {
  mode: getInitialTheme(),
  density: getInitialDensity(),
  sidebarOpen: getInitialSidebarOpen(),
};

// İlk yüklemede density attribute (theme App useEffect ile de set edilir)
if (typeof document !== 'undefined') {
  applyDensityToDom(initialState.density);
  document.documentElement.setAttribute('data-theme', initialState.mode);
}

const themeSlice = createSlice({
  name: 'theme',
  initialState,
  reducers: {
    toggleTheme: (state) => {
      state.mode = state.mode === 'dark' ? 'light' : 'dark';
      localStorage.setItem('theme', state.mode);
      document.documentElement.setAttribute('data-theme', state.mode);
    },
    setTheme: (state, action: PayloadAction<ThemeMode>) => {
      state.mode = action.payload;
      localStorage.setItem('theme', action.payload);
      document.documentElement.setAttribute('data-theme', action.payload);
    },
    setDensity: (state, action: PayloadAction<DensityMode>) => {
      state.density = action.payload;
      localStorage.setItem('density', action.payload);
      applyDensityToDom(action.payload);
    },
    toggleDensity: (state) => {
      state.density = state.density === 'comfortable' ? 'compact' : 'comfortable';
      localStorage.setItem('density', state.density);
      applyDensityToDom(state.density);
    },
    toggleSidebar: (state) => {
      state.sidebarOpen = !state.sidebarOpen;
      persistSidebarOpen(state.sidebarOpen);
    },
    setSidebarOpen: (state, action: PayloadAction<boolean>) => {
      state.sidebarOpen = action.payload;
      persistSidebarOpen(action.payload);
    },
  },
});

export const {
  toggleTheme,
  setTheme,
  setDensity,
  toggleDensity,
  toggleSidebar,
  setSidebarOpen,
} = themeSlice.actions;
export default themeSlice.reducer;
