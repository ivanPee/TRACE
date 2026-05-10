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

export default function DriverDashboardScreen({ navigation }) {
  const { currentUser, rides, bookings, logout } = useAppContext();
  const { exitToWelcome } = useAppShell();
  const ride = rides[0];

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="home" />}>
      <HeaderBlock
        eyebrow="Driver Panel"
        title={`Welcome, ${currentUser?.firstName || 'Driver'}`}
        subtitle="Track assignments, switch ride statuses, and keep parents updated in real time."
      />

      <StatGrid
        items={[
          { label: 'Assigned Trips', value: bookings.length },
          { label: 'Active Rides', value: rides.length },
          { label: 'ETA', value: ride ? `${ride.etaMinutes}m` : '-' },
          { label: 'Approval', value: currentUser?.approvalStatus === 'approved' ? 'OK' : 'PENDING' },
        ]}
      />

      <SectionCard title="Next active ride" subtitle="Use this area to begin a pickup workflow." tone="soft">
        {ride ? (
          <>
            <Pill label={ride.status} tone="warning" />
            <Text>Student: {ride.studentName}</Text>
            <Text>Parent: {ride.parentName}</Text>
            <Text>Pickup Time: {ride.pickupTime}</Text>
            <Text>Vehicle: {ride.vehicle}</Text>
            <Text>Distance left: {ride.distanceKm} km</Text>
            <AppButton label="Open Trip Controls" onPress={() => navigation.navigate('DriverTrips')} />
          </>
        ) : (
          <Text>No assigned students yet. Assignments appear after parents select you for a booking.</Text>
        )}
      </SectionCard>

      <SectionCard title="Driver tools">
        <AppButton label="Open Booking Manager" onPress={() => navigation.navigate('Bookings')} />
        <AppButton label="Track Live Route" variant="secondary" onPress={() => navigation.navigate('ActiveRideMap')} />
        <AppButton label="View Transactions" variant="ghost" onPress={() => navigation.navigate('Transactions')} />
        <AppButton label="Open Notifications" variant="ghost" onPress={() => navigation.navigate('Notifications')} />
        <AppButton label="Chat with Parent" variant="ghost" onPress={() => navigation.navigate('Chat')} />
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
