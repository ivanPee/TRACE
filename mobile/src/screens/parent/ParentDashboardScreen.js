import React from 'react';
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
  const { currentUser, students, bookings, rides, notifications, logout } = useAppContext();
  const { exitToWelcome } = useAppShell();
  const activeRide = rides[0];

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="home" />}>
      <HeaderBlock
        eyebrow="Parent Panel"
        title={`Welcome, ${currentUser?.firstName || 'Parent'}`}
        subtitle="Manage student records, submit bookings, and monitor transport status from one place."
      />

      <StatGrid
        items={[
          { label: 'Students', value: students.length },
          { label: 'Bookings', value: bookings.length },
          { label: 'Active Rides', value: rides.length },
          { label: 'Unread Alerts', value: notifications.length },
        ]}
      />

      <SectionCard title="Current ride" subtitle="Quick view of the most recent assigned trip." tone="soft">
        {activeRide ? (
          <>
            <Pill label={activeRide.status} tone="warning" />
            <Text>{activeRide.studentName} is assigned to {activeRide.driverName}.</Text>
            <Text>ETA: {activeRide.etaMinutes} minutes - Vehicle: {activeRide.vehicle}</Text>
            <Text>Distance left: {activeRide.distanceKm} km</Text>
            <AppButton label="Track Live Ride" onPress={() => navigation.navigate('ActiveRideMap')} />
          </>
        ) : (
          <Text>No active ride yet. Register a student, choose a driver, then create a booking.</Text>
        )}
      </SectionCard>

      <SectionCard title="Parent actions">
        <AppButton label="Manage Students" onPress={() => navigation.navigate('Students')} />
        <AppButton label="Open Bookings" variant="secondary" onPress={() => navigation.navigate('Bookings')} />
        <AppButton label="View Transactions" variant="ghost" onPress={() => navigation.navigate('Transactions')} />
        <AppButton label="Open Notifications" variant="ghost" onPress={() => navigation.navigate('Notifications')} />
        <AppButton label="Chat with Driver" variant="ghost" onPress={() => navigation.navigate('Chat')} />
        <AppButton label="View Profile" variant="ghost" onPress={() => navigation.navigate('Profile')} />
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
