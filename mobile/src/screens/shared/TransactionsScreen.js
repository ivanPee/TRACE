import React, { useState } from 'react';
import AppNavBar from '../../components/AppNavBar';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import StatGrid from '../../components/StatGrid';
import { useAppContext } from '../../context/AppContext';

export default function TransactionsScreen({ navigation }) {
  const { currentRole, bookings, rides, refreshDashboard } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const completedRides = rides.filter((ride) => ride.status === 'Completed');
  const activeStatuses = ['assigned', 'driver arriving', 'arrived', 'picked up', 'in transit', 'dropped off'];
  const assignedBookings = bookings.filter((booking) => activeStatuses.includes(String(booking.status).toLowerCase().replace('_', ' '))).length;
  const pendingBookings = bookings.filter((booking) => String(booking.status).toLowerCase() === 'pending').length;
  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="transactions" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="Transactions"
        title="Ride history and successful trip records."
        subtitle="Use this screen for completed rides, current assignment counts, and operational transaction tracking."
      />

      <StatGrid
        items={[
          { label: 'Completed', value: completedRides.length, icon: 'check-circle' },
          { label: 'Assigned', value: assignedBookings, icon: 'route' },
          { label: 'Pending', value: pendingBookings, icon: 'clock' },
          { label: 'Role', value: (currentRole || 'guest').toUpperCase(), icon: 'id-badge' },
        ]}
      />

      {rides.map((ride) => {
        const isCompleted = ride.status === 'Completed';

        return (
          <SectionCard key={ride.id} title={ride.studentName} subtitle={`${ride.pickupTime || 'Pickup pending'} - ${ride.dropoffTime || 'Drop-off pending'}`} icon="receipt">
            <Pill label={isCompleted ? 'Successful Ride' : ride.status} tone={isCompleted ? 'success' : 'warning'} />
            <InfoRow icon="car" label="Driver" value={ride.driverName} />
            <InfoRow icon="user-friends" label="Parent" value={ride.parentName} />
            <InfoRow icon="shuttle-van" label="Vehicle" value={ride.vehicle} />
            <InfoRow icon="road" label="Distance" value={`${ride.distanceKm} km remaining`} />
          </SectionCard>
        );
      })}
    </Screen>
  );
}
