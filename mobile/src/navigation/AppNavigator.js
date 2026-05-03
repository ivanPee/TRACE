import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAppContext } from '../context/AppContext';
import SplashScreen from '../screens/common/SplashScreen';
import WelcomeScreen from '../screens/auth/WelcomeScreen';
import LoginScreen from '../screens/auth/LoginScreen';
import RegisterRoleScreen from '../screens/auth/RegisterRoleScreen';
import RegisterParentScreen from '../screens/auth/RegisterParentScreen';
import RegisterDriverScreen from '../screens/auth/RegisterDriverScreen';
import ParentDashboardScreen from '../screens/parent/ParentDashboardScreen';
import StudentsScreen from '../screens/parent/StudentsScreen';
import AddStudentScreen from '../screens/parent/AddStudentScreen';
import BookRideScreen from '../screens/parent/BookRideScreen';
import DriverDashboardScreen from '../screens/driver/DriverDashboardScreen';
import DriverTripsScreen from '../screens/driver/DriverTripsScreen';
import StudentDashboardScreen from '../screens/student/StudentDashboardScreen';
import NotificationsScreen from '../screens/shared/NotificationsScreen';
import ChatScreen from '../screens/shared/ChatScreen';
import ProfileScreen from '../screens/shared/ProfileScreen';
import ActiveRideMapScreen from '../screens/tracking/ActiveRideMapScreen';

const Stack = createNativeStackNavigator();

export default function AppNavigator() {
  const { currentRole } = useAppContext();

  return (
    <Stack.Navigator
      initialRouteName="Splash"
      screenOptions={{
        headerShadowVisible: false,
        headerStyle: { backgroundColor: '#fffdf8' },
        contentStyle: { backgroundColor: '#fffdf8' },
      }}
    >
      <Stack.Screen name="Splash" component={SplashScreen} options={{ headerShown: false }} />
      <Stack.Screen name="Welcome" component={WelcomeScreen} options={{ headerShown: false }} />
      <Stack.Screen name="Login" component={LoginScreen} options={{ title: 'Login' }} />
      <Stack.Screen name="RegisterRole" component={RegisterRoleScreen} options={{ title: 'Choose Role' }} />
      <Stack.Screen name="RegisterParent" component={RegisterParentScreen} options={{ title: 'Parent Registration' }} />
      <Stack.Screen name="RegisterDriver" component={RegisterDriverScreen} options={{ title: 'Driver Registration' }} />
      <Stack.Screen name="ParentDashboard" component={ParentDashboardScreen} options={{ title: 'Parent Home' }} />
      <Stack.Screen name="Students" component={StudentsScreen} options={{ title: 'Students' }} />
      <Stack.Screen name="AddStudent" component={AddStudentScreen} options={{ title: 'Add Student' }} />
      <Stack.Screen name="BookRide" component={BookRideScreen} options={{ title: 'Book Ride' }} />
      <Stack.Screen name="DriverDashboard" component={DriverDashboardScreen} options={{ title: 'Driver Home' }} />
      <Stack.Screen name="DriverTrips" component={DriverTripsScreen} options={{ title: 'Driver Trips' }} />
      <Stack.Screen name="StudentDashboard" component={StudentDashboardScreen} options={{ title: 'Student Home' }} />
      <Stack.Screen name="Notifications" component={NotificationsScreen} options={{ title: 'Notifications' }} />
      <Stack.Screen name="Chat" component={ChatScreen} options={{ title: currentRole === 'driver' ? 'Parent Chat' : 'Driver Chat' }} />
      <Stack.Screen name="Profile" component={ProfileScreen} options={{ title: 'Profile' }} />
      <Stack.Screen name="ActiveRideMap" component={ActiveRideMapScreen} options={{ title: 'Live Tracking' }} />
    </Stack.Navigator>
  );
}
