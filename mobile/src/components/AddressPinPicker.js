import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import AppButton from './AppButton';
import OpenStreetMapView from './OpenStreetMapView';
import { api } from '../services/api';
import { colors } from '../theme/colors';

const SEARCH_LIMIT = 8;
const DEFAULT_COORDINATE = {
  latitude: 10.6765,
  longitude: 122.9509,
};

const completeAddressQuery = (query) => {
  const trimmed = String(query || '').trim().replace(/\s+/g, ' ');
  const normalized = trimmed.toLowerCase();

  if (!trimmed || normalized.includes('bacolod') || normalized.includes('negros') || normalized.includes('philippines')) {
    return trimmed;
  }

  return `${trimmed}, Bacolod City, Negros Occidental, Philippines`;
};

const searchLocations = async (query, center) => {
  const data = await api.searchLocations({
    query,
    latitude: center.latitude,
    longitude: center.longitude,
    limit: SEARCH_LIMIT,
  });

  return data.results || [];
};

export default function AddressPinPicker({ label = 'Address', value, latitude, longitude, onChange }) {
  const [searching, setSearching] = useState(false);
  const [results, setResults] = useState([]);
  const [status, setStatus] = useState('');
  const suppressAutoSearchRef = useRef(false);
  const lastAutoQueryRef = useRef('');
  const addressValue = String(value || '');
  const coordinate = useMemo(() => ({
    latitude: Number(latitude) || DEFAULT_COORDINATE.latitude,
    longitude: Number(longitude) || DEFAULT_COORDINATE.longitude,
  }), [latitude, longitude]);

  const updateLocation = ({ address = addressValue, latitude: nextLatitude, longitude: nextLongitude }) => {
    onChange({
      address,
      latitude: String(nextLatitude),
      longitude: String(nextLongitude),
    });
  };

  const chooseResult = (result) => {
    suppressAutoSearchRef.current = true;
    updateLocation({
      address: result.address,
      latitude: result.latitude,
      longitude: result.longitude,
    });
    setResults([]);
    setStatus(`Pinned from ${result.source}${result.precision ? ` / ${result.precision}` : ''}.`);
  };

  useEffect(() => {
    const query = completeAddressQuery(addressValue);

    if (suppressAutoSearchRef.current) {
      suppressAutoSearchRef.current = false;
      return undefined;
    }

    if (query.length < 6 || query === lastAutoQueryRef.current) {
      return undefined;
    }

    let cancelled = false;
    const timer = setTimeout(async () => {
      lastAutoQueryRef.current = query;

      try {
        const matches = await searchLocations(query, coordinate);

        if (!cancelled && matches.length) {
          setResults(matches);
          setStatus(`Suggestions for ${query}`);
        }
      } catch {
        // Keep typing smooth; the explicit Find button will show actionable errors.
      }
    }, 650);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [addressValue, coordinate]);

  const reverseGeocode = async (nextCoordinate) => {
    updateLocation({
      latitude: nextCoordinate.latitude,
      longitude: nextCoordinate.longitude,
    });
    setResults([]);
    setStatus('Pin moved. Looking up the street address...');

    try {
      const result = await api.reverseGeocode({
        latitude: nextCoordinate.latitude,
        longitude: nextCoordinate.longitude,
      });

      if (result.address) {
        updateLocation({
          address: result.address,
          latitude: nextCoordinate.latitude,
          longitude: nextCoordinate.longitude,
        });
        setStatus('Pinned address updated.');
      } else {
        setStatus('Pin updated. Type a label if the street name is missing.');
      }
    } catch {
      setStatus('Pin updated. Address lookup is unavailable, but the coordinates were saved.');
      // The pin coordinates are still useful even if reverse geocoding is unavailable.
    }
  };

  const geocodeAddress = async () => {
    if (!addressValue.trim()) {
      Alert.alert('Pin address', 'Enter an address or tap the map to place the pin.');
      return;
    }

    setSearching(true);
    setResults([]);
    const query = completeAddressQuery(addressValue);
    setStatus(`Searching ${query}...`);

    try {
      const matches = await searchLocations(query, coordinate);
      const result = matches[0];

      if (!result) {
        setStatus('No matching location found. Try adding barangay, city, or province.');
        return;
      }

      chooseResult(result);
      setResults(matches.slice(1));
    } catch (error) {
      setStatus(error.message || 'Map search is unavailable. You can still tap or drag the pin.');
    } finally {
      setSearching(false);
    }
  };

  return (
    <View style={styles.wrapper}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        value={addressValue}
        onChangeText={(address) => updateLocation({ address, latitude: coordinate.latitude, longitude: coordinate.longitude })}
        placeholder="Search or type the address"
        multiline
        style={[styles.input, styles.multiline]}
        placeholderTextColor={colors.placeholder}
        onSubmitEditing={geocodeAddress}
      />
      <AppButton icon="search-location" label={searching ? 'Searching...' : 'Find Address'} variant="secondary" disabled={searching} onPress={geocodeAddress} />
      {status ? <Text style={styles.status}>{status}</Text> : null}
      {results.length ? (
        <View style={styles.results}>
          {results.map((result) => (
            <Pressable key={result.id} style={({ pressed }) => [styles.result, pressed && styles.resultPressed]} onPress={() => chooseResult(result)}>
              <Text style={styles.resultTitle} numberOfLines={2}>{result.address}</Text>
              <Text style={styles.resultMeta}>
                {result.precision ? `${result.precision} / ` : ''}{Number(result.latitude).toFixed(6)}, {Number(result.longitude).toFixed(6)}
              </Text>
            </Pressable>
          ))}
        </View>
      ) : null}
      <View style={styles.mapWrap}>
        <OpenStreetMapView
          center={coordinate}
          markers={[
            {
              coordinate,
              type: label.toLowerCase().includes('drop') ? 'dropoff' : 'pickup',
              draggable: true,
              title: 'Pinned address',
              description: 'Drag or tap the map to update this location',
            },
          ]}
          zoom={18}
          style={styles.map}
          onMapPress={reverseGeocode}
          onMarkerDragEnd={reverseGeocode}
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
  status: {
    color: colors.slate,
    fontSize: 12,
    lineHeight: 18,
    marginBottom: 8,
  },
  results: {
    gap: 8,
    marginBottom: 10,
  },
  result: {
    backgroundColor: colors.white,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  resultPressed: {
    opacity: 0.78,
  },
  resultTitle: {
    color: colors.deep,
    fontSize: 13,
    fontWeight: '700',
    lineHeight: 18,
  },
  resultMeta: {
    color: colors.slate,
    fontSize: 11,
    marginTop: 4,
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
