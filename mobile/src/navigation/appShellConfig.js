export const shellTabsByRole = {
  parent: [
    { key: 'home', route: 'ParentDashboard', icon: 'home', label: 'Home' },
    { key: 'students', route: 'Students', icon: 'students', label: 'Children' },
    { key: 'plans', route: 'MonthlyPlanManage', icon: 'plans', label: 'Plan' },
    { key: 'transactions', route: 'Transactions', icon: 'transactions', label: 'Transactions' },
    { key: 'profile', route: 'Profile', icon: 'profile', label: 'Profile' },
  ],
  driver: [
    { key: 'home', route: 'DriverDashboard', icon: 'home', label: 'Home' },
    { key: 'students', route: 'Students', icon: 'students', label: 'Children' },
    { key: 'bookings', route: 'Bookings', icon: 'route', label: 'Transport' },
    { key: 'transactions', route: 'Transactions', icon: 'transactions', label: 'Transactions' },
    { key: 'profile', route: 'Profile', icon: 'profile', label: 'Profile' },
  ],
  student: [
    { key: 'home', route: 'StudentDashboard', icon: 'home', label: 'Home' },
    { key: 'ride', route: 'ActiveRideMap', icon: 'ride', label: 'Trip' },
    { key: 'support', route: 'Chat', icon: 'support', label: 'Support' },
    { key: 'profile', route: 'Profile', icon: 'profile', label: 'Profile' },
  ],
};

export const defaultRouteByRole = {
  parent: 'ParentDashboard',
  driver: 'DriverDashboard',
  student: 'StudentDashboard',
};

const activeNavByRole = {
  parent: {
    ParentDashboard: 'home',
    Students: 'students',
    AddStudent: 'students',
    MonthlyPlanManage: 'plans',
    BookRide: 'plans',
    Bookings: 'plans',
    Chat: 'plans',
    Notifications: 'plans',
    ActiveRideMap: 'plans',
    Transactions: 'transactions',
    Profile: 'profile',
  },
  driver: {
    DriverDashboard: 'home',
    Students: 'students',
    Bookings: 'bookings',
    DriverTrips: 'bookings',
    Chat: 'bookings',
    Notifications: 'bookings',
    ActiveRideMap: 'bookings',
    Transactions: 'transactions',
    Profile: 'profile',
  },
  student: {
    StudentDashboard: 'home',
    ActiveRideMap: 'ride',
    Chat: 'support',
    Notifications: 'support',
    Profile: 'profile',
  },
};

export function getShellInitialRoute(role) {
  return defaultRouteByRole[role] || 'ParentDashboard';
}

export function getActiveNavKey(role, routeName) {
  return activeNavByRole[role]?.[routeName] || shellTabsByRole[role || 'parent']?.[0]?.key || 'home';
}
