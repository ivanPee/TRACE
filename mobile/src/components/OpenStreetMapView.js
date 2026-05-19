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

export default function OpenStreetMapView({ center, markers = [], polylines = [], zoom = 15, style, locateOnLoad = false, onMapPress, onMarkerDragEnd, onCurrentLocation, onLocationError }) {
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
      .trace-marker {
        align-items: center;
        border: 3px solid #ffffff;
        border-radius: 999px;
        box-shadow: 0 6px 18px rgba(20, 54, 66, 0.28);
        color: #ffffff;
        display: flex;
        font-size: 12px;
        font-weight: 800;
        height: 34px;
        justify-content: center;
        min-width: 34px;
        padding: 0 6px;
        width: 34px;
      }
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
          color: marker.color || '#f77f00',
          label: escapeHtml(marker.label || ''),
          offsetY: Number(marker.offsetY || 0),
          zIndexOffset: Number(marker.zIndexOffset || 0),
          size: Number(marker.size || 34),
        }))
      )};
      const polylines = ${serialize(
        safePolylines.map((polyline) => ({
          coordinates: polyline.coordinates.map(toLatLng),
          color: polyline.color || '#1f2a44',
          width: polyline.width || 5,
          opacity: polyline.opacity ?? 0.9,
          dashArray: polyline.dashArray || null,
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
        L.polyline(line.coordinates, {
          color: line.color,
          weight: line.width,
          opacity: line.opacity,
          dashArray: line.dashArray,
          lineCap: 'round',
          lineJoin: 'round'
        }).addTo(map);
      });

      markers.forEach((marker, index) => {
        const popup = marker.description ? '<strong>' + marker.title + '</strong><br>' + marker.description : marker.title;
        const icon = L.divIcon({
          className: '',
          html: '<div class="trace-marker" style="background:' + marker.color + ';height:' + marker.size + 'px;min-width:' + marker.size + 'px;width:' + marker.size + 'px;transform:translateY(' + marker.offsetY + 'px)">' + (marker.label || '') + '</div>',
          iconSize: [Math.max(46, marker.size), Math.max(34, marker.size)],
          iconAnchor: [23, 17],
          popupAnchor: [0, -18]
        });
        const leafletMarker = L.marker(marker.coordinate, { draggable: marker.draggable, icon, zIndexOffset: marker.zIndexOffset }).addTo(map).bindPopup(popup);
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

      if (${locateOnLoad ? 'true' : 'false'} && navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          (position) => {
            const coordinate = {
              latitude: position.coords.latitude,
              longitude: position.coords.longitude,
            };
            postMessage({ type: 'currentLocation', coordinate });
          },
          (error) => {
            postMessage({ type: 'locationError', message: error.message || 'Location unavailable' });
          },
          { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 }
        );
      }
    </script>
  </body>
</html>`;
  }, [center, locateOnLoad, markers, polylines, zoom]);

  const handleMessage = (event) => {
    try {
      const message = JSON.parse(event.nativeEvent.data);

      if (message.type === 'mapPress') {
        onMapPress?.(message.coordinate);
      }

      if (message.type === 'markerDragEnd') {
        onMarkerDragEnd?.(message.coordinate, message.index);
      }

      if (message.type === 'currentLocation') {
        onCurrentLocation?.(message.coordinate);
      }

      if (message.type === 'locationError') {
        onLocationError?.(message.message);
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
        geolocationEnabled
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
