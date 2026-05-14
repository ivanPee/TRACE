import React, { useMemo } from 'react';
import { StyleSheet, View } from 'react-native';
import { WebView } from 'react-native-webview';

const escapeHtml = (value) =>
  String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const normalizePoint = (point) => {
  if (!point || !Number.isFinite(Number(point.latitude)) || !Number.isFinite(Number(point.longitude))) {
    return null;
  }

  return {
    latitude: Number(point.latitude),
    longitude: Number(point.longitude),
  };
};

const toLatLng = (point) => [point.latitude, point.longitude];

const serialize = (value) => JSON.stringify(value).replace(/<\//g, '<\\/');

export default function OpenStreetMapView({ center, markers = [], polylines = [], zoom = 15, style, onMapPress, onMarkerDragEnd }) {
  const html = useMemo(() => {
    const safeCenter = normalizePoint(center) || { latitude: 10.676, longitude: 122.562 };
    const safeMarkers = markers
      .map((marker) => ({ ...marker, coordinate: normalizePoint(marker.coordinate) }))
      .filter((marker) => marker.coordinate);
    const safePolylines = polylines
      .map((polyline) => ({ ...polyline, coordinates: (polyline.coordinates || []).map(normalizePoint).filter(Boolean) }))
      .filter((polyline) => polyline.coordinates.length);
    const allPoints = [
      safeCenter,
      ...safeMarkers.map((marker) => marker.coordinate),
      ...safePolylines.flatMap((polyline) => polyline.coordinates),
    ];

    return `
<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
      html, body, #map { height: 100%; margin: 0; padding: 0; }
      body { background: #eef2f6; }
      .leaflet-container { font-family: Arial, sans-serif; }
    </style>
  </head>
  <body>
    <div id="map"></div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
      const center = ${serialize(toLatLng(safeCenter))};
      const markers = ${serialize(
        safeMarkers.map((marker) => ({
          coordinate: toLatLng(marker.coordinate),
          title: escapeHtml(marker.title),
          description: escapeHtml(marker.description),
          draggable: Boolean(marker.draggable),
        }))
      )};
      const polylines = ${serialize(
        safePolylines.map((polyline) => ({
          coordinates: polyline.coordinates.map(toLatLng),
          color: polyline.color || '#1f2a44',
          width: polyline.width || 5,
        }))
      )};
      const allPoints = ${serialize(allPoints.map(toLatLng))};
      const postMessage = (payload) => {
        if (window.ReactNativeWebView) {
          window.ReactNativeWebView.postMessage(JSON.stringify(payload));
        }
      };

      const map = L.map('map', { zoomControl: true }).setView(center, ${Number(zoom) || 15});
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);

      polylines.forEach((line) => {
        L.polyline(line.coordinates, { color: line.color, weight: line.width, opacity: 0.9 }).addTo(map);
      });

      markers.forEach((marker, index) => {
        const popup = marker.description ? '<strong>' + marker.title + '</strong><br>' + marker.description : marker.title;
        const leafletMarker = L.marker(marker.coordinate, { draggable: marker.draggable }).addTo(map).bindPopup(popup);
        leafletMarker.on('dragend', (event) => {
          const point = event.target.getLatLng();
          postMessage({ type: 'markerDragEnd', index, coordinate: { latitude: point.lat, longitude: point.lng } });
        });
      });

      map.on('click', (event) => {
        postMessage({ type: 'mapPress', coordinate: { latitude: event.latlng.lat, longitude: event.latlng.lng } });
      });

      if (allPoints.length > 1) {
        map.fitBounds(L.latLngBounds(allPoints), { padding: [32, 32], maxZoom: ${Number(zoom) || 15} });
      }
    </script>
  </body>
</html>`;
  }, [center, markers, polylines, zoom]);

  const handleMessage = (event) => {
    try {
      const message = JSON.parse(event.nativeEvent.data);

      if (message.type === 'mapPress') {
        onMapPress?.(message.coordinate);
      }

      if (message.type === 'markerDragEnd') {
        onMarkerDragEnd?.(message.coordinate, message.index);
      }
    } catch {
      // Ignore malformed messages from the embedded map.
    }
  };

  return (
    <View style={[styles.container, style]}>
      <WebView
        source={{ html }}
        javaScriptEnabled
        domStorageEnabled
        originWhitelist={['*']}
        mixedContentMode="always"
        onMessage={handleMessage}
        style={styles.webView}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    overflow: 'hidden',
  },
  webView: {
    flex: 1,
    backgroundColor: 'transparent',
  },
});
