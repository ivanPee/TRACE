import React, { useState } from 'react';
import AppButton from '../../components/AppButton';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import ImagePickerField, { appendImage } from '../../components/ImagePickerField';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function RegisterParentScreen({ navigation }) {
  const { registerParent } = useAppContext();
  const [form, setForm] = useState({
    firstName: '',
    lastName: '',
    email: '',
    mobileNumber: '',
    address: '',
    profilePhoto: null,
    validId: null,
    password: '',
  });

  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));

  const handleSubmit = async () => {
    const payload = new FormData();
    ['firstName', 'lastName', 'email', 'mobileNumber', 'address', 'password'].forEach((key) => payload.append(key, form[key]));
    appendImage(payload, 'profile_photo', form.profilePhoto);
    appendImage(payload, 'valid_id', form.validId);
    await registerParent(payload);
    navigation.reset({
      index: 0,
      routes: [{ name: 'MainApp' }],
    });
  };

  return (
    <Screen>
      <HeaderBlock
        eyebrow="Parent Signup"
        title="Create the guardian account first."
        subtitle="This account will manage bookings and create student records with unique LRNs."
      />
      <SectionCard>
        <FormInput label="First Name" value={form.firstName} onChangeText={(value) => updateField('firstName', value)} placeholder="Angela" />
        <FormInput label="Last Name" value={form.lastName} onChangeText={(value) => updateField('lastName', value)} placeholder="Villanueva" />
        <FormInput label="Email" value={form.email} onChangeText={(value) => updateField('email', value)} placeholder="parent@example.com" />
        <FormInput label="Mobile Number" value={form.mobileNumber} onChangeText={(value) => updateField('mobileNumber', value)} placeholder="09171234567" />
        <ImagePickerField label="Profile Image" value={form.profilePhoto} onChange={(value) => updateField('profilePhoto', value)} />
        <ImagePickerField label="Valid ID Image" value={form.validId} onChange={(value) => updateField('validId', value)} />
        <FormInput label="Password" value={form.password} onChangeText={(value) => updateField('password', value)} placeholder="Password" secureTextEntry />
        <FormInput label="Address" value={form.address} onChangeText={(value) => updateField('address', value)} placeholder="Pickup/home address" multiline />
        <AppButton label="Create Parent Account" onPress={handleSubmit} />
      </SectionCard>
    </Screen>
  );
}
