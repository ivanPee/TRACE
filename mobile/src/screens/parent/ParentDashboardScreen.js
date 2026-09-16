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
        subtitle="Register child accounts, set monthly plans, and track the current daily trip."
      />

      <StatGrid
        items={[
          { label: 'Children', value: students.length, icon: 'child' },
          { label: 'Daily Routes', value: bookings.length, icon: 'calendar-check' },
          { label: 'Active Trips', value: rides.length, icon: 'route' },
          { label: 'Unread Alerts', value: notifications.length, icon: 'bell' },
        ]}
      />

      <SectionCard title="Current trip" subtitle="Quick view of the most recent assigned trip." tone="soft" icon="car">
        {activeRide ? (
          <>
            <Pill label={activeRide.status} tone="warning" />
            <DriverProfileCard ride={activeRide} context="active" />
            <Text>{activeRide.studentName} is assigned to {activeRide.driverName}.</Text>
            <Text>ETA: {activeRide.etaMinutes} minutes - Vehicle: {activeRide.vehicle}</Text>
            <Text>Distance left: {activeRide.distanceKm} km</Text>
            <AppButton icon="map-marked-alt" label="Track Live Trip" onPress={() => navigation.navigate('ActiveRideMap')} />
          </>
        ) : (
          <Text>No active trip yet. Paid monthly plans become daily transport routes once a driver claims them.</Text>
        )}
      </SectionCard>

      <SectionCard title="Parent actions" icon="th-large">
        <AppButton icon="child" label="Manage Children" onPress={() => navigation.navigate('Students')} />
        <AppButton icon="calendar-check" label="Manage Monthly Plan" onPress={() => navigation.navigate('MonthlyPlanManage')} />
        <AppButton icon="calendar-plus" label="Create Transport Plan" variant="secondary" onPress={() => navigation.navigate('BookRide')} />
        <AppButton icon="route" label="View Transport Logs" variant="ghost" onPress={() => navigation.navigate('Bookings')} />
        <AppButton icon="history" label="View Transactions" variant="ghost" onPress={() => navigation.navigate('Transactions')} />
        <AppButton icon="bell" label="Open Notifications" variant="ghost" onPress={() => navigation.navigate('Notifications')} />
        <AppButton icon="comments" label="Messages" variant="ghost" onPress={() => navigation.navigate('Chat')} />
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
