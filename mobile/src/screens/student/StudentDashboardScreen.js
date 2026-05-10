import React from 'react';
import { Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { useAppShell } from '../../navigation/AppShellContext';

export default function StudentDashboardScreen({ navigation }) {
  const { currentUser, rides, logout } = useAppContext();
  const { exitToWelcome } = useAppShell();
  const ride = rides[0];

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="home" />}>
      <HeaderBlock
        eyebrow="Student Panel"
        title={`Hello, ${currentUser?.firstName || 'Student'}`}
        subtitle="Keep the student view calm and simple: vehicle status, ETA, pickup reminders, and a quick emergency action."
      />

      <SectionCard title="Today's service ride" subtitle={currentUser?.schoolName || 'Assigned school'}>
        {ride ? (
          <>
            <Pill label={ride.status} tone="warning" />
            <Text>Driver: {ride.driverName}</Text>
            <Text>Vehicle: {ride.vehicle}</Text>
            <Text>ETA: {ride.etaMinutes} minutes</Text>
            <Text>Distance left: {ride.distanceKm} km</Text>
            <AppButton label="View Live Map" onPress={() => navigation.navigate('ActiveRideMap')} />
          </>
        ) : (
          <Text>No assigned ride is active yet.</Text>
        )}
      </SectionCard>

      <SectionCard title="Student actions">
        <AppButton label="Open Notifications" variant="ghost" onPress={() => navigation.navigate('Notifications')} />
        <AppButton label="Emergency Alert" variant="secondary" onPress={() => navigation.navigate('Chat')} />
        <AppButton
          label="Logout"
          variant="ghost"
          onPress={() => {
            logout();
            exitToWelcome();
          }}
        />
      </SectionCard>
    </Screen>
  );
}
