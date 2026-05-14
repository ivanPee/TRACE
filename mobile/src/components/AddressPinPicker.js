import React, { useRef, useState } from 'react';
import { Alert, Platform, StyleSheet, Text, TextInput, View } from 'react-native';
import MapView, { Marker, UrlTile } from 'react-native-maps';
import AppButton from './AppButton';
import { colors } from '../theme/colors';

const DEFAULT_COORDINATE = {
  latitude: 10.676,
  longitude: 122.562,
};

export default function AddressPinPicker({ label = 'Address', value, latitude, longitude, onChange }) {
  const mapRef = useRef(null);
  const [searching, setSearching] = useState(false);
  const coordinate = {
    latitude: Number(latitude) || DEFAULT_COORDINATE.latitude,
    longitude: Number(longitude) || DEFAULT_COORDINATE.longitude,
  };

  const updateLocation = ({ address = value, latitude: nextLatitude, longitude: nextLongitude }) => {
    onChange({
      address,
      latitude: String(nextLatitude),
      longitude: String(nextLongitude),
    });
  };

  const moveMap = (nextCoordinate) => {
    mapRef.current?.animateToRegion(
      {
        ...nextCoordinate,
        latitudeDelta: 0.01,
        longitudeDelta: 0.01,
      },
      350
    );
  };

  const reverseGeocode = async (nextCoordinate) => {
    updateLocation({
      latitude: nextCoordinate.latitude,
      longitude: nextCoordinate.longitude,
    });

    try {
      const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${nextCoordinate.latitude}&lon=${nextCoordinate.longitude}`;
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      const json = await response.json();

      if (json.display_name) {
        updateLocation({
          address: json.display_name,
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
      const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(value)}`;
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      const json = await response.json();
      const result = json?.[0];

      if (!result) {
        Alert.alert('Pin address', 'No matching location was found. Try a more specific address.');
        return;
      }

      const nextCoordinate = {
        latitude: Number(result.lat),
        longitude: Number(result.lon),
      };

      updateLocation({
        address: result.display_name || value,
        latitude: nextCoordinate.latitude,
        longitude: nextCoordinate.longitude,
      });
      moveMap(nextCoordinate);
    } catch {
      Alert.alert('Pin address', 'Cannot search right now. Check your internet connection and try again.');
    } finally {
      setSearching(false);
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
        <MapView
          ref={mapRef}
          initialRegion={{
            ...coordinate,
            latitudeDelta: 0.03,
            longitudeDelta: 0.03,
          }}
          style={styles.map}
          mapType={Platform.OS === 'android' ? 'none' : 'standard'}
          onPress={(event) => {
            const nextCoordinate = event.nativeEvent.coordinate;
            moveMap(nextCoordinate);
            reverseGeocode(nextCoordinate);
          }}
        >
          <UrlTile urlTemplate="https://tile.openstreetmap.org/{z}/{x}/{y}.png" maximumZ={19} flipY={false} />
          <Marker
            coordinate={coordinate}
            draggable
            title="Pinned address"
            description="Drag or tap the map to update this location"
            onDragEnd={(event) => {
              const nextCoordinate = event.nativeEvent.coordinate;
              moveMap(nextCoordinate);
              reverseGeocode(nextCoordinate);
            }}
          />
        </MapView>
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
