import React, { useState } from 'react';
import AppButton from '../../components/AppButton';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function RegisterDriverScreen({ navigation }) {
  const { registerDriver } = useAppContext();
  const [form, setForm] = useState({
    firstName: '',
    lastName: '',
    email: '',
    mobileNumber: '',
    licenseNumber: '',
    vehiclePlateNumber: '',
    vehicleModel: '',
  });

  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));

  const handleSubmit = () => {
    registerDriver({
      firstName: form.firstName || 'New',
      lastName: form.lastName || 'Driver',
      email: form.email || 'newdriver@trace.test',
      mobileNumber: form.mobileNumber || '09179992222',
      licenseNumber: form.licenseNumber || 'T00-00-000000',
      vehiclePlateNumber: form.vehiclePlateNumber || 'XYZ-2026',
      vehicleModel: form.vehicleModel || 'Nissan Urvan',
    });
    navigation.reset({
      index: 0,
      routes: [{ name: 'DriverDashboard' }],
    });
  };

  return (
    <Screen>
      <HeaderBlock
        eyebrow="Driver Signup"
        title="Register the driver and vehicle details."
        subtitle="The final version should connect this form to PHP file uploads for driver license and vehicle documents."
      />
      <SectionCard>
        <FormInput label="First Name" value={form.firstName} onChangeText={(value) => updateField('firstName', value)} placeholder="Marco" />
        <FormInput label="Last Name" value={form.lastName} onChangeText={(value) => updateField('lastName', value)} placeholder="Ramos" />
        <FormInput label="Email" value={form.email} onChangeText={(value) => updateField('email', value)} placeholder="driver@example.com" />
        <FormInput label="Mobile Number" value={form.mobileNumber} onChangeText={(value) => updateField('mobileNumber', value)} placeholder="09179876543" />
        <FormInput label="Driver License Number" value={form.licenseNumber} onChangeText={(value) => updateField('licenseNumber', value)} placeholder="N01-23-456789" />
        <FormInput label="Vehicle Plate Number" value={form.vehiclePlateNumber} onChangeText={(value) => updateField('vehiclePlateNumber', value)} placeholder="ABC-1234" />
        <FormInput label="Vehicle Model" value={form.vehicleModel} onChangeText={(value) => updateField('vehicleModel', value)} placeholder="Toyota Hiace" />
        <AppButton label="Create Driver Account" variant="secondary" onPress={handleSubmit} />
      </SectionCard>
    </Screen>
  );
}
