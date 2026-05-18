import React, { useEffect, useRef, useState } from 'react';
import { Alert, PermissionsAndroid, Platform, StyleSheet, Text, TextInput, View } from 'react-native';
import AppButton from './AppButton';
import OpenStreetMapView from './OpenStreetMapView';
import { colors } from '../theme/colors';

const DEFAULT_COORDINATE = {
  latitude: 10.676,
  longitude: 122.562,
};

const GEOCODER_HEADERS = {
  Accept: 'application/json',
  'Accept-Language': 'en',
  'User-Agent': 'TRACE-Mobile/1.0',
};

const isDefaultCoordinate = (coordinate) =>
  Math.abs(Number(coordinate.latitude) - DEFAULT_COORDINATE.latitude) < 0.000001 &&
  Math.abs(Number(coordinate.longitude) - DEFAULT_COORDINATE.longitude) < 0.000001;

const fetchJson = async (url) => {
  const response = await fetch(url, { headers: GEOCODER_HEADERS });

  if (!response.ok) {
    throw new Error(`Geocoder returned ${response.status}`);
  }

  return response.json();
};

export default function AddressPinPicker({ label = 'Address', value, latitude, longitude, onChange }) {
  const [searching, setSearching] = useState(false);
  const [canLocate, setCanLocate] = useState(Platform.OS !== 'android');
  const usedCurrentLocation = useRef(false);
  const coordinate = {
    latitude: Number(latitude) || DEFAULT_COORDINATE.latitude,
    longitude: Number(longitude) || DEFAULT_COORDINATE.longitude,
  };

  const updateLocation = ({ address = value, latitude: nextLatitude, longitude: nextLongitude }) => {
    onChange({
      address: address || '',
      latitude: String(nextLatitude),
      longitude: String(nextLongitude),
    });
  };

  useEffect(() => {
    if (Platform.OS !== 'android') {
      return;
    }

    let mounted = true;

    const requestLocationPermission = async () => {
      try {
        const result = await PermissionsAndroid.request(PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION);

        if (mounted) {
          setCanLocate(result === PermissionsAndroid.RESULTS.GRANTED);
        }
      } catch {
        if (mounted) {
          setCanLocate(false);
        }
      }
    };

    requestLocationPermission();

    return () => {
      mounted = false;
    };
  }, []);

  const formatPhotonFeature = (feature) => {
    const properties = feature?.properties || {};

    return [properties.name, properties.street, properties.city || properties.county, properties.state, properties.country]
      .filter(Boolean)
      .join(', ');
  };

  const reverseGeocodeAddress = async (nextCoordinate) => {
    try {
      const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&lat=${nextCoordinate.latitude}&lon=${nextCoordinate.longitude}`;
      const json = await fetchJson(url);

      if (json.display_name) {
        return json.display_name;
      }
    } catch {
      // Try the fallback geocoder below.
    }

    const fallbackUrl = `https://photon.komoot.io/reverse?lat=${nextCoordinate.latitude}&lon=${nextCoordinate.longitude}&limit=1`;
    const fallbackJson = await fetchJson(fallbackUrl);
    const feature = fallbackJson?.features?.[0];

    return formatPhotonFeature(feature);
  };

  const reverseGeocode = async (nextCoordinate) => {
    updateLocation({
      latitude: nextCoordinate.latitude,
      longitude: nextCoordinate.longitude,
    });

    try {
      const address = await reverseGeocodeAddress(nextCoordinate);

      if (address) {
        updateLocation({
          address,
          latitude: nextCoordinate.latitude,
          longitude: nextCoordinate.longitude,
        });
      }
    } catch {
      // The pin coordinates are still useful even if reverse geocoding is unavailable.
    }
  };

  const geocodeAddress = async () => {
    if (!value.trim()) {
      Alert.alert('Pin address', 'Enter an address or tap the map to place the pin.');
      return;
    }

    setSearching(true);

    try {
      const query = encodeURIComponent(value);
      let result;

      try {
        const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=1&countrycodes=ph&q=${query}`;
        const json = await fetchJson(url);
        const item = json?.[0];

        if (item) {
          result = {
            latitude: Number(item.lat),
            longitude: Number(item.lon),
            address: item.display_name || value,
          };
        }
      } catch {
        // Nominatim can throttle or reject mobile requests; Photon gives us a second route.
      }

      if (!result) {
        const fallbackUrl = `https://photon.komoot.io/api/?q=${query}&limit=1&lat=${coordinate.latitude}&lon=${coordinate.longitude}`;
        const fallbackJson = await fetchJson(fallbackUrl);
        const feature = fallbackJson?.features?.[0];
        const [fallbackLongitude, fallbackLatitude] = feature?.geometry?.coordinates || [];

        if (feature && Number.isFinite(Number(fallbackLatitude)) && Number.isFinite(Number(fallbackLongitude))) {
          result = {
            latitude: Number(fallbackLatitude),
            longitude: Number(fallbackLongitude),
            address: formatPhotonFeature(feature) || value,
          };
        }
      }

      if (!result) {
        Alert.alert('Pin address', 'No matching location was found. Try a more specific address.');
        return;
      }

      updateLocation({
        address: result.address,
        latitude: result.latitude,
        longitude: result.longitude,
      });
    } catch (error) {
      Alert.alert('Pin address', 'Address search is unavailable right now. You can still tap or drag the pin on the map.');
    } finally {
      setSearching(false);
    }
  };

  const handleCurrentLocation = async (nextCoordinate) => {
    if (usedCurrentLocation.current || !isDefaultCoordinate(coordinate)) {
      return;
    }

    usedCurrentLocation.current = true;
    updateLocation({
      address: value,
      latitude: nextCoordinate.latitude,
      longitude: nextCoordinate.longitude,
    });

    if (!value.trim()) {
      try {
        const address = await reverseGeocodeAddress(nextCoordinate);

        if (address) {
          updateLocation({
            address,
            latitude: nextCoordinate.latitude,
            longitude: nextCoordinate.longitude,
          });
        }
      } catch {
        // The current pin is still more accurate than the app fallback coordinate.
      }
    }
  };

  return (
    <View style={styles.wrapper}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        value={value}
        onChangeText={(address) => updateLocation({ address, latitude: coordinate.latitude, longitude: coordinate.longitude })}
        placeholder="Search or type the address"
        multiline
        style={[styles.input, styles.multiline]}
        placeholderTextColor="#98a2b3"
      />
      <AppButton icon="search-location" label={searching ? 'Searching...' : 'Find Address'} variant="secondary" onPress={geocodeAddress} />
      <View style={styles.mapWrap}>
        <OpenStreetMapView
          center={coordinate}
          markers={[
            {
              coordinate,
              draggable: true,
              title: 'Pinned address',
              description: 'Drag or tap the map to update this location',
            },
          ]}
          zoom={16}
          style={styles.map}
          locateOnLoad={canLocate}
          onMapPress={reverseGeocode}
          onMarkerDragEnd={reverseGeocode}
          onCurrentLocation={handleCurrentLocation}
        />
      </View>
      <Text style={styles.coords}>
        Lat {Number(coordinate.latitude).toFixed(6)} / Long {Number(coordinate.longitude).toFixed(6)}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    marginBottom: 14,
  },
  label: {
    marginBottom: 6,
    color: colors.ink,
    fontSize: 14,
    fontWeight: '700',
  },
  input: {
    backgroundColor: colors.white,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 14,
    paddingHorizontal: 14,
    paddingVertical: 12,
    color: colors.ink,
    marginBottom: 10,
  },
  multiline: {
    minHeight: 86,
    textAlignVertical: 'top',
  },
  mapWrap: {
    height: 280,
    borderRadius: 8,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: colors.line,
    marginTop: 10,
  },
  map: {
    height: '100%',
    width: '100%',
  },
  coords: {
    color: colors.slate,
    fontSize: 12,
    marginTop: 8,
  },
});
