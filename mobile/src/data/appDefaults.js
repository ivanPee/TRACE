export const roles = [
  { key: 'parent', label: 'Parent', description: 'Books trips and manages child accounts.' },
  { key: 'driver', label: 'Driver', description: 'Handles trips, updates status, and shares GPS.' },
  { key: 'student', label: 'Child', description: 'Views trip progress and safety updates.' },
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
  monthlyPlan: null,
  monthlyPlans: [],
  billing: null,
  rides: [],
  transactions: [],
  notifications: [],
  messages: [],
};
