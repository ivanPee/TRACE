import React from 'react';
import { Text } from 'react-native';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

const statuses = ['Driver Arriving', 'Arrived', 'Picked Up', 'In Transit', 'Dropped Off', 'Completed'];

export default function DriverTripsScreen({ navigation }) {
  const { rides, updateRideStatus } = useAppContext();
  const ride = rides[0];

  return (
    <Screen>
      <HeaderBlock
        eyebrow="Trip Controls"
        title="Manage the active student ride."
        subtitle="In the backend version these buttons should call the PHP ride status endpoint and push location updates during the trip."
      />

      <SectionCard title={ride.studentName} subtitle={ride.vehicle}>
        <Pill label={ride.status} tone="warning" />
        <InfoRow label="Parent" value={ride.parentName} />
        <InfoRow label="Pickup" value={ride.pickupTime} />
        <InfoRow label="Drop-off Target" value={ride.dropoffTime} />
        <InfoRow label="ETA" value={`${ride.etaMinutes} mins`} />
      </SectionCard>

      <SectionCard title="Update trip status">
        {statuses.map((status) => (
          <AppButton key={status} label={status} variant={status === 'Completed' ? 'secondary' : 'ghost'} onPress={() => updateRideStatus(status)} />
        ))}
      </SectionCard>

      <AppButton label="Open Live Map" onPress={() => navigation.navigate('ActiveRideMap')} />
    </Screen>
  );
}
