import React, { useEffect, useMemo, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import OpenStreetMapView from '../../components/OpenStreetMapView';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { colors } from '../../theme/colors';

export default function ActiveRideMapScreen({ navigation }) {
  const { currentRole, rides, refreshDashboard, setTrackingActive, advanceRideSimulation, resetRideSimulation, pushRideLocation, trackRide } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const [roadRoute, setRoadRoute] = useState([]);
  const ride = rides[0];
  const origin = ride?.pickupLocation || ride?.routePoints?.[0];
  const destination = ride?.dropoffLocation || ride?.routePoints?.[ride?.routePoints?.length - 1];
  const statusKey = String(ride?.status || '').toLowerCase();
  const isPastPickup = ['picked up', 'in transit', 'dropped off', 'completed'].includes(statusKey);
  const nextStopLabel = isPastPickup ? 'Drop-off' : 'Pickup';
  const lastLocationText = ride?.lastLocationAt ? new Date(ride.lastLocationAt.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'Waiting';
  const activeRouteColor = isPastPickup ? '#1d9bf0' : '#16a34a';
  const routeStops = useMemo(
    () => [ride?.location, !isPastPickup ? origin : null, destination].filter(Boolean),
    [destination, isPastPickup, origin, ride?.location]
  );
  const routePoints = useMemo(() => {
    if (roadRoute.length) {
      return roadRoute;
    }

    return routeStops.length ? routeStops : ride?.routePoints?.length ? ride.routePoints : [];
  }, [ride?.routePoints, roadRoute, routeStops]);
  const progressPercent = Math.max(0, Math.min(100, Math.round((ride?.progress || 0) * 100)));

  useEffect(() => {
    if (routeStops.length < 2) {
      return undefined;
    }

    let cancelled = false;
    const fetchRoute = async () => {
      try {
        const coordinates = routeStops.map((point) => `${point.longitude},${point.latitude}`).join(';');
        const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${coordinates}?overview=full&geometries=geojson`);
        const json = await response.json();
        const points = json.routes?.[0]?.geometry?.coordinates?.map(([longitude, latitude]) => ({ latitude, longitude })) || [];

        if (!cancelled && points.length) {
          setRoadRoute(points);
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
  }, [routeStops]);

  useEffect(() => {
    if (!ride?.id) {
      return undefined;
    }

    const syncRide = async () => {
      if (currentRole === 'driver' && ride.isTracking) {
        const currentIndex = ride.currentPointIndex || 0;
        const route = ride.routePoints || [];
        const nextLocation = route.length ? route[Math.min(currentIndex + 1, route.length - 1)] : ride.location;

        advanceRideSimulation();
        await pushRideLocation(nextLocation);
      } else {
        await trackRide(ride.id);
      }
    };

    const timer = setInterval(syncRide, 5000);

    return () => clearInterval(timer);
  }, [advanceRideSimulation, currentRole, pushRideLocation, ride?.currentPointIndex, ride?.id, ride?.isTracking, ride?.location, ride?.routePoints, trackRide]);

  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
      if (ride?.id) {
        await trackRide(ride.id);
      }
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
        <OpenStreetMapView
          center={ride.location}
          style={styles.map}
          markers={[
            { coordinate: ride.location, title: ride.driverName, description: `${ride.status} - ${ride.etaMinutes} mins ETA`, color: colors.accent, label: 'CAR' },
            origin ? { coordinate: origin, title: 'Pickup Point', description: ride.studentName, color: colors.success, label: 'P' } : null,
            destination ? { coordinate: destination, title: 'Drop-off Point', description: 'School destination', color: colors.deep, label: 'D' } : null,
          ].filter(Boolean)}
          polylines={[
            routePoints.length ? { coordinates: routePoints, color: colors.white, width: 12, opacity: 0.95 } : null,
            routePoints.length ? { coordinates: routePoints, color: colors.ink, width: 9, opacity: 0.18 } : null,
            routePoints.length ? { coordinates: routePoints, color: activeRouteColor, width: 6, opacity: 0.95 } : null,
          ].filter(Boolean)}
        />
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
        <InfoRow icon="map-marker-alt" label="Next stop" value={nextStopLabel} />
        <InfoRow icon="sync-alt" label="Last location" value={lastLocationText} />
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
        <Text style={styles.note}>OpenStreetMap tiles and OSRM road routing refresh with TRACE ride updates every 5 seconds.</Text>
      </SectionCard>
    </Screen>
  );
}

const styles = StyleSheet.create({
  mapWrap: {
    overflow: 'hidden',
    borderRadius: 24,
    marginBottom: 16,
    position: 'relative',
  },
  map: {
    height: 420,
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
