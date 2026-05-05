import React from 'react';
import { Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import Pill from '../../components/Pill';
import RoleSimulator from '../../components/RoleSimulator';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import StatGrid from '../../components/StatGrid';
import { useAppContext } from '../../context/AppContext';

export default function DriverDashboardScreen({ navigation }) {
  const { currentRole, currentUser, rides, bookings, logout, loginAsRole } = useAppContext();
  const ride = rides[0];

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="home" />}>
      <RoleSimulator currentRole={currentRole} loginAsRole={loginAsRole} navigation={navigation} />

      <HeaderBlock
        eyebrow="Driver Panel"
        title={`Welcome, ${currentUser?.firstName || 'Driver'}`}
        subtitle="Track assignments, switch ride statuses, and keep parents updated in real time."
      />

      <StatGrid
        items={[
          { label: 'Assigned Trips', value: bookings.length },
          { label: 'Active Rides', value: rides.length },
          { label: 'ETA', value: `${ride.etaMinutes}m` },
          { label: 'Approval', value: currentUser?.approvalStatus === 'approved' ? 'OK' : 'PENDING' },
        ]}
      />

      <SectionCard title="Next active ride" subtitle="Use this area to begin a pickup workflow." tone="soft">
        <Pill label={ride.status} tone="warning" />
        <Text>Student: {ride.studentName}</Text>
        <Text>Parent: {ride.parentName}</Text>
        <Text>Pickup Time: {ride.pickupTime}</Text>
        <Text>Vehicle: {ride.vehicle}</Text>
        <Text>Distance left: {ride.distanceKm} km</Text>
        <AppButton label="Open Trip Controls" onPress={() => navigation.navigate('DriverTrips')} />
      </SectionCard>

      <SectionCard title="Driver tools">
        <AppButton label="Track Live Route" onPress={() => navigation.navigate('ActiveRideMap')} />
        <AppButton label="Open Notifications" variant="ghost" onPress={() => navigation.navigate('Notifications')} />
        <AppButton label="Chat with Parent" variant="ghost" onPress={() => navigation.navigate('Chat')} />
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
