import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { authApi } from '../../services/api';
import {
  User,
  AuthResponse,
  LoginCredentials,
  LoginResult,
  RegisterData,
  isTwoFactorChallenge,
} from '../../types';
import toast from 'react-hot-toast';

interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;
}

export type CheckAuthArg = { silent?: boolean } | undefined;

const isSilentCheck = (arg: CheckAuthArg): boolean =>
  Boolean(arg && typeof arg === 'object' && arg.silent === true);

const initialState: AuthState = {
  user: JSON.parse(localStorage.getItem('user') || 'null'),
  token: localStorage.getItem('token'),
  isAuthenticated: !!localStorage.getItem('token'),
  isLoading: false,
  error: null,
};

/** Light /me yanıtında permissions/modules eksikse mevcut state ile birleştir */
function mergeUserPreservingAuthz(incoming: User, previous: User | null): User {
  if (!previous) {
    return incoming;
  }

  const permissions =
    incoming.permissions && incoming.permissions.length > 0
      ? incoming.permissions
      : previous.permissions;

  const previousModules = previous.company?.active_modules;
  const incomingModules = incoming.company?.active_modules;
  const active_modules =
    incomingModules && incomingModules.length > 0
      ? incomingModules
      : previousModules;

  return {
    ...previous,
    ...incoming,
    permissions,
    company: incoming.company
      ? {
          ...previous.company,
          ...incoming.company,
          active_modules: active_modules ?? incoming.company.active_modules ?? [],
        }
      : previous.company,
  };
}

function persistSession(user: User, token: string, employee?: unknown): void {
  localStorage.setItem('token', token);
  localStorage.setItem('user', JSON.stringify(user));
  if (employee) {
    localStorage.setItem('employee', JSON.stringify(employee));
  }
}

// Login thunk — 2FA aktifse challenge döner (token yazılmaz)
export const login = createAsyncThunk<LoginResult, LoginCredentials & { portal_login?: boolean }>(
  'auth/login',
  async (credentials, { rejectWithValue }) => {
    try {
      const response = await authApi.login(credentials);
      const data = response.data.data as {
        requires_2fa?: boolean;
        challenge_token?: string;
        expires_in?: number;
        user: User | { id: number; name: string; email: string };
        token?: string;
        employee?: unknown;
      };

      if (data.requires_2fa === true && data.challenge_token) {
        // Eski oturum Bearer'ı challenge verify'ı bozmasın
        localStorage.removeItem('token');
        return {
          requires_2fa: true as const,
          challenge_token: data.challenge_token,
          expires_in: data.expires_in ?? 300,
          user: data.user as { id: number; name: string; email: string },
        };
      }

      const user = data.user as User;
      const token = data.token as string;
      persistSession(user, token, data.employee);
      return { user, token };
    } catch (error: unknown) {
      const err = error as {
        response?: {
          data?: {
            message?: string;
            errors?: { code?: string; portal_url?: string };
          };
        };
      };
      const errors = err.response?.data?.errors;
      return rejectWithValue({
        message: err.response?.data?.message || 'Giriş yapılamadı',
        code: typeof errors?.code === 'string' ? errors.code : undefined,
        portal_url: typeof errors?.portal_url === 'string' ? errors.portal_url : undefined,
      });
    }
  }
);

export type VerifyTwoFactorPayload = {
  challenge_token: string;
  code?: string;
  recovery_code?: string;
};

export type VerifyTwoFactorReject = {
  status?: number;
  message: string;
};

// 2FA challenge tamamla → gerçek Sanctum token
export const verifyTwoFactor = createAsyncThunk<
  AuthResponse,
  VerifyTwoFactorPayload,
  { rejectValue: VerifyTwoFactorReject }
>(
  'auth/verifyTwoFactor',
  async ({ challenge_token, code, recovery_code }, { rejectWithValue }) => {
    try {
      const body = code ? { code } : { recovery_code };
      const response = await authApi.verifyTwoFactor(body, challenge_token);
      const { user, token, employee } = response.data.data as AuthResponse & { employee?: unknown };
      persistSession(user, token, employee);
      return { user, token };
    } catch (error: unknown) {
      const err = error as { response?: { status?: number; data?: { message?: string } } };
      return rejectWithValue({
        status: err.response?.status,
        message: err.response?.data?.message || 'Doğrulama başarısız',
      });
    }
  }
);

// Register thunk
export const register = createAsyncThunk<AuthResponse, RegisterData>(
  'auth/register',
  async (data, { rejectWithValue }) => {
    try {
      const response = await authApi.register(data);
      const { user, token } = response.data.data;

      localStorage.setItem('token', token);
      localStorage.setItem('user', JSON.stringify(user));

      return { user, token };
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      return rejectWithValue(err.response?.data?.message || 'Kayıt oluşturulamadı');
    }
  }
);

