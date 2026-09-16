import React, { createContext, useCallback, useContext, useMemo } from 'react';
import { Provider as ReduxProvider, useDispatch, useSelector } from 'react-redux';
import { appActions, appThunks } from '../store/appSlice';
import { store } from '../store/store';

const AppContext = createContext(null);

function AppContextBridge({ children }) {
  const state = useSelector((currentState) => currentState.app);
  const dispatch = useDispatch();

  const login = useCallback((payload) => dispatch(appThunks.login(payload)).unwrap(), [dispatch]);
  const logout = useCallback(async () => {
    if (state.currentRole === 'driver' && state.token) {
      try {
        await dispatch(appThunks.updateDriverAvailability(false)).unwrap();
      } catch {
        // Logout should continue even if the availability request cannot reach the API.
      }
    }

    dispatch(appActions.logout());
  }, [dispatch, state.currentRole, state.token]);
  const registerParent = useCallback((payload) => dispatch(appThunks.registerParent(payload)).unwrap(), [dispatch]);
  const registerDriver = useCallback((payload) => dispatch(appThunks.registerDriver(payload)).unwrap(), [dispatch]);
  const refreshDashboard = useCallback(() => dispatch(appThunks.refreshDashboard()).unwrap(), [dispatch]);
  const updateProfile = useCallback((payload) => dispatch(appThunks.updateProfile(payload)).unwrap(), [dispatch]);
  const addStudent = useCallback((payload) => dispatch(appThunks.addStudent(payload)).unwrap(), [dispatch]);
  const updateStudent = useCallback((studentId, payload) => dispatch(appThunks.updateStudent({ studentId, payload })).unwrap(), [dispatch]);
  const createBooking = useCallback((payload) => dispatch(appThunks.createBooking(payload)).unwrap(), [dispatch]);
  const estimateMonthlyPlan = useCallback((payload) => dispatch(appThunks.estimateMonthlyPlan(payload)).unwrap(), [dispatch]);
  const payMonthlyPlan = useCallback((payload) => dispatch(appThunks.payMonthlyPlan(payload)).unwrap(), [dispatch]);
  const updateMonthlyPlan = useCallback((planId, payload) => dispatch(appThunks.updateMonthlyPlan({ planId, payload })).unwrap(), [dispatch]);
  const cancelMonthlyPlan = useCallback((planId) => dispatch(appThunks.cancelMonthlyPlan(planId)).unwrap(), [dispatch]);
  const cancelMonthlyPlanDay = useCallback((bookingId, payload) => dispatch(appThunks.cancelMonthlyPlanDay({ bookingId, payload })).unwrap(), [dispatch]);
  const approveBooking = useCallback((bookingId, options = {}) => dispatch(appThunks.approveBooking({ bookingId, ...options })).unwrap(), [dispatch]);
  const rejectBooking = useCallback((bookingId) => dispatch(appThunks.rejectBooking(bookingId)).unwrap(), [dispatch]);
  const updateDriverAvailability = useCallback((isOnline) => dispatch(appThunks.updateDriverAvailability(isOnline)).unwrap(), [dispatch]);
  const updateRideStatus = useCallback((status, rideId, payload) => dispatch(appThunks.updateRideStatus({ status, rideId, payload })).unwrap(), [dispatch]);
  const transferRide = useCallback((rideId, driverId, options = {}) => dispatch(appThunks.transferRide({ rideId, driverId, ...options })).unwrap(), [dispatch]);
  const pushRideLocation = useCallback((location) => dispatch(appThunks.pushRideLocation(location)).unwrap(), [dispatch]);
  const trackRide = useCallback((rideId) => dispatch(appThunks.trackRide(rideId)).unwrap(), [dispatch]);
  const sendMessage = useCallback((payload) => dispatch(appThunks.sendRemoteMessage(payload)).unwrap(), [dispatch]);

  const value = useMemo(
    () => ({
      ...state,
      login,
      logout,
      registerParent,
      registerDriver,
      refreshDashboard,
      updateProfile,
      addStudent,
      updateStudent,
      createBooking,
      estimateMonthlyPlan,
      payMonthlyPlan,
      updateMonthlyPlan,
      cancelMonthlyPlan,
      cancelMonthlyPlanDay,
      approveBooking,
      rejectBooking,
      updateDriverAvailability,
      updateRideStatus,
      transferRide,
      pushRideLocation,
      trackRide,
      sendMessage,
    }),
    [
      state,
      login,
      logout,
      registerParent,
      registerDriver,
      refreshDashboard,
      updateProfile,
      addStudent,
      updateStudent,
      createBooking,
      estimateMonthlyPlan,
      payMonthlyPlan,
      updateMonthlyPlan,
      cancelMonthlyPlan,
      cancelMonthlyPlanDay,
      approveBooking,
      rejectBooking,
      updateDriverAvailability,
      updateRideStatus,
      transferRide,
      pushRideLocation,
      trackRide,
      sendMessage,
    ]
  );

  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
}

export function AppProvider({ children }) {
  return (
    <ReduxProvider store={store}>
      <AppContextBridge>{children}</AppContextBridge>
    </ReduxProvider>
  );
}

export function useAppContext() {
  const context = useContext(AppContext);

  if (!context) {
    throw new Error('useAppContext must be used within AppProvider.');
  }

  return context;
}
