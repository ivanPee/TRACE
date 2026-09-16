package com.traceclishell

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Bundle
import android.os.Looper
import androidx.core.content.ContextCompat
import com.facebook.react.bridge.Arguments
import com.facebook.react.bridge.Promise
import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.bridge.ReactContextBaseJavaModule
import com.facebook.react.bridge.ReactMethod
import com.facebook.react.modules.core.DeviceEventManagerModule

class TraceLocationModule(private val reactContext: ReactApplicationContext) :
  ReactContextBaseJavaModule(reactContext), LocationListener {

  private val maxCachedLocationAgeMs = 30000L
  private var locationManager: LocationManager? = null
  private var maxAccuracyMeters: Float = 100f

  override fun getName(): String = "TraceLocationModule"

  @ReactMethod
  fun start(intervalMs: Double, distanceMeters: Double, accuracyMeters: Double, promise: Promise) {
    if (!hasLocationPermission()) {
      promise.reject("permission_denied", "Location permission is required.")
      return
    }

    val manager = reactContext.getSystemService(Context.LOCATION_SERVICE) as LocationManager
    val providers = listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
      .filter { manager.isProviderEnabled(it) }

    if (providers.isEmpty()) {
      promise.reject("provider_unavailable", "Turn on device location services.")
      return
    }

    locationManager = manager
    maxAccuracyMeters = accuracyMeters.toFloat().coerceAtLeast(10f)
    stopUpdates()

    try {
      providers.forEach { provider ->
        manager.requestLocationUpdates(
          provider,
          intervalMs.toLong().coerceAtLeast(1000L),
          distanceMeters.toFloat().coerceAtLeast(0f),
          this,
          Looper.getMainLooper()
        )
        manager.getLastKnownLocation(provider)
          ?.takeIf { System.currentTimeMillis() - it.time <= maxCachedLocationAgeMs }
          ?.let { emitLocation(it) }
      }
      promise.resolve(true)
    } catch (exception: SecurityException) {
      promise.reject("permission_denied", "Location permission is required.")
    }
  }

  @ReactMethod
  fun stop() {
    stopUpdates()
  }

  @ReactMethod
  fun addListener(eventName: String) = Unit

  @ReactMethod
  fun removeListeners(count: Int) = Unit

  override fun onLocationChanged(location: Location) {
    emitLocation(location)
  }

  @Deprecated("Deprecated in Android API 29")
  override fun onStatusChanged(provider: String?, status: Int, extras: Bundle?) = Unit

  override fun onProviderEnabled(provider: String) = Unit

  override fun onProviderDisabled(provider: String) {
    sendError("Location provider disabled.")
  }

  private fun hasLocationPermission(): Boolean =
    ContextCompat.checkSelfPermission(reactContext, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED ||
      ContextCompat.checkSelfPermission(reactContext, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED

  private fun emitLocation(location: Location) {
    if (location.hasAccuracy() && location.accuracy > maxAccuracyMeters) {
      sendError("Weak GPS fix (${location.accuracy.toInt()} m).")
      return
    }

    val payload = Arguments.createMap().apply {
      putDouble("latitude", location.latitude)
      putDouble("longitude", location.longitude)
      putDouble("accuracy", if (location.hasAccuracy()) location.accuracy.toDouble() else -1.0)
      putDouble("speed", if (location.hasSpeed()) (location.speed * 3.6).toDouble() else -1.0)
      putDouble("heading", if (location.hasBearing()) location.bearing.toDouble() else -1.0)
      putString("provider", location.provider ?: "unknown")
      putDouble("timestamp", location.time.toDouble())
    }

    reactContext
      .getJSModule(DeviceEventManagerModule.RCTDeviceEventEmitter::class.java)
      .emit("traceLocation", payload)
  }

  private fun sendError(message: String) {
    val payload = Arguments.createMap().apply {
      putString("message", message)
    }

    reactContext
      .getJSModule(DeviceEventManagerModule.RCTDeviceEventEmitter::class.java)
      .emit("traceLocationError", payload)
  }

  private fun stopUpdates() {
    try {
      locationManager?.removeUpdates(this)
    } catch (_: SecurityException) {
      // Location permission can be revoked while the app is running.
    }
  }
}
