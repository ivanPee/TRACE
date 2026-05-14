import React, { useState } from 'react';
import { Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import StatGrid from '../../components/StatGrid';
import { useAppContext } from '../../context/AppContext';
import { useAppShell } from '../../navigation/AppShellContext';

export default function ParentDashboardScreen({ navigation }) {
  const { currentUser, students, bookings, rides, notifications, logout, refreshDashboard } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const { exitToWelcome } = useAppShell();
  const activeRide = rides[0];
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
        eyebrow="Parent Panel"
        title={`Welcome, ${currentUser?.firstName || 'Parent'}`}
        subtitle="Manage student records, submit bookings, and monitor transport status from one place."
      />

      <StatGrid
        items={[
          { label: 'Students', value: students.length, icon: 'child' },
          { label: 'Bookings', value: bookings.length, icon: 'calendar-check' },
          { label: 'Active Rides', value: rides.length, icon: 'route' },
          { label: 'Unread Alerts', value: notifications.length, icon: 'bell' },
        ]}
      />

      <SectionCard title="Current ride" subtitle="Quick view of the most recent assigned trip." tone="soft" icon="car">
        {activeRide ? (
          <>
            <Pill label={activeRide.status} tone="warning" />
            <Text>{activeRide.studentName} is assigned to {activeRide.driverName}.</Text>
            <Text>ETA: {activeRide.etaMinutes} minutes - Vehicle: {activeRide.vehicle}</Text>
            <Text>Distance left: {activeRide.distanceKm} km</Text>
            <AppButton icon="map-marked-alt" label="Track Live Ride" onPress={() => navigation.navigate('ActiveRideMap')} />
          </>
        ) : (
          <Text>No active ride yet. Register a student, choose a driver, then create a booking.</Text>
        )}
      </SectionCard>

      <SectionCard title="Parent actions" icon="th-large">
        <AppButton icon="child" label="Manage Students" onPress={() => navigation.navigate('Students')} />
        <AppButton icon="calendar-check" label="Open Bookings" variant="secondary" onPress={() => navigation.navigate('Bookings')} />
        <AppButton icon="history" label="View Transactions" variant="ghost" onPress={() => navigation.navigate('Transactions')} />
        <AppButton icon="bell" label="Open Notifications" variant="ghost" onPress={() => navigation.navigate('Notifications')} />
        <AppButton icon="comments" label="Chat with Driver" variant="ghost" onPress={() => navigation.navigate('Chat')} />
        <AppButton icon="user-circle" label="View Profile" variant="ghost" onPress={() => navigation.navigate('Profile')} />
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
