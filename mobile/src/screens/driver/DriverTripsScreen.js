import React, { useState } from 'react';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { rideStatusSteps } from '../../data/appDefaults';

export default function DriverTripsScreen({ navigation }) {
  const { rides, updateRideStatus, setTrackingActive, advanceRideSimulation, resetRideSimulation, refreshDashboard } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const ride = rides[0];
  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  if (!ride) {
    return (
      <Screen bottomBar={<AppNavBar navigation={navigation} active="bookings" />} refreshing={refreshing} onRefresh={handleRefresh}>
        <HeaderBlock eyebrow="Trip Controls" title="No active assigned ride." subtitle="Assigned students appear here after a parent books you as the driver." />
      </Screen>
    );
  }

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="bookings" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="Trip Controls"
        title="Manage the active student ride."
        subtitle="Status updates are saved to the backend and reflected for the parent automatically."
      />

      <SectionCard title={ride.studentName} subtitle={ride.vehicle} icon="car-side">
        <Pill label={ride.status} tone="warning" />
        <InfoRow icon="user-friends" label="Parent" value={ride.parentName} />
        <InfoRow icon="clock" label="Pickup" value={ride.pickupTime || 'Pending'} />
        <InfoRow icon="flag-checkered" label="Drop-off Target" value={ride.dropoffTime || 'Pending'} />
        <InfoRow icon="stopwatch" label="ETA" value={`${ride.etaMinutes} mins`} />
      </SectionCard>

      <SectionCard title="Update trip status" icon="tasks">
        {rideStatusSteps.map((status) => (
          <AppButton key={status} icon={status === 'Completed' ? 'check-circle' : 'circle'} label={status} variant={status === 'Completed' ? 'secondary' : 'ghost'} onPress={() => updateRideStatus(status)} />
        ))}
      </SectionCard>

      <SectionCard title="GPS sharing" icon="satellite-dish">
        <AppButton icon={ride.isTracking ? 'pause' : 'play'} label={ride.isTracking ? 'Pause Live Sharing' : 'Start Live Sharing'} onPress={() => setTrackingActive(!ride.isTracking)} />
        <AppButton icon="location-arrow" label="Push Next Location" variant="secondary" onPress={advanceRideSimulation} />
        <AppButton icon="undo" label="Reset Route" variant="ghost" onPress={resetRideSimulation} />
      </SectionCard>

      <AppButton icon="map-marked-alt" label="Open Live Map" onPress={() => navigation.navigate('ActiveRideMap')} />
    </Screen>
  );
}
