import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { initialState } from '../data/appDefaults';
import { api } from '../services/api';

const asArray = (value) => (Array.isArray(value) ? value : []);
const isCompletedRide = (ride) => ['completed', 'cancelled'].includes(String(ride?.rawStatus || ride?.status || '').toLowerCase().replace(/\s+/g, '_'));
const firstRideOrThrow = (rides) => {
  const ride = asArray(rides)[0];

  if (!ride?.id) {
    throw new Error('No active trip is available.');
  }

  return ride;
};

const mergeData = (state, payload = {}) => {
  state.students = payload.students === undefined ? state.students : asArray(payload.students);
  state.bookings = payload.bookings === undefined ? state.bookings : asArray(payload.bookings);
  state.monthlyPlan = payload.monthlyPlan ?? state.monthlyPlan;
  state.monthlyPlans = payload.monthlyPlans === undefined ? state.monthlyPlans : asArray(payload.monthlyPlans);
  state.billing = payload.billing ?? state.billing;
  state.rides = payload.rides === undefined
    ? state.rides
    : asArray(payload.rides).filter((ride) => !isCompletedRide(ride));
  state.transactions = payload.transactions === undefined ? state.transactions : asArray(payload.transactions);
  state.notifications = payload.notifications === undefined ? state.notifications : asArray(payload.notifications);
  state.messages = payload.messages === undefined ? state.messages : asArray(payload.messages);
  state.availableDrivers = payload.drivers === undefined ? state.availableDrivers : asArray(payload.drivers);
};

const dashboardForRole = async (apiClient, role, token) => {
  if (role === 'driver') {
    return apiClient.driverDashboard(token);
  }

  if (role === 'parent') {
    return apiClient.parentDashboard(token);
  }

  if (role === 'student') {
    return apiClient.studentDashboard(token);
  }

  return {};
};

export const login = createAsyncThunk('app/login', async (payload) => {
  const auth = await api.login(payload);
  const dashboard = await dashboardForRole(api, auth.user.role, auth.token);
  const drivers = auth.user.role === 'parent' || auth.user.role === 'driver' ? await api.drivers(auth.token) : { drivers: [] };
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
  const drivers = await api.drivers(auth.token);
  return { ...auth, ...dashboard, ...drivers };
});

export const refreshDashboard = createAsyncThunk('app/refreshDashboard', async (_, { getState }) => {
  const { token, currentRole } = getState().app;
  if (!token) {
    return {};
  }
  const dashboard = await dashboardForRole(api, currentRole, token);
  const drivers = currentRole === 'parent' || currentRole === 'driver' ? await api.drivers(token) : { drivers: [] };
  return { ...dashboard, ...drivers };
});

export const updateProfile = createAsyncThunk('app/updateProfile', async (payload, { getState }) => {
  const { token } = getState().app;
  return api.updateProfile(token, payload);
});

export const addStudent = createAsyncThunk('app/addStudent', async (payload, { getState }) => {
  const { token } = getState().app;
  return api.addStudent(token, payload);
});

export const updateStudent = createAsyncThunk('app/updateStudent', async ({ studentId, payload }, { getState }) => {
  const { token } = getState().app;
  return api.updateStudent(token, studentId, payload);
});

export const createBooking = createAsyncThunk('app/createBooking', async (payload, { getState }) => {
  const { token } = getState().app;
  return api.createBooking(token, payload);
});

export const estimateMonthlyPlan = createAsyncThunk('app/estimateMonthlyPlan', async (payload, { getState }) => {
  const { token } = getState().app;
  return api.estimateMonthlyPlan(token, payload);
});

export const payMonthlyPlan = createAsyncThunk('app/payMonthlyPlan', async (payload, { getState }) => {
  const { token } = getState().app;
  return api.payMonthlyPlan(token, payload);
});

export const updateMonthlyPlan = createAsyncThunk('app/updateMonthlyPlan', async ({ planId, payload }, { getState }) => {
  const { token } = getState().app;
  return api.updateMonthlyPlan(token, planId, payload);
});

export const cancelMonthlyPlan = createAsyncThunk('app/cancelMonthlyPlan', async (planId, { getState }) => {
  const { token } = getState().app;
  return api.cancelMonthlyPlan(token, planId);
});

export const cancelMonthlyPlanDay = createAsyncThunk('app/cancelMonthlyPlanDay', async ({ bookingId, payload }, { getState }) => {
  const { token } = getState().app;
  return api.cancelMonthlyPlanDay(token, bookingId, payload);
});

export const approveBooking = createAsyncThunk('app/approveBooking', async (payload, { getState }) => {
  const { token } = getState().app;
  const bookingId = typeof payload === 'object' ? payload.bookingId : payload;
  const includeCarpool = typeof payload === 'object' ? payload.includeCarpool : false;

  return api.approveBooking(token, bookingId, { includeCarpool });
});

export const rejectBooking = createAsyncThunk('app/rejectBooking', async (bookingId, { getState }) => {
  const { token } = getState().app;
  return api.rejectBooking(token, bookingId);
});

export const updateDriverAvailability = createAsyncThunk('app/updateDriverAvailability', async (isOnline, { getState }) => {
  const { token } = getState().app;
  return api.updateDriverAvailability(token, isOnline);
});

