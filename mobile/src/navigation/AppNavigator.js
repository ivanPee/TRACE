import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { colors } from '../theme/colors';
import SplashScreen from '../screens/common/SplashScreen';
import WelcomeScreen from '../screens/auth/WelcomeScreen';
import LoginScreen from '../screens/auth/LoginScreen';
import RegisterRoleScreen from '../screens/auth/RegisterRoleScreen';
import RegisterParentScreen from '../screens/auth/RegisterParentScreen';
import RegisterDriverScreen from '../screens/auth/RegisterDriverScreen';
import AppShell from './AppShell';

const Stack = createNativeStackNavigator();

export default function AppNavigator() {
  return (
    <Stack.Navigator
      initialRouteName="Splash"
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
      <Stack.Screen name="Splash" component={SplashScreen} options={{ headerShown: false }} />
      <Stack.Screen name="Welcome" component={WelcomeScreen} options={{ headerShown: false }} />
      <Stack.Screen name="Login" component={LoginScreen} options={{ title: 'Login' }} />
      <Stack.Screen name="RegisterRole" component={RegisterRoleScreen} options={{ title: 'Choose Role' }} />
      <Stack.Screen name="RegisterParent" component={RegisterParentScreen} options={{ title: 'Parent Registration' }} />
      <Stack.Screen name="RegisterDriver" component={RegisterDriverScreen} options={{ title: 'Driver Registration' }} />
      <Stack.Screen name="MainApp" component={AppShell} options={{ headerShown: false, animation: 'fade' }} />
    </Stack.Navigator>
  );
}
