import React, { useEffect, useRef, useState } from 'react';
import { PermissionsAndroid, Platform, StyleSheet, Text, View } from 'react-native';
import MapView, { Marker, Polyline, UrlTile } from 'react-native-maps';
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
  const { currentRole, rides, refreshDashboard, setTrackingActive, advanceRideSimulation, resetRideSimulation, pushRideLocation, trackRide } = useAppContext();
  const [locationAllowed, setLocationAllowed] = useState(Platform.OS !== 'android');
  const [refreshing, setRefreshing] = useState(false);
  const [roadRoute, setRoadRoute] = useState([]);
  const ride = rides[0];
  const mapRef = useRef(null);
  const origin = ride?.pickupLocation || ride?.routePoints?.[0];
  const destination = ride?.dropoffLocation || ride?.routePoints?.[ride?.routePoints?.length - 1];
  const routePoints = roadRoute.length ? roadRoute : ride?.routePoints?.length ? ride.routePoints : [];
  const completedRoute = routePoints.slice(0, (ride?.currentPointIndex || 0) + 1);
  const remainingRoute = routePoints.slice(ride?.currentPointIndex || 0);
  const progressPercent = Math.round((ride?.progress || 0) * 100);

  useEffect(() => {
    const requestPermission = async () => {
      if (Platform.OS !== 'android') {
        return;
      }

      const granted = await PermissionsAndroid.request(PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION);
      setLocationAllowed(granted === PermissionsAndroid.RESULTS.GRANTED);
    };

    requestPermission();
  }, []);

  useEffect(() => {
    if (!origin || !destination) {
      return undefined;
    }

    let cancelled = false;
    const fetchRoute = async () => {
      try {
        const coordinates = `${origin.longitude},${origin.latitude};${destination.longitude},${destination.latitude}`;
        const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${coordinates}?overview=full&geometries=geojson`);
        const json = await response.json();
        const points = json.routes?.[0]?.geometry?.coordinates?.map(([longitude, latitude]) => ({ latitude, longitude })) || [];

        if (!cancelled && points.length) {
          setRoadRoute(points);
          mapRef.current?.fitToCoordinates(points, {
            edgePadding: { top: 70, right: 40, bottom: 70, left: 40 },
            animated: true,
          });
        }
      } catch {
        if (!cancelled) {
          setRoadRoute([]);
        }
      }
    };

    fetchRoute();

    return () => {
      cancelled = true;
    };
  }, [destination, origin]);

  useEffect(() => {
    if (!ride?.id) {
      return undefined;
    }

    const syncRide = async () => {
      if (currentRole === 'driver' && ride.isTracking) {
        advanceRideSimulation();
        await pushRideLocation({
          latitude: ride.location.latitude,
          longitude: ride.location.longitude,
        });
      } else {
        await trackRide(ride.id);
      }
    };

    const timer = setInterval(syncRide, 10000);

    return () => clearInterval(timer);
  }, [advanceRideSimulation, currentRole, pushRideLocation, ride?.id, ride?.isTracking, ride?.location, trackRide]);

  useEffect(() => {
    if (!ride?.location) {
      return;
    }

    mapRef.current?.animateToRegion(
      {
        ...ride.location,
        latitudeDelta: 0.018,
        longitudeDelta: 0.018,
      },
      450
    );
  }, [ride?.location]);
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
      <Screen bottomBar={<AppNavBar navigation={navigation} active={currentRole === 'student' ? 'ride' : 'bookings'} />} refreshing={refreshing} onRefresh={handleRefresh}>
        <HeaderBlock eyebrow="Live Tracking" title="No active ride to track." subtitle="Tracking starts after a driver is assigned and the trip begins." />
      </Screen>
    );
  }

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active={currentRole === 'student' ? 'ride' : 'bookings'} />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="Live Tracking"
        title={currentRole === 'driver' ? 'Driver route controls' : 'Real-time route view'}
        subtitle="Driver GPS updates are saved to the backend and shared with parent and student tracking views."
      />

      <View style={styles.mapWrap}>
        <MapView ref={mapRef} initialRegion={ride.location} style={styles.map} mapType={Platform.OS === 'android' ? 'none' : 'standard'} showsUserLocation={locationAllowed}>
          <UrlTile urlTemplate="https://tile.openstreetmap.org/{z}/{x}/{y}.png" maximumZ={19} flipY={false} />
          <Marker coordinate={ride.location} title={ride.driverName} description={`${ride.status} - ${ride.etaMinutes} mins ETA`} pinColor={colors.accent} />
          {origin ? <Marker coordinate={origin} title="Pickup Point" description={ride.studentName} pinColor={colors.success} /> : null}
          {destination ? <Marker coordinate={destination} title="Drop-off Point" description="School destination" pinColor={colors.plum} /> : null}
          {remainingRoute.length ? <Polyline coordinates={remainingRoute} strokeWidth={5} strokeColor={colors.line} /> : null}
          {completedRoute.length ? <Polyline coordinates={completedRoute} strokeWidth={6} strokeColor={colors.deep} /> : null}
        </MapView>
      </View>

      <SectionCard title={`${ride.studentName} - ${ride.vehicle}`} subtitle="Shared trip summary" icon="route">
        <Pill label={ride.status} tone="warning" />
        <View style={styles.progressTrack}>
          <View style={[styles.progressFill, { width: `${progressPercent}%` }]} />
        </View>
        <InfoRow icon="car" label="Driver" value={ride.driverName} />
        <InfoRow icon="user-friends" label="Parent" value={ride.parentName} />
        <InfoRow icon="stopwatch" label="ETA" value={`${ride.etaMinutes} mins`} />
        <InfoRow icon="road" label="Distance left" value={`${ride.distanceKm} km`} />
        <InfoRow icon="chart-line" label="Route progress" value={`${progressPercent}%`} />
      </SectionCard>

      <SectionCard title="Tracking controls" subtitle={currentRole === 'driver' ? 'Use these while the trip is active.' : 'Latest shared vehicle location.'} icon="satellite-dish">
        {currentRole === 'driver' ? (
          <>
            <AppButton icon={ride.isTracking ? 'pause' : 'play'} label={ride.isTracking ? 'Pause Tracking' : 'Start Tracking'} onPress={() => setTrackingActive(!ride.isTracking)} />
            <AppButton icon="location-arrow" label="Push Next Location" variant="secondary" onPress={advanceRideSimulation} />
            <AppButton icon="undo" label="Reset Simulation" variant="ghost" onPress={resetRideSimulation} />
          </>
        ) : null}
        <Text style={styles.note}>OpenStreetMap tiles and OSRM road routing refresh with TRACE ride updates every 10 seconds.</Text>
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
