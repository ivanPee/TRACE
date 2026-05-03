import React from 'react';
import { Text } from 'react-native';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import StatGrid from '../../components/StatGrid';
import { useAppContext } from '../../context/AppContext';

export default function ParentDashboardScreen({ navigation }) {
  const { currentUser, students, bookings, rides, logout } = useAppContext();
  const activeRide = rides[0];

  return (
    <Screen>
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
          { label: 'Unread Alerts', value: 2 },
        ]}
      />

      <SectionCard title="Current ride" subtitle="Quick view of the most recent assigned trip." tone="soft">
        <Pill label={activeRide.status} tone="warning" />
        <Text>{activeRide.studentName} is assigned to {activeRide.driverName}.</Text>
        <Text>ETA: {activeRide.etaMinutes} minutes • Vehicle: {activeRide.vehicle}</Text>
        <AppButton label="Track Live Ride" onPress={() => navigation.navigate('ActiveRideMap')} />
      </SectionCard>

      <SectionCard title="Parent actions">
        <AppButton label="Manage Students" onPress={() => navigation.navigate('Students')} />
        <AppButton label="Book a New Ride" variant="secondary" onPress={() => navigation.navigate('BookRide')} />
        <AppButton label="Open Notifications" variant="ghost" onPress={() => navigation.navigate('Notifications')} />
        <AppButton label="Chat with Driver" variant="ghost" onPress={() => navigation.navigate('Chat')} />
        <AppButton label="View Profile" variant="ghost" onPress={() => navigation.navigate('Profile')} />
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
