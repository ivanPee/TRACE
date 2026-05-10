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
    vehicleColor: '',
    password: '',
  });

  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));

  const handleSubmit = async () => {
    await registerDriver({
      firstName: form.firstName,
      lastName: form.lastName,
      email: form.email,
      mobileNumber: form.mobileNumber,
      licenseNumber: form.licenseNumber,
      vehiclePlateNumber: form.vehiclePlateNumber,
      vehicleModel: form.vehicleModel,
      vehicleColor: form.vehicleColor,
      password: form.password,
    });
    navigation.reset({
      index: 0,
      routes: [{ name: 'MainApp' }],
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
        <FormInput label="Vehicle Color" value={form.vehicleColor} onChangeText={(value) => updateField('vehicleColor', value)} placeholder="White" />
        <FormInput label="Password" value={form.password} onChangeText={(value) => updateField('password', value)} placeholder="Password" secureTextEntry />
        <AppButton label="Create Driver Account" variant="secondary" onPress={handleSubmit} />
      </SectionCard>
    </Screen>
  );
}
