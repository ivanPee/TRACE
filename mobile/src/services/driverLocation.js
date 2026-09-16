import { NativeEventEmitter, NativeModules, PermissionsAndroid, Platform } from 'react-native';

const { TraceLocationModule } = NativeModules;
const locationEvents = TraceLocationModule ? new NativeEventEmitter(TraceLocationModule) : null;

export async function requestLocationPermission() {
  if (Platform.OS !== 'android') {
    return true;
  }

  const granted = await PermissionsAndroid.request(PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION, {
    title: 'TRACE location access',
    message: 'TRACE needs driver GPS to show the real vehicle location before pickup.',
    buttonPositive: 'Allow',
    buttonNegative: 'Not now',
  });

  return granted === PermissionsAndroid.RESULTS.GRANTED;
}

export function normalizeNativeLocation(location) {
  return {
    latitude: Number(location.latitude),
    longitude: Number(location.longitude),
    accuracy: Number(location.accuracy) >= 0 ? Number(location.accuracy) : undefined,
    speed: Number(location.speed) >= 0 ? Number(location.speed) : undefined,
    heading: Number(location.heading) >= 0 ? Number(location.heading) : undefined,
    provider: location.provider,
  };
}

export async function startDriverLocationUpdates({ onLocation, onError, intervalMs = 10000, distanceMeters = 5, accuracyMeters = 100 }) {
  if (!TraceLocationModule || !locationEvents) {
    throw new Error('Driver GPS module is not available on this device.');
  }

  const hasPermission = await requestLocationPermission();

  if (!hasPermission) {
    throw new Error('Location permission is required to share the driver location.');
  }

  const locationSubscription = locationEvents.addListener('traceLocation', (location) => {
    onLocation(normalizeNativeLocation(location));
  });
  const errorSubscription = locationEvents.addListener('traceLocationError', (error) => {
    onError?.(error?.message || 'Driver GPS is unavailable.');
  });

  try {
    await TraceLocationModule.start(intervalMs, distanceMeters, accuracyMeters);
  } catch (error) {
    locationSubscription.remove();
    errorSubscription.remove();
    throw error;
  }

  return () => {
    locationSubscription.remove();
    errorSubscription.remove();
    TraceLocationModule.stop();
  };
}

export function stopDriverLocationUpdates() {
  TraceLocationModule?.stop();
}
