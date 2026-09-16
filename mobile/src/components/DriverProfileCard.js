import React from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';
import FontAwesome5 from 'react-native-vector-icons/FontAwesome5';
import { colors } from '../theme/colors';

const initialsFor = (name) => {
  const parts = String(name || 'Driver').trim().split(/\s+/).filter(Boolean);
  return parts.slice(0, 2).map((part) => part[0]?.toUpperCase()).join('') || 'DR';
};

const normalizeDriver = (driver, ride = {}) => ({
  name: driver?.name || [driver?.firstName, driver?.lastName].filter(Boolean).join(' ') || ride.driverName || 'Assigned driver',
  profilePhotoUrl: driver?.profilePhotoUrl || driver?.profilePhoto || ride.driverProfilePhotoUrl || null,
  vehicle: driver?.vehicle || ride.vehicle || '-',
  vehicleModel: driver?.vehicleModel || ride.vehicleModel || '',
  vehiclePlateNumber: driver?.vehiclePlateNumber || ride.vehiclePlateNumber || '',
  vehicleColor: driver?.vehicleColor || ride.vehicleColor || '',
  vehicleCapacity: driver?.vehicleCapacity || ride.vehicleCapacity || null,
  licenseNumber: driver?.licenseNumber || ride.driverLicenseNumber || '',
  licenseExpiry: driver?.licenseExpiry || ride.driverLicenseExpiry || '',
  approvalStatus: driver?.approvalStatus || ride.driverApprovalStatus || 'approved',
  isOnline: driver?.isOnline ?? ride.driverIsOnline,
  hasDriverLocation: ride.hasDriverLocation,
  locationQuality: ride.locationQuality || '',
  licensePhotoUrl: driver?.licensePhotoUrl || driver?.licensePhotoPath || ride.driverLicensePhotoUrl || null,
  vehicleOrcrUrl: driver?.vehicleOrcrUrl || driver?.vehicleOrcrPath || ride.vehicleOrcrUrl || null,
});

function Credential({ icon, label, value }) {
  if (!value) {
    return null;
  }

  return (
    <View style={styles.credential}>
      <FontAwesome5 name={icon} size={11} solid color={colors.slate} />
      <Text style={styles.credentialText}>{label}: {value}</Text>
    </View>
  );
}

export default function DriverProfileCard({ driver, ride, context = 'transport' }) {
  const data = normalizeDriver(driver, ride);
  const vehicleLabel = data.vehicleModel || data.vehiclePlateNumber
    ? [data.vehicleModel, data.vehiclePlateNumber].filter(Boolean).join(' - ')
    : data.vehicle;
  const statusLabel = data.isOnline === undefined ? data.approvalStatus : `${data.isOnline ? 'Online' : 'Offline'} / ${data.approvalStatus}`;
  const gpsLabel = data.hasDriverLocation === undefined ? '' : data.hasDriverLocation ? (data.locationQuality || 'Live') : 'Waiting';

  return (
    <View style={styles.wrapper}>
      <View style={styles.header}>
        {data.profilePhotoUrl ? (
          <Image source={{ uri: data.profilePhotoUrl }} style={styles.avatar} />
        ) : (
          <View style={styles.avatarFallback}>
            <Text style={styles.avatarText}>{initialsFor(data.name)}</Text>
          </View>
        )}
        <View style={styles.identity}>
          <Text style={styles.name}>{data.name}</Text>
          <Text style={styles.meta}>{context === 'live' ? 'Live transport driver' : 'TRACE transport driver'}</Text>
        </View>
        <View style={styles.verified}>
          <FontAwesome5 name="shield-alt" size={12} solid color={colors.success} />
          <Text style={styles.verifiedText}>Verified</Text>
        </View>
      </View>

      <View style={styles.credentials}>
        <Credential icon="car-side" label="Vehicle" value={vehicleLabel} />
        <Credential icon="palette" label="Color" value={data.vehicleColor} />
        <Credential icon="users" label="Seats" value={data.vehicleCapacity ? `${data.vehicleCapacity}` : ''} />
        <Credential icon="id-card" label="License" value={data.licenseNumber} />
        <Credential icon="calendar-check" label="License expiry" value={data.licenseExpiry} />
        <Credential icon="certificate" label="Status" value={statusLabel} />
        <Credential icon="satellite-dish" label="GPS" value={gpsLabel} />
        <Credential icon="file-alt" label="License photo" value={data.licensePhotoUrl ? 'Uploaded' : ''} />
        <Credential icon="clipboard-check" label="ORCR" value={data.vehicleOrcrUrl ? 'Uploaded' : ''} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    gap: 10,
    marginBottom: 12,
  },
  header: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 12,
  },
  avatar: {
    width: 54,
    height: 54,
    borderRadius: 27,
    backgroundColor: colors.line,
  },
  avatarFallback: {
    width: 54,
    height: 54,
    borderRadius: 27,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.ink,
  },
  avatarText: {
    color: colors.white,
    fontSize: 17,
    fontWeight: '900',
  },
  identity: {
    flex: 1,
  },
  name: {
    color: colors.ink,
    fontSize: 16,
    fontWeight: '900',
  },
  meta: {
    color: colors.slate,
    fontSize: 12,
    marginTop: 2,
  },
  verified: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 5,
  },
  verifiedText: {
    color: colors.success,
    fontSize: 11,
    fontWeight: '800',
  },
  credentials: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  credential: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 6,
    minHeight: 28,
    paddingHorizontal: 8,
    paddingVertical: 5,
    backgroundColor: colors.sky,
    borderRadius: 8,
  },
  credentialText: {
    color: colors.deep,
    fontSize: 11,
    fontWeight: '700',
  },
});
