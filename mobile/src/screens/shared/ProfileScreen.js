import React, { useState } from 'react';
import { Image, StyleSheet, Text, View } from 'react-native';
import AddressPinPicker from '../../components/AddressPinPicker';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import ImagePickerField, { appendImage } from '../../components/ImagePickerField';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { useAppShell } from '../../navigation/AppShellContext';
import { colors } from '../../theme/colors';

export default function ProfileScreen({ navigation }) {
  const { currentRole, currentUser, logout, updateProfile, refreshDashboard, error } = useAppContext();
  const [editing, setEditing] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [form, setForm] = useState({
    firstName: currentUser?.firstName || '',
    lastName: currentUser?.lastName || '',
    email: currentUser?.email || '',
    mobileNumber: currentUser?.mobileNumber || '',
    password: '',
    address: currentUser?.address || '',
    addressLatitude: currentUser?.addressLatitude ? String(currentUser.addressLatitude) : '10.676',
    addressLongitude: currentUser?.addressLongitude ? String(currentUser.addressLongitude) : '122.562',
    emergencyContactName: currentUser?.emergencyContactName || '',
    emergencyContactNumber: currentUser?.emergencyContactNumber || '',
    licenseNumber: currentUser?.licenseNumber || '',
    licenseExpiry: currentUser?.licenseExpiry || '',
    vehicleType: currentUser?.vehicleType || '',
    vehiclePlateNumber: currentUser?.vehiclePlateNumber || '',
    vehicleModel: currentUser?.vehicleModel || '',
    vehicleColor: currentUser?.vehicleColor || '',
    profilePhoto: null,
    validId: null,
    licensePhoto: null,
    vehiclePhoto: null,
    vehicleOrcr: null,
  });
  const { exitToWelcome } = useAppShell();
  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));
  const updateAddress = ({ address, latitude, longitude }) => {
    setForm((current) => ({
      ...current,
      address,
      addressLatitude: latitude,
      addressLongitude: longitude,
    }));
  };
  const handleSave = async () => {
    const payload = new FormData();
    Object.entries(form).forEach(([key, value]) => {
      if (value === null || typeof value === 'object') {
        return;
      }

      payload.append(key, value);
    });
    appendImage(payload, 'profile_photo', form.profilePhoto);
    appendImage(payload, 'valid_id', form.validId);
    appendImage(payload, 'license_photo', form.licensePhoto);
    appendImage(payload, 'vehicle_photo', form.vehiclePhoto);
    appendImage(payload, 'vehicle_orcr', form.vehicleOrcr);
    await updateProfile(payload);
    setEditing(false);
  };
  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="profile" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock eyebrow="Account" title="Profile summary" subtitle="Edit your account details, password, and verification information." />

      <SectionCard title={`${currentUser?.firstName || ''} ${currentUser?.lastName || ''}`.trim()} subtitle={currentUser?.email} icon="user-circle">
        <Pill label={(currentRole || 'guest').toUpperCase()} />
        {currentUser?.profilePhotoUrl ? <Image source={{ uri: currentUser.profilePhotoUrl }} style={styles.heroPhoto} resizeMode="cover" /> : null}
        {editing ? (
          <>
            <FormInput label="First Name" value={form.firstName} onChangeText={(value) => updateField('firstName', value)} placeholder="First name" />
            <FormInput label="Last Name" value={form.lastName} onChangeText={(value) => updateField('lastName', value)} placeholder="Last name" />
            <FormInput label="Email" value={form.email} onChangeText={(value) => updateField('email', value)} placeholder="name@example.com" keyboardType="email-address" />
            <FormInput label="Mobile Number" value={form.mobileNumber} onChangeText={(value) => updateField('mobileNumber', value)} placeholder="09xxxxxxxxx" />
            <FormInput label="New Password" value={form.password} onChangeText={(value) => updateField('password', value)} placeholder="Leave blank to keep current password" secureTextEntry />
            <ImagePickerField label="Profile Image" value={form.profilePhoto} onChange={(value) => updateField('profilePhoto', value)} previewUri={currentUser?.profilePhotoUrl} />
            {currentRole === 'parent' ? (
              <>
                <AddressPinPicker label="Home Address" value={form.address} latitude={form.addressLatitude} longitude={form.addressLongitude} onChange={updateAddress} />
                <FormInput label="Emergency Contact Name" value={form.emergencyContactName} onChangeText={(value) => updateField('emergencyContactName', value)} placeholder="Contact name" />
                <FormInput label="Emergency Contact Number" value={form.emergencyContactNumber} onChangeText={(value) => updateField('emergencyContactNumber', value)} placeholder="Contact number" />
                <ImagePickerField label="Valid ID Image" value={form.validId} onChange={(value) => updateField('validId', value)} previewUri={currentUser?.validIdUrl} />
              </>
            ) : null}
            {currentRole === 'driver' ? (
              <>
                <FormInput label="License Number" value={form.licenseNumber} onChangeText={(value) => updateField('licenseNumber', value)} placeholder="License number" />
                <FormInput label="License Expiry" value={form.licenseExpiry} onChangeText={(value) => updateField('licenseExpiry', value)} placeholder="YYYY-MM-DD" />
                <FormInput label="Vehicle Type" value={form.vehicleType} onChangeText={(value) => updateField('vehicleType', value)} placeholder="School Service" />
                <FormInput label="Vehicle Plate Number" value={form.vehiclePlateNumber} onChangeText={(value) => updateField('vehiclePlateNumber', value)} placeholder="ABC-1234" />
                <FormInput label="Vehicle Model" value={form.vehicleModel} onChangeText={(value) => updateField('vehicleModel', value)} placeholder="Toyota Hiace" />
                <FormInput label="Vehicle Color" value={form.vehicleColor} onChangeText={(value) => updateField('vehicleColor', value)} placeholder="White" />
                <ImagePickerField label="Driver License Image" value={form.licensePhoto} onChange={(value) => updateField('licensePhoto', value)} previewUri={currentUser?.licensePhotoUrl} />
                <ImagePickerField label="Vehicle Photo" value={form.vehiclePhoto} onChange={(value) => updateField('vehiclePhoto', value)} previewUri={currentUser?.vehiclePhotoUrl} />
                <ImagePickerField label="Vehicle ORCR Image" value={form.vehicleOrcr} onChange={(value) => updateField('vehicleOrcr', value)} previewUri={currentUser?.vehicleOrcrUrl} />
              </>
            ) : null}
            {error ? <InfoRow icon="exclamation-circle" label="Error" value={error} /> : null}
            <AppButton icon="save" label="Save Changes" onPress={handleSave} />
            <AppButton icon="times" label="Cancel" variant="ghost" onPress={() => setEditing(false)} />
          </>
        ) : (
          <>
            <InfoRow icon="phone-alt" label="Mobile Number" value={currentUser?.mobileNumber || '-'} />
            {currentRole === 'parent' ? <InfoRow icon="home" label="Address" value={currentUser?.address || '-'} /> : null}
            {currentRole === 'parent' ? <InfoRow icon="address-book" label="Emergency Contact" value={currentUser?.emergencyContactName || '-'} /> : null}
            {currentRole === 'driver' ? <InfoRow icon="id-card" label="License Number" value={currentUser?.licenseNumber || '-'} /> : null}
            {currentRole === 'driver' ? <InfoRow icon="shuttle-van" label="Vehicle" value={`${currentUser?.vehicleModel || '-'} - ${currentUser?.vehiclePlateNumber || '-'}`} /> : null}
            {currentRole === 'driver' ? <InfoRow icon="truck" label="Vehicle Type" value={currentUser?.vehicleType || '-'} /> : null}
            {(currentRole === 'parent' && currentUser?.validIdUrl) || (currentRole === 'driver' && (currentUser?.licensePhotoUrl || currentUser?.vehiclePhotoUrl || currentUser?.vehicleOrcrUrl)) ? (
              <View style={styles.gallery}>
                {currentRole === 'parent' && currentUser?.validIdUrl ? (
                  <View style={styles.galleryItem}>
                    <Text style={styles.galleryLabel}>Valid ID</Text>
                    <Image source={{ uri: currentUser.validIdUrl }} style={styles.galleryImage} resizeMode="cover" />
                  </View>
                ) : null}
                {currentRole === 'driver' && currentUser?.licensePhotoUrl ? (
                  <View style={styles.galleryItem}>
                    <Text style={styles.galleryLabel}>License</Text>
                    <Image source={{ uri: currentUser.licensePhotoUrl }} style={styles.galleryImage} resizeMode="cover" />
                  </View>
                ) : null}
                {currentRole === 'driver' && currentUser?.vehiclePhotoUrl ? (
                  <View style={styles.galleryItem}>
                    <Text style={styles.galleryLabel}>Vehicle</Text>
                    <Image source={{ uri: currentUser.vehiclePhotoUrl }} style={styles.galleryImage} resizeMode="cover" />
                  </View>
                ) : null}
                {currentRole === 'driver' && currentUser?.vehicleOrcrUrl ? (
                  <View style={styles.galleryItem}>
                    <Text style={styles.galleryLabel}>ORCR</Text>
                    <Image source={{ uri: currentUser.vehicleOrcrUrl }} style={styles.galleryImage} resizeMode="cover" />
                  </View>
                ) : null}
              </View>
            ) : null}
            <AppButton icon="edit" label="Edit Account" onPress={() => setEditing(true)} />
          </>
        )}
        <AppButton
          label="Logout"
          icon="sign-out-alt"
          variant="ghost"
          onPress={async () => {
            await logout();
            exitToWelcome();
          }}
        />
      </SectionCard>
    </Screen>
  );
}

const styles = StyleSheet.create({
  heroPhoto: {
    width: 112,
    height: 112,
    borderRadius: 28,
    marginTop: 14,
    marginBottom: 18,
    backgroundColor: colors.line,
  },
  gallery: {
    marginTop: 8,
    gap: 14,
  },
  galleryItem: {
    gap: 6,
  },
  galleryLabel: {
    fontSize: 13,
    fontWeight: '700',
    color: colors.ink,
  },
  galleryImage: {
    width: '100%',
    height: 170,
    borderRadius: 18,
    backgroundColor: colors.line,
  },
});