export const updateRideStatus = createAsyncThunk('app/updateRideStatus', async (payload, { getState }) => {
  const { token, rides } = getState().app;
  const status = typeof payload === 'object' ? payload.status : payload;
  const rideId = typeof payload === 'object' && payload.rideId ? payload.rideId : firstRideOrThrow(rides).id;
  const body = typeof payload === 'object' ? payload.payload : null;

  return api.updateRideStatus(token, rideId, status, body);
});

export const transferRide = createAsyncThunk('app/transferRide', async ({ rideId, driverId, handoffNote = '', includeRoute = true }, { getState }) => {
  const { token } = getState().app;
  return api.transferRide(token, rideId, { driverId, handoffNote, includeRoute });
});

export const pushCurrentLocation = createAsyncThunk('app/pushCurrentLocation', async (_, { getState }) => {
  const { token, rides } = getState().app;
  const ride = firstRideOrThrow(rides);
  const location = ride.location || {};
  const latitude = Number(location.latitude);
  const longitude = Number(location.longitude);

  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
    throw new Error('No current trip location is available.');
  }

  return api.pushLocation(token, ride.id, {
    latitude,
    longitude,
    recorded_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
  });
});

export const pushRideLocation = createAsyncThunk('app/pushRideLocation', async (location, { getState }) => {
  const { token, rides } = getState().app;
  const ride = firstRideOrThrow(rides);

  return api.pushLocation(token, ride.id, {
    ...location,
    recorded_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
  });
});

export const trackRide = createAsyncThunk('app/trackRide', async (rideId, { getState }) => {
  const { token } = getState().app;
  const tracked = await api.trackRide(token, rideId);
  return { rideId, tracked };
});

export const sendRemoteMessage = createAsyncThunk('app/sendRemoteMessage', async (payload, { getState }) => {
  const { token, currentRole } = getState().app;
  await api.sendMessage(token, payload);
  const dashboard = await dashboardForRole(api, currentRole, token);
  return dashboard;
});

const appSlice = createSlice({
  name: 'app',
  initialState,
  reducers: {
    logout(state) {
      Object.assign(state, initialState);
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
      state.token = action.payload.token ?? state.token;
      state.currentUser = action.payload.user ?? state.currentUser;
      state.currentRole = action.payload.user?.role ?? state.currentRole;
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
      .addCase(updateProfile.pending, pending)
      .addCase(updateProfile.fulfilled, authed)
      .addCase(updateProfile.rejected, rejected)
      .addCase(addStudent.pending, pending)
      .addCase(addStudent.fulfilled, refreshed)
      .addCase(addStudent.rejected, rejected)
      .addCase(updateStudent.pending, pending)
      .addCase(updateStudent.fulfilled, refreshed)
      .addCase(updateStudent.rejected, rejected)
      .addCase(createBooking.pending, pending)
      .addCase(createBooking.fulfilled, refreshed)
      .addCase(createBooking.rejected, rejected)
      .addCase(estimateMonthlyPlan.fulfilled, refreshed)
      .addCase(payMonthlyPlan.pending, pending)
      .addCase(payMonthlyPlan.fulfilled, refreshed)
      .addCase(payMonthlyPlan.rejected, rejected)
      .addCase(updateMonthlyPlan.pending, pending)
      .addCase(updateMonthlyPlan.fulfilled, refreshed)
      .addCase(updateMonthlyPlan.rejected, rejected)
      .addCase(cancelMonthlyPlan.pending, pending)
      .addCase(cancelMonthlyPlan.fulfilled, refreshed)
      .addCase(cancelMonthlyPlan.rejected, rejected)
      .addCase(cancelMonthlyPlanDay.pending, pending)
      .addCase(cancelMonthlyPlanDay.fulfilled, refreshed)
      .addCase(cancelMonthlyPlanDay.rejected, rejected)
      .addCase(approveBooking.pending, pending)
      .addCase(approveBooking.fulfilled, refreshed)
      .addCase(approveBooking.rejected, rejected)
      .addCase(rejectBooking.pending, pending)
      .addCase(rejectBooking.fulfilled, refreshed)
      .addCase(rejectBooking.rejected, rejected)
      .addCase(updateDriverAvailability.fulfilled, refreshed)
      .addCase(updateRideStatus.pending, pending)
      .addCase(updateRideStatus.fulfilled, refreshed)
      .addCase(updateRideStatus.rejected, rejected)
      .addCase(transferRide.pending, pending)
      .addCase(transferRide.fulfilled, refreshed)
      .addCase(transferRide.rejected, rejected)
      .addCase(pushCurrentLocation.fulfilled, refreshed)
      .addCase(pushRideLocation.fulfilled, refreshed)
      .addCase(trackRide.fulfilled, (state, action) => {
        const ride = state.rides.find((item) => item.id === action.payload.rideId);

        if (!ride) {
          return;
        }

        Object.assign(ride, action.payload.tracked);
      })
      .addCase(sendRemoteMessage.pending, pending)
      .addCase(sendRemoteMessage.fulfilled, refreshed)
      .addCase(sendRemoteMessage.rejected, rejected);
  },
});

export const appActions = appSlice.actions;
export const appThunks = { login, registerParent, registerDriver, refreshDashboard, updateProfile, addStudent, updateStudent, createBooking, estimateMonthlyPlan, payMonthlyPlan, updateMonthlyPlan, cancelMonthlyPlan, cancelMonthlyPlanDay, approveBooking, rejectBooking, updateDriverAvailability, updateRideStatus, transferRide, pushCurrentLocation, pushRideLocation, trackRide, sendRemoteMessage };
export default appSlice.reducer;
