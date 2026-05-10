import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { initialState, rideStatusSteps } from '../data/appDefaults';
import { api } from '../services/api';

const mergeData = (state, payload = {}) => {
  state.students = payload.students ?? state.students;
  state.bookings = payload.bookings ?? state.bookings;
  state.rides = payload.rides ?? state.rides;
  state.notifications = payload.notifications ?? state.notifications;
  state.messages = payload.messages ?? state.messages;
  state.availableDrivers = payload.drivers ?? state.availableDrivers;
};

export const login = createAsyncThunk('app/login', async (payload) => {
  const auth = await api.login(payload);
  const dashboard = auth.user.role === 'driver'
    ? await api.driverDashboard(auth.token)
    : auth.user.role === 'parent'
      ? await api.parentDashboard(auth.token)
      : {};
  const drivers = auth.user.role === 'parent' ? await api.drivers(auth.token) : { drivers: [] };
  return { ...auth, ...dashboard, ...drivers };
});

export const registerParent = createAsyncThunk('app/registerParent', async (payload) => {
  const auth = await api.registerParent(payload);
  const dashboard = await api.parentDashboard(auth.token);
  const drivers = await api.drivers(auth.token);
  return { ...auth, ...dashboard, ...drivers };
});

export const registerDriver = createAsyncThunk('app/registerDriver', async (payload) => {
  const auth = await api.registerDriver(payload);
  const dashboard = await api.driverDashboard(auth.token);
  return { ...auth, ...dashboard };
});

export const refreshDashboard = createAsyncThunk('app/refreshDashboard', async (_, { getState }) => {
  const { token, currentRole } = getState().app;
  if (!token) {
    return {};
  }
  const dashboard = currentRole === 'driver' ? await api.driverDashboard(token) : currentRole === 'parent' ? await api.parentDashboard(token) : {};
  const drivers = currentRole === 'parent' ? await api.drivers(token) : { drivers: [] };
  return { ...dashboard, ...drivers };
});

export const addStudent = createAsyncThunk('app/addStudent', async (payload, { getState }) => {
  const { token } = getState().app;
  return api.addStudent(token, payload);
});

export const createBooking = createAsyncThunk('app/createBooking', async (payload, { getState }) => {
  const { token } = getState().app;
  return api.createBooking(token, payload);
});

export const updateRideStatus = createAsyncThunk('app/updateRideStatus', async (status, { getState }) => {
  const { token, rides } = getState().app;
  return api.updateRideStatus(token, rides[0].id, status);
});

export const pushCurrentLocation = createAsyncThunk('app/pushCurrentLocation', async (_, { getState }) => {
  const { token, rides } = getState().app;
  const ride = rides[0];
  return api.pushLocation(token, ride.id, {
    latitude: ride.location.latitude,
    longitude: ride.location.longitude,
    recorded_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
  });
});

const appSlice = createSlice({
  name: 'app',
  initialState,
  reducers: {
    logout(state) {
      Object.assign(state, initialState);
    },
    setTrackingActive(state, action) {
      const ride = state.rides[0];

      if (!ride || ride.status === 'Completed') {
        return;
      }

      ride.isTracking = action.payload;
    },
    advanceRideSimulation(state) {
      const ride = state.rides[0];

      if (!ride || ride.status === 'Completed' || !ride.routePoints?.length) {
        return;
      }

      const maxIndex = ride.routePoints.length - 1;
      const nextIndex = Math.min((ride.currentPointIndex || 0) + 1, maxIndex);
      const nextProgress = maxIndex === 0 ? 1 : nextIndex / maxIndex;
      const statusIndex = Math.min(Math.floor(nextProgress * (rideStatusSteps.length - 1)), rideStatusSteps.length - 1);

      ride.currentPointIndex = nextIndex;
      ride.location = {
        ...ride.location,
        ...ride.routePoints[nextIndex],
      };
      ride.progress = nextProgress;
      ride.status = rideStatusSteps[statusIndex];
      ride.etaMinutes = Math.max(0, Math.ceil((1 - nextProgress) * 12));
      ride.distanceKm = Math.max(0, Number((3.4 * (1 - nextProgress)).toFixed(1)));
      ride.isTracking = nextIndex < maxIndex;
    },
    resetRideSimulation(state) {
      const ride = state.rides[0];

      if (!ride || !ride.routePoints?.length) {
        return;
      }

      ride.status = 'Driver Arriving';
      ride.etaMinutes = 8;
      ride.distanceKm = 3.4;
      ride.progress = 0;
      ride.currentPointIndex = 0;
      ride.isTracking = false;
      ride.location = {
        ...ride.location,
        ...ride.routePoints[0],
      };
    },
    sendMessage(state, action) {
      const text = action.payload;

      if (!state.currentRole || !text.trim()) {
        return;
      }

      const receiverRole = state.currentRole === 'parent' ? 'driver' : 'parent';
      const sender = state.currentUser?.firstName || 'TRACE User';

      state.messages.push({
        id: `local-msg-${Date.now()}`,
        senderRole: state.currentRole,
        senderName: sender,
        receiverRole,
        text: text.trim(),
        time: 'Just now',
      });
    },
  },
  extraReducers: (builder) => {
    const pending = (state) => {
      state.loading = true;
      state.error = null;
    };
    const rejected = (state, action) => {
      state.loading = false;
      state.error = action.error.message;
    };
    const authed = (state, action) => {
      state.loading = false;
      state.error = null;
      state.token = action.payload.token;
      state.currentUser = action.payload.user;
      state.currentRole = action.payload.user.role;
      mergeData(state, action.payload);
    };
    const refreshed = (state, action) => {
      state.loading = false;
      state.error = null;
      mergeData(state, action.payload);
    };

    builder
      .addCase(login.pending, pending)
      .addCase(login.fulfilled, authed)
      .addCase(login.rejected, rejected)
      .addCase(registerParent.pending, pending)
      .addCase(registerParent.fulfilled, authed)
      .addCase(registerParent.rejected, rejected)
      .addCase(registerDriver.pending, pending)
      .addCase(registerDriver.fulfilled, authed)
      .addCase(registerDriver.rejected, rejected)
      .addCase(refreshDashboard.pending, pending)
      .addCase(refreshDashboard.fulfilled, refreshed)
      .addCase(refreshDashboard.rejected, rejected)
      .addCase(addStudent.pending, pending)
      .addCase(addStudent.fulfilled, refreshed)
      .addCase(addStudent.rejected, rejected)
      .addCase(createBooking.pending, pending)
      .addCase(createBooking.fulfilled, refreshed)
      .addCase(createBooking.rejected, rejected)
      .addCase(updateRideStatus.pending, pending)
      .addCase(updateRideStatus.fulfilled, refreshed)
      .addCase(updateRideStatus.rejected, rejected)
      .addCase(pushCurrentLocation.fulfilled, refreshed);
  },
});

export const appActions = appSlice.actions;
export const appThunks = { login, registerParent, registerDriver, refreshDashboard, addStudent, createBooking, updateRideStatus, pushCurrentLocation };
export default appSlice.reducer;
