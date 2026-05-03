import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import MapView, { Marker, Polyline } from 'react-native-maps';
import HeaderBlock from '../../components/HeaderBlock';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { colors } from '../../theme/colors';

export default function ActiveRideMapScreen() {
  const { rides } = useAppContext();
  const ride = rides[0];
  const origin = ride.routePoints[0];
  const destination = ride.routePoints[ride.routePoints.length - 1];

  return (
    <Screen>
      <HeaderBlock
        eyebrow="Live Tracking"
        title="Real-time route view"
        subtitle="This screen is set up like a transport app: active status, ETA, route path, and a moving driver marker."
      />

      <View style={styles.mapWrap}>
        <MapView initialRegion={ride.location} style={styles.map}>
          <Marker coordinate={ride.location} title={ride.driverName} description="Driver current location" pinColor={colors.accent} />
          <Marker coordinate={origin} title="Pickup Point" description={ride.studentName} pinColor={colors.success} />
          <Marker coordinate={destination} title="Drop-off Point" description="School destination" pinColor={colors.plum} />
          <Polyline coordinates={ride.routePoints} strokeWidth={5} strokeColor={colors.deep} />
        </MapView>
      </View>

      <SectionCard title={`${ride.studentName} • ${ride.vehicle}`} subtitle="Shared trip summary">
        <Pill label={ride.status} tone="warning" />
        <Text>Driver: {ride.driverName}</Text>
        <Text>Parent: {ride.parentName}</Text>
        <Text>ETA: {ride.etaMinutes} minutes</Text>
        <Text>Pickup Time: {ride.pickupTime}</Text>
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
});
