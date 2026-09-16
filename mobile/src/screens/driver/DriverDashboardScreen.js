import React, { useState } from 'react';
import { Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import DriverProfileCard from '../../components/DriverProfileCard';
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
  const pendingTransports = bookings.filter((booking) => booking.canApprove);
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
        subtitle="Track assignments, switch trip statuses, and keep parents updated in real time."
      />

      <StatGrid
        items={[
          { label: 'Today Transport', value: bookings.length, icon: 'calendar-alt' },
          { label: 'Active Trips', value: rides.length, icon: 'route' },
          { label: 'ETA', value: ride ? `${ride.etaMinutes}m` : '-', icon: 'stopwatch' },
          { label: 'Open Routes', value: pendingTransports.length, icon: 'bell' },
        ]}
      />

      <SectionCard title="Availability" subtitle="Online drivers can claim open daily transport routes." icon="broadcast-tower">
        <DriverProfileCard driver={currentUser} context="profile" />
        <Pill label={isOnline ? 'Online' : 'Offline'} tone={isOnline ? 'success' : 'warning'} />
        <AppButton icon="power-off" label={isOnline ? 'Go Offline' : 'Go Online'} variant="secondary" onPress={handleAvailability} />
      </SectionCard>

      <SectionCard title="Next active trip" subtitle="Use this area to begin a pickup workflow." tone="soft" icon="car-side">
        {ride ? (
          <>
            <Pill label={ride.status} tone="warning" />
            <Text>Child: {ride.studentName}</Text>
            <Text>Parent: {ride.parentName}</Text>
            <Text>Pickup Time: {ride.pickupTime}</Text>
            <Text>Vehicle: {ride.vehicle}</Text>
            <Text>Distance left: {ride.distanceKm} km</Text>
            <AppButton icon="tasks" label="Open Trip Controls" onPress={() => navigation.navigate('DriverTrips')} />
          </>
        ) : (
          <Text>No assigned children yet. Claim a transport route from the transport manager.</Text>
        )}
      </SectionCard>

      <SectionCard title="Driver tools" icon="th-large">
        <AppButton icon="calendar-check" label="Open Transport Manager" onPress={() => navigation.navigate('Bookings')} />
        <AppButton icon="route" label="Track Live Route" variant="secondary" onPress={() => navigation.navigate('ActiveRideMap')} />
        <AppButton icon="history" label="View Transactions" variant="ghost" onPress={() => navigation.navigate('Transactions')} />
        <AppButton icon="bell" label="Open Notifications" variant="ghost" onPress={() => navigation.navigate('Notifications')} />
        <AppButton icon="comments" label="Messages" variant="ghost" onPress={() => navigation.navigate('Chat')} />
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
