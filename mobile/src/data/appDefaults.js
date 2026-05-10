export const roles = [
  { key: 'parent', label: 'Parent', description: 'Books rides and manages student accounts.' },
  { key: 'driver', label: 'Driver', description: 'Handles trips, updates status, and shares GPS.' },
  { key: 'student', label: 'Student', description: 'Views trip progress and safety updates.' },
];

export const rideStatusSteps = ['Driver Arriving', 'Arrived', 'Picked Up', 'In Transit', 'Dropped Off', 'Completed'];

export const initialState = {
  currentRole: null,
  currentUser: null,
  token: null,
  loading: false,
  error: null,
  users: {},
  availableDrivers: [],
  students: [],
  bookings: [],
  rides: [],
  notifications: [],
  messages: [],
};
