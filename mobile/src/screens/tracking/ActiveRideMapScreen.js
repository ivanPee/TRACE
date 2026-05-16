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
  const routePoints = useMemo(() => {
    if (roadRoute.length) {
      return roadRoute;
    }

    return ride?.routePoints?.length ? ride.routePoints : [];
  }, [ride?.routePoints, roadRoute]);
  const currentPointIndex = useMemo(() => {
    if (!ride?.location || !routePoints.length) {
      return ride?.currentPointIndex || 0;
    }

    return routePoints.reduce(
      (closestIndex, point, index) => {
        const closest = routePoints[closestIndex];
        const currentDistance = Math.abs(Number(point.latitude) - Number(ride.location.latitude)) + Math.abs(Number(point.longitude) - Number(ride.location.longitude));
        const closestDistance = Math.abs(Number(closest.latitude) - Number(ride.location.latitude)) + Math.abs(Number(closest.longitude) - Number(ride.location.longitude));
        return currentDistance < closestDistance ? index : closestIndex;
      },
      Math.min(ride.currentPointIndex || 0, routePoints.length - 1)
    );
  }, [ride?.currentPointIndex, ride?.location, routePoints]);
  const completedRoute = routePoints.slice(0, currentPointIndex + 1);
  const remainingRoute = routePoints.slice(currentPointIndex);
  const progressPercent = Math.round(routePoints.length > 1 ? currentPointIndex / (routePoints.length - 1) * 100 : (ride?.progress || 0) * 100);

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
        const currentIndex = ride.currentPointIndex || 0;
        const route = ride.routePoints || [];
        const nextLocation = route.length ? route[Math.min(currentIndex + 1, route.length - 1)] : ride.location;

        advanceRideSimulation();
        await pushRideLocation(nextLocation);
      } else {
        await trackRide(ride.id);
      }
    };

    const timer = setInterval(syncRide, 10000);

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
            { coordinate: ride.location, title: ride.driverName, description: `${ride.status} - ${ride.etaMinutes} mins ETA` },
            origin ? { coordinate: origin, title: 'Pickup Point', description: ride.studentName } : null,
            destination ? { coordinate: destination, title: 'Drop-off Point', description: 'School destination' } : null,
          ].filter(Boolean)}
          polylines={[
            remainingRoute.length ? { coordinates: remainingRoute, color: colors.line, width: 5 } : null,
            completedRoute.length ? { coordinates: completedRoute, color: colors.deep, width: 6 } : null,
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
