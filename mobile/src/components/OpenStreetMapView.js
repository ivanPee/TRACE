import React, { useMemo } from 'react';
import { StyleSheet, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { colors } from '../theme/colors';

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

const markerSvg = {
  driver: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11l1.5-4.5h11L19 11h1a1 1 0 0 1 1 1v5h-2a2 2 0 0 1-4 0H9a2 2 0 0 1-4 0H3v-5a1 1 0 0 1 1-1h1zm2.6-3L6.7 11h10.6l-.9-3H7.6zM7 16.2a.8.8 0 1 0 0 1.6.8.8 0 0 0 0-1.6zm10 0a.8.8 0 1 0 0 1.6.8.8 0 0 0 0-1.6z"/></svg>',
  pickup: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l9 8h-3v9H6v-9H3l9-8zm-3 9v6h6v-6H9z"/></svg>',
  dropoff: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 21V4h11l.5 3H20v9h-9l-.5-3H7v8H5z"/></svg>',
  student: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm-7 8a7 7 0 0 1 14 0v1H5v-1z"/></svg>',
  pin: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5z"/></svg>',
};

export default function OpenStreetMapView({ center, markers = [], polylines = [], zoom = 15, style, onMapPress, onMarkerDragEnd }) {
  const html = useMemo(() => {
    const safeCenter = normalizePoint(center) || { latitude: 10.6765, longitude: 122.9509 };
    const safeMarkers = markers
      .map((marker) => ({ ...marker, coordinate: normalizePoint(marker.coordinate) }))
      .filter((marker) => marker.coordinate);
    const safePolylines = polylines
      .map((polyline) => ({ ...polyline, coordinates: (polyline.coordinates || []).map(normalizePoint).filter(Boolean) }))
      .filter((polyline) => polyline.coordinates.length);
    const fitPoints = [
      ...safeMarkers.map((marker) => marker.coordinate),
      ...safePolylines.flatMap((polyline) => polyline.coordinates),
    ];
    const allPoints = fitPoints.length ? fitPoints : [safeCenter];
    const hasRoute = safePolylines.some((polyline) => polyline.coordinates.length > 1);

    return `
<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
      html, body, #map { height: 100%; margin: 0; padding: 0; }
      body { background: ${colors.paper}; }
      .leaflet-container {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
        background: ${colors.sand};
      }
      .leaflet-control-zoom {
        border: 0 !important;
        box-shadow: 0 10px 26px ${colors.markerShadow};
        overflow: hidden;
        border-radius: 14px;
      }
      .leaflet-control-zoom a {
        width: 40px;
        height: 40px;
        line-height: 40px;
        border: 0 !important;
        color: ${colors.deep};
        font-size: 20px;
        font-weight: 800;
      }
      .leaflet-control-attribution {
        border-radius: 8px 0 0 0;
        color: ${colors.slate};
        font-size: 10px;
      }
      .trace-control {
        background: ${colors.white};
        border: 0;
        border-radius: 14px;
        box-shadow: 0 10px 26px ${colors.markerShadow};
        color: ${colors.deep};
        cursor: pointer;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 10px;
        min-width: 72px;
        padding: 11px 12px;
      }
      .trace-control:active { transform: translateY(1px); }
      .trace-marker { background: transparent; border: 0; }
      .trace-marker-core {
        width: 42px;
        height: 42px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        border: 3px solid ${colors.white};
        box-shadow: 0 10px 24px ${colors.markerShadow};
        color: ${colors.white};
        font-weight: 800;
        font-size: 13px;
        box-sizing: border-box;
        position: relative;
        transition: transform 160ms ease, box-shadow 160ms ease;
      }
      .trace-marker-driver .trace-marker-core::after {
        content: "";
        position: absolute;
        inset: -9px;
        border-radius: 22px;
        border: 2px solid rgba(48, 52, 60, 0.16);
        animation: tracePulse 1.8s ease-out infinite;
      }
      @keyframes tracePulse {
        from { opacity: 0.9; transform: scale(0.72); }
        to { opacity: 0; transform: scale(1.18); }
      }
      .trace-marker-core svg {
        width: 19px;
        height: 19px;
        display: block;
        fill: currentColor;
      }
      .trace-marker-badge {
        position: absolute;
        right: -8px;
        bottom: -6px;
        min-width: 22px;
        height: 22px;
        border-radius: 11px;
        display: grid;
        place-items: center;
        padding: 0 4px;
        background: ${colors.white};
        color: ${colors.deep};
        border: 1px solid ${colors.line};
        font-size: 10px;
        line-height: 22px;
        font-weight: 900;
        box-sizing: border-box;
      }
      .trace-marker-driver .trace-marker-core { background: ${colors.road}; font-size: 16px; }
      .trace-marker-pickup .trace-marker-core { background: ${colors.accent}; color: ${colors.deep}; }
      .trace-marker-dropoff .trace-marker-core { background: ${colors.danger}; }
      .trace-marker-student .trace-marker-core { background: ${colors.success}; }
      .trace-marker-pin .trace-marker-core { background: ${colors.slate}; }
      .trace-label {
        background: ${colors.white};
        border: 1px solid ${colors.line};
        border-radius: 10px;
        box-shadow: 0 6px 14px ${colors.markerShadow};
        color: ${colors.deep};
        font-size: 11px;
        font-weight: 800;
        padding: 5px 7px;
      }
      .trace-popup .leaflet-popup-content-wrapper {
        border-radius: 14px;
        box-shadow: 0 12px 26px ${colors.markerShadow};
      }
      .trace-popup .leaflet-popup-content {
        color: ${colors.deep};
        font-size: 12px;
        line-height: 18px;
        margin: 11px 13px;
      }
      .leaflet-interactive { transition: stroke-dashoffset 160ms ease; }
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
          type: marker.type || 'pin',
          symbol: marker.symbol ? escapeHtml(marker.symbol) : '',
          label: marker.label ? escapeHtml(marker.label) : '',
          heading: Number(marker.heading) || 0,
          accuracy: Number(marker.accuracy) || 0,
          draggable: Boolean(marker.draggable),
        }))
      )};
      const markerSvgs = ${serialize(markerSvg)};
      const polylines = ${serialize(
        safePolylines.map((polyline) => ({
          coordinates: polyline.coordinates.map(toLatLng),
          color: polyline.color || '${colors.road}',
          width: polyline.width || 5,
          dashed: Boolean(polyline.dashed),
        }))
      )};
      const allPoints = ${serialize(allPoints.map(toLatLng))};
      const postMessage = (payload) => {
        if (window.ReactNativeWebView) {
          window.ReactNativeWebView.postMessage(JSON.stringify(payload));
        }
      };

      const map = L.map('map', {
        zoomControl: false,
        preferCanvas: true,
        zoomSnap: 0.25,
        zoomDelta: 0.5,
        dragging: true,
        touchZoom: true,
        doubleClickZoom: true,
        scrollWheelZoom: true,
        boxZoom: false,
        tap: false,
        bounceAtZoomLimits: false
      }).setView(center, ${Number(zoom) || 15});
      L.control.zoom({ position: 'bottomright' }).addTo(map);
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);

      polylines.forEach((line) => {
        L.polyline(line.coordinates, { color: '${colors.white}', weight: line.width + 8, opacity: 0.92, lineCap: 'round', lineJoin: 'round' }).addTo(map);
        L.polyline(line.coordinates, { color: '${colors.deep}', weight: line.width + 4, opacity: 0.24, lineCap: 'round', lineJoin: 'round' }).addTo(map);
        L.polyline(line.coordinates, {
          color: line.color,
          weight: line.width,
          opacity: 0.95,
          lineCap: 'round',
          lineJoin: 'round',
          dashArray: line.dashed ? '10 12' : null
        }).addTo(map);
      });

      const markerIcon = (marker) => {
        const typeClass = ['driver', 'pickup', 'dropoff', 'student'].includes(marker.type) ? marker.type : 'pin';
        const symbol = markerSvgs[typeClass] || markerSvgs.pin;
        const badge = marker.symbol ? '<b class="trace-marker-badge">' + marker.symbol + '</b>' : '';
        const headingStyle = marker.type === 'driver' ? 'transform: rotate(' + marker.heading + 'deg)' : '';

        return L.divIcon({
          className: 'trace-marker trace-marker-' + typeClass,
          html: '<div class="trace-marker-core" style="' + headingStyle + '"><span>' + symbol + '</span>' + badge + '</div>',
          iconSize: [42, 42],
          iconAnchor: [21, 21],
          popupAnchor: [0, -18],
        });
      };

      markers.forEach((marker, index) => {
        const popup = marker.description ? '<strong>' + marker.title + '</strong><br>' + marker.description : marker.title;
        if (marker.accuracy > 0 && marker.type === 'driver') {
          L.circle(marker.coordinate, {
            radius: marker.accuracy,
            color: '${colors.road}',
            weight: 1,
            fillColor: '${colors.road}',
            fillOpacity: 0.08,
            opacity: 0.16
          }).addTo(map);
        }
        const leafletMarker = L.marker(marker.coordinate, { draggable: marker.draggable, icon: markerIcon(marker) }).addTo(map).bindPopup(popup, { className: 'trace-popup' });
        if (marker.label) {
          leafletMarker.bindTooltip(marker.label, {
            permanent: true,
            direction: 'bottom',
            offset: [0, 18],
            className: 'trace-label'
          });
        }
        leafletMarker.on('dragend', (event) => {
          const point = event.target.getLatLng();
          postMessage({ type: 'markerDragEnd', index, coordinate: { latitude: point.lat, longitude: point.lng } });
        });
      });

      map.on('click', (event) => {
        postMessage({ type: 'mapPress', coordinate: { latitude: event.latlng.lat, longitude: event.latlng.lng } });
      });

      setTimeout(() => map.invalidateSize(), 80);
      setTimeout(() => map.invalidateSize(), 320);

      const fitRoute = () => {
        if (allPoints.length > 1) {
          map.flyToBounds(L.latLngBounds(allPoints), { padding: [54, 44], maxZoom: ${Number(zoom) || 16}, duration: 0.35 });
        } else if (allPoints.length === 1) {
          map.flyTo(allPoints[0], ${Number(zoom) || 16}, { duration: 0.25 });
        }
      };

      const routeControl = L.control({ position: 'bottomleft' });
      routeControl.onAdd = () => {
        const button = L.DomUtil.create('button', 'trace-control');
        button.type = 'button';
        button.innerHTML = '${hasRoute ? 'Fit route' : 'Center'}';
        L.DomEvent.disableClickPropagation(button);
        L.DomEvent.on(button, 'click', fitRoute);
        return button;
      };
      routeControl.addTo(map);

      fitRoute();
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
        nestedScrollEnabled
        overScrollMode="never"
        scrollEnabled={false}
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
