import React, { useState } from 'react';
import AddressPinPicker from '../../components/AddressPinPicker';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import DriverProfileCard from '../../components/DriverProfileCard';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import ImagePickerField, { appendImage } from '../../components/ImagePickerField';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { useAppShell } from '../../navigation/AppShellContext';

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
    addressLatitude: currentUser?.addressLatitude ? String(currentUser.addressLatitude) : '10.6765',
    addressLongitude: currentUser?.addressLongitude ? String(currentUser.addressLongitude) : '122.9509',
    emergencyContactName: currentUser?.emergencyContactName || '',
    emergencyContactNumber: currentUser?.emergencyContactNumber || '',
    licenseNumber: currentUser?.licenseNumber || '',
    licenseExpiry: currentUser?.licenseExpiry || '',
    vehiclePlateNumber: currentUser?.vehiclePlateNumber || '',
    vehicleModel: currentUser?.vehicleModel || '',
    vehicleColor: currentUser?.vehicleColor || '',
    vehicleCapacity: currentUser?.vehicleCapacity ? String(currentUser.vehicleCapacity) : '4',
    lrn: currentUser?.lrn || '',
    schoolName: currentUser?.schoolName || '',
    gradeLevel: currentUser?.gradeLevel || '',
    pickupAddress: currentUser?.pickupAddress || '',
    pickupLatitude: currentUser?.pickupLatitude ? String(currentUser.pickupLatitude) : '10.6765',
    pickupLongitude: currentUser?.pickupLongitude ? String(currentUser.pickupLongitude) : '122.9509',
    dropoffAddress: currentUser?.dropoffAddress || '',
    dropoffLatitude: currentUser?.dropoffLatitude ? String(currentUser.dropoffLatitude) : '10.6684',
    dropoffLongitude: currentUser?.dropoffLongitude ? String(currentUser.dropoffLongitude) : '123.0198',
    notes: currentUser?.notes || '',
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
  const updatePickup = ({ address, latitude, longitude }) => {
    setForm((current) => ({
      ...current,
      pickupAddress: address,
      pickupLatitude: latitude,
      pickupLongitude: longitude,
    }));
  };
  const updateDropoff = ({ address, latitude, longitude }) => {
    setForm((current) => ({
      ...current,
      dropoffAddress: address,
      dropoffLatitude: latitude,
      dropoffLongitude: longitude,
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
        {editing ? (
          <>
            <FormInput label="First Name" value={form.firstName} onChangeText={(value) => updateField('firstName', value)} placeholder="First name" />
            <FormInput label="Last Name" value={form.lastName} onChangeText={(value) => updateField('lastName', value)} placeholder="Last name" />
            <FormInput label="Email" value={form.email} onChangeText={(value) => updateField('email', value)} placeholder="name@example.com" keyboardType="email-address" />
            <FormInput label="Mobile Number" value={form.mobileNumber} onChangeText={(value) => updateField('mobileNumber', value)} placeholder="09xxxxxxxxx" />
            <FormInput label="New Password" value={form.password} onChangeText={(value) => updateField('password', value)} placeholder="Leave blank to keep current password" secureTextEntry />
            <ImagePickerField label="Profile Image" value={form.profilePhoto} onChange={(value) => updateField('profilePhoto', value)} />
            {currentRole === 'parent' ? (
              <>
                <AddressPinPicker label="Home Address" value={form.address} latitude={form.addressLatitude} longitude={form.addressLongitude} onChange={updateAddress} />
                <FormInput label="Emergency Contact Name" value={form.emergencyContactName} onChangeText={(value) => updateField('emergencyContactName', value)} placeholder="Contact name" />
                <FormInput label="Emergency Contact Number" value={form.emergencyContactNumber} onChangeText={(value) => updateField('emergencyContactNumber', value)} placeholder="Contact number" />
                <ImagePickerField label="Valid ID Image" value={form.validId} onChange={(value) => updateField('validId', value)} />
              </>
            ) : null}
            {currentRole === 'driver' ? (
              <>
                <FormInput label="License Number" value={form.licenseNumber} onChangeText={(value) => updateField('licenseNumber', value)} placeholder="License number" />
                <FormInput label="License Expiry" value={form.licenseExpiry} onChangeText={(value) => updateField('licenseExpiry', value)} placeholder="YYYY-MM-DD" />
                <FormInput label="Vehicle Plate Number" value={form.vehiclePlateNumber} onChangeText={(value) => updateField('vehiclePlateNumber', value)} placeholder="ABC-1234" />
                <FormInput label="Vehicle Model" value={form.vehicleModel} onChangeText={(value) => updateField('vehicleModel', value)} placeholder="Toyota Hiace" />
                <FormInput label="Vehicle Color" value={form.vehicleColor} onChangeText={(value) => updateField('vehicleColor', value)} placeholder="White" />
                <FormInput label="Vehicle Capacity" value={form.vehicleCapacity} onChangeText={(value) => updateField('vehicleCapacity', value.replace(/[^0-9]/g, ''))} placeholder="4" keyboardType="number-pad" />
                <ImagePickerField label="Driver License Image" value={form.licensePhoto} onChange={(value) => updateField('licensePhoto', value)} />
                <ImagePickerField label="Vehicle Photo" value={form.vehiclePhoto} onChange={(value) => updateField('vehiclePhoto', value)} />
                <ImagePickerField label="Vehicle ORCR Image" value={form.vehicleOrcr} onChange={(value) => updateField('vehicleOrcr', value)} />
              </>
            ) : null}
            {currentRole === 'student' ? (
              <>
                <FormInput label="LRN" value={form.lrn} onChangeText={(value) => updateField('lrn', value)} placeholder="Learner reference number" />
                <FormInput label="School Name" value={form.schoolName} onChangeText={(value) => updateField('schoolName', value)} placeholder="School name" />
                <FormInput label="Grade Level" value={form.gradeLevel} onChangeText={(value) => updateField('gradeLevel', value)} placeholder="Grade level" />
                <AddressPinPicker label="Pickup Point" value={form.pickupAddress} latitude={form.pickupLatitude} longitude={form.pickupLongitude} onChange={updatePickup} />
                <AddressPinPicker label="Drop-off Point" value={form.dropoffAddress} latitude={form.dropoffLatitude} longitude={form.dropoffLongitude} onChange={updateDropoff} />
                <FormInput label="Medical Notes" value={form.notes} onChangeText={(value) => updateField('notes', value)} placeholder="Allergies, needs, or notes" multiline />
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
            {currentRole === 'driver' ? <DriverProfileCard driver={currentUser} context="profile" /> : null}
            {currentRole === 'driver' ? <InfoRow icon="id-card" label="License Number" value={currentUser?.licenseNumber || '-'} /> : null}
            {currentRole === 'driver' ? <InfoRow icon="shuttle-van" label="Vehicle" value={`${currentUser?.vehicleModel || '-'} - ${currentUser?.vehiclePlateNumber || '-'}`} /> : null}
            {currentRole === 'driver' ? <InfoRow icon="users" label="Vehicle Capacity" value={`${currentUser?.vehicleCapacity || 1} seats`} /> : null}
            {currentRole === 'driver' ? <InfoRow icon="file-alt" label="ORCR Path" value={currentUser?.vehicleOrcrPath || '-'} /> : null}
            {currentRole === 'student' ? <InfoRow icon="id-card" label="LRN" value={currentUser?.lrn || '-'} /> : null}
            {currentRole === 'student' ? <InfoRow icon="school" label="School" value={currentUser?.schoolName || '-'} /> : null}
            {currentRole === 'student' ? <InfoRow icon="graduation-cap" label="Grade Level" value={currentUser?.gradeLevel || '-'} /> : null}
            {currentRole === 'student' ? <InfoRow icon="map-marker-alt" label="Pickup" value={currentUser?.pickupAddress || '-'} /> : null}
            {currentRole === 'student' ? <InfoRow icon="flag-checkered" label="Drop-off" value={currentUser?.dropoffAddress || '-'} /> : null}
            {currentRole === 'student' ? <InfoRow icon="notes-medical" label="Medical Notes" value={currentUser?.notes || '-'} /> : null}
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
