import React, { useState } from 'react';
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

export default function ProfileScreen({ navigation }) {
  const { currentRole, currentUser, logout, updateProfile, error } = useAppContext();
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({
    firstName: currentUser?.firstName || '',
    lastName: currentUser?.lastName || '',
    email: currentUser?.email || '',
    mobileNumber: currentUser?.mobileNumber || '',
    password: '',
    address: currentUser?.address || '',
    emergencyContactName: currentUser?.emergencyContactName || '',
    emergencyContactNumber: currentUser?.emergencyContactNumber || '',
    licenseNumber: currentUser?.licenseNumber || '',
    licenseExpiry: currentUser?.licenseExpiry || '',
    vehiclePlateNumber: currentUser?.vehiclePlateNumber || '',
    vehicleModel: currentUser?.vehicleModel || '',
    vehicleColor: currentUser?.vehicleColor || '',
    profilePhoto: null,
    validId: null,
    licensePhoto: null,
    vehicleOrcr: null,
  });
  const { exitToWelcome } = useAppShell();
  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));
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
    appendImage(payload, 'vehicle_orcr', form.vehicleOrcr);
    await updateProfile(payload);
    setEditing(false);
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="profile" />}>
      <HeaderBlock eyebrow="Account" title="Profile summary" subtitle="Edit your account details, password, and verification information." />

      <SectionCard title={`${currentUser?.firstName || ''} ${currentUser?.lastName || ''}`.trim()} subtitle={currentUser?.email}>
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
                <FormInput label="Address" value={form.address} onChangeText={(value) => updateField('address', value)} placeholder="Home address" multiline />
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
                <ImagePickerField label="Driver License Image" value={form.licensePhoto} onChange={(value) => updateField('licensePhoto', value)} />
                <ImagePickerField label="Vehicle ORCR Image" value={form.vehicleOrcr} onChange={(value) => updateField('vehicleOrcr', value)} />
              </>
            ) : null}
            {error ? <InfoRow label="Error" value={error} /> : null}
            <AppButton label="Save Changes" onPress={handleSave} />
            <AppButton label="Cancel" variant="ghost" onPress={() => setEditing(false)} />
          </>
        ) : (
          <>
            <InfoRow label="Mobile Number" value={currentUser?.mobileNumber || '-'} />
            {currentRole === 'parent' ? <InfoRow label="Address" value={currentUser?.address || '-'} /> : null}
            {currentRole === 'parent' ? <InfoRow label="Emergency Contact" value={currentUser?.emergencyContactName || '-'} /> : null}
            {currentRole === 'driver' ? <InfoRow label="License Number" value={currentUser?.licenseNumber || '-'} /> : null}
            {currentRole === 'driver' ? <InfoRow label="Vehicle" value={`${currentUser?.vehicleModel || '-'} - ${currentUser?.vehiclePlateNumber || '-'}`} /> : null}
            {currentRole === 'driver' ? <InfoRow label="ORCR Path" value={currentUser?.vehicleOrcrPath || '-'} /> : null}
            <AppButton label="Edit Account" onPress={() => setEditing(true)} />
          </>
        )}
        <AppButton
          label="Logout"
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
