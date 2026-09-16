import React, { useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import { launchCamera } from 'react-native-image-picker';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { appendImage } from '../../components/ImagePickerField';
import { useAppContext } from '../../context/AppContext';
import { colors } from '../../theme/colors';

const isBoarded = (status) => ['picked_up', 'in_transit', 'dropped_off', 'completed'].includes(String(status || '').toLowerCase());
const isDroppedOff = (status) => ['dropped_off', 'completed'].includes(String(status || '').toLowerCase());

export default function DriverTripsScreen({ navigation }) {
  const { rides, updateRideStatus, refreshDashboard } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const [statusUpdating, setStatusUpdating] = useState(false);
  const ride = rides[0];
  const orderedStops = ride?.orderedStops?.length
    ? ride.orderedStops
    : ride?.carpoolStops?.map((stop, index) => ({
      id: `pickup-${stop.rideId || index}`,
      type: 'pickup',
      rideId: stop.rideId || ride.id,
      studentName: stop.studentName,
      address: stop.pickupAddress,
      expectedTime: stop.expectedPickupTime || ride.scheduledTime,
      etaMinutes: stop.etaMinutes || ride.etaMinutes,
      passengerCount: index + 1,
      status: stop.status,
      rawStatus: stop.rawStatus,
    })) || [];
  const routeStarted = orderedStops.some((stop) => ['driver_arriving', 'arrived', 'picked_up', 'in_transit', 'dropped_off', 'completed'].includes(String(stop.rawStatus || '').toLowerCase()));
  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  const handleStatus = async (status, rideId = ride.id, payload = null) => {
    setStatusUpdating(true);
    try {
      await updateRideStatus(status, rideId, payload);
      await refreshDashboard();
    } finally {
      setStatusUpdating(false);
    }
  };

  const handleDropoff = async (rideId = ride.id) => {
    const result = await launchCamera({
      mediaType: 'photo',
      quality: 0.75,
      saveToPhotos: false,
    });

    if (result.didCancel) {
      return;
    }

    const asset = result.assets?.[0];

    if (!asset?.uri) {
      Alert.alert('Drop-off photo', 'Please take a clear photo of the child before completing drop-off.');
      return;
    }

    const payload = new FormData();
    appendImage(payload, 'dropoff_photo', asset);
    await handleStatus('Completed', rideId, payload);
  };

  if (!ride) {
    return (
      <Screen bottomBar={<AppNavBar navigation={navigation} active="bookings" />} refreshing={refreshing} onRefresh={handleRefresh}>
        <HeaderBlock eyebrow="Trip Controls" title="No active assigned trip." subtitle="Assigned children appear here after a parent creates transport for your route." />
      </Screen>
    );
  }

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="bookings" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="Trip Controls"
        title="Manage the active child trip."
        subtitle="Status updates are saved to the backend and reflected for the parent automatically."
      />

      <SectionCard title={orderedStops.length > 1 ? 'Ordered route manifest' : ride.studentName} subtitle={ride.vehicle} icon="car-side">
        <Pill label={ride.status} tone="warning" />
        <InfoRow icon="user-friends" label="Parent" value={ride.parentName} />
        <InfoRow icon="users" label="Route stops" value={`${orderedStops.length || 1}`} />
        <InfoRow icon="clock" label="Expected start" value={ride.scheduledTime || 'Pending'} />
        <InfoRow icon="stopwatch" label="Next ETA" value={`${orderedStops[0]?.etaMinutes ?? ride.etaMinutes} mins`} />
        <AppButton icon="play" label="Start Trip" variant={routeStarted ? 'ghost' : 'primary'} disabled={routeStarted || statusUpdating} onPress={() => handleStatus('Driver Arriving', orderedStops[0]?.rideId || ride.id)} />
      </SectionCard>

      <SectionCard title="Stop list" icon="tasks">
        {orderedStops.map((stop, index) => {
          const rawStatus = String(stop.rawStatus || '').toLowerCase();
          const pickup = stop.type === 'pickup';
          const complete = pickup ? isBoarded(rawStatus) : isDroppedOff(rawStatus);
          const waitingForBoarding = !pickup && !isBoarded(rawStatus);

          return (
            <View key={stop.id || `${stop.type}-${stop.rideId}-${index}`} style={styles.stopRow}>
              <View style={styles.stopHeader}>
                <Pill label={`${index + 1}`} tone={complete ? 'success' : 'warning'} />
                <View style={styles.stopText}>
                  <Text style={styles.stopTitle}>{pickup ? 'Pickup' : 'Drop-off'}: {stop.studentName}</Text>
                  <Text style={styles.stopMeta}>{stop.expectedTime || 'Time pending'} / ETA {stop.etaMinutes ?? '-'} mins / {stop.passengerCount} aboard after stop</Text>
                </View>
              </View>
              <InfoRow icon={pickup ? 'map-marker-alt' : 'flag-checkered'} label="Stop" value={stop.address || '-'} />
              <AppButton
                icon={complete ? 'check-circle' : pickup ? 'user-check' : 'sign-out-alt'}
                label={complete ? stop.status : pickup ? 'Mark Child Boarded' : 'Take Drop-off Photo'}
                variant={complete ? 'secondary' : 'ghost'}
                disabled={statusUpdating || complete || waitingForBoarding}
                onPress={() => (pickup ? handleStatus('Picked Up', stop.rideId) : handleDropoff(stop.rideId))}
              />
            </View>
          );
        })}
      </SectionCard>

      <SectionCard title="GPS sharing" icon="satellite-dish">
        <InfoRow icon="map-marker-alt" label="Pickup" value={ride.pickupAddress || 'Saved pickup pin'} />
        <InfoRow icon="flag-checkered" label="Drop-off" value={ride.dropoffAddress || 'Saved drop-off pin'} />
        <InfoRow icon="satellite" label="Driver GPS" value={ride.hasDriverLocation ? ride.locationQuality || 'Live' : 'Open the live map to share current GPS'} />
        <AppButton icon="location-arrow" label="Open Live Map to Share GPS" variant="secondary" onPress={() => navigation.navigate('ActiveRideMap')} />
      </SectionCard>

      <AppButton icon="map-marked-alt" label="Open Live Map" onPress={() => navigation.navigate('ActiveRideMap')} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  stopRow: {
    borderColor: colors.line,
    borderRadius: 8,
    borderWidth: 1,
    marginBottom: 12,
    padding: 12,
  },
  stopHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 10,
    marginBottom: 4,
  },
  stopText: {
    flex: 1,
  },
  stopTitle: {
    color: colors.deep,
    fontSize: 15,
    fontWeight: '800',
  },
  stopMeta: {
    color: colors.slate,
    fontSize: 12,
    marginTop: 2,
  },
});
