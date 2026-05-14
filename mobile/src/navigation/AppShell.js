import React, { useState } from 'react';
import { NavigationContainer, StackActions, useNavigationContainerRef, useNavigation } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { SafeAreaView } from 'react-native-safe-area-context';
import { StyleSheet, View } from 'react-native';
import AppNavBar from '../components/AppNavBar';
import { useAppContext } from '../context/AppContext';
import { colors } from '../theme/colors';
import ParentDashboardScreen from '../screens/parent/ParentDashboardScreen';
import StudentsScreen from '../screens/parent/StudentsScreen';
import AddStudentScreen from '../screens/parent/AddStudentScreen';
import BookRideScreen from '../screens/parent/BookRideScreen';
import DriverDashboardScreen from '../screens/driver/DriverDashboardScreen';
import DriverTripsScreen from '../screens/driver/DriverTripsScreen';
import StudentDashboardScreen from '../screens/student/StudentDashboardScreen';
import NotificationsScreen from '../screens/shared/NotificationsScreen';
import ChatScreen from '../screens/shared/ChatScreen';
import BookingsScreen from '../screens/shared/BookingsScreen';
import ProfileScreen from '../screens/shared/ProfileScreen';
import TransactionsScreen from '../screens/shared/TransactionsScreen';
import ActiveRideMapScreen from '../screens/tracking/ActiveRideMapScreen';
import { AppShellContext } from './AppShellContext';
import { getActiveNavKey, getShellInitialRoute } from './appShellConfig';

const ShellStack = createNativeStackNavigator();

export default function AppShell() {
  const rootNavigation = useNavigation();
  const appNavigationRef = useNavigationContainerRef();
  const { currentRole } = useAppContext();
  const initialRouteName = getShellInitialRoute(currentRole);
  const [activeRouteName, setActiveRouteName] = useState(initialRouteName);
  const activeKey = getActiveNavKey(currentRole, activeRouteName);

  const exitToWelcome = () => {
    rootNavigation.reset({ index: 0, routes: [{ name: 'Welcome' }] });
  };

  const syncRouteName = () => {
    const currentRoute = appNavigationRef.getCurrentRoute();
    if (currentRoute?.name) {
      setActiveRouteName(currentRoute.name);
    }
  };

  const handleTabPress = (routeName) => {
    if (!appNavigationRef.isReady() || activeRouteName === routeName) {
      return;
    }

    appNavigationRef.dispatch(StackActions.replace(routeName));
  };

  return (
    <AppShellContext.Provider value={{ isInAppShell: true, exitToWelcome }}>
      <SafeAreaView edges={['bottom']} style={styles.safeArea}>
        <View style={styles.container}>
          <View style={styles.content}>
            <NavigationContainer independent ref={appNavigationRef} onReady={syncRouteName} onStateChange={syncRouteName}>
              <ShellStack.Navigator
                key={currentRole || 'guest'}
                initialRouteName={initialRouteName}
                screenOptions={{
                  headerShadowVisible: false,
                  headerBackTitleVisible: false,
                  headerTintColor: colors.ink,
                  headerTitleStyle: {
                    color: colors.ink,
                    fontWeight: '800',
                  },
                  headerStyle: { backgroundColor: colors.paper },
                  contentStyle: { backgroundColor: colors.paper },
                  animation: 'slide_from_right',
                }}
              >
                <ShellStack.Screen name="ParentDashboard" component={ParentDashboardScreen} options={{ title: 'Parent Home' }} />
                <ShellStack.Screen name="Students" component={StudentsScreen} options={{ title: 'Students' }} />
                <ShellStack.Screen name="AddStudent" component={AddStudentScreen} options={{ title: 'Add Student' }} />
                <ShellStack.Screen name="BookRide" component={BookRideScreen} options={{ title: 'Book Ride' }} />
                <ShellStack.Screen name="Bookings" component={BookingsScreen} options={{ title: 'Bookings' }} />
                <ShellStack.Screen name="DriverDashboard" component={DriverDashboardScreen} options={{ title: 'Driver Home' }} />
                <ShellStack.Screen name="DriverTrips" component={DriverTripsScreen} options={{ title: 'Driver Trips' }} />
                <ShellStack.Screen name="StudentDashboard" component={StudentDashboardScreen} options={{ title: 'Student Home' }} />
                <ShellStack.Screen name="Notifications" component={NotificationsScreen} options={{ title: 'Notifications' }} />
                <ShellStack.Screen name="Chat" component={ChatScreen} options={{ title: currentRole === 'student' ? 'Parent Chat' : currentRole === 'driver' ? 'Trip Chat' : 'Driver Chat' }} />
                <ShellStack.Screen name="Profile" component={ProfileScreen} options={{ title: 'Profile' }} />
                <ShellStack.Screen name="Transactions" component={TransactionsScreen} options={{ title: 'Transactions' }} />
                <ShellStack.Screen name="ActiveRideMap" component={ActiveRideMapScreen} options={{ title: 'Live Tracking' }} />
              </ShellStack.Navigator>
            </NavigationContainer>
          </View>
          <AppNavBar navigation={appNavigationRef} active={activeKey} onTabPress={handleTabPress} />
        </View>
      </SafeAreaView>
    </AppShellContext.Provider>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: colors.deep,
  },
  container: {
    flex: 1,
    backgroundColor: colors.paper,
  },
  content: {
    flex: 1,
    backgroundColor: colors.paper,
  },
});
