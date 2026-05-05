import React from 'react';
import { Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import Pill from '../../components/Pill';
import RoleSimulator from '../../components/RoleSimulator';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function StudentDashboardScreen({ navigation }) {
  const { currentRole, currentUser, rides, logout, loginAsRole } = useAppContext();
  const ride = rides[0];

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="home" />}>
      <RoleSimulator currentRole={currentRole} loginAsRole={loginAsRole} navigation={navigation} />

      <HeaderBlock
        eyebrow="Student Panel"
        title={`Hello, ${currentUser?.firstName || 'Student'}`}
        subtitle="Keep the student view calm and simple: vehicle status, ETA, pickup reminders, and a quick emergency action."
      />

      <SectionCard title="Today's service ride" subtitle={currentUser?.schoolName || 'Assigned school'}>
        <Pill label={ride.status} tone="warning" />
        <Text>Driver: {ride.driverName}</Text>
        <Text>Vehicle: {ride.vehicle}</Text>
        <Text>ETA: {ride.etaMinutes} minutes</Text>
        <Text>Distance left: {ride.distanceKm} km</Text>
        <AppButton label="View Live Map" onPress={() => navigation.navigate('ActiveRideMap')} />
      </SectionCard>

      <SectionCard title="Student actions">
        <AppButton label="Open Notifications" variant="ghost" onPress={() => navigation.navigate('Notifications')} />
        <AppButton label="Emergency Alert" variant="secondary" onPress={() => navigation.navigate('Chat')} />
        <AppButton
          label="Logout"
          variant="ghost"
          onPress={() => {
            logout();
            navigation.reset({ index: 0, routes: [{ name: 'Welcome' }] });
          }}
        />
      </SectionCard>
    </Screen>
  );
}
