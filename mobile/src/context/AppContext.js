import React, { createContext, useCallback, useContext, useMemo } from 'react';
import { Provider as ReduxProvider, useDispatch, useSelector } from 'react-redux';
import { appActions } from '../store/appSlice';
import { store } from '../store/store';

const AppContext = createContext(null);

function AppContextBridge({ children }) {
  const state = useSelector((currentState) => currentState.app);
  const dispatch = useDispatch();

  const loginAsRole = useCallback((role) => dispatch(appActions.loginAsRole(role)), [dispatch]);
  const logout = useCallback(() => dispatch(appActions.logout()), [dispatch]);
  const registerParent = useCallback((payload) => dispatch(appActions.registerParent(payload)), [dispatch]);
  const registerDriver = useCallback((payload) => dispatch(appActions.registerDriver(payload)), [dispatch]);
  const addStudent = useCallback((payload) => dispatch(appActions.addStudent(payload)), [dispatch]);
  const createBooking = useCallback((payload) => dispatch(appActions.createBooking(payload)), [dispatch]);
  const updateRideStatus = useCallback((status) => dispatch(appActions.updateRideStatus(status)), [dispatch]);
  const setTrackingActive = useCallback((isTracking) => dispatch(appActions.setTrackingActive(isTracking)), [dispatch]);
  const advanceRideSimulation = useCallback(() => dispatch(appActions.advanceRideSimulation()), [dispatch]);
  const resetRideSimulation = useCallback(() => dispatch(appActions.resetRideSimulation()), [dispatch]);
  const sendMessage = useCallback((text) => dispatch(appActions.sendMessage(text)), [dispatch]);

  const value = useMemo(
    () => ({
      ...state,
      loginAsRole,
      logout,
      registerParent,
      registerDriver,
      addStudent,
      createBooking,
      updateRideStatus,
      setTrackingActive,
      advanceRideSimulation,
      resetRideSimulation,
      sendMessage,
    }),
    [
      state,
      loginAsRole,
      logout,
      registerParent,
      registerDriver,
      addStudent,
      createBooking,
      updateRideStatus,
      setTrackingActive,
      advanceRideSimulation,
      resetRideSimulation,
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
