import { createSlice } from '@reduxjs/toolkit';
import { initialState, rideStatusSteps } from '../data/mockData';

const appSlice = createSlice({
  name: 'app',
  initialState,
  reducers: {
    loginAsRole(state, action) {
      const role = action.payload;
      state.currentRole = role;
      state.currentUser = state.users[role];
    },
    logout(state) {
      state.currentRole = null;
      state.currentUser = null;
    },
    registerParent(state, action) {
      const payload = action.payload;
      const parent = {
        id: 'parent-custom',
        role: 'parent',
        firstName: payload.firstName,
        lastName: payload.lastName,
        email: payload.email,
        mobileNumber: payload.mobileNumber,
        address: payload.address,
      };

      state.users.parent = parent;
      state.currentRole = 'parent';
      state.currentUser = parent;
    },
    registerDriver(state, action) {
      const payload = action.payload;
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

      state.users.driver = driver;
      state.currentRole = 'driver';
      state.currentUser = driver;
      state.notifications.unshift({
        id: `notif-driver-${Date.now()}`,
        role: 'driver',
        title: 'Registration received',
        body: 'Your driver application is pending admin approval.',
        time: 'Just now',
      });
    },
    addStudent(state, action) {
      const payload = action.payload;
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

      state.students.unshift(newStudent);
      state.notifications.unshift({
        id: `notif-student-${Date.now()}`,
        role: 'parent',
        title: 'Student account added',
        body: `${payload.studentName} is now linked to your account.`,
        time: 'Just now',
      });
    },
    createBooking(state, action) {
      const payload = action.payload;
      state.bookings.unshift({
        id: `booking-${Date.now()}`,
        studentId: payload.studentId,
        studentName: payload.studentName,
        pickupAddress: payload.pickupAddress,
        dropoffAddress: payload.dropoffAddress,
        scheduledDate: payload.scheduledDate,
        scheduledTime: payload.scheduledTime,
        tripType: payload.tripType,
        status: 'pending',
        driverName: 'To be assigned',
      });
      state.notifications.unshift({
        id: `notif-booking-${Date.now()}`,
        role: 'parent',
        title: 'Booking submitted',
        body: `${payload.studentName}'s ride request is waiting for assignment.`,
        time: 'Just now',
      });
    },
    updateRideStatus(state, action) {
      const status = action.payload;
      const ride = state.rides[0];

      if (!ride) {
        return;
      }

      ride.status = status;
      if (status === 'Completed') {
        ride.progress = 1;
        ride.etaMinutes = 0;
        ride.distanceKm = 0;
        ride.isTracking = false;
      }
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

      if (!ride || ride.status === 'Completed') {
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

      if (!ride) {
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
        id: `msg-${Date.now()}`,
        senderRole: state.currentRole,
        senderName: sender,
        receiverRole,
        text: text.trim(),
        time: 'Just now',
      });
    },
  },
});

export const appActions = appSlice.actions;
export default appSlice.reducer;
