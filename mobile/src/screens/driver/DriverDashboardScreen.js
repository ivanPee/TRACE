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

export default function DriverDashboardScreen({ navigation }) {
  const { currentUser, rides, bookings, logout, refreshDashboard, updateDriverAvailability } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const [isOnline, setIsOnline] = useState(true);
  const { exitToWelcome } = useAppShell();
  const ride = rides[0];
  const pendingBookings = bookings.filter((booking) => booking.canApprove);
  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };
  const handleAvailability = async () => {
    const nextValue = !isOnline;
    setIsOnline(nextValue);
    await updateDriverAvailability(nextValue);
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="home" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="Driver Panel"
        title={`Welcome, ${currentUser?.firstName || 'Driver'}`}
        subtitle="Track assignments, switch ride statuses, and keep parents updated in real time."
      />

      <StatGrid
        items={[
          { label: 'Assigned Trips', value: bookings.length, icon: 'calendar-alt' },
          { label: 'Active Rides', value: rides.length, icon: 'route' },
          { label: 'ETA', value: ride ? `${ride.etaMinutes}m` : '-', icon: 'stopwatch' },
          { label: 'Requests', value: pendingBookings.length, icon: 'bell' },
        ]}
      />

      <SectionCard title="Availability" subtitle="Parents can see this while choosing a driver." icon="broadcast-tower">
        <Pill label={isOnline ? 'Online' : 'Offline'} tone={isOnline ? 'success' : 'warning'} />
        <AppButton icon="power-off" label={isOnline ? 'Go Offline' : 'Go Online'} variant="secondary" onPress={handleAvailability} />
      </SectionCard>

      <SectionCard title="Next active ride" subtitle="Use this area to begin a pickup workflow." tone="soft" icon="car-side">
        {ride ? (
          <>
            <Pill label={ride.status} tone="warning" />
            <Text>Student: {ride.studentName}</Text>
            <Text>Parent: {ride.parentName}</Text>
            <Text>Pickup Time: {ride.pickupTime}</Text>
            <Text>Vehicle: {ride.vehicle}</Text>
            <Text>Distance left: {ride.distanceKm} km</Text>
            <AppButton icon="tasks" label="Open Trip Controls" onPress={() => navigation.navigate('DriverTrips')} />
          </>
        ) : (
          <Text>No assigned students yet. Assignments appear after parents select you for a booking.</Text>
        )}
      </SectionCard>

      <SectionCard title="Driver tools" icon="th-large">
        <AppButton icon="calendar-check" label="Open Booking Manager" onPress={() => navigation.navigate('Bookings')} />
        <AppButton icon="route" label="Track Live Route" variant="secondary" onPress={() => navigation.navigate('ActiveRideMap')} />
        <AppButton icon="history" label="View Transactions" variant="ghost" onPress={() => navigation.navigate('Transactions')} />
        <AppButton icon="bell" label="Open Notifications" variant="ghost" onPress={() => navigation.navigate('Notifications')} />
        <AppButton icon="comments" label="Chat with Parent" variant="ghost" onPress={() => navigation.navigate('Chat')} />
        <AppButton icon="user-circle" label="View Profile" variant="ghost" onPress={() => navigation.navigate('Profile')} />
        <AppButton
          icon="sign-out-alt"
          label="Logout"
          variant="ghost"
          onPress={async () => {
            await logout();
            exitToWelcome();
          }}
        />
      </SectionCard>
    </Screen>
  );
}
