import React, { useEffect, useRef } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import MapView, { Marker, Polyline } from 'react-native-maps';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { colors } from '../../theme/colors';

export default function ActiveRideMapScreen({ navigation }) {
  const { currentRole, rides, setTrackingActive, advanceRideSimulation, resetRideSimulation } = useAppContext();
  const ride = rides[0];
  const mapRef = useRef(null);
  const origin = ride.routePoints[0];
  const destination = ride.routePoints[ride.routePoints.length - 1];
  const completedRoute = ride.routePoints.slice(0, (ride.currentPointIndex || 0) + 1);
  const remainingRoute = ride.routePoints.slice(ride.currentPointIndex || 0);
  const progressPercent = Math.round((ride.progress || 0) * 100);

  useEffect(() => {
    if (!ride.isTracking) {
      return undefined;
    }

    const timer = setInterval(() => {
      advanceRideSimulation();
    }, 1800);

    return () => clearInterval(timer);
  }, [advanceRideSimulation, ride.isTracking]);

  useEffect(() => {
    mapRef.current?.animateToRegion(
      {
        ...ride.location,
        latitudeDelta: 0.018,
        longitudeDelta: 0.018,
      },
      450
    );
  }, [ride.location]);

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active={currentRole === 'student' ? 'ride' : 'bookings'} />}>
      <HeaderBlock
        eyebrow="Live Tracking"
        title={currentRole === 'driver' ? 'Driver route simulator' : 'Real-time route view'}
        subtitle="The tracking is static mock data for now, but it behaves like live GPS: the vehicle marker moves, ETA changes, and every role sees the same ride state."
      />

      <View style={styles.mapWrap}>
        <MapView ref={mapRef} initialRegion={ride.location} style={styles.map}>
          <Marker coordinate={ride.location} title={ride.driverName} description={`${ride.status} - ${ride.etaMinutes} mins ETA`} pinColor={colors.accent} />
          <Marker coordinate={origin} title="Pickup Point" description={ride.studentName} pinColor={colors.success} />
          <Marker coordinate={destination} title="Drop-off Point" description="School destination" pinColor={colors.plum} />
          <Polyline coordinates={remainingRoute} strokeWidth={5} strokeColor={colors.line} />
          <Polyline coordinates={completedRoute} strokeWidth={6} strokeColor={colors.deep} />
        </MapView>
      </View>

      <SectionCard title={`${ride.studentName} - ${ride.vehicle}`} subtitle="Shared trip summary">
        <Pill label={ride.status} tone="warning" />
        <View style={styles.progressTrack}>
          <View style={[styles.progressFill, { width: `${progressPercent}%` }]} />
        </View>
        <InfoRow label="Driver" value={ride.driverName} />
        <InfoRow label="Parent" value={ride.parentName} />
        <InfoRow label="ETA" value={`${ride.etaMinutes} mins`} />
        <InfoRow label="Distance left" value={`${ride.distanceKm} km`} />
        <InfoRow label="Route progress" value={`${progressPercent}%`} />
      </SectionCard>

      <SectionCard title="Tracking controls" subtitle={currentRole === 'driver' ? 'Use these to simulate live GPS sharing.' : 'Use these to preview what parents and students will see.'}>
        <AppButton label={ride.isTracking ? 'Pause Tracking' : 'Start Tracking'} onPress={() => setTrackingActive(!ride.isTracking)} />
        <AppButton label="Move One Step" variant="secondary" onPress={advanceRideSimulation} />
        <AppButton label="Reset Simulation" variant="ghost" onPress={resetRideSimulation} />
        <Text style={styles.note}>In the connected version, the driver app will push GPS coordinates to the backend and parent/student apps will receive updates from the same ride record.</Text>
      </SectionCard>
    </Screen>
  );
}

const styles = StyleSheet.create({
  mapWrap: {
    overflow: 'hidden',
    borderRadius: 24,
    marginBottom: 16,
  },
  map: {
    height: 360,
    width: '100%',
  },
  progressTrack: {
    height: 10,
    borderRadius: 999,
    backgroundColor: colors.line,
    overflow: 'hidden',
    marginBottom: 12,
    marginTop: 10,
  },
  progressFill: {
    height: '100%',
    borderRadius: 999,
    backgroundColor: colors.success,
  },
  note: {
    color: colors.slate,
    fontSize: 13,
    lineHeight: 19,
  },
});
