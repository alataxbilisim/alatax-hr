import { createSlice, PayloadAction } from '@reduxjs/toolkit';

type ThemeMode = 'dark' | 'light';

interface ThemeState {
  mode: ThemeMode;
  sidebarOpen: boolean;
}

// Tema tercihini localStorage'dan al veya sistem tercihini kullan
const getInitialTheme = (): ThemeMode => {
  const savedTheme = localStorage.getItem('theme') as ThemeMode;
  if (savedTheme) {
    return savedTheme;
  }
  
  // Kullanıcı bilgisinden al
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
  
  // Sistem tercihini kontrol et
  if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    return 'dark';
  }
  
  return 'dark'; // Varsayılan
};

const initialState: ThemeState = {
  mode: getInitialTheme(),
  sidebarOpen: true,
};

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
    toggleSidebar: (state) => {
      state.sidebarOpen = !state.sidebarOpen;
    },
    setSidebarOpen: (state, action: PayloadAction<boolean>) => {
      state.sidebarOpen = action.payload;
    },
  },
});

export const { toggleTheme, setTheme, toggleSidebar, setSidebarOpen } = themeSlice.actions;
export default themeSlice.reducer;