// Logout thunk
export const logout = createAsyncThunk<void, void>(
  'auth/logout',
  async (_, { rejectWithValue }) => {
    try {
      await authApi.logout();
      localStorage.removeItem('token');
      localStorage.removeItem('user');
    } catch (error: unknown) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      const err = error as { response?: { data?: { message?: string } } };
      return rejectWithValue(err.response?.data?.message || 'Çıkış yapılamadı');
    }
  }
);

// Get current user thunk
export const fetchCurrentUser = createAsyncThunk<User, void>(
  'auth/fetchCurrentUser',
  async (_, { rejectWithValue }) => {
    try {
      const response = await authApi.me();
      const user = response.data.data.user;
      localStorage.setItem('user', JSON.stringify(user));
      return user;
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } };
      return rejectWithValue(err.response?.data?.message || 'Kullanıcı bilgisi alınamadı');
    }
  }
);

/**
 * Token doğrula.
 * silent: isLoading dokunulmaz; /me?light=1 (izin dump yok); mevcut permissions korunur.
 */
export const checkAuth = createAsyncThunk<User | null, CheckAuthArg>(
  'auth/checkAuth',
  async (arg, { getState }) => {
    const token = localStorage.getItem('token');
    if (!token) {
      return null;
    }

    const silent = isSilentCheck(arg);

    try {
      const response = await authApi.me({ light: silent });
      let user = response.data.data.user as User;

      if (silent) {
        const prev = (getState() as { auth: AuthState }).auth.user
          ?? (JSON.parse(localStorage.getItem('user') || 'null') as User | null);
        user = mergeUserPreservingAuthz(user, prev);
      }

      localStorage.setItem('user', JSON.stringify(user));
      return user;
    } catch (error: unknown) {
      const status = (error as { response?: { status?: number } })?.response?.status;
      if (status === 401 || status === 403) {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        return null;
      }
      // Silent ağ hatası oturumu düşürmesin
      if (silent) {
        throw error;
      }
      return null;
    }
  }
);

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setUser: (state, action: PayloadAction<User>) => {
      state.user = action.payload;
      localStorage.setItem('user', JSON.stringify(action.payload));
    },
    clearError: (state) => {
      state.error = null;
    },
    updateUserPreferences: (state, action: PayloadAction<Partial<User['preferences']>>) => {
      if (state.user) {
        state.user.preferences = { ...state.user.preferences, ...action.payload };
        localStorage.setItem('user', JSON.stringify(state.user));
      }
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(login.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(login.fulfilled, (state, action) => {
        state.isLoading = false;
        if (isTwoFactorChallenge(action.payload)) {
          // Challenge aşaması — oturum açılmaz
          state.isAuthenticated = false;
          state.token = null;
          state.error = null;
          return;
        }
        state.isAuthenticated = true;
        state.user = action.payload.user;
        state.token = action.payload.token;
      })
      .addCase(login.rejected, (state, action) => {
        state.isLoading = false;
        const payload = action.payload as string | { message?: string } | undefined;
        state.error = typeof payload === 'string' ? payload : (payload?.message ?? 'Giriş yapılamadı');
      })
      .addCase(verifyTwoFactor.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(verifyTwoFactor.fulfilled, (state, action) => {
        state.isLoading = false;
        state.isAuthenticated = true;
        state.user = action.payload.user;
        state.token = action.payload.token;
        state.error = null;
      })
      .addCase(verifyTwoFactor.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.payload?.message ?? 'Doğrulama başarısız';
      })
      .addCase(register.pending, (state) => {
        state.isLoading = true;
        state.error = null;
      })
      .addCase(register.fulfilled, (state, action) => {
        state.isLoading = false;
        state.isAuthenticated = true;
        state.user = action.payload.user;
        state.token = action.payload.token;
        toast.success('Kayıt başarılı. Hoş geldiniz!');
      })
      .addCase(register.rejected, (state, action) => {
        state.isLoading = false;
        state.error = action.payload as string;
      })
      .addCase(logout.fulfilled, (state) => {
        state.user = null;
        state.token = null;
        state.isAuthenticated = false;
        toast.success('Çıkış yapıldı');
      })
      .addCase(fetchCurrentUser.fulfilled, (state, action) => {
        state.user = action.payload;
      })
      .addCase(checkAuth.pending, (state, action) => {
        if (!isSilentCheck(action.meta.arg)) {
          state.isLoading = true;
        }
      })
      .addCase(checkAuth.fulfilled, (state, action) => {
        if (!isSilentCheck(action.meta.arg)) {
          state.isLoading = false;
        }
        if (action.payload) {
          state.user = action.payload;
          state.isAuthenticated = true;
        } else {
          state.user = null;
          state.token = null;
          state.isAuthenticated = false;
        }
      })
      .addCase(checkAuth.rejected, (state, action) => {
        if (!isSilentCheck(action.meta.arg)) {
          state.isLoading = false;
          state.user = null;
          state.token = null;
          state.isAuthenticated = false;
        }
        // silent reject: oturum ve isLoading aynen kalır
      });
  },
});

export const { setUser, clearError, updateUserPreferences } = authSlice.actions;
export default authSlice.reducer;
