import React, { useState } from 'react';
import { Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import DriverProfileCard from '../../components/DriverProfileCard';
import HeaderBlock from '../../components/HeaderBlock';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { useAppShell } from '../../navigation/AppShellContext';

export default function StudentDashboardScreen({ navigation }) {
  const { currentUser, rides, logout, refreshDashboard } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const { exitToWelcome } = useAppShell();
  const ride = rides[0];
  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="home" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="Child Panel"
        title={`Hello, ${currentUser?.firstName || 'Child'}`}
        subtitle="Keep the child view calm and simple: vehicle status, ETA, pickup reminders, and a quick emergency action."
      />

      <SectionCard title="Today's service trip" subtitle={currentUser?.schoolName || 'Assigned school'} icon="route">
        {ride ? (
          <>
            <Pill label={ride.status} tone="warning" />
            <DriverProfileCard ride={ride} context="active" />
            <Text>Driver: {ride.driverName}</Text>
            <Text>Vehicle: {ride.vehicle}</Text>
            <Text>ETA: {ride.etaMinutes} minutes</Text>
            <Text>Distance left: {ride.distanceKm} km</Text>
            <AppButton icon="map-marked-alt" label="View Live Map" onPress={() => navigation.navigate('ActiveRideMap')} />
          </>
        ) : (
          <Text>No assigned trip is active yet.</Text>
        )}
      </SectionCard>

      <SectionCard title="Child actions" icon="th-large">
        <AppButton icon="bell" label="Open Notifications" variant="ghost" onPress={() => navigation.navigate('Notifications')} />
        <AppButton icon="comments" label="Messages" variant="secondary" onPress={() => navigation.navigate('Chat')} />
        <AppButton
          icon="sign-out-alt"
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
