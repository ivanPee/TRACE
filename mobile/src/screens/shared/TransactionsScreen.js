import React from 'react';
import AppNavBar from '../../components/AppNavBar';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import StatGrid from '../../components/StatGrid';
import { useAppContext } from '../../context/AppContext';

export default function TransactionsScreen({ navigation }) {
  const { currentRole, bookings, rides } = useAppContext();
  const completedRides = rides.filter((ride) => ride.status === 'Completed');
  const pendingBookings = bookings.filter((booking) => booking.status !== 'assigned').length;
  const assignedBookings = bookings.filter((booking) => booking.status === 'assigned').length;

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="transactions" />}>
      <HeaderBlock
        eyebrow="Transactions"
        title="Ride history and successful trip records."
        subtitle="Use this screen for completed rides, current assignment counts, and operational transaction tracking."
      />

      <StatGrid
        items={[
          { label: 'Completed', value: completedRides.length },
          { label: 'Assigned', value: assignedBookings },
          { label: 'Pending', value: pendingBookings },
          { label: 'Role', value: (currentRole || 'guest').toUpperCase() },
        ]}
      />

      {rides.map((ride) => {
        const isCompleted = ride.status === 'Completed';

        return (
          <SectionCard key={ride.id} title={ride.studentName} subtitle={`${ride.pickupTime} - ${ride.dropoffTime}`}>
            <Pill label={isCompleted ? 'Successful Ride' : ride.status} tone={isCompleted ? 'success' : 'warning'} />
            <InfoRow label="Driver" value={ride.driverName} />
            <InfoRow label="Parent" value={ride.parentName} />
            <InfoRow label="Vehicle" value={ride.vehicle} />
            <InfoRow label="Distance" value={`${ride.distanceKm} km remaining`} />
          </SectionCard>
        );
      })}
    </Screen>
  );
}
