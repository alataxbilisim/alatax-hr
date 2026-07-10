import { createSlice, createAsyncThunk, PayloadAction } from '@reduxjs/toolkit';
import { notificationsApi } from '../../services/api';
import { Notification } from '../../types';

interface NotificationState {
  notifications: Notification[];
  unreadCount: number;
  isLoading: boolean;
}

const initialState: NotificationState = {
  notifications: [],
  unreadCount: 0,
  isLoading: false,
};

// Fetch notifications thunk
export const fetchNotifications = createAsyncThunk<{ notifications: Notification[]; unread_count: number }, void>(
  'notifications/fetchNotifications',
  async () => {
    const response = await notificationsApi.list();
    return response.data.data;
  }
);

// Mark as read thunk
export const markAsRead = createAsyncThunk<string, string>(
  'notifications/markAsRead',
  async (id) => {
    await notificationsApi.markAsRead(id);
    return id;
  }
);

// Mark all as read thunk
export const markAllAsRead = createAsyncThunk<void, void>(
  'notifications/markAllAsRead',
  async () => {
    await notificationsApi.markAllAsRead();
  }
);

const notificationSlice = createSlice({
  name: 'notifications',
  initialState,
  reducers: {
    addNotification: (state, action: PayloadAction<Notification>) => {
      state.notifications.unshift(action.payload);
      state.unreadCount += 1;
    },
    setUnreadCount: (state, action: PayloadAction<number>) => {
      state.unreadCount = action.payload;
    },
  },
  extraReducers: (builder) => {
    builder
      .addCase(fetchNotifications.pending, (state) => {
        state.isLoading = true;
      })
      .addCase(fetchNotifications.fulfilled, (state, action) => {
        state.isLoading = false;
        state.notifications = action.payload.notifications;
        state.unreadCount = action.payload.unread_count;
      })
      .addCase(markAsRead.fulfilled, (state, action) => {
        const notification = state.notifications.find((n) => n.id === action.payload);
        if (notification && !notification.read_at) {
          notification.read_at = new Date().toISOString();
          state.unreadCount = Math.max(0, state.unreadCount - 1);
        }
      })
      .addCase(markAllAsRead.fulfilled, (state) => {
        state.notifications = state.notifications.map((n) => ({
          ...n,
          read_at: n.read_at || new Date().toISOString(),
        }));
        state.unreadCount = 0;
      });
  },
});

export const { addNotification, setUnreadCount } = notificationSlice.actions;
export default notificationSlice.reducer;

