import React, { useEffect, useMemo, useState } from 'react';
import { Alert, Image, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import FontAwesome5 from 'react-native-vector-icons/FontAwesome5';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import DriverProfileCard from '../../components/DriverProfileCard';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import OpenStreetMapView from '../../components/OpenStreetMapView';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { startDriverLocationUpdates, stopDriverLocationUpdates } from '../../services/driverLocation';
import { colors } from '../../theme/colors';

const sameCoordinate = (left, right) =>
  left && right
  && Math.abs(Number(left.latitude) - Number(right.latitude)) < 0.000001
  && Math.abs(Number(left.longitude) - Number(right.longitude)) < 0.000001;

const normalizeCoordinate = (point) => {
  if (!point || !Number.isFinite(Number(point.latitude)) || !Number.isFinite(Number(point.longitude))) {
    return null;
  }

  return { latitude: Number(point.latitude), longitude: Number(point.longitude) };
};

const directDistanceKm = (left, right) => {
  const from = normalizeCoordinate(left);
  const to = normalizeCoordinate(right);

  if (!from || !to) {
    return Number.POSITIVE_INFINITY;
  }

  const earthRadiusKm = 6371;
  const latDelta = ((to.latitude - from.latitude) * Math.PI) / 180;
  const lngDelta = ((to.longitude - from.longitude) * Math.PI) / 180;
  const a =
    Math.sin(latDelta / 2) ** 2 +
    Math.cos((from.latitude * Math.PI) / 180) * Math.cos((to.latitude * Math.PI) / 180) * Math.sin(lngDelta / 2) ** 2;

  return earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
};

const nearestOrder = (start, points) => {
  const remaining = points.map((point) => ({ ...point }));
  const ordered = [];
  let cursor = normalizeCoordinate(start) || normalizeCoordinate(remaining[0]?.coordinate);

  while (remaining.length) {
    let bestIndex = 0;
    let bestDistance = Number.POSITIVE_INFINITY;

    remaining.forEach((point, index) => {
      const distance = directDistanceKm(cursor, point.coordinate);

      if (distance < bestDistance) {
        bestDistance = distance;
        bestIndex = index;
      }
    });

    const [next] = remaining.splice(bestIndex, 1);
    ordered.push(next);
    cursor = normalizeCoordinate(next.coordinate) || cursor;
  }

  return ordered;
};

const routeDistanceKm = (points) => points.reduce((total, point, index) => {
  if (index === 0) {
    return total;
  }

  const segmentDistance = directDistanceKm(points[index - 1], point);

  return Number.isFinite(segmentDistance) ? total + segmentDistance : total;
}, 0);

export default function ActiveRideMapScreen({ navigation }) {
  const { currentRole, rides, refreshDashboard, pushRideLocation, trackRide } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const [roadRoute, setRoadRoute] = useState([]);
  const [routeStats, setRouteStats] = useState(null);
  const [routeStatus, setRouteStatus] = useState('Waiting for route points');
  const [gpsStatus, setGpsStatus] = useState('Waiting for driver GPS');
  const [isSharingGps, setIsSharingGps] = useState(false);
  const [isMapFullscreen, setIsMapFullscreen] = useState(false);
  const ride = rides[0];
  const driverLatitude = ride?.location?.latitude ?? ride?.driver?.latitude;
  const driverLongitude = ride?.location?.longitude ?? ride?.driver?.longitude;
  const hasDriverCoordinate = Number.isFinite(Number(driverLatitude)) && Number.isFinite(Number(driverLongitude));
  const driverLocation = useMemo(
    () => (hasDriverCoordinate ? { latitude: Number(driverLatitude), longitude: Number(driverLongitude) } : null),
    [driverLatitude, driverLongitude, hasDriverCoordinate]
  );
  const carpoolStops = useMemo(() => (ride?.carpoolStops?.length ? ride.carpoolStops : []), [ride?.carpoolStops]);
  const orderedStops = useMemo(() => (ride?.orderedStops?.length ? ride.orderedStops : []), [ride?.orderedStops]);
  const pickupStops = useMemo(
    () => carpoolStops
      .map((stop) => ({ ...stop, coordinate: stop.pickupLocation }))
      .filter((stop) => Number.isFinite(Number(stop.coordinate?.latitude)) && Number.isFinite(Number(stop.coordinate?.longitude))),
    [carpoolStops]
  );
  const pickupLocation = pickupStops[0]?.coordinate || ride?.pickupLocation || ride?.routePoints?.[0];
  const pickupLocations = useMemo(
    () => (pickupStops.length ? pickupStops.map((stop) => stop.coordinate) : pickupLocation ? [pickupLocation] : []),
    [pickupLocation, pickupStops]
  );
  const dropoffLocation = ride?.dropoffLocation || ride?.routePoints?.[ride?.routePoints?.length - 1];
  const isPickedUp = Boolean(ride?.isPickedUp) || ['Picked Up', 'In Transit', 'Dropped Off', 'Completed'].includes(ride?.status);
  const routeStopCandidates = useMemo(() => {
    if (orderedStops.length) {
      const pendingStops = orderedStops
        .map((stop) => ({
          type: stop.type,
          status: stop.status,
          coordinate: normalizeCoordinate(stop.location),
        }))
        .filter((stop) => stop.coordinate)
        .filter((stop) => {
          if (stop.type === 'pickup') {
            return !['Boarded'].includes(stop.status);
          }

          return !['Dropped Off'].includes(stop.status);
        });
      const pickups = nearestOrder(driverLocation || pickupLocation, pendingStops.filter((stop) => stop.type === 'pickup'));
      const lastPickup = pickups[pickups.length - 1]?.coordinate || driverLocation || pickupLocation;
      const dropoffs = nearestOrder(lastPickup, pendingStops.filter((stop) => stop.type === 'dropoff'));

      return [...pickups, ...dropoffs].map((stop) => stop.coordinate);
    }

    return [...(!isPickedUp ? pickupLocations : []), dropoffLocation].map(normalizeCoordinate).filter(Boolean);
  }, [driverLocation, dropoffLocation, isPickedUp, orderedStops, pickupLocation, pickupLocations]);
  const waypoints = [driverLocation, ...routeStopCandidates]
    .filter(Boolean)
    .filter((point, index, points) => index === 0 || !sameCoordinate(point, points[index - 1]));
  const waypointCoordinates = waypoints.map((point) => `${point.longitude},${point.latitude}`).join(';');
  const exactRoutePoints = roadRoute.length ? roadRoute : waypoints;
  const progressPercent = Math.round((ride?.progress || 0) * 100);
  const fallbackDistanceKm = exactRoutePoints.length > 1 ? Number(routeDistanceKm(exactRoutePoints).toFixed(1)) : 0;
  const etaMinutes = routeStats?.durationMinutes ?? ride?.etaMinutes ?? 0;
  const distanceKm = routeStats?.distanceKm ?? ride?.distanceKm ?? fallbackDistanceKm;
  const nextStopLabel = ride?.nextStopLabel || (isPickedUp ? 'Drop-off point' : pickupStops[0]?.studentName || 'Pickup point');
  const gpsIsLive = ride?.hasDriverLocation || gpsStatus.toLowerCase().includes('live');
  const routePrecision = roadRoute.length ? 'Road route' : exactRoutePoints.length > 1 ? 'Direct route' : 'Awaiting route';
  const mapMarkers = [
    driverLocation ? {
      coordinate: driverLocation,
      type: 'driver',
      heading: ride?.driver?.heading || ride?.movement?.heading,
      accuracy: ride?.location?.accuracy,
      label: 'Driver',
      title: ride?.driverName,
      description: `${ride?.status} - ${etaMinutes} mins ETA`,
    } : null,
    ...(pickupStops.length ? pickupStops.map((stop, index) => ({
      coordinate: stop.coordinate,
      type: 'pickup',
      symbol: `P${index + 1}`,
      label: stop.studentName || `Pickup ${index + 1}`,
      title: `Pickup ${index + 1}`,
      description: stop.studentName,
    })) : pickupLocation ? [{ coordinate: pickupLocation, type: 'pickup', label: 'Pickup', title: 'Pickup Point', description: ride?.studentName }] : []),
    dropoffLocation ? { coordinate: dropoffLocation, type: 'dropoff', label: 'Drop-off', title: 'Drop-off Point', description: 'Destination' } : null,
  ].filter(Boolean);
  const mapPolylines = [
    exactRoutePoints.length > 1 ? { coordinates: exactRoutePoints, color: roadRoute.length ? colors.road : colors.slate, width: roadRoute.length ? 6 : 5, dashed: !roadRoute.length } : null,
  ].filter(Boolean);

  useEffect(() => {
    if (!waypointCoordinates || waypointCoordinates.split(';').length < 2) {
      setRoadRoute([]);
      setRouteStats(null);
      setRouteStatus('Waiting for route points');
      return undefined;
    }

    let cancelled = false;
    const controller = new AbortController();
    const fetchRoute = async () => {
      try {
        setRouteStatus('Calculating optimized road route...');
        const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${waypointCoordinates}?overview=full&geometries=geojson`, { signal: controller.signal });
        const json = await response.json();
        const fastestRoute = json.routes?.[0];
        const points = fastestRoute?.geometry?.coordinates?.map(([longitude, latitude]) => ({ latitude, longitude })) || [];

        if (!cancelled && points.length) {
          setRoadRoute(points);
          setRouteStats({
            distanceKm: Number((fastestRoute.distance / 1000).toFixed(1)),
            durationMinutes: Math.max(1, Math.round(fastestRoute.duration / 60)),
          });
          setRouteStatus('Optimized road route');
        } else if (!cancelled) {
          setRoadRoute([]);
          setRouteStats(null);
          setRouteStatus('Showing direct route fallback');
        }
      } catch (error) {
        if (error.name === 'AbortError') {
          return;
        }

        if (!cancelled) {
          setRoadRoute([]);
          setRouteStats(null);
          setRouteStatus('Showing direct route fallback');
        }
      }
    };

    fetchRoute();

    return () => {
      cancelled = true;
      controller.abort();
    };
  }, [waypointCoordinates]);

  useEffect(() => {
    if (!ride?.id) {
      return undefined;
    }

    const syncRide = async () => {
      try {
        await trackRide(ride.id);
      } catch {
        // Keep the tracking screen open even when a poll fails temporarily.
      }
    };

    syncRide();
    const timer = setInterval(syncRide, 10000);

    return () => clearInterval(timer);
  }, [ride?.id, trackRide]);

  useEffect(() => {
    if (currentRole !== 'driver' || !ride?.id || !isSharingGps) {
      stopDriverLocationUpdates();
      return undefined;
    }

    let stopUpdates;
    let cancelled = false;

    startDriverLocationUpdates({
      onLocation: async (location) => {
        if (cancelled) {
          return;
        }

        setGpsStatus(`Live GPS${location.accuracy ? ` (${Math.round(location.accuracy)} m)` : ''}`);
        try {
          await pushRideLocation(location);
        } catch (error) {
          setGpsStatus(error.message || 'Cannot send driver GPS');
        }
      },
      onError: (message) => {
        if (!cancelled) {
          setGpsStatus(message);
        }
      },
    })
      .then((stop) => {
        stopUpdates = stop;
      })
      .catch((error) => {
        if (!cancelled) {
          setIsSharingGps(false);
          setGpsStatus(error.message || 'Driver GPS is unavailable.');
          Alert.alert('Driver GPS', error.message || 'Driver GPS is unavailable.');
        }
      });

    return () => {
      cancelled = true;
      stopUpdates?.();
    };
  }, [currentRole, isSharingGps, pushRideLocation, ride?.id]);

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
        <HeaderBlock eyebrow="Live Tracking" title="No active trip to track." subtitle="Tracking starts after a driver is assigned and the trip begins." />
      </Screen>
    );
  }

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active={currentRole === 'student' ? 'ride' : 'bookings'} />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="Live Tracking"
        title={currentRole === 'driver' ? 'Driver route controls' : 'Real-time route view'}
        subtitle="Driver GPS updates are saved to the backend and shared with parent and child tracking views."
      />

      <View style={styles.mapWrap}>
        <OpenStreetMapView
          center={driverLocation || pickupLocation || dropoffLocation}
          style={styles.map}
          markers={mapMarkers}
          polylines={mapPolylines}
        />
        <View style={styles.mapTopPanel}>
          <View style={styles.mapMetric}>
            <Text style={styles.mapMetricLabel}>ETA</Text>
            <Text style={styles.mapMetricValue}>{etaMinutes} min</Text>
          </View>
          <View style={styles.mapMetric}>
            <Text style={styles.mapMetricLabel}>Distance</Text>
            <Text style={styles.mapMetricValue}>{distanceKm} km</Text>
          </View>
          <View style={styles.mapMetricWide}>
            <Text style={styles.mapMetricLabel}>Next</Text>
            <Text style={styles.mapMetricValue} numberOfLines={1}>{nextStopLabel}</Text>
          </View>
        </View>
        <View style={styles.mapBottomPanel}>
          <View style={styles.routeStatusPill}>
            <View style={[styles.statusDot, gpsIsLive ? styles.statusDotLive : styles.statusDotWaiting]} />
            <Text style={styles.routeStatusText}>{routePrecision} / {routeStatus}</Text>
          </View>
          <Pressable accessibilityRole="button" accessibilityLabel="Open fullscreen map" style={({ pressed }) => [styles.expandButton, pressed && styles.pressed]} onPress={() => setIsMapFullscreen(true)}>
            <FontAwesome5 name="expand" size={14} color={colors.white} />
          </Pressable>
        </View>
      </View>

      <Modal visible={isMapFullscreen} animationType="slide" onRequestClose={() => setIsMapFullscreen(false)}>
        <View style={styles.fullscreen}>
          <OpenStreetMapView
            center={driverLocation || pickupLocation || dropoffLocation}
            style={styles.fullscreenMap}
            markers={mapMarkers}
            polylines={mapPolylines}
            zoom={14}
          />
          <View style={styles.fullscreenBar}>
            <View>
              <Text style={styles.fullscreenTitle}>{routeStatus}</Text>
              <Text style={styles.fullscreenMeta}>{distanceKm} km / {etaMinutes} mins / Next: {nextStopLabel}</Text>
            </View>
            <Pressable style={({ pressed }) => [styles.closeButton, pressed && styles.pressed]} onPress={() => setIsMapFullscreen(false)}>
              <Text style={styles.closeButtonText}>Close</Text>
            </Pressable>
          </View>
        </View>
      </Modal>

      <SectionCard title={`${pickupStops.length > 1 ? 'Carpool route' : ride.studentName} - ${ride.vehicle}`} subtitle="Shared trip summary" icon="route">
        <Pill label={ride.status} tone="warning" />
        <View style={styles.progressTrack}>
          <View style={[styles.progressFill, { width: `${progressPercent}%` }]} />
        </View>
        <DriverProfileCard ride={ride} context="live" />
        <InfoRow icon="car" label="Driver" value={ride.driverName} />
        <InfoRow icon="user-friends" label="Parent" value={ride.parentName} />
        {pickupStops.length > 1 ? <InfoRow icon="users" label="Children" value={pickupStops.map((stop) => stop.studentName).join(', ')} /> : null}
        <InfoRow icon="map-signs" label="Next stop" value={nextStopLabel} />
        <InfoRow icon="stopwatch" label="ETA" value={`${etaMinutes} mins`} />
        <InfoRow icon="road" label="Route distance" value={`${distanceKm} km`} />
        <InfoRow icon="satellite" label="Driver GPS" value={ride.hasDriverLocation ? ride.locationQuality || gpsStatus : gpsStatus} />
        <InfoRow icon="chart-line" label="Route progress" value={`${progressPercent}%`} />
        {ride.dropoffPhotoUrl ? (
          <View style={styles.dropoffProof}>
            <Text style={styles.dropoffProofTitle}>Drop-off photo</Text>
            <Image source={{ uri: ride.dropoffPhotoUrl }} style={styles.dropoffProofImage} resizeMode="cover" />
          </View>
        ) : null}
      </SectionCard>

      {orderedStops.length ? (
        <SectionCard title="Trip stops" subtitle="Shared route timeline" icon="map-signs">
          {orderedStops.map((stop, index) => (
            <View key={stop.id || `${stop.type}-${stop.rideId}-${index}`} style={styles.stopRow}>
              <View style={styles.stopHeader}>
                <Pill label={`${index + 1}`} tone={stop.status === 'Dropped Off' || stop.status === 'Boarded' ? 'success' : 'warning'} />
                <View style={styles.stopText}>
                  <Text style={styles.stopTitle}>{stop.type === 'dropoff' ? 'Drop-off' : 'Pickup'}: {stop.studentName}</Text>
                  <Text style={styles.stopMeta}>{stop.expectedTime || '-'} / ETA {stop.etaMinutes ?? '-'} mins / {stop.passengerCount} aboard after stop</Text>
                </View>
              </View>
              <InfoRow icon={stop.type === 'dropoff' ? 'flag-checkered' : 'map-marker-alt'} label={stop.status || 'Pending'} value={stop.address || '-'} />
            </View>
          ))}
        </SectionCard>
      ) : null}

      <SectionCard title="Driver GPS" subtitle={currentRole === 'driver' ? 'Share the real vehicle position before pickup and during the trip.' : 'Latest shared vehicle location.'} icon="satellite-dish">
        {currentRole === 'driver' ? (
          <>
            <AppButton icon={isSharingGps ? 'pause' : 'location-arrow'} label={isSharingGps ? 'Stop Live GPS' : 'Share Current GPS'} onPress={() => setIsSharingGps((current) => !current)} />
            <InfoRow icon="satellite" label="GPS status" value={gpsStatus} />
          </>
        ) : null}
        <Text style={styles.note}>OpenStreetMap tiles and OSRM road routing use the saved pickup, drop-off, and live driver GPS coordinates.</Text>
      </SectionCard>
    </Screen>
  );
}

const styles = StyleSheet.create({
  mapWrap: {
    overflow: 'hidden',
    borderColor: colors.line,
    borderRadius: 8,
    borderWidth: 1,
    marginBottom: 16,
    backgroundColor: colors.white,
    shadowColor: colors.shadow,
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.14,
    shadowRadius: 18,
    elevation: 4,
  },
  map: {
    height: 420,
    width: '100%',
  },
  mapTopPanel: {
    position: 'absolute',
    left: 12,
    top: 12,
    right: 12,
    alignItems: 'stretch',
    flexDirection: 'row',
    gap: 8,
  },
  mapMetric: {
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 8,
    borderWidth: 1,
    minWidth: 78,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  mapMetricWide: {
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 8,
    borderWidth: 1,
    flex: 1,
    minWidth: 0,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  mapMetricLabel: {
    color: colors.slate,
    fontSize: 10,
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  mapMetricValue: {
    color: colors.deep,
    fontSize: 14,
    fontWeight: '900',
    marginTop: 2,
  },
  mapBottomPanel: {
    position: 'absolute',
    left: 12,
    right: 12,
    bottom: 12,
    alignItems: 'center',
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'space-between',
  },
  routeStatusPill: {
    alignItems: 'center',
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 8,
    borderWidth: 1,
    flex: 1,
    flexDirection: 'row',
    gap: 8,
    minHeight: 42,
    minWidth: 0,
    paddingHorizontal: 12,
  },
  statusDot: {
    borderRadius: 5,
    height: 10,
    width: 10,
  },
  statusDotLive: {
    backgroundColor: colors.success,
  },
  statusDotWaiting: {
    backgroundColor: colors.warning,
  },
  routeStatusText: {
    color: colors.deep,
    fontSize: 12,
    fontWeight: '800',
    flex: 1,
  },
  expandButton: {
    alignItems: 'center',
    backgroundColor: colors.ink,
    borderRadius: 8,
    height: 42,
    justifyContent: 'center',
    width: 42,
  },
  fullscreen: {
    flex: 1,
    backgroundColor: colors.paper,
  },
  fullscreenMap: {
    flex: 1,
    width: '100%',
  },
  fullscreenBar: {
    position: 'absolute',
    top: 16,
    left: 16,
    right: 16,
    alignItems: 'center',
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 8,
    borderWidth: 1,
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  fullscreenTitle: {
    color: colors.deep,
    fontSize: 13,
    fontWeight: '800',
  },
  fullscreenMeta: {
    color: colors.slate,
    fontSize: 12,
    marginTop: 2,
  },
  closeButton: {
    backgroundColor: colors.ink,
    borderRadius: 8,
    paddingHorizontal: 14,
    paddingVertical: 9,
  },
  closeButtonText: {
    color: colors.white,
    fontSize: 13,
    fontWeight: '800',
  },
  pressed: {
    opacity: 0.85,
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
  note: {
    color: colors.slate,
    fontSize: 13,
    lineHeight: 19,
  },
  dropoffProof: {
    marginTop: 12,
  },
  dropoffProofTitle: {
    color: colors.deep,
    fontSize: 13,
    fontWeight: '800',
    marginBottom: 8,
  },
  dropoffProofImage: {
    backgroundColor: colors.line,
    borderRadius: 8,
    height: 220,
    width: '100%',
  },
});
