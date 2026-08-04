import { configureStore } from '@reduxjs/toolkit';
import authReducer from '@shared/store/slices/authSlice';
import themeReducer from '@shared/store/slices/themeSlice';
import notificationReducer from '@shared/store/slices/notificationSlice';
import branchContextReducer from './store/branchContextSlice';
import companyContextReducer from './store/companyContextSlice';

export const store = configureStore({
  reducer: {
    auth: authReducer,
    theme: themeReducer,
    notifications: notificationReducer,
    branchContext: branchContextReducer,
    companyContext: companyContextReducer,
  },
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
