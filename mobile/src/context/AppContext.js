import React, { createContext, useContext, useMemo, useState } from 'react';
import { initialState } from '../data/mockData';

const AppContext = createContext(null);

export function AppProvider({ children }) {
  const [state, setState] = useState(initialState);

  const loginAsRole = (role) => {
    setState((current) => ({
      ...current,
      currentRole: role,
      currentUser: current.users[role],
    }));
  };

  const logout = () => {
    setState((current) => ({
      ...current,
      currentRole: null,
      currentUser: null,
    }));
  };

  const registerParent = (payload) => {
    const parent = {
      id: 'parent-custom',
      role: 'parent',
      firstName: payload.firstName,
      lastName: payload.lastName,
      email: payload.email,
      mobileNumber: payload.mobileNumber,
      address: payload.address,
    };

    setState((current) => ({
      ...current,
      users: {
        ...current.users,
        parent,
      },
      currentRole: 'parent',
      currentUser: parent,
    }));
  };

  const registerDriver = (payload) => {
    const driver = {
      id: 'driver-custom',
      role: 'driver',
      firstName: payload.firstName,
      lastName: payload.lastName,
      email: payload.email,
      mobileNumber: payload.mobileNumber,
      licenseNumber: payload.licenseNumber,
      vehiclePlateNumber: payload.vehiclePlateNumber,
      vehicleModel: payload.vehicleModel,
      approvalStatus: 'pending',
    };

    setState((current) => ({
      ...current,
      users: {
        ...current.users,
        driver,
      },
      currentRole: 'driver',
      currentUser: driver,
      notifications: [
        {
          id: `notif-driver-${Date.now()}`,
          role: 'driver',
          title: 'Registration received',
          body: 'Your driver application is pending admin approval.',
          time: 'Just now',
        },
        ...current.notifications,
      ],
    }));
  };

  const addStudent = (payload) => {
    const newStudent = {
      id: `student-${Date.now()}`,
      userId: `student-user-${Date.now()}`,
      name: payload.studentName,
      lrn: payload.lrn,
      schoolName: payload.schoolName,
      gradeLevel: payload.gradeLevel,
      pickupAddress: payload.pickupAddress,
      dropoffAddress: payload.dropoffAddress,
      emergencyContact: payload.emergencyContact,
      notes: payload.notes,
    };

    setState((current) => ({
      ...current,
      students: [newStudent, ...current.students],
      notifications: [
        {
          id: `notif-student-${Date.now()}`,
          role: 'parent',
          title: 'Student account added',
          body: `${payload.studentName} is now linked to your account.`,
          time: 'Just now',
        },
        ...current.notifications,
      ],
    }));
  };

  const createBooking = (payload) => {
    const booking = {
      id: `booking-${Date.now()}`,
      studentId: payload.studentId,
      studentName: payload.studentName,
      pickupAddress: payload.pickupAddress,
      dropoffAddress: payload.dropoffAddress,
      scheduledDate: payload.scheduledDate,
      scheduledTime: payload.scheduledTime,
      tripType: payload.tripType,
      status: 'pending',
      fare: 'PHP 150.00',
      driverName: 'To be assigned',
    };

    setState((current) => ({
      ...current,
      bookings: [booking, ...current.bookings],
      notifications: [
        {
          id: `notif-booking-${Date.now()}`,
          role: 'parent',
          title: 'Booking submitted',
          body: `${payload.studentName}'s ride request is waiting for assignment.`,
          time: 'Just now',
        },
        ...current.notifications,
      ],
    }));
  };

  const updateRideStatus = (status) => {
    setState((current) => ({
      ...current,
      rides: current.rides.map((ride, index) =>
        index === 0
          ? {
              ...ride,
              status,
            }
          : ride
      ),
    }));
  };

  const sendMessage = (text) => {
    if (!state.currentRole || !text.trim()) {
      return;
    }

    const receiverRole = state.currentRole === 'parent' ? 'driver' : 'parent';
    const sender = state.currentUser?.firstName || 'TRACE User';

    setState((current) => ({
      ...current,
      messages: [
        ...current.messages,
        {
          id: `msg-${Date.now()}`,
          senderRole: current.currentRole,
          senderName: sender,
          receiverRole,
          text: text.trim(),
          time: 'Just now',
        },
      ],
    }));
  };

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
      sendMessage,
    }),
    [state]
  );

  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
}

export function useAppContext() {
  const context = useContext(AppContext);

  if (!context) {
    throw new Error('useAppContext must be used within AppProvider.');
  }

  return context;
}
